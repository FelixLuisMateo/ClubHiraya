<?php
// api/expire_reservations.php
// CLI-safe script to expire ended reservations.
//
// Behavior:
// - Find reservations that are "ended" (end <= NOW()) OR where start + duration_minutes <= NOW()
// - Archive them into reservations_archive (if archive table exists)
// - Delete them from reservations
// - For affected tables, if there are no other active reservations (reserved/occupied), set table.status = 'available' and guest = ''
//
// Intended to be run via cron (every minute) to automatically mark cards available and keep DB tidy.
//
// Safety: everything is run in transactions and errors are logged to PHP error log. Run manually first to verify.

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (php_sapi_name() === 'cli') {
    // ok
} else {
    // If invoked via web, optionally restrict (you may remove this if you want web access)
    // For security, only allow local requests by default
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
}

require_once __DIR__ . '/db.php';

try {
    // We will:
    // 1) identify expirable reservations (ended)
    //    We'll use: (r.end IS NOT NULL AND r.end <= NOW())
    //      OR (r.end IS NULL AND r.start IS NOT NULL AND DATE_ADD(r.start, INTERVAL COALESCE(r.duration_minutes,90) MINUTE) <= NOW())
    //    This handles both rows with explicit end and rows with start+duration.
    //
    // 2) Archive them by inserting into reservations_archive (if table exists)
    // 3) Delete them from reservations
    // 4) Update tables to available if they have no active reservation left

    // Step 0: quick check whether reservations_archive exists
    $archiveExists = false;
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'reservations_archive'")->fetchColumn();
        if ($chk) $archiveExists = true;
    } catch (Exception $e) {
        // ignore, assume archive not present
        $archiveExists = false;
    }

    // Identify reservation ids to expire (select into array)
    $sqlFind = "
      SELECT r.id, r.table_id, r.date, r.start_time, r.end_time, r.`start`, r.`end`, r.guest,
             r.party_size, r.status, r.duration_minutes, r.total_price, r.created_at, r.updated_at
      FROM reservations r
      WHERE
        (
          (r.`end` IS NOT NULL AND r.`end` <= NOW())
          OR
          (r.`end` IS NULL AND r.`start` IS NOT NULL AND DATE_ADD(r.`start`, INTERVAL COALESCE(r.duration_minutes,90) MINUTE) <= NOW())
        )
        AND r.status IN ('reserved','occupied')
      ORDER BY r.id ASC
      LIMIT 1000
    ";
    $stmt = $pdo->prepare($sqlFind);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        // Nothing to do
        $msg = '[' . date('c') . '] expire_reservations: no expired reservations found' . PHP_EOL;
        error_log($msg);
        if (php_sapi_name() !== 'cli') echo json_encode(['success' => true, 'processed' => 0]);
        exit;
    }

    // Collect ids and affected table_ids
    $ids = array_column($rows, 'id');
    $tableIds = array_values(array_unique(array_column($rows, 'table_id')));

    // Begin transaction
    $pdo->beginTransaction();

    // Step 2: archive (if archive exists)
    $archivedCount = 0;
    if ($archiveExists) {
        // Build insert using INSERT ... SELECT to move matching rows into archive
        // We use reservation_id to keep original id value.
        $inIds = implode(',', array_map('intval', $ids));
        $archiveSql = "
          INSERT INTO reservations_archive
            (reservation_id, table_id, `date`, start_time, end_time, `start`, `end`, guest, party_size, status, duration_minutes, total_price, created_at, updated_at, deleted_at, deleted_by, deletion_note)
          SELECT
            r.id AS reservation_id,
            r.table_id,
            r.date,
            r.start_time,
            r.end_time,
            r.`start`,
            r.`end`,
            r.guest,
            r.party_size,
            r.status,
            r.duration_minutes,
            r.total_price,
            r.created_at,
            r.updated_at,
            NOW() AS deleted_at,
            CONCAT('auto-expire@', IFNULL(:server_addr,'cli')) AS deleted_by,
            'auto-expire' AS deletion_note
          FROM reservations r
          WHERE r.id IN ($inIds)
        ";
        $archStmt = $pdo->prepare($archiveSql);
        $serverAddr = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $archStmt->execute([':server_addr' => $serverAddr]);
        $archivedCount = $archStmt->rowCount();
    }

    // Step 3: delete the reservations
    $delSql = "DELETE FROM reservations WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")";
    $delStmt = $pdo->prepare($delSql);
    $delStmt->execute();
    $deletedCount = $delStmt->rowCount();

    // Step 4: for affected tables, if they have no remaining active reservations, set them available
    // Build a placeholder list
    $tablePlaceholders = implode(',', array_map('intval', $tableIds));

    // We'll update tables that are in the affected set and which have no active reservations now.
    $updateTablesSql = "
      UPDATE `tables` t
      SET t.status = 'available', t.guest = ''
      WHERE t.id IN ($tablePlaceholders)
        AND NOT EXISTS (
          SELECT 1 FROM reservations r2
          WHERE r2.table_id = t.id AND r2.status IN ('reserved','occupied')
        )
    ";
    $updStmt = $pdo->prepare($updateTablesSql);
    $updStmt->execute();
    $tablesUpdated = $updStmt->rowCount();

    $pdo->commit();

    $logMsg = sprintf("[%s] expire_reservations: archived=%d deleted=%d tables_updated=%d ids=%s\n",
        date('c'), $archivedCount, $deletedCount, $tablesUpdated, implode(',', $ids)
    );
    error_log($logMsg);

    if (php_sapi_name() !== 'cli') {
        echo json_encode([
            'success' => true,
            'archived' => $archivedCount,
            'deleted' => $deletedCount,
            'tables_updated' => $tablesUpdated,
            'processed_ids' => $ids
        ]);
    } else {
        echo $logMsg;
    }
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $err = '[' . date('c') . '] expire_reservations: error: ' . $e->getMessage();
    error_log($err);
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } else {
        echo $err, PHP_EOL;
    }
    exit;
}
?>