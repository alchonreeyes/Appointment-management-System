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
    $cooldown_minutes = 2;
    if (isset($_SESSION['last_appointment_time'])) {
        $time_passed = time() - $_SESSION['last_appointment_time'];
        $time_remaining = ($cooldown_minutes * 60) - $time_passed;
        if ($time_remaining > 0) {
            $minutes_left = ceil($time_remaining / 60);
            echo json_encode(['success' => false, 'message' => "Please wait $minutes_left minute(s) before booking again."]);
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
if ($recentCount >= 6) {
    
    // 1. Cancel all their recent appointments
    $cancelIds = array_column($recentBookings, 'appointment_id');
    $placeholders = str_repeat('?,', count($cancelIds) - 1) . '?';
    
    $pdo->prepare("
        UPDATE appointments 
        SET status_id = 5, reason_cancel = 'Auto-cancelled: Suspicious activity'
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
        'message' => 'Account suspended: Suspicious booking activity detected. All recent appointments have been cancelled.'
    ]);
    exit;
}

// Normal check: Max 3 bookings in 24 hours (for regular users)
$checkDaily = $pdo->prepare("
    SELECT COUNT(*) as daily_count 
    FROM appointments 
    WHERE client_id = ? 
      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
      AND status_id IN (1, 2)
");
$checkDaily->execute([$client_id]);
$dailyCount = $checkDaily->fetch()['daily_count'];

if ($dailyCount >= 3) {
    echo json_encode([
        'success' => false, 
        'message' => 'You can only book 3 appointments per day. Please try again tomorrow.'
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

    // ========================================
    // 7. START TRANSACTION
    // ========================================
    $pdo->beginTransaction();

    foreach ($appointments as $slot) {
        $date = $slot['date'] ?? '';
        $time = $slot['time'] ?? '';
        
        if (empty($date) || empty($time)) continue;

        // Check closed dates
        $checkClosed = $pdo->prepare("SELECT status FROM schedule_settings WHERE schedule_date = ? AND status = 'Closed'");
        $checkClosed->execute([$date]);
        if ($checkClosed->fetch()) {
            throw new Exception("Sorry, the clinic is closed on " . date('F j, Y', strtotime($date)) . ".");
        }

        // Check slot availability
        $checkSlot = $pdo->prepare("
            SELECT slot_id, used_slots, max_slots 
            FROM appointment_slots 
            WHERE service_id = ? AND appointment_date = ? AND appointment_time = ?
            FOR UPDATE
        ");
        $checkSlot->execute([$service_id, $date, $time]);
        $slotData = $checkSlot->fetch(PDO::FETCH_ASSOC);

        if (!$slotData) {
            $createSlot = $pdo->prepare("
                INSERT INTO appointment_slots (service_id, appointment_date, appointment_time, max_slots, used_slots) 
                VALUES (?, ?, ?, 1, 1)
            ");
            $createSlot->execute([$service_id, $date, $time]);
        } else {
            if ($slotData['used_slots'] >= $slotData['max_slots']) {
                throw new Exception("Sorry, the " . date('g:i A', strtotime($time)) . " slot on " . date('F j, Y', strtotime($date)) . " is fully booked.");
            }
            
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

        // Insert based on type
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

    $pdo->commit();
    $_SESSION['last_appointment_time'] = time();

    echo json_encode(['success' => true, 'message' => 'Successfully booked!', 'group_id' => $appointment_group_id]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Booking Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]); 
}