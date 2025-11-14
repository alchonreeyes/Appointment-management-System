<?php
session_start();
require '../config/db_mysqli.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ========== HANDLE AJAX REQUESTS ==========
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    // Get client_id first
    $stmt_client = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ?");
    $stmt_client->bind_param("i", $user_id);
    $stmt_client->execute();
    $client_result = $stmt_client->get_result()->fetch_assoc();
    
    if (!$client_result) {
        echo json_encode(['success' => false, 'message' => 'Client not found']);
        exit();
    }
    
    $client_id = $client_result['client_id'];
    
    // CANCEL APPOINTMENT
    if ($_POST['ajax_action'] === 'cancel') {
        $appointment_id = intval($_POST['appointment_id']);
        
        // Get Cancel status_id
        $stmt_status = $conn->prepare("SELECT status_id FROM appointmentstatus WHERE status_name = 'Cancel'");
        $stmt_status->execute();
        $cancel_status = $stmt_status->get_result()->fetch_assoc();
        
        if (!$cancel_status) {
            echo json_encode(['success' => false, 'message' => 'Cancel status not found']);
            exit();
        }
        
        // Update only if it's pending and belongs to this user
        $update_query = "
            UPDATE appointments 
            SET status_id = ? 
            WHERE appointment_id = ? 
            AND client_id = ? 
            AND status_id = (SELECT status_id FROM appointmentstatus WHERE status_name = 'Pending')
        ";
        
        $stmt_update = $conn->prepare($update_query);
        $stmt_update->bind_param("iii", $cancel_status['status_id'], $appointment_id, $client_id);
        
        if ($stmt_update->execute() && $stmt_update->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not cancel. Appointment may no longer be pending.']);
        }
        exit();
    }
    
    // VIEW APPOINTMENT DETAILS
    if ($_POST['ajax_action'] === 'view') {
        $appointment_id = intval($_POST['appointment_id']);
        
        $view_query = "
            SELECT 
                a.*,
                s.status_name,
                ser.service_name,
                st.full_name as staff_name
            FROM appointments a
            LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
            LEFT JOIN services ser ON a.service_id = ser.service_id
            LEFT JOIN staff st ON a.staff_id = st.staff_id
            WHERE a.appointment_id = ? AND a.client_id = ?
        ";
        
        $stmt_view = $conn->prepare($view_query);
        $stmt_view->bind_param("ii", $appointment_id, $client_id);
        $stmt_view->execute();
        $appt = $stmt_view->get_result()->fetch_assoc();
        
        if ($appt) {
            echo json_encode(['success' => true, 'appointment' => $appt]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        }
        exit();
    }
}

// ========== FETCH USER DATA ==========
$query = "SELECT u.*, c.client_id, c.birth_date, c.gender, c.age, c.suffix, c.occupation 
          FROM users u 
          LEFT JOIN clients c ON u.id = c.user_id 
          WHERE u.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: ../public/login.php");
    exit();
}

// Get user initials
$name_parts = explode(' ', $user['full_name']);
$initials = count($name_parts) >= 2 
    ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1))
    : strtoupper(substr($user['full_name'], 0, 2));

// Filters
$status_filter = $_GET['status'] ?? 'All';
$date_filter = $_GET['date'] ?? 'All';

// Build WHERE clause
$where_conditions = ["a.client_id = ?"];
$params = [$user['client_id']];
$param_types = "i";

