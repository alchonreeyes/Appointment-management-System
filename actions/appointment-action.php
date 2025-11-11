<?php
session_start();
include '../config/db.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // ✅ Must be logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    // 🔑 Get client_id linked to the logged-in user
    $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Client record not found.']);
        exit;
    }

    $client_id = $client['client_id'];

    // ✅ Gather common form inputs
    $service_id = $_POST['service_id'] ?? 1;
    $full_name = trim($_POST['full_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $phone_number = trim($_POST['contact_number'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $consent_info = isset($_POST['consent_info']) ? 1 : 0;
    $consent_reminders = isset($_POST['consent_reminders']) ? 1 : 0;
    $consent_terms = isset($_POST['consent_terms']) ? 1 : 0;

    // ✅ Determine appointment type (based on existing fields)
    $type = 'normal';
    if (isset($_POST['certificate_purpose'])) {
        $type = 'medical';
    } elseif (isset($_POST['ishihara_test_type'])) {
        $type = 'ishihara';
    }

    // ✅ Parse the 3-day appointment selection
    $dates = [];
    if (!empty($_POST['appointment_dates_json'])) {
        $dates = json_decode($_POST['appointment_dates_json'], true);
    } elseif (!empty($_POST['appointment_date'])) {
        // fallback: single-date mode (for compatibility)
        $dates = [$_POST['appointment_date']];
    }

    if (!is_array($dates) || count($dates) === 0) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one appointment date.']);
        exit;
    }

    // ✅ Slot availability check for each selected date
    foreach ($dates as $date) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as confirmed_count 
            FROM appointments 
            WHERE appointment_date = ? 
            AND status_id = (SELECT status_id FROM appointmentstatus WHERE status_name = 'Confirmed')
        ");
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $maxSlots = 3;
        if ($result['confirmed_count'] >= $maxSlots) {
            echo json_encode([
                'success' => false,
                'message' => "Sorry, $date is already fully booked (3 confirmed appointments)."
            ]);
            exit;
        }
    }

    // ✅ Everything looks good — insert appointment group
    $appointment_group_id = uniqid('grp_', true);

    $pdo->beginTransaction();

    foreach ($dates as $date) {
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
                ':certificate_purpose' => $_POST['certificate_purpose'] ?? 'Fit to Work',
                ':certificate_other' => $_POST['certificate_other'] ?? '',
                ':appointment_date' => $date,
                ':appointment_time' => $appointment_time,
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
                ':appointment_time' => $appointment_time,
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
                ':appointment_time' => $appointment_time,
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

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Appointment successfully created for multiple dates.',
        'group_id' => $appointment_group_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
