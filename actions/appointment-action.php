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

    public function bookAppointment($data){
        try {
            $sql = "INSERT INTO appointments (
                        client_id, staff_id, service_id, status_id,
                        full_name, suffix, gender, age, phone_number, occupation,
                        appointment_date, appointment_time,
                        wear_glasses, symptoms, concern,
                        consent_info, consent_reminders, consent_terms
                    ) VALUES (
                        :client_id, :staff_id, :service_id, :status_id,
                        :full_name, :suffix, :gender, :age, :phone_number, :occupation,
                        :appointment_date, :appointment_time,
                        :wear_glasses, :symptoms, :concern,
                        :consent_info, :consent_reminders, :consent_terms
                    )";
            
            $stmt = $this->pdo->prepare($sql);

            // Required links
            $stmt->bindParam(':client_id', $data['client_id'], PDO::PARAM_INT);

            // Optional until features are ready
            $stmt->bindParam(':staff_id', $data['staff_id'], PDO::PARAM_NULL);
            $stmt->bindParam(':service_id', $data['service_id'], PDO::PARAM_NULL);

            // Default = Pending (1)
            $stmt->bindParam(':status_id', $data['status_id'], PDO::PARAM_INT);

            // Form fields
            $stmt->bindParam(':full_name', $data['full_name']);
            $stmt->bindParam(':suffix', $data['suffix']);
            $stmt->bindParam(':gender', $data['gender']);
            $stmt->bindParam(':age', $data['age']);
            $stmt->bindParam(':phone_number', $data['phone_number']);
            $stmt->bindParam(':occupation', $data['occupation']);
            $stmt->bindParam(':appointment_date', $data['appointment_date']);
            $stmt->bindParam(':appointment_time', $data['appointment_time']);
            $stmt->bindParam(':wear_glasses', $data['wear_glasses']);
            $stmt->bindParam(':symptoms', $data['symptoms']);
            $stmt->bindParam(':concern', $data['concern']);
            $stmt->bindParam(':consent_info', $data['consent_info']);
            $stmt->bindParam(':consent_reminders', $data['consent_reminders']);
            $stmt->bindParam(':consent_terms', $data['consent_terms']);

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

    // 🔑 Get client_id from clients table
    $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        die("Error: Client record not found for this user. Please contact support.");
    }

    $client_id = $client['client_id'];

    // Collect form data
    $full_name = trim($_POST['full_name']);
    $suffix = trim($_POST['suffix'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $phone_number = trim($_POST['contact_number'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');

    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');

    $wear_glasses = $_POST['wear_glasses'] ?? null;

    $symptoms = $_POST['symptoms'] ?? [];
    $symptoms_str = implode(", ", $symptoms);

    $concern = trim($_POST['concern'] ?? '');
    $consent_info = isset($_POST['consent_info']) ? 1 : 0;
    $consent_reminders = isset($_POST['consent_reminders']) ? 1 : 0;
    $consent_terms = isset($_POST['consent_terms']) ? 1 : 0;

    // Defaults for foreign keys
    $staff_id = null; // No staff assigned yet
    $service_id = null; // No service assigned yet
    $status_id = 1; // Pending

    $appointment = new Appointment($pdo);
    $appointment->bookAppointment([
        'client_id' => $client_id,
        'staff_id' => $staff_id,
        'service_id' => $service_id,
        'status_id' => $status_id,
        'full_name' => $full_name,
        'suffix' => $suffix,
        'gender' => $gender,
        'age' => $age,
        'phone_number' => $phone_number,
        'occupation' => $occupation,
        'appointment_date' => $appointment_date,
        'appointment_time' => $appointment_time,
        'wear_glasses' => $wear_glasses,
        'symptoms' => $symptoms_str,
        'concern' => $concern,
        'consent_info' => $consent_info,
        'consent_reminders' => $consent_reminders,
        'consent_terms' => $consent_terms
    ]);
}
?>
