<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
// ========================================
// SIMPLE COOLDOWN CHECK - 3 minutes
// ========================================
$cooldown_minutes = 3;

if (isset($_SESSION['last_appointment_time'])) {
    $time_passed = time() - $_SESSION['last_appointment_time'];
    $time_remaining = ($cooldown_minutes * 60) - $time_passed;
    
    if ($time_remaining > 0) {
        $minutes_left = ceil($time_remaining / 60);
        echo json_encode([
            'success' => false, 
            'message' => "Please wait $minutes_left minute(s) before creating another appointment."
        ]);
        exit;
    }
}

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Check for the segmented client ID
    if (!isset($_SESSION['client_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    // --- UNIFIED CLIENT ID FETCH ---
    $client_user_id = $_SESSION['client_id'];
    $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
    $stmt->execute([$client_user_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        // This handles the security/integrity check
        throw new Exception("Client record not found in database."); 
    }
    $client_id = $client['client_id'];

    // Common input fields
    $service_id = $_POST['service_id'] ?? 6;
    $full_name = trim($_POST['full_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $phone_number = trim($_POST['contact_number'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $consent_info = isset($_POST['consent_info']) ? 1 : 0;
    $concern = trim($_POST['concern'] ?? '');
    $consent_reminders = isset($_POST['consent_reminders']) ? 1 : 0;
    $consent_terms = isset($_POST['consent_terms']) ? 1 : 0;
    // --- NEW CODE START ---
    // Capture Brands (Array to String)
    $brands = isset($_POST['brands']) ? implode(", ", $_POST['brands']) : '';
    
    // Capture Shapes (Array to String)
    $shapes = isset($_POST['frame_shape']) ? implode(", ", $_POST['frame_shape']) : '';
    // Detect appointment type
    // NEW: Capture Selected Products
$selected_products = isset($_POST['selected_products']) ? implode(", ", $_POST['selected_products']) : 'None';
    $type = 'normal';
    if (isset($_POST['certificate_purpose'])) $type = 'medical';
    elseif (isset($_POST['ishihara_test_type'])) $type = 'ishihara';

    // Parse the appointment dates JSON
    $appointments = [];
    if (!empty($_POST['appointment_dates_json'])) {
        $decoded = json_decode($_POST['appointment_dates_json'], true);
        if (is_array($decoded)) {
            $appointments = $decoded;
        }
    }

    // Validate we have appointments
    if (empty($appointments)) {
        echo json_encode(['success' => false, 'message' => 'Please select appointment date(s) and time(s).']);
        exit;
    }

    // Create professional "Ecommerce Style" Reference ID
    // Format: EM-YYYYMMDD-Random (e.g., EM-20251211-8492)
    $prefix = "EM"; 
    $dateStr = date('Ymd'); // Current date like 20251211
    $random = rand(1000, 9999); // Random 4-digit number
    
    $appointment_group_id = "$prefix-$dateStr-$random";
    // Start transaction
    $pdo->beginTransaction();

    foreach ($appointments as $slot) {
        $date = $slot['date'] ?? '';
        $time = $slot['time'] ?? '';

        if (empty($date) || empty($time)) continue;

        // Check if this specific date+time slot is full
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS confirmed_count
            FROM appointments
            WHERE appointment_date = ?
              AND appointment_time = ?
              AND status_id IN (1, 2)
        ");
        $stmt->execute([$date, $time]);
        $confirmedCount = $stmt->fetchColumn();
        $maxSlots = 3;

        if ($confirmedCount >= $maxSlots) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => "Sorry, $date at $time is fully booked. Please select a different time."
            ]);
            exit;
        }

        if ($type === 'medical') {
            // Service ID 12
            $sql = "INSERT INTO appointments (
                        client_id, service_id, full_name, suffix, gender, age, phone_number, occupation,
                        certificate_purpose, certificate_other, appointment_date, appointment_time,
                        consent_info, consent_reminders, consent_terms, appointment_group_id, status_id
                    ) VALUES (
                        :client_id, :service_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                        :certificate_purpose, :certificate_other, :appointment_date, :appointment_time,
                        :consent_info, :consent_reminders, :consent_terms, :appointment_group_id, 1
                    )";
            // FIX: Map actual POST variables for Medical Certificate
            $params[':certificate_purpose'] = $_POST['certificate_purpose'] ?? 'Fit to Work';
            // Only capture 'certificate_other' if 'Other' was selected
            $params[':certificate_other'] = ($_POST['certificate_purpose'] ?? '') === 'Other' 
                ? ($_POST['certificate_other'] ?? null) : null;
            
        } elseif ($type === 'ishihara') {
            // Service ID 13
            $sql = "INSERT INTO appointments (
                        client_id, service_id, full_name, suffix, gender, age, phone_number, occupation,
                        appointment_date, appointment_time, ishihara_test_type, ishihara_reason, previous_color_issues, ishihara_notes,
                        consent_info, consent_reminders, consent_terms, appointment_group_id, status_id
                    ) VALUES (
                        :client_id, :service_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                        :appointment_date, :appointment_time, :ishihara_test_type, :ishihara_reason, :previous_color_issues, :ishihara_notes,
                        :consent_info, :consent_reminders, :consent_terms, :appointment_group_id, 1
                    )";
            // FIX: Map actual POST variables for Ishihara Test
            $params[':ishihara_test_type'] = $_POST['ishihara_test_type'] ?? 'Basic Screening';
            $params[':ishihara_reason'] = $_POST['ishihara_reason'] ?? null;
            $params[':previous_color_issues'] = $_POST['previous_color_issues'] ?? null;
            $params[':ishihara_notes'] = $_POST['ishihara_notes'] ?? null;

        } else {
            // Normal appointment (UPDATED FOR PRODUCTS)
$sql = "INSERT INTO appointments (
            client_id, service_id, full_name, suffix, gender, age, phone_number, occupation,
            appointment_date, appointment_time,
            wear_glasses, symptoms, concern,
            selected_products, /* <--- NEW COLUMN */
            consent_info, consent_reminders, consent_terms,
            appointment_group_id, status_id
        ) VALUES (
            :client_id, :service_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
            :appointment_date, :appointment_time,
            :wear_glasses, :symptoms, :concern,
            :selected_products, /* <--- NEW VALUE */
            :consent_info, :consent_reminders, :consent_terms,
            :appointment_group_id,
            (SELECT status_id FROM appointmentstatus WHERE status_name = 'Pending' LIMIT 1)
        )";

$stmt = $pdo->prepare($sql);
$stmt->execute([
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
    ':wear_glasses' => $_POST['wear_glasses'] ?? null,
    ':symptoms' => isset($_POST['symptoms']) ? implode(", ", $_POST['symptoms']) : '',
    ':concern' => $concern,
    ':selected_products' => $selected_products, // <--- Bind the new data
    ':consent_info' => $consent_info,
    ':consent_reminders' => $consent_reminders,
    ':consent_terms' => $consent_terms,
    ':appointment_group_id' => $appointment_group_id
]);
        }
    }

    // Commit all appointments
    $pdo->commit();

    // ========================================
    // SET COOLDOWN - User can't book again for 5 minutes
    // ========================================
    $_SESSION['last_appointment_time'] = time();

    echo json_encode([
        'success' => true,
        'message' => count($appointments) . ' appointment(s) successfully created.',
        'group_id' => $appointment_group_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    // Return generic error message for security
    echo json_encode(['success' => false, 'message' => 'An error occurred during booking. Please try again.']); 
}
?>