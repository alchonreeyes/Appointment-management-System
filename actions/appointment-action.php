<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
require_once __DIR__ . '/../config/encryption_util.php'; 

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
    // 4. COOLDOWN CHECK (15 Minutes)
    // ========================================
    $cooldown_seconds = 60; // 1 minute
if (isset($_SESSION['last_appointment_time'])) {
    $time_passed = time() - $_SESSION['last_appointment_time'];
    $time_remaining = $cooldown_seconds - $time_passed;
    if ($time_remaining > 0) {
        $seconds_left = $time_remaining;
        echo json_encode(['success' => false, 'message' => "Please wait $seconds_left second(s) before booking again."]);
        exit;
    }
}

    // ========================================
// 5. ANTI-SPAM: Detect Aggressive Booking
// ========================================
// Check bookings in last 10 minutes
$checkAggressive = $pdo->prepare("
    SELECT appointment_id, appointment_date, appointment_time, service_id
    FROM appointments 
    WHERE client_id = ? 
      AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
      AND status_id IN (1, 2)
    ORDER BY created_at DESC
");
$checkAggressive->execute([$client_id]);
$recentBookings = $checkAggressive->fetchAll(PDO::FETCH_ASSOC);
$recentCount = count($recentBookings);


// If 6+ bookings in 10 minutes = SPAMMER
// (3 appointments per booking × 2 bookings = 6 appointments)
if ($recentCount >= 6) {
    
    // 1. Cancel all their recent appointments
    $cancelIds = array_column($recentBookings, 'appointment_id');
    $placeholders = str_repeat('?,', count($cancelIds) - 1) . '?';
    
    $pdo->prepare("
        UPDATE appointments 
        SET status_id = 5, reason_cancel = 'Auto-cancelled: Suspicious activity detected'
        WHERE appointment_id IN ($placeholders)
    ")->execute($cancelIds);
    
  // 2. Restore all slots they took
    foreach ($recentBookings as $booking) {
        $pdo->prepare("
            UPDATE appointment_slots 
            SET used_slots = used_slots - 1 
            WHERE service_id = ? 
              AND appointment_date = ? 
              AND appointment_time = ?
              AND used_slots > 0
        ")->execute([
            $booking['service_id'],
            $booking['appointment_date'],
            $booking['appointment_time']
        ]);
    }
    
    // 3. Suspend the account
    $pdo->prepare("UPDATE clients SET account_status = 'suspended' WHERE client_id = ?")
        ->execute([$client_id]);
    
    echo json_encode([
        'success' => false, 
        'message' => 'Account suspended: Suspicious booking activity detected. All recent appointments have been cancelled. Please contact the clinic to resolve this issue.'
    ]);
    exit;
}
// Normal check: Max 3 appointments per hour
$checkDaily = $pdo->prepare("
    SELECT COUNT(*) as daily_count 
    FROM appointments 
    WHERE client_id = ? 
      AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
      AND status_id IN (1, 2)
");
$checkDaily->execute([$client_id]);
$dailyCount = $checkDaily->fetch()['daily_count'];

if ($dailyCount >= 3) {
    echo json_encode([
        'success' => false, 
        'message' => 'You can only book 3 appointments per hour. Please try again later.'
    ]);
    exit;
    
}

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