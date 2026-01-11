<?php
/**
 * Restore Missed Appointment Slots
 * 
 * This script should run every hour via cron job:
 * 0 * * * * /usr/bin/php /path/to/restore_missed_slots.php
 */

require_once __DIR__ . '/../config/db.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // ========================================
    // 1. MARK MISSED APPOINTMENTS
    // ========================================
    $markMissed = $pdo->prepare("
        UPDATE appointments
        SET status_id = 4
        WHERE status_id IN (1, 2)
        AND CONCAT(appointment_date, ' ', appointment_time) < NOW()
    ");
    $markMissed->execute();
    $missedCount = $markMissed->rowCount();

    // ========================================
    // 2. RESTORE SLOTS FROM MISSED APPOINTMENTS
    // ========================================
    $restoreSlots = $pdo->prepare("
        UPDATE appointment_slots AS slots
        INNER JOIN appointments AS appts 
            ON slots.service_id = appts.service_id 
            AND slots.appointment_date = appts.appointment_date
        SET slots.used_slots = GREATEST(slots.used_slots - 1, 0)
        WHERE appts.status_id = 4
        AND appts.updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $restoreSlots->execute();
    $restoredCount = $restoreSlots->rowCount();

    echo "✅ Processed: $missedCount appointments marked as missed.\n";
    echo "✅ Restored: $restoredCount slots freed up.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}