<?php
// api/update_table.php
// Update table (cabin) metadata. If status is set to 'available' this version also clears the guest text.
//
// Input: JSON body preferred. Supported fields:
//   id (int, required)
//   status (optional) - 'available'|'reserved'|'occupied'
//   seats (optional) - int
//   guest (optional) - string
//   name (optional) - string
//   price_per_hour (optional) - decimal
//
// Response: { success: true, affected: <n> } or error JSON

header('Content-Type: application/json; charset=utf-8');

// DEV: enable during development, disable in production
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = [];
if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = $decoded;
    }
}
if (!is_array($input)) {
    $input = $_POST;
}

$id = isset($input['id']) ? (int)$input['id'] : 0;
$status = array_key_exists('status', $input) ? (is_null($input['status']) ? null : trim($input['status'])) : null;
$seats = array_key_exists('seats', $input) ? (is_null($input['seats']) ? null : (int)$input['seats']) : null;
$guest = array_key_exists('guest', $input) ? (is_null($input['guest']) ? null : trim($input['guest'])) : null;
$name  = array_key_exists('name', $input) ? (is_null($input['name']) ? null : trim($input['name'])) : null;
$price = array_key_exists('price_per_hour', $input) ? (is_null($input['price_per_hour']) ? null : (float)$input['price_per_hour']) : null;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

$allowedStatuses = ['available','occupied','reserved'];
$fields = [];
$params = [':id' => $id];

// Fetch previous guest for logging when we might reset it
$prevGuest = null;
$shouldLogGuestReset = false;
try {
    $stmtPrev = $pdo->prepare("SELECT IFNULL(guest, '') AS guest FROM `tables` WHERE id = :id LIMIT 1");
    $stmtPrev->execute([':id' => $id]);
    $prevRow = $stmtPrev->fetch(PDO::FETCH_ASSOC);
    if ($prevRow) $prevGuest = (string)$prevRow['guest'];
} catch (Exception $e) {
    // Non-fatal: continue without previous guest
    $prevGuest = null;
}

// Validate and prepare fields
if ($status !== null) {
    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }
    $fields[] = "`status` = :status";
    $params[':status'] = $status;

    // If status is being set to 'available', clear guest text (reset to empty)
    // This ensures when an operator marks the cabin available, the guest display is cleared.
    // If the client explicitly provided a guest value and still set status to 'available',
    // this code will clear guest anyway to guarantee "reset" semantics. If you want to
    // allow overriding guest while setting available, change this behavior.
    if ($status === 'available') {
        $fields[] = "`guest` = :guest_reset";
        $params[':guest_reset'] = '';
        // mark that we'll want to log the reset after a successful update
        $shouldLogGuestReset = true;
    }
}

if ($seats !== null) {
    if ($seats < 1 || $seats > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid seats']);
        exit;
    }
    $fields[] = "`seats` = :seats";
    $params[':seats'] = $seats;
}

if ($guest !== null) {
    // If status was set to 'available' we already added a guest reset above, so prefer reset.
    // But if status wasn't changed to 'available', allow updating guest.
    if (!($status === 'available')) {
        if (strlen($guest) > 255) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Guest name too long']);
            exit;
        }
        $fields[] = "`guest` = :guest";
        $params[':guest'] = $guest;
    }
}

if ($name !== null) {
    if (strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Name too long']);
        exit;
    }
    $fields[] = "`name` = :name";
    $params[':name'] = $name;
}

if ($price !== null) {
    if ($price < 0 || $price > 1000000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid price_per_hour']);
        exit;
    }
    // Note: your DB column may be named price_per_hour or price — update query below expects price_per_hour.
    $fields[] = "`price` = :price";
    $params[':price'] = round($price, 2);
}

if (empty($fields)) {
    echo json_encode(['success' => false, 'error' => 'No fields to update']);
    exit;
}

$sql = "UPDATE `tables` SET " . implode(', ', $fields) . " WHERE id = :id LIMIT 1";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $affected = $stmt->rowCount();

    // If we set status to available and the update changed the row, log the guest reset
    if ($shouldLogGuestReset && $affected > 0) {
        try {
            $logPath = __DIR__ . '/update_table_guest_reset.log';
            $now = (new DateTime())->format('Y-m-d H:i:s');
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 200) : '';
            $prev = $prevGuest !== null ? $prevGuest : '';
            $msg = sprintf("[%s] Guest reset on table id=%d previous_guest=%s ip=%s ua=%s\n",
                $now,
                $id,
                json_encode($prev, JSON_UNESCAPED_UNICODE),
                $ip,
                str_replace(["\r","\n"], ' ', $ua)
            );
            // Append to file atomically
            file_put_contents($logPath, $msg, FILE_APPEND | LOCK_EX);
        } catch (Throwable $logEx) {
            // Don't fail the request because logging failed; write to PHP error log as fallback
            error_log("update_table.php: failed to write guest-reset log: " . $logEx->getMessage());
        }
    }

    // If we set status to available we also want to clear any guest in reservations table (optional).
    // Uncomment the block below if you want to also clear guest from current reservations for this table:
    /*
    if (isset($params[':status']) && $params[':status'] === 'available') {
        $clearRes = $pdo->prepare("UPDATE reservations SET guest = '' WHERE table_id = :id AND status IN ('reserved','occupied')");
        $clearRes->execute([':id' => $id]);
    }
    */

    echo json_encode(['success' => true, 'affected' => $affected]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>