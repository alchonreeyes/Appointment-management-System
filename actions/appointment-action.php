<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
require_once __DIR__ . '/../config/encryption_util.php'; 

// ========================================
// 1. COOLDOWN CHECK (3 Minutes)
// ========================================
$cooldown_minutes = 1;
if (isset($_SESSION['last_appointment_time'])) {
    $time_passed = time() - $_SESSION['last_appointment_time'];
    $time_remaining = ($cooldown_minutes * 60) - $time_passed;
    if ($time_remaining > 0) {
        $minutes_left = ceil($time_remaining / 60);
        echo json_encode(['success' => false, 'message' => "Please wait $minutes_left minute(s) before booking again."]);
        exit;
    }
}

try {
    $db = new Database();
    $pdo = $db->getConnection();

    if (!isset($_SESSION['client_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }

    // --- FETCH INTERNAL CLIENT_ID ---
    $client_user_id = $_SESSION['client_id'];
    $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
    $stmt->execute([$client_user_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) throw new Exception("Client record not found.");
    $client_id = $client['client_id'];

    // =======================================================
    // 2. ENCRYPT SENSITIVE DATA
    // =======================================================
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

    // Reference ID
    $appointment_group_id = "EM-" . date('Ymd') . "-" . rand(1000, 9999);
    
    $service_id = intval($_POST['service_id'] ?? 0);

    // ========================================
    // 3. START TRANSACTION WITH SLOT LOCKING
    // ========================================
    $pdo->beginTransaction();

    foreach ($appointments as $slot) {
        $date = $slot['date'] ?? '';
        $time = $slot['time'] ?? '';
        
        if (empty($date) || empty($time)) continue;

        // ========================================
        // 4. CHECK CLOSED DATES FROM schedule_settings
        // ========================================
        $checkClosed = $pdo->prepare("SELECT status FROM schedule_settings WHERE schedule_date = ? AND status = 'Closed'");
        $checkClosed->execute([$date]);
        if ($checkClosed->fetch()) {
            throw new Exception("Sorry, the clinic is closed on " . date('F j, Y', strtotime($date)) . ".");
        }

 // ========================================
// 5. SLOT AVAILABILITY CHECK (PREVENTS OVERBOOKING)
// ========================================
$checkSlot = $pdo->prepare("
    SELECT slot_id, used_slots, max_slots 
    FROM appointment_slots 
    WHERE service_id = ? AND appointment_date = ? AND appointment_time = ?
    FOR UPDATE
");
$checkSlot->execute([$service_id, $date, $time]);
$slotData = $checkSlot->fetch(PDO::FETCH_ASSOC);

if (!$slotData) {
    // Create new slot record
    $createSlot = $pdo->prepare("
        INSERT INTO appointment_slots (service_id, appointment_date, appointment_time, max_slots, used_slots) 
        VALUES (?, ?, ?, 1, 1)
    ");
    $createSlot->execute([$service_id, $date, $time]);
} else {
    // Check if full
    if ($slotData['used_slots'] >= $slotData['max_slots']) {
        throw new Exception("Sorry, the " . date('g:i A', strtotime($time)) . " slot on " . date('F j, Y', strtotime($date)) . " is fully booked. Please select another time.");
    }
    
    // Increment
    $updateSlot = $pdo->prepare("
        UPDATE appointment_slots 
        SET used_slots = used_slots + 1 
        WHERE slot_id = ?
    ");
    $updateSlot->execute([$slotData['slot_id']]);
}

        // Common Parameters
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

        // ========================================
        // 6. INSERT APPOINTMENT BASED ON TYPE
        // ========================================
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