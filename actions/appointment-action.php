<?php
session_start();
include '../config/db.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) throw new Exception("Database connection failed");

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    // Get client ID
    $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) throw new Exception("Client record not found.");
    $client_id = $client['client_id'];

    // Common input fields
    $service_id = $_POST['service_id'] ?? 1;
    $full_name = trim($_POST['full_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $phone_number = trim($_POST['contact_number'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $consent_info = isset($_POST['consent_info']) ? 1 : 0;
    $consent_reminders = isset($_POST['consent_reminders']) ? 1 : 0;
    $consent_terms = isset($_POST['consent_terms']) ? 1 : 0;

    // Detect appointment type
    $type = 'normal';
    if (isset($_POST['certificate_purpose'])) $type = 'medical';
    elseif (isset($_POST['ishihara_test_type'])) $type = 'ishihara';

    // Parse the appointment dates JSON (should contain array of {date, time} objects)
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

    // Create unique group ID for this set of appointments
    $appointment_group_id = uniqid('grp_', true);
    
    // Start transaction
    $pdo->beginTransaction();

    foreach ($appointments as $slot) {
        $date = $slot['date'] ?? '';
        $time = $slot['time'] ?? '';

        if (empty($date) || empty($time)) continue;

        // ✅ Check if this specific date+time slot is full (max 3 confirmed)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS confirmed_count
            FROM appointments
            WHERE appointment_date = ?
              AND appointment_time = ?
              AND status_id = (SELECT status_id FROM appointmentstatus WHERE status_name = 'Confirmed')
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

        // ✅ Insert appointment based on type
        if ($type === 'medical') {
            $sql = "INSERT INTO appointments (
                        client_id, service_id, full_name, suffix, gender, age, phone_number, occupation,
                        certificate_purpose, certificate_other,
                        appointment_date, appointment_time,
                        consent_info, consent_reminders, consent_terms,
                        appointment_group_id, status_id
                    ) VALUES (
                        :client_id, :service_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                        :certificate_purpose, :certificate_other,
                        :appointment_date, :appointment_time,
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
                ':certificate_purpose' => 'Medical Purposes',  // Automatically fills when booking a medical type
                ':certificate_other' => '', // leave blank if not applicable
                ':appointment_date' => $date,
                ':appointment_time' => $time,
                ':consent_info' => $consent_info,
                ':consent_reminders' => $consent_reminders,
                ':consent_terms' => $consent_terms,
                ':appointment_group_id' => $appointment_group_id
            ]);
        } elseif ($type === 'ishihara') {
            $sql = "INSERT INTO appointments (
                        client_id, service_id, full_name, suffix, gender, age, phone_number, occupation,
                        appointment_date, appointment_time,
                        ishihara_test_type, ishihara_reason, previous_color_issues, ishihara_notes,
                        consent_info, consent_reminders, consent_terms,
                        appointment_group_id, status_id
                    ) VALUES (
                        :client_id, :service_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                        :appointment_date, :appointment_time,
                        :ishihara_test_type, :ishihara_reason, :previous_color_issues, :ishihara_notes,
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
                ':ishihara_test_type' => $_POST['ishihara_test_type'] ?? '',
                ':ishihara_reason' => $_POST['ishihara_reason'] ?? '',
                ':previous_color_issues' => $_POST['previous_color_issues'] ?? '',
                ':ishihara_notes' => $_POST['ishihara_notes'] ?? '',
                ':consent_info' => $consent_info,
                ':consent_reminders' => $consent_reminders,
                ':consent_terms' => $consent_terms,
                ':appointment_group_id' => $appointment_group_id
            ]);
        } else {
            // Normal appointment
            $sql = "INSERT INTO appointments (
                        client_id, service_id, full_name, suffix, gender, age, phone_number, occupation,
                        appointment_date, appointment_time,
                        wear_glasses, symptoms, concern,
                        consent_info, consent_reminders, consent_terms,
                        appointment_group_id, status_id
                    ) VALUES (
                        :client_id, :service_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                        :appointment_date, :appointment_time,
                        :wear_glasses, :symptoms, :concern,
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
                ':concern' => $_POST['concern'] ?? '',
                ':consent_info' => $consent_info,
                ':consent_reminders' => $consent_reminders,
                ':consent_terms' => $consent_terms,
                ':appointment_group_id' => $appointment_group_id
            ]);
        }
    }

    // Commit all appointments
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => count($appointments) . ' appointment(s) successfully created.',
        'group_id' => $appointment_group_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
