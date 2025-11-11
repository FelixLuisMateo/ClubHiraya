<?php
/**
 * cron_notify_reservations.php
 *
 * Run from CLI (cron). Finds reservations ending soon and marks notified_at after sending an alert.
 *
 * Adjust configuration below:
 * - $notifyWindowMinutes : how many minutes ahead to notify (e.g. 5 minutes before end)
 * - $graceMinutes : how many minutes in the past to still consider (to catch missed items)
 * - DB require path: change to point to your api/db.php or create a small DB connect snippet
 *
 * Example crontab (run every minute):
 * * * * * /usr/bin/php /path/to/cron_notify_reservations.php >> /var/log/notify_reservations.log 2>&1
 */

declare(strict_types=1);

// CONFIG
$notifyWindowMinutes = 5;   // notify for reservations that end within the next X minutes
$graceMinutes = 15;         // also catch reservations that ended within the past X minutes (missed notifications)
$adminEmail = 'admin@example.com'; // change to your email (or leave empty to disable email)
$useEmail = true;
$useWebhook = false;
$webhookUrl = ''; // e.g. 'https://hooks.example.com/reservation-ended'
$dryRun = false; // set true to test without updating DB

// Adjust this include path to where your db.php is located. If this script lives in the project root and db.php in api/, use:
$dbPath = __DIR__ . '/api/db.php';
if (!file_exists($dbPath)) {
    // fallback: try same directory
    $dbPath = __DIR__ . '/db.php';
}
if (!file_exists($dbPath)) {
    echo "[" . date('c') . "] ERROR: db.php not found at expected paths. Adjust \$dbPath.\n";
    exit(1);
}

// include DB connection (expects $pdo variable from db.php)
require_once $dbPath;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "[" . date('c') . "] ERROR: PDO connection not available. Check db.php.\n";
    exit(1);
}

try {
    // Prepare query to find reservations that:
    // - are in status reserved or occupied
    // - have not been notified yet (notified_at IS NULL)
    // - end between (NOW() - grace) and (NOW() + notifyWindow)
    $sql = "
      SELECT r.id, r.table_id, r.guest, r.start, r.end, r.start_time, r.end_time, t.name AS table_name
      FROM reservations r
      JOIN tables t ON t.id = r.table_id
      WHERE r.status IN ('reserved','occupied')
        AND r.notified_at IS NULL
        AND r.`end` <= (NOW() + INTERVAL :notify_minutes MINUTE)
        AND r.`end` >= (NOW() - INTERVAL :grace_minutes MINUTE)
      ORDER BY r.`end` ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':notify_minutes' => $notifyWindowMinutes,
        ':grace_minutes'  => $graceMinutes,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($rows);
    echo "[" . date('c') . "] Found {$count} reservation(s) to notify.\n";

    if ($count === 0) {
        exit(0);
    }

    // Prepare update statement (mark notified_at)
    $updateStmt = $pdo->prepare("UPDATE reservations SET notified_at = NOW() WHERE id = :id LIMIT 1");

    foreach ($rows as $r) {
        $resId = $r['id'];
        $tableName = $r['table_name'] ?? ('Table #' . $r['table_id']);
        $guest = $r['guest'] ?? '';
        $endDt = $r['end']; // datetime string
        $message = sprintf(
            "Reservation ending soon:\nReservation ID: %s\nCabin: %s\nGuest: %s\nEnds at: %s",
            $resId,
            $tableName,
            $guest,
            $endDt
        );

        // 1) Send email (simple mail; for production consider a reliable SMTP library)
        $emailSent = false;
        if ($useEmail && !empty($adminEmail)) {
            $subject = "[Alert] Reservation ending soon - {$tableName}";
            $headers = "From: no-reply@example.com\r\n";
            // Suppress warnings; check return value
            if (php_sapi_name() !== 'cli' && headers_sent()) {
                // nothing special
            }
            if (!$dryRun) {
                $emailSent = mail($adminEmail, $subject, $message, $headers);
            } else {
                echo "[DRY-RUN] Would send email to {$adminEmail} with subject: {$subject}\n";
                $emailSent = true;
            }
        }

        // 2) Send webhook (optional)
        $webhookResult = false;
        if ($useWebhook && !empty($webhookUrl)) {
            $payload = json_encode([
                'reservation_id' => $resId,
                'table_id'       => $r['table_id'],
                'table_name'     => $tableName,
                'guest'          => $guest,
                'end'            => $endDt,
            ]);
            if (!$dryRun) {
                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $resp = curl_exec($ch);
                $err = curl_error($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $webhookResult = ($err === '' && $code >= 200 && $code < 300);
                if (!$webhookResult) {
                    echo "[" . date('c') . "] Webhook failed for reservation {$resId}. HTTP code={$code}, error='{$err}', resp='{$resp}'\n";
                }
            } else {
                echo "[DRY-RUN] Would POST to webhook {$webhookUrl} payload: {$payload}\n";
                $webhookResult = true;
            }
        }

        // If no notification channels configured, just log and still mark notified_at to avoid repetition
        $notifiedOk = ($useEmail ? $emailSent : true) && ($useWebhook ? $webhookResult : true);

        if ($notifiedOk) {
            if (!$dryRun) {
                // Update notified_at
                $updateStmt->execute([':id' => $resId]);
                echo "[" . date('c') . "] Marked notified_at for reservation {$resId}.\n";
            } else {
                echo "[DRY-RUN] Would mark notified_at for reservation {$resId}.\n";
            }
        } else {
            echo "[" . date('c') . "] Notification attempt failed for reservation {$resId}. Not marking notified_at.\n";
        }
    }

    echo "[" . date('c') . "] Done.\n";
    exit(0);

} catch (Throwable $ex) {
    echo "[" . date('c') . "] ERROR: " . $ex->getMessage() . "\n";
    exit(2);
}
?>