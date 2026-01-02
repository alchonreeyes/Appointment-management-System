<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
require_once __DIR__ . '/../config/encryption_util.php'; 

// ========================================
// 1. CONFIGURATION: BLOCKED DATES (Manual Restriction)
// ========================================
$blocked_dates = [
    '2025-12-12', // Christmas Party
    '2025-12-25', // Christmas Day
    '2026-01-01'  // New Year
];

// ========================================
// 2. COOLDOWN CHECK (3 Minutes)
// ========================================
$cooldown_minutes = 3;
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
    // 3. ENCRYPT SENSITIVE DATA (PII & MEDICAL INFO)
    // =======================================================
    // A. Personal Info
    $full_name    = encrypt_data(trim($_POST['full_name'] ?? ''));
    $phone_number = encrypt_data(trim($_POST['contact_number'] ?? ''));
    $occupation   = encrypt_data(trim($_POST['occupation'] ?? '')); // Encrypted

    // B. Medical Info (Normal Appointment)
    $concern_raw  = trim($_POST['concern'] ?? '');
    $concern      = encrypt_data($concern_raw); // Encrypted

    $symptoms_raw = isset($_POST['symptoms']) ? (is_array($_POST['symptoms']) ? implode(", ", $_POST['symptoms']) : $_POST['symptoms']) : '';
    $symptoms     = encrypt_data($symptoms_raw); // Encrypted

    // C. Ishihara Specifics
    $ish_reason   = encrypt_data(trim($_POST['ishihara_reason'] ?? '')); // Encrypted
    $ish_prev     = encrypt_data(trim($_POST['previous_color_issues'] ?? '')); // Encrypted
    $ish_notes    = encrypt_data(trim($_POST['ishihara_notes'] ?? '')); // Encrypted

    // D. Certificate Specifics
    $cert_purpose = trim($_POST['certificate_purpose'] ?? ''); // Plain Text (Usually generic dropdown)
    $cert_other   = encrypt_data(trim($_POST['certificate_other'] ?? '')); // Encrypted (Custom Input)

    // Common Plain Text Fields
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

    $pdo->beginTransaction();

    foreach ($appointments as $slot) {
        $date = $slot['date'] ?? '';
        $time = $slot['time'] ?? '';
        
        if (empty($date) || empty($time)) continue;

        // ========================================
        // 4. CLOSURE DATE CHECK
        // ========================================
        if (in_array($date, $blocked_dates)) {
            throw new Exception("Sorry, the clinic is closed on " . date('F j, Y', strtotime($date)) . ".");
        }

        // Common Parameters for all Queries
        $params = [
            ':client_id' => $client_id,
            ':service_id' => $_POST['service_id'] ?? 6,
            ':full_name' => $full_name,       // Encrypted
            ':suffix' => $suffix,
            ':gender' => $gender,
            ':age' => $age,
            ':phone_number' => $phone_number, // Encrypted
            ':occupation' => $occupation,     // Encrypted
            ':appointment_date' => $date,
            ':appointment_time' => $time,
            ':consent_info' => isset($_POST['consent_info']) ? 1 : 0,
            ':consent_reminders' => isset($_POST['consent_reminders']) ? 1 : 0,
            ':consent_terms' => isset($_POST['consent_terms']) ? 1 : 0,
            ':appointment_group_id' => $appointment_group_id
        ];

        // =======================================================
        // 5. INSERT QUERY BASED ON TYPE
        // =======================================================
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
            $params[':certificate_other'] = $cert_other; // Encrypted

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
            $params[':ishihara_reason'] = $ish_reason; // Encrypted
            $params[':previous_color_issues'] = $ish_prev; // Encrypted
            $params[':ishihara_notes'] = $ish_notes; // Encrypted

        } else {
            // Normal appointment
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
            $params[':symptoms'] = $symptoms; // Encrypted
            $params[':concern'] = $concern;   // Encrypted
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
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]); 
}