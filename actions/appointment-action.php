

<?php
session_start();
include '../config/db.php';

$db = new Database();
$pdo = $db->getConnection();

class Appointment {

    private $pdo;

    public function __construct($pdo)
    {
         $this->pdo = $pdo;
    }

    public function bookAppointment($data, $type){
        try {
            if ($type === 'medical') {
                // ✅ MEDICAL CERTIFICATE
                $sql = "INSERT INTO appointments (
                            client_id, full_name, suffix, gender, age, phone_number, occupation,
                            certificate_purpose, certificate_other,
                            appointment_date, appointment_time,
                            consent_info, consent_reminders, consent_terms
                        ) VALUES (
                            :client_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                            :certificate_purpose, :certificate_other,
                            :appointment_date, :appointment_time,
                            :consent_info, :consent_reminders, :consent_terms
                        )";
            } elseif ($type === 'ishihara') {
                // ✅ ISHIHARA TEST
                $sql = "INSERT INTO appointments (
                            client_id, full_name, suffix, gender, age, phone_number, occupation,
                            appointment_date, appointment_time,
                            ishihara_test_type, ishihara_reason, previous_color_issues, ishihara_notes,
                            consent_info, consent_reminders, consent_terms
                        ) VALUES (
                            :client_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                            :appointment_date, :appointment_time,
                            :ishihara_test_type, :ishihara_reason, :previous_color_issues, :ishihara_notes,
                            :consent_info, :consent_reminders, :consent_terms
                        )";
            } else {
                // ✅ NORMAL APPOINTMENT
                $sql = "INSERT INTO appointments (
                            client_id, full_name, suffix, gender, age, phone_number, occupation,
                            appointment_date, appointment_time,
                            wear_glasses, symptoms, concern,
                            consent_info, consent_reminders, consent_terms
                        ) VALUES (
                            :client_id, :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                            :appointment_date, :appointment_time,
                            :wear_glasses, :symptoms, :concern,
                            :consent_info, :consent_reminders, :consent_terms
                        )";
            }

            $stmt = $this->pdo->prepare($sql);

            // 🔹 Common fields
            $stmt->bindParam(':client_id', $data['client_id']);
            $stmt->bindParam(':full_name', $data['full_name']);
            $stmt->bindParam(':suffix', $data['suffix']);
            $stmt->bindParam(':gender', $data['gender']);
            $stmt->bindParam(':age', $data['age']);
            $stmt->bindParam(':phone_number', $data['phone_number']);
            $stmt->bindParam(':occupation', $data['occupation']);
            $stmt->bindParam(':appointment_date', $data['appointment_date']);
            $stmt->bindParam(':appointment_time', $data['appointment_time']);
            $stmt->bindParam(':consent_info', $data['consent_info']);
            $stmt->bindParam(':consent_reminders', $data['consent_reminders']);
            $stmt->bindParam(':consent_terms', $data['consent_terms']);

            // 🔹 Extra per type
            if ($type === 'medical') {
                $stmt->bindParam(':certificate_purpose', $data['certificate_purpose']);
                $stmt->bindParam(':certificate_other', $data['certificate_other']);
            } elseif ($type === 'ishihara') {
                $stmt->bindParam(':ishihara_test_type', $data['ishihara_test_type']);
                $stmt->bindParam(':ishihara_reason', $data['ishihara_reason']);
                $stmt->bindParam(':previous_color_issues', $data['previous_color_issues']);
                $stmt->bindParam(':ishihara_notes', $data['ishihara_notes']);
            } else {
                $stmt->bindParam(':wear_glasses', $data['wear_glasses']);
                $stmt->bindParam(':symptoms', $data['symptoms']);
                $stmt->bindParam(':concern', $data['concern']);
            }

            if ($stmt->execute()) {
                header("Location: ../public/success.php");
                exit();
            } else {
                echo "Error: Could not book appointment.";
            }
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../public/login.php");
        exit();
    }

    // 🔑 Get client_id from the clients table based on logged in user
    $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        die("Error: Client record not found for this user. Please contact support.");
    }

    $client_id = $client['client_id'];

    // 🔹 Common fields
    $full_name = trim($_POST['full_name']);
    $suffix = trim($_POST['suffix'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $phone_number = trim($_POST['contact_number'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $consent_info = isset($_POST['consent_info']) ? 1 : 0;
    $consent_reminders = isset($_POST['consent_reminders']) ? 1 : 0;
    $consent_terms = isset($_POST['consent_terms']) ? 1 : 0;

    // 🔹 Detect type
    $type = 'normal';
    if (isset($_POST['certificate_purpose'])) {
        $type = 'medical';
    } elseif (isset($_POST['ishihara_test_type'])) {
        $type = 'ishihara';
    }

    // 🔹 Build data
    $data = [
        'client_id' => $client_id,
        'full_name' => $full_name,
        'suffix' => $suffix,
        'gender' => $gender,
        'age' => $age,
        'phone_number' => $phone_number,
        'occupation' => $occupation,
        'appointment_date' => $appointment_date,
        'appointment_time' => $appointment_time,
        'consent_info' => $consent_info,
        'consent_reminders' => $consent_reminders,
        'consent_terms' => $consent_terms
    ];

    // 🔹 Add per type
    if ($type === 'medical') {
    // Automatically set default medical purpose instead of relying on radio buttons
    $data['certificate_purpose'] = 'Fit to Work'; // or 'Medical Certificate'
    $data['certificate_other'] = ''; // you can leave this empty or set a note if needed
    

    } elseif ($type === 'ishihara') {
        $data['ishihara_test_type'] = $_POST['ishihara_test_type'] ?? '';
        $data['ishihara_reason'] = trim($_POST['ishihara_reason'] ?? '');
        $data['previous_color_issues'] = $_POST['previous_color_issues'] ?? 'Unknown';
        $data['ishihara_notes'] = trim($_POST['ishihara_notes'] ?? '');
    } else {
        $data['wear_glasses'] = $_POST['wear_glasses'] ?? null;
        $symptoms = $_POST['symptoms'] ?? [];
        $data['symptoms'] = implode(", ", $symptoms);
        $data['concern'] = trim($_POST['concern'] ?? '');
    }

    // 🔹 Save
    $appointment = new Appointment($pdo);
    $appointment->bookAppointment($data, $type);
}
?>