if ($status_filter !== 'All') {
    $where_conditions[] = "s.status_name = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($date_filter !== 'All' && !empty($date_filter)) {
    $where_conditions[] = "DATE(a.appointment_date) = ?";
    $params[] = $date_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Fetch appointments
$appointments_query = "
    SELECT 
        a.appointment_id,
        a.full_name,
        a.appointment_date,
        a.appointment_time,
        a.symptoms,
        a.concern,
        a.wear_glasses,
        a.notes,
        s.status_name,
        ser.service_name,
        st.full_name as staff_name
    FROM appointments a
    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
    LEFT JOIN services ser ON a.service_id = ser.service_id
    LEFT JOIN staff st ON a.staff_id = st.staff_id
    WHERE {$where_clause}
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";

$stmt_appts = $conn->prepare($appointments_query);
$stmt_appts->bind_param($param_types, ...$params);
$stmt_appts->execute();
$appointments = $stmt_appts->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        SUM(CASE WHEN s.status_name = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN s.status_name = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN s.status_name = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN s.status_name = 'Cancel' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN s.status_name = 'Missed' THEN 1 ELSE 0 END) as missed
    FROM appointments a
    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
    WHERE a.client_id = ?
";

$stmt_stats = $conn->prepare($stats_query);
$stmt_stats->bind_param("i", $user['client_id']);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Eye Master Clinic</title>
    <link rel="stylesheet" href="./style/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Additional styles for appointments page */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #dee2e6;
            transition: all 0.3s;
        }

        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-box.pending { border-color: #ffc107; }
        .stat-box.confirmed { border-color: #28a745; }
        .stat-box.completed { border-color: #007bff; }
        .stat-box.cancelled { border-color: #6c757d; }
        .stat-box.missed { border-color: #dc3545; }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .filters-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 11px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: #f8f9fa;
            min-width: 150px;
        }

        .appointments-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .appointment-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
            border-left: 4px solid #8B0000;
        }

        .appointment-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateX(5px);
        }

        .appointment-card.pending { border-left-color: #ffc107; }
        .appointment-card.confirmed { border-left-color: #28a745; }
        .appointment-card.completed { border-left-color: #007bff; }
        .appointment-card.cancelled { border-left-color: #6c757d; }
        .appointment-card.missed { border-left-color: #dc3545; }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .appointment-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .appointment-id {
            font-size: 12px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
        }

        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.confirmed { background: #d4edda; color: #155724; }
        .status-badge.completed { background: #cce5ff; color: #004085; }
        .status-badge.cancelled { background: #e2e3e5; color: #383d41; }
        .status-badge.missed { background: #f8d7da; color: #721c24; }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .detail-item i {
            color: #8B0000;
            width: 20px;
        }

        .detail-label {
            color: #6c757d;
            font-weight: 500;
        }

        .detail-value {
            color: #2c3e50;
            font-weight: 600;
        }

        .appointment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-view {
            background: #007bff;
            color: white;
        }

        .btn-view:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
        }

        .btn-cancel:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-book {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            font-size: 14px;
        }

        .btn-book:hover {
            background: #218838;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #495057;
        }

        .empty-state p {
            margin-bottom: 20px;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
        }

        .modal-body {
            padding: 25px;
        }

        .detail-grid {
            display: grid;
            gap: 15px;
        }

        .detail-row {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .detail-row label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .detail-row .value {
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .appointment-details {
                grid-template-columns: 1fr;
            }

            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group select,
            .filter-group input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php' ?>
    
    <div class="link-section">
        <a href="../public/home.php"><i class="fa-solid fa-house"></i></a>
        <a href="#" class="side-toggle"><i class="fa-solid fa-bars"></i></a>
    </div>

    <?php include 'sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="profile">
        <div class="profile-details">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <div class="avatar-circle"><?php echo $initials; ?></div>
                </div>
                <div class="profile-header-info">
                    <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <p class="user-id">ID: <?php echo $user['id']; ?></p>
                    <span class="user-badge">CLIENT</span>
                </div>
            </div>

            <!-- Main Content -->
            <div class="profile-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <h2 class="section-title" style="margin-bottom: 0;">
                        <i class="fa-solid fa-calendar-check"></i>
                        My Appointments
                    </h2>
                    <a href="../public/book_appointment.php" class="btn btn-book">
                        <i class="fa-solid fa-plus"></i> Book New Appointment
                    </a>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-box pending">
                        <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-box confirmed">
                        <div class="stat-number"><?php echo $stats['confirmed'] ?? 0; ?></div>
                        <div class="stat-label">Confirmed</div>
                    </div>
                    <div class="stat-box completed">
                        <div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-box missed">
                        <div class="stat-number"><?php echo $stats['missed'] ?? 0; ?></div>
                        <div class="stat-label">Missed</div>
                    </div>
                    <div class="stat-box cancelled">
                        <div class="stat-number"><?php echo $stats['cancelled'] ?? 0; ?></div>
                        <div class="stat-label">Cancelled</div>
                    </div>
                </div>

                <!-- Filters -->
                <form method="GET" class="filters-bar">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" onchange="this.form.submit()">
                            <option value="All" <?php echo $status_filter === 'All' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Confirmed" <?php echo $status_filter === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Missed" <?php echo $status_filter === 'Missed' ? 'selected' : ''; ?>>Missed</option>
                            <option value="Cancel" <?php echo $status_filter === 'Cancel' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date">Date</label>
                        <input type="date" name="date" id="date" value="<?php echo $date_filter !== 'All' ? $date_filter : ''; ?>" onchange="this.form.submit()">
                    </div>
                    <?php if ($status_filter !== 'All' || $date_filter !== 'All'): ?>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <a href="appointments.php" class="btn-small" style="background: #6c757d; color: white; text-decoration: none;">
                                <i class="fa-solid fa-times"></i> Clear Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </form>

                <!-- Appointments List -->
                <div class="appointments-list">
                    <?php if (empty($appointments)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-calendar-xmark"></i>
                            <h3>No Appointments Found</h3>
                            <p>You don't have any appointments yet. Book your first appointment to get started!</p>
                            <a href="../public/appointment_form.php" class="btn btn-book">
                                <i class="fa-solid fa-plus"></i> Book Appointment
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($appointments as $appt): 
                            $status_class = strtolower($appt['status_name']);
                        ?>
                            <div class="appointment-card <?php echo $status_class; ?>">
                                <div class="appointment-header">
                                    <div>
                                        <div class="appointment-title"><?php echo htmlspecialchars($appt['service_name'] ?? 'General Appointment'); ?></div>
                                        <span class="appointment-id">ID: #<?php echo $appt['appointment_id']; ?></span>
                                    </div>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($appt['status_name']); ?>
                                    </span>
                                </div>

                                <div class="appointment-details">
                                    <div class="detail-item">
                                        <i class="fa-solid fa-calendar"></i>
                                        <div>
                                            <div class="detail-label">Date</div>
                                            <div class="detail-value"><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fa-solid fa-clock"></i>
                                        <div>
                                            <div class="detail-label">Time</div>
                                            <div class="detail-value"><?php echo date('g:i A', strtotime($appt['appointment_time'])); ?></div>
                                        </div>
                                    </div>
                                    <?php if (!empty($appt['staff_name'])): ?>
                                    <div class="detail-item">
                                        <i class="fa-solid fa-user-doctor"></i>
                                        <div>
                                            <div class="detail-label">Staff</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($appt['staff_name']); ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="appointment-actions">
                                    <button class="btn-small btn-view" onclick="viewAppointment(<?php echo $appt['appointment_id']; ?>)">
                                        <i class="fa-solid fa-eye"></i> View Details
                                    </button>
                                    <?php if ($appt['status_name'] === 'Pending'): ?>
                                        <button class="btn-small btn-cancel" onclick="cancelAppointment(<?php echo $appt['appointment_id']; ?>)">
                                            <i class="fa-solid fa-times"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-calendar-check"></i> Appointment Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php' ?>

    <script>
        // Debug: Check if functions are loaded
        console.log('Script loaded');

        // Sidebar Toggle
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const toggleBtn = document.querySelector('.side-toggle');

        if (toggleBtn && sidebar && sidebarOverlay) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                this.classList.toggle('active');
            });

            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                if (toggleBtn) toggleBtn.classList.remove('active');
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    if (toggleBtn) toggleBtn.classList.remove('active');
                }
            });
        }

        // View Appointment Details
        function viewAppointment(id) {
            console.log('viewAppointment called with ID:', id);
            
            const formData = new FormData();
            formData.append('ajax_action', 'view');
            formData.append('appointment_id', id);

            console.log('Sending view request...');

            fetch('appointments.php', {
                method: 'POST',
                body: formData
            })
            .then(res => {
                console.log('Response status:', res.status);
                return res.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    
                    if (data.success) {
                        const appt = data.appointment;
                        const modalBody = document.getElementById('modalBody');
                        
                        if (!modalBody) {
                            console.error('Modal body not found!');
                            return;
                        }
                        
                        // Format date
                        const dateObj = new Date(appt.appointment_date);
                        const formattedDate = dateObj.toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric' 
                        });
                        
                        // Format time
                        const timeStr = appt.appointment_time;
                        const [hours, minutes] = timeStr.split(':');
                        const hour = parseInt(hours);
                        const ampm = hour >= 12 ? 'PM' : 'AM';
                        const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                        const formattedTime = `${displayHour}:${minutes} ${ampm}`;
                        
                        modalBody.innerHTML = `
                            <div class="detail-grid">
                                <div class="detail-row">
                                    <label>Appointment ID</label>
                                    <div class="value">#${appt.appointment_id}</div>
                                </div>
                                <div class="detail-row">
                                    <label>Service</label>
                                    <div class="value">${appt.service_name || 'N/A'}</div>
                                </div>
                                <div class="detail-row">
                                    <label>Date</label>
                                    <div class="value">${formattedDate}</div>
                                </div>
                                <div class="detail-row">
                                    <label>Time</label>
                                    <div class="value">${formattedTime}</div>
                                </div>
                                <div class="detail-row">
                                    <label>Status</label>
                                    <div class="value">
                                        <span class="status-badge ${appt.status_name.toLowerCase()}">${appt.status_name}</span>
                                    </div>
                                </div>
                                ${appt.staff_name ? `
                                <div class="detail-row">
                                    <label>Assigned Staff</label>
                                    <div class="value">${appt.staff_name}</div>
                                </div>` : ''}
                                ${appt.symptoms ? `
                                <div class="detail-row">
                                    <label>Symptoms</label>
                                    <div class="value">${appt.symptoms}</div>
                                </div>` : ''}
                                ${appt.concern ? `
                                <div class="detail-row">
                                    <label>Concerns</label>
                                    <div class="value">${appt.concern}</div>
                                </div>` : ''}
                                ${appt.wear_glasses ? `
                                <div class="detail-row">
                                    <label>Wears Glasses</label>
                                    <div class="value">${appt.wear_glasses}</div>
                                </div>` : ''}
                                ${appt.notes ? `
                                <div class="detail-row">
                                    <label>Notes</label>
                                    <div class="value">${appt.notes}</div>
                                </div>` : ''}
                            </div>
                        `;
                        
                        const modal = document.getElementById('viewModal');
                        if (modal) {
                            modal.classList.add('active');
                            console.log('Modal opened');
                        } else {
                            console.error('Modal element not found!');
                        }
                    } else {
                        alert(data.message || 'Failed to load appointment details.');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.log('Response was not valid JSON');
                    alert('Error: Invalid response from server');
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                alert('Error loading appointment details: ' + err.message);
            });
        }

        // Cancel Appointment
        function cancelAppointment(id) {
            console.log('cancelAppointment called with ID:', id);
            
            if (!confirm('Are you sure you want to cancel this appointment?')) {
                return;
            }

            const formData = new FormData();
            formData.append('ajax_action', 'cancel');
            formData.append('appointment_id', id);

            console.log('Sending cancel request...');

            fetch('appointments.php', {
                method: 'POST',
                body: formData
            })
            .then(res => {
                console.log('Response status:', res.status);
                return res.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    
                    if (data.success) {
                        alert(data.message || 'Appointment cancelled successfully.');
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to cancel appointment.');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    alert('Error: Invalid response from server');
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                alert('Error cancelling appointment: ' + err.message);
            });
        }

        // Close Modal
        function closeModal() {
            const modal = document.getElementById('viewModal');
            if (modal) {
                modal.classList.remove('active');
                console.log('Modal closed');
            }
        }

        // Close modal on overlay click
        const viewModal = document.getElementById('viewModal');
        if (viewModal) {
            viewModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        } else {
            console.warn('View modal not found');
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        console.log('All event listeners attached');
    </script>
</body>
</html>