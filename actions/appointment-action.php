<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
require_once __DIR__ . '/../config/encryption_util.php'; 

// ========================================
// HELPER FUNCTION: Suspend spammer and clean up
// ========================================
function suspendSpammer($pdo, $client_id, $bookingsToCancel, $reason) {
    $pdo->beginTransaction();
    
    try {
        if (empty($bookingsToCancel)) {
            throw new Exception("No bookings to cancel");
        }
        
        // 1. Cancel all flagged appointments
        $cancelIds = array_column($bookingsToCancel, 'appointment_id');
        $placeholders = str_repeat('?,', count($cancelIds) - 1) . '?';
        
        $pdo->prepare("
            UPDATE appointments 
            SET status_id = 5, 
                reason_cancel = ?
            WHERE appointment_id IN ($placeholders)
        ")->execute(array_merge([$reason], $cancelIds));
        
        // 2. Restore all slots
        foreach ($bookingsToCancel as $booking) {
            $pdo->prepare("
                UPDATE appointment_slots 
                SET used_slots = GREATEST(used_slots - 1, 0)
                WHERE service_id = ? 
                  AND appointment_date = ? 
                  AND appointment_time = ?
            ")->execute([
                $booking['service_id'],
                $booking['appointment_date'],
                $booking['appointment_time']
            ]);
        }
        
        // 3. Suspend account
        $pdo->prepare("
            UPDATE clients 
            SET account_status = 'suspended' 
            WHERE client_id = ?
        ")->execute([$client_id]);
        
        // 4. Log incident
        $pdo->prepare("
            INSERT INTO spam_log (client_id, booking_count, detection_time, ip_address, reason)
            VALUES (?, ?, NOW(), ?, ?)
        ")->execute([
            $client_id, 
            count($bookingsToCancel), 
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $reason
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => false, 
            'message' => '⚠️ Your account has been suspended due to unusual booking activity. All recent appointments have been cancelled. Please contact the clinic to resolve this issue.'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Suspend spammer error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'System error. Please contact the clinic.'
        ]);
    }
}

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // ========================================
    // 1. CHECK SESSION
    // ========================================
    if (!isset($_SESSION['client_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }

    // ========================================
    // 2. GET CLIENT_ID
    // ========================================
    $client_user_id = $_SESSION['client_id'];
    $stmt = $pdo->prepare("SELECT client_id, account_status FROM clients WHERE user_id = ?");
    $stmt->execute([$client_user_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Client record not found.']);
        exit;
    }
    
    $client_id = $client['client_id'];
    $account_status = $client['account_status'] ?? 'active';

    // ========================================
    // 3. CHECK IF SUSPENDED
    // ========================================
    if ($account_status === 'suspended') {
        echo json_encode(['success' => false, 'message' => 'Your account has been suspended. Please contact the clinic.']);
        exit;
    }

    // ========================================
    // 4. IMPROVED ANTI-SPAM DETECTION
    // ========================================

    // 🔥 CHECK 1: Cooldown between bookings (30 seconds)
    $cooldown_seconds = 30;
    if (isset($_SESSION['last_appointment_time'])) {
        $time_passed = time() - $_SESSION['last_appointment_time'];
        $time_remaining = $cooldown_seconds - $time_passed;
        if ($time_remaining > 0) {
            echo json_encode([
                'success' => false, 
                'message' => "Please wait $time_remaining second(s) before booking again."
            ]);
            exit;
        }
    }

    // 🔥 CHECK 2: Rapid booking detection (5+ appointments in 5 minutes)
    $checkRapid = $pdo->prepare("
        SELECT appointment_id, appointment_date, appointment_time, service_id, created_at
        FROM appointments 
        WHERE client_id = ? 
          AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
          AND status_id IN (1, 2)
        ORDER BY created_at DESC
    ");
    $checkRapid->execute([$client_id]);
    $rapidBookings = $checkRapid->fetchAll(PDO::FETCH_ASSOC);
    $rapidCount = count($rapidBookings);

    // 🚨 INSTANT SPAM: 5+ appointments in 5 minutes
    if ($rapidCount >= 5) {
        error_log("🚨 RAPID SPAM: Client ID $client_id booked $rapidCount appointments in 5 minutes");
        suspendSpammer($pdo, $client_id, $rapidBookings, "Rapid booking spam: $rapidCount appointments in 5 minutes");
        exit;
    }

    // 🔥 CHECK 3: Daily slot hoarding (15+ appointments in 24 hours)
    $checkDaily = $pdo->prepare("
        SELECT appointment_id, appointment_date, appointment_time, service_id, created_at
        FROM appointments 
        WHERE client_id = ? 
          AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          AND status_id IN (1, 2)
        ORDER BY created_at DESC
    ");
    $checkDaily->execute([$client_id]);
    $dailyBookings = $checkDaily->fetchAll(PDO::FETCH_ASSOC);
    $dailyCount = count($dailyBookings);

    // 🚨 SLOT HOARDING: 15+ appointments in 24 hours
    if ($dailyCount >= 15) {
        error_log("🚨 SLOT HOARDING: Client ID $client_id has $dailyCount appointments in 24 hours");
        suspendSpammer($pdo, $client_id, $dailyBookings, "Slot hoarding: $dailyCount appointments in 24 hours");
        exit;
    }

    // 🔥 CHECK 4: Pattern detection - Same time slots being booked repeatedly
    $checkPattern = $pdo->prepare("
        SELECT appointment_time, COUNT(*) as time_count
        FROM appointments 
        WHERE client_id = ? 
          AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
          AND status_id IN (1, 2)
        GROUP BY appointment_time
        HAVING time_count >= 3
    ");
    $checkPattern->execute([$client_id]);
    $suspiciousPatterns = $checkPattern->fetchAll(PDO::FETCH_ASSOC);

    // 🚨 PATTERN SPAM: Booking same time slot 3+ times in 2 hours
    if (count($suspiciousPatterns) > 0) {
        error_log("🚨 PATTERN SPAM: Client ID $client_id booking same time slots repeatedly");
        
        // Get all their recent bookings for cancellation
        $checkRecent = $pdo->prepare("
            SELECT appointment_id, appointment_date, appointment_time, service_id
            FROM appointments 
            WHERE client_id = ? 
              AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
              AND status_id IN (1, 2)
        ");
        $checkRecent->execute([$client_id]);
        $recentBookings = $checkRecent->fetchAll(PDO::FETCH_ASSOC);
        
        suspendSpammer($pdo, $client_id, $recentBookings, "Suspicious pattern: Booking same time slots repeatedly");
        exit;
    }

    // 🔥 CHECK 5: Normal   - Max 6 appointments per 2 hours (WITH SUSPENSION)
    $checkTwoHour = $pdo->prepare("
        SELECT appointment_id, appointment_date, appointment_time, service_id, created_at
        FROM appointments 
        WHERE client_id = ? 
          AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
          AND status_id IN (1, 2)
        ORDER BY created_at DESC
    ");
    $checkTwoHour->execute([$client_id]);
    $twoHourBookings = $checkTwoHour->fetchAll(PDO::FETCH_ASSOC);
    $twoHourCount = count($twoHourBookings);

    if ($twoHourCount >= 6) {
        error_log("🚨 EXCESSIVE BOOKING: Client ID $client_id has $twoHourCount appointments in 2 hours");
        suspendSpammer($pdo, $client_id, $twoHourBookings, "Excessive booking: $twoHourCount appointments in 2 hours");
        exit;
    }

    // ========================================
    // 6. ENCRYPT SENSITIVE DATA
    // ========================================
    // ... rest of your code continues here
    // ========================================
    // 6. ENCRYPT SENSITIVE DATA
    // ========================================
    $full_name    = encrypt_data(trim($_POST['full_name'] ?? ''));
    $phone_number = encrypt_data(trim($_POST['contact_number'] ?? ''));
    $occupation   = encrypt_data(trim($_POST['occupation'] ?? ''));
    $concern_raw  = trim($_POST['concern'] ?? '');
    $concern      = encrypt_data($concern_raw);
    $symptoms_raw = isset($_POST['symptoms']) ? (is_array($_POST['symptoms']) ? implode(", ", $_POST['symptoms']) : $_POST['symptoms']) : '';
    $symptoms     = encrypt_data($symptoms_raw);
    $ish_reason   = encrypt_data(trim($_POST['ishihara_reason'] ?? ''));
    $ish_prev     = encrypt_data(trim($_POST['previous_color_issues'] ?? ''));
    $ish_notes    = encrypt_data(trim($_POST['ishihara_notes'] ?? ''));
    $cert_purpose = trim($_POST['certificate_purpose'] ?? '');
    $cert_other   = encrypt_data(trim($_POST['certificate_other'] ?? ''));
    $suffix = trim($_POST['suffix'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $age = intval($_POST['age'] ?? 0);

    // Detect Type
    $type = 'normal';
    if (isset($_POST['certificate_purpose'])) $type = 'medical';
    elseif (isset($_POST['ishihara_test_type'])) $type = 'ishihara';

    // Parse Appointments
    $appointments = [];
    if (!empty($_POST['appointment_dates_json'])) {
        $decoded = json_decode($_POST['appointment_dates_json'], true);
        if (is_array($decoded)) $appointments = $decoded;
    }

    if (empty($appointments)) {
        echo json_encode(['success' => false, 'message' => 'Please select appointment date(s).']);
        exit;
        }

    $appointment_group_id = "EM-" . date('Ymd') . "-" . rand(1000, 9999);
    $service_id = intval($_POST['service_id'] ?? 0);

    $pdo->beginTransaction();
 // ========================================
// 7. START TRANSACTION BEFORE CHECKING SLOTS
// ========================================

try {
    foreach ($appointments as $slot) {
        $date = $slot['date'] ?? '';
        $time = $slot['time'] ?? '';
        
        if (empty($date) || empty($time)) continue;
        
        // Check closed dates
        // Check closed dates and times
$checkClosed = $pdo->prepare("
    SELECT time_from, time_to 
    FROM schedule_settings 
    WHERE schedule_date = ? AND status = 'Closed'
");
$checkClosed->execute([$date]);
$closure = $checkClosed->fetch(PDO::FETCH_ASSOC);

if ($closure) {
    // If time_from and time_to are NULL, entire day is closed
    if ($closure['time_from'] === null && $closure['time_to'] === null) {
        throw new Exception("Sorry, the clinic is closed on " . date('F j, Y', strtotime($date)) . ".");
    }
    
    // Check if appointment time falls within closure range
    $timeFrom = $closure['time_from'];
    $timeTo = $closure['time_to'];
    $checkTime = $time . ':00';
    
    if ($checkTime >= $timeFrom && $checkTime <= $timeTo) {
        throw new Exception("Sorry, the clinic is closed from " . date('g:i A', strtotime($timeFrom)) . " to " . date('g:i A', strtotime($timeTo)) . " on " . date('F j, Y', strtotime($date)) . ".");
    }
}

        // ✅ ATOMIC CHECK: Lock the row for this slot
        $checkSlot = $pdo->prepare("
            SELECT slot_id, used_slots, max_slots 
            FROM appointment_slots 
            WHERE service_id = ? AND appointment_date = ? AND appointment_time = ?
            FOR UPDATE
        ");
        $checkSlot->execute([$service_id, $date, $time]);
        $slotData = $checkSlot->fetch(PDO::FETCH_ASSOC);

        if (!$slotData) {
            // Create new slot with 1 booking
            $createSlot = $pdo->prepare("
                INSERT INTO appointment_slots (service_id, appointment_date, appointment_time, max_slots, used_slots) 
                VALUES (?, ?, ?, 1, 1)
            ");
            $createSlot->execute([$service_id, $date, $time]);
        } else {
            // ✅ CRITICAL: Check if slot is full AFTER locking
            if ($slotData['used_slots'] >= $slotData['max_slots']) {
                throw new Exception("Sorry, the " . date('g:i A', strtotime($time)) . " slot on " . date('F j, Y', strtotime($date)) . " is fully booked.");
                }
            
            // ✅ ATOMIC UPDATE: Increment within the locked transaction
            $updateSlot = $pdo->prepare("
                UPDATE appointment_slots 
                SET used_slots = used_slots + 1 
                WHERE slot_id = ?
            ");
            $updateSlot->execute([$slotData['slot_id']]);
        }

        // Common parameters
        $params = [
            ':client_id' => $client_id,
            ':service_id' => $service_id,
            ':full_name' => $full_name,
            ':suffix' => $suffix,
            ':gender' => $gender,
            ':age' => $age,
            ':phone_number' => $phone_number,
            ':occupation' => $occupation,
            ':appointment_date' => $date,
            ':appointment_time' => $time,
            ':consent_info' => isset($_POST['consent_info']) ? 1 : 0,
            ':consent_reminders' => isset($_POST['consent_reminders']) ? 1 : 0,
            ':consent_terms' => isset($_POST['consent_terms']) ? 1 : 0,
            ':appointment_group_id' => $appointment_group_id
        ];

        // Insert based on type (rest of your code stays the same)
        if ($type === 'medical') {
            $sql = "INSERT INTO appointments (
                        client_id, service_id, full_name, suffix, gender, age, phone_number, occupation,
                        certificate_purpose, certificate_other, appointment_date, appointment_time,
                        consent_info, consent_reminders, consent_terms, appointment_group_id, status_id
                    ) VALUES (
                        :client_id, :service_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                        :certificate_purpose, :certificate_other, :appointment_date, :appointment_time,
                        :consent_info, :consent_reminders, :consent_terms, :appointment_group_id, 1
                    )";
            $params[':certificate_purpose'] = $cert_purpose; 
            $params[':certificate_other'] = $cert_other;

        } elseif ($type === 'ishihara') {
            $sql = "INSERT INTO appointments (
                        client_id, service_id, full_name, suffix, gender, age, phone_number, occupation,
                        appointment_date, appointment_time, ishihara_test_type, ishihara_reason, previous_color_issues, ishihara_notes,
                        consent_info, consent_reminders, consent_terms, appointment_group_id, status_id
                    ) VALUES (
                        :client_id, :service_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                        :appointment_date, :appointment_time, :ishihara_test_type, :ishihara_reason, :previous_color_issues, :ishihara_notes,
                        :consent_info, :consent_reminders, :consent_terms, :appointment_group_id, 1
                    )";
            $params[':ishihara_test_type'] = $_POST['ishihara_test_type'] ?? 'Basic Screening';
            $params[':ishihara_reason'] = $ish_reason;
            $params[':previous_color_issues'] = $ish_prev;
            $params[':ishihara_notes'] = $ish_notes;

        } else {
            $sql = "INSERT INTO appointments (
                        client_id, service_id, full_name, suffix, gender, age, phone_number, occupation,
                        appointment_date, appointment_time, wear_glasses, symptoms, concern,
                        selected_products, consent_info, consent_reminders, consent_terms,
                        appointment_group_id, status_id
                    ) VALUES (
                        :client_id, :service_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                        :appointment_date, :appointment_time, :wear_glasses, :symptoms, :concern,
                        :selected_products, :consent_info, :consent_reminders, :consent_terms,
                        :appointment_group_id, 1
                    )";
            $params[':wear_glasses'] = $_POST['wear_glasses'] ?? null;
            $params[':symptoms'] = $symptoms;
            $params[':concern'] = $concern;
            $params[':selected_products'] = isset($_POST['selected_products']) ? implode(", ", $_POST['selected_products']) : 'None';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // ✅ COMMIT: Release all locks
    $pdo->commit();
    $_SESSION['last_appointment_time'] = time();

    echo json_encode(['success' => true, 'message' => 'Successfully booked!', 'group_id' => $appointment_group_id]);

} catch (Exception $e) {
    // ✅ ROLLBACK: Release locks and undo changes (INNER CATCH)
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Booking Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

} catch (Exception $e) {
    // ✅ OUTER CATCH: Handles errors from entire script
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("System Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A system error occurred. Please try again later.']);
}
?>