<?php
include '../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

class Appointment{

    private $pdo;

    public function __construct($pdo)
    {
         $this->pdo = $pdo;
    }

    public function bookAppointment($data){
        try {
            $sql = "INSERT INTO appointments (full_name, suffix, gender, age, phone_number, occupation, appointment_date, appointment_time, wear_glasses, symptoms, concern, consent_info, consent_reminders, consent_terms) 
                    VALUES (:full_name, :suffix, :gender, :age, :phone_number, :occupation, :appointment_date, :appointment_time, :wear_glasses, :symptoms, :concern, :consent_info, :consent_reminders, :consent_terms)";
            
            $stmt = $this->pdo->prepare($sql);

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
                // Optionally, you can redirect or show a success message
                header("Location: ../success.php"); // Create a success page
                exit();
            } else {
                // Handle error
                echo "Error: Could not book appointment.";
            }
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $suffix = trim($_POST['suffix'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $phone_number = trim($_POST['contact_number'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');

    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');

    // Radio (may not exist if not chosen)
    $wear_glasses = $_POST['wear_glasses'] ?? null;

    // Checkbox array → convert to string
    $symptoms = $_POST['symptoms'] ?? [];
    $symptoms_str = implode(", ", $symptoms);

    $concern = trim($_POST['concern'] ?? '');
    $consent_info = isset($_POST['consent_info']) ? 1 : 0;
    $consent_reminders = isset($_POST['consent_reminders']) ? 1 : 0;
    $consent_terms = isset($_POST['consent_terms']) ? 1 : 0;

    // Pass to your OOP Appointment class
    $appointment = new Appointment($pdo);
    $appointment->bookAppointment([
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