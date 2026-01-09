<?php

// Include backend logic located in the same directory as this file
$actionFile = __DIR__ . '/admin_dashboard-action.php';   
//roger kung napapansin mo
//may dalawang tatlong admin_dashboard yung isa kasi action mag kakatabi lang
require_once __DIR__ . '/../../config/encryption_util.php';

if (file_exists($actionFile)) {
    require_once $actionFile;
} else {
    // Log missing file for debugging; avoid exposing details to users in production
    error_log("admin_dashboard: missing action file: $actionFile");
    // Optionally: throw an exception or show a friendly message
    // throw new RuntimeException("Required backend file not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Eye Master Clinic - Admin Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>
<link rel="stylesheet" href="admin_dashboard.css?v=<?php echo @filemtime(__DIR__ . '/admin_dashboard.css') ?: time(); ?>">
</head>
<body>

<!-- <div id="page-loader-overlay">
    <div class="loader-spinner-fullpage"></div>
    <p>Loading Dashboard...</p>
</div> -->


<div id="loader-overlay">
    <div class="loader-content">
        <div class="loader-spinner"></div>
        <p id="loader-text">Loading...</p>
    </div>
</div>

<div id="toast-overlay-global" class="toast-overlay" style="z-index: 10000;">
    </div>



<div class="popup-overlay" onclick="closePopup()"></div>

<header>
    <div class="logo-section">
        <img src="../photo/LOGO.jpg" alt="Logo"> <strong>EYE MASTER CLINIC</strong>
    </div>
    <button id="menu-toggle" aria-label="Open navigation">☰</button>
    <nav id="main-nav">
        <a href="admin_dashboard.php" class="active">🏠 Dashboard</a>
        <a href="appointment.php">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php">💊 Product & Services</a>
        <a href="account.php">👤 Account</a>
        <a href="profile.php">🔍 Profile</a>
    </nav>
</header>

<div class="dashboard">
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></h1>
            <p>Here's what's happening at your clinic today</p>
        </div>
        <div class="top-controls">
            
            <div class="filter-group">
                <div style="position: relative;">
                    <button class="filter-btn" id="yearBtn" onclick="toggleDropdown('yearDropdown')"><?= $filterYear ?></button>
                    <div id="yearDropdown" class="dropdown">
                        <?= $yearDropdownItems ?>
                    </div>
                </div>

                <div style="position: relative;">
                    <button class="filter-btn" id="monthBtn" onclick="toggleDropdown('monthDropdown')"><?= $filterMonth ?></button>
                    <div id="monthDropdown" class="dropdown">
                        <?= $monthDropdownItems ?>
                    </div>
                </div>

                <div style="position: relative;">
                    <button class="filter-btn" id="weekBtn" onclick="toggleDropdown('weekDropdown')"><?= $weekBtnText ?></button>
                    <div id="weekDropdown" class="dropdown">
                        <?= $weekDropdownItems ?>
                    </div>
                </div>
                
                <div style="position: relative;">
                    <button class="filter-btn" id="dayBtn" onclick="toggleDropdown('dayDropdown')"><?= $dayBtnText ?></button>
                    <div id="dayDropdown" class="dropdown">
                        <?= $dayDropdownItems ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="stats">
        <div class="card">
            <p>Total Appointments</p>
            <h2><?= $totalAppointmentsToday ?></h2>
        </div>
        <div class="card">
            <p>Total Patients</p>
            <h2><?= $totalPatients ?></h2>
        </div>
<div class="card">
            <p>Pending Appointments</p>
            <h2><?= $pendingAppointments ?></h2>
        </div>

        <div class="card">
            <p>Missed Appointments</p>
            <h2><?= $missedAppointments ?></h2>
        </div>
        <div class="card">
            <p>Completed Appointments</p>
            <h2><?= $completedToday ?></h2>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-box">
            <h3>Appointments Overview</h3>
            <div class="chart-wrapper">
                <canvas id="appointmentsChart"></canvas>
            </div>
        </div>
        
        <div class="chart-box">
            <h3>Appointment Status</h3>
            <div class="chart-wrapper">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <div class="bottom-section">
        <div class="weekly-box">
            <h3>Weekly Appointments Overview</h3>
            <div class="weekly-wrapper">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
        
        <div class="right-section">
            <div class="recent">
                <h3>Recent Appointments</h3>
                <?php if (empty($recentAppointments)): ?>
                    <div class="empty-state">No recent appointments</div>
                <?php else: ?>
                    <?php foreach ($recentAppointments as $apt): ?>
                    <div class="recent-item">
                        <div class="recent-item-info">
                            <h4>    <?= htmlspecialchars(decrypt_data($apt['full_name'])) ?></h4>
                            <p><?= htmlspecialchars($apt['service_name']) ?> - <?= date('g:i A', strtotime($apt['appointment_date'])) ?></p>
                        </div>
                        <span class="status <?= strtolower($apt['status_name']) ?>">
                            <?= htmlspecialchars($apt['status_name']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="qr-section">
                <div class="qr-code-display">
    <?php 
        // 1. Find the latest CONFIRMED appointment to show as a test
        $qrTestSql = "SELECT appointment_id FROM appointments 
                      JOIN appointmentstatus s ON appointments.status_id = s.status_id 
                      WHERE s.status_name = 'Confirmed' 
                      ORDER BY appointment_date DESC, appointment_time DESC 
                      LIMIT 1";
        $qrTestResult = $conn->query($qrTestSql);
        
        // Default to a dummy number if no appointments exist yet
        $qrData = "123"; 
        
        if ($qrTestResult && $qrTestResult->num_rows > 0) {
            $qrData = $qrTestResult->fetch_assoc()['appointment_id'];
        }
    ?>
    <!-- 2. Generate QR with ONLY the ID number -->
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= $qrData ?>" alt="QR Code for Appt #<?= $qrData ?>">
    <p style="font-size:12px; color:#666; margin-top:5px;">Scan to test (ID: <?= $qrData ?>)</p>
</div>
                <button class="scan-btn" onclick="startScan()">Click to Scan</button>
            </div>
        </div>
    </div>
</div>
<script>
function testQRCode() {
    // Test with the ID we know exists
    const testData = "148";
    console.log('Testing with:', testData);
    processScannedData(testData);
}
</script>

<div id="popup">
    <div id="popup-header"><h3>Confirmation</h3></div>
    <div id="popup-content"></div>
    <div id="popup-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
        <button id="confirmActionBtn" style="display: none; background: #27ae60;">Confirm</button>
        <button onclick="closePopup()">Close</button>
    </div>
</div>

<!-- ====================================================================== -->
<!-- (Request #2 & #3) IN-UPDATE ANG MODAL NA ITO -->
<!-- ====================================================================== -->
<div class="detail-overlay" id="appointmentDetailModal">
    <div class="detail-card">
        
        <div class="detail-header">
            <!-- REQUEST #3: Idinagdag ang ID na "detail-title" -->
            <div class="detail-title" id="detail-title">
                Appointment Details
            </div>
            <span class="detail-id" id="detail-id">#0</span>
        </div>

        <!-- REQUEST #2: Pinalitan ang laman para maging dynamic -->
        <div id="detailModalBody" style="padding: 24px 28px; max-height: 70vh; overflow-y: auto; font-size: 15px;">
            <!-- Dito ilalagay ng JavaScript ang lahat ng data -->
        </div>

        <div class="detail-actions">
            <input type="hidden" id="modal_appointment_id" value="">
            <button class="btn-small btn-close" onclick="closeAppointmentDetailModal()">Back</button>
            
            <!-- ====================================================================== -->
            <!-- **** REQUEST #1 (CANCEL BUTTON) FIX **** -->
            <!-- Pinalitan ang `updateScannedStatus('Cancel')` -->
            <!-- ====================================================================== -->
            <button class="btn-small btn-cancel" style="background: #dc2626; color: white;" 
                    onclick="promptScannedCancel()">
                Cancel
            </button>
            
            <button class="btn-small btn-accept" style="background: #16a34a; color: white;" 
                    onclick="updateScannedStatus('Completed')">
                Complete
            </button>
        </div>

    </div>
</div>

<div class="qr-modal-overlay" id="qrScannerModal" style="display: none;">
    <div class="qr-modal-content">
        <button class="qr-modal-close" onclick="stopScan()">✕</button>
        <h3>Scan Appointment QR Code</h3>
        <p>Hold the QR code steady in the center of the frame.</p>
        <div id="qr-reader-container">
            <div id="qr-reader"></div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- **** REQUEST #1 (CANCEL BUTTON) FIX **** -->
<!-- Idinagdag ang HTML para sa Reason Modal (kinopya mula sa appointment.php) -->
<!-- ====================================================================== -->
<div id="reasonModal" class="confirm-modal" aria-hidden="true" style="z-index: 3001;">
    <div class="confirm-card" role="dialog" aria-modal="true">
        <div class="confirm-header">
            <div class="confirm-icon danger">!</div>
            <div class="confirm-title">Reason for Cancellation</div>
        </div>
        <div class="confirm-msg" id="confirmMsg">
         Please provide a reason for cancelling this appointment. This will be included in the email to the client.
        </div>
        <div id="reasonInputWrapper">
            <textarea id="cancelReasonInput" rows="4" placeholder="Type reason here..."></textarea>
        </div>
        <div class="confirm-actions">
            <button id="reasonBack" class="btn-small btn-close">Back</button>
            <button id="reasonSubmit" class="btn-small btn-cancel">Submit Cancellation</button>
        </div>
    </div>
</div>


<footer>
    © 2025 EyeMaster. All rights reserved.
</footer>

<script>

    // ... (your existing chart code) ...

// ===================================
// === LOADER FUNCTIONS =======
// ===================================
const loaderOverlay = document.getElementById('loader-overlay');
const loaderText = document.getElementById('loader-text');

function showLoader(message = 'Loading...') {
    loaderText.textContent = message;
    loaderOverlay.style.display = 'flex';
}

function hideLoader() {
    loaderOverlay.style.display = 'none';
}

// ===================================
// <-- FIX #5: BAGONG 1-SECOND PAGE LOADER
// ===================================
// admin_dashboard.php (O anumang Admin file na may page loader)
document.addEventListener('DOMContentLoaded', () => {
    const pageLoader = document.getElementById('page-loader-overlay');
    const content = document.getElementById('main-content'); // O .dashboard

    // Para sa Page Loader (na may 1s delay)
    if (pageLoader) {
        pageLoader.style.display = 'none'; // Direktang itago
    }
    if (content) {
        content.style.display = 'block'; // Direktang ipakita
        content.style.animation = 'fadeInContent 0.5s ease';
    }
    
    // Para sa Dashboard.php, tanggalin ang visibility: hidden; sa CSS/HTML kung gumamit ka nito
    const dashboard = document.querySelector('.dashboard');
    if(dashboard) dashboard.style.visibility = 'visible';
});


// Real data from PHP
const dailyData = <?php echo json_encode($dailyData); ?>;
const statusData = <?php echo json_encode($statusData); ?>;
const weeklyData = <?php echo json_encode($weeklyData); ?>;

// Prepare chart data
const dailyLabels = dailyData.length > 0 ? dailyData.map(d => {
    const date = new Date(d.date);
    return (date.getMonth() + 1) + '/' + date.getDate();
}) : ['No Data'];
const dailyValues = dailyData.length > 0 ? dailyData.map(d => parseInt(d.count)) : [0];

const statusLabels = statusData.length > 0 ? statusData.map(s => s.status_name) : ['No Data'];
const statusValues = statusData.length > 0 ? statusData.map(s => parseInt(s.count)) : [1];
const statusColors = statusData.length > 0 ? statusData.map(s => {
    const status = s.status_name.toLowerCase(); 
    if (status.includes('completed') || status.includes('approved')) return '#27ae60';
    if (status.includes('pending')) return '#f39c12';
    if (status.includes('cancel') || status.includes('missed')) return '#e74c3c'; // Idinagdag ang 'missed'
    return '#95a5a6';
}) : ['#e0e0e0'];

const dayMap = {
    'Monday': 'Mon', 'Tuesday': 'Tue', 'Wednesday': 'Wed',
    'Thursday': 'Thu', 'Friday': 'Fri', 'Saturday': 'Sat', 'Sunday': 'Sun'
};

const fixedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const weeklyMap = {};
weeklyData.forEach(item => {
    weeklyMap[item.day] = parseInt(item.count);
});

const weeklyLabels = fixedDays.map(day => dayMap[day]);
const weeklyValues = fixedDays.map(day => weeklyMap[day] || 0);

const weeklyColors = weeklyValues.map((val, idx) => idx % 2 === 0 ? '#e74c3c' : '#27ae60');

// Line Chart (Appointments Overview)
const ctx1 = document.getElementById('appointmentsChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: dailyLabels,
        datasets: [{
            label: 'Appointments',
            data: dailyValues,
            borderColor: '#e74c3c',
            backgroundColor: 'rgba(231, 76, 60, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#e74c3c'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

// Doughnut Chart (Status)
const ctx2 = document.getElementById('statusChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusValues,
            backgroundColor: statusColors,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 15, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' }
            }
        }
    }
});

// Bar Chart (Weekly Data)
const ctx3 = document.getElementById('weeklyChart').getContext('2d');
new Chart(ctx3, {
    type: 'bar',
    data: {
        labels: weeklyLabels,
        datasets: [{
            label: 'Appointments',
            data: weeklyValues,
            backgroundColor: weeklyColors,
            borderRadius: 6,
            barThickness: 35
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { font: { size: 10 } } },
            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});


// Global references
const popup = document.getElementById('popup');
const popupHeader = document.getElementById('popup-header');
const popupContent = document.getElementById('popup-content');
const popupOverlay = document.querySelector('.popup-overlay');
const confirmBtn = document.getElementById('confirmActionBtn');
let html5QrCode = null;

// ===================================
// BAGO: Dalawang klase ng Toast
// ===================================

// ===================================
// Global Toast Function
// ===================================
function showGlobalToast(msg, type = 'success') {
    let overlay = document.getElementById('toast-overlay-global');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'toast-overlay-global';
        overlay.className = 'toast-overlay';
        document.body.appendChild(overlay);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`; 
    toast.innerHTML = `
        <div class="toast-icon">${type === 'success' ? '✓' : '✕'}</div>
        <div class="toast-message">${msg}</div>
    `;
    
    overlay.innerHTML = '';
    overlay.appendChild(toast);
    overlay.classList.add('show');
    
    const timer = setTimeout(() => {
        if(overlay) overlay.classList.remove('show');
    }, 2500);
    
    overlay.addEventListener('click', () => {
        clearTimeout(timer);
        if(overlay) overlay.classList.remove('show');
    }, { once: true });
}


// Popup Functions
function openPopup(header, content, isConfirmation = false, callback = null) {
    popupHeader.innerHTML = `<h3>${header}</h3>`;
    popupContent.innerHTML = content;
    
    if (isConfirmation) {
        confirmBtn.style.display = 'block';
        confirmBtn.onclick = () => {
            if (callback) callback();
            closePopup();
        };
    } else {
        confirmBtn.style.display = 'none';
        confirmBtn.onclick = null;
    }

    popup.classList.add('active');
    popupOverlay.classList.add('active');
}

function closePopup() { 
    popup.classList.remove('active');
    popupOverlay.classList.remove('active');
    
    const qrModal = document.getElementById('qrScannerModal');
    if (qrModal && qrModal.style.display !== 'none') {
         stopScan();
    }
}

// Dropdown functionality
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    const allDropdowns = document.querySelectorAll('.dropdown');
    
    allDropdowns.forEach(d => {
        if (d.id !== id) d.classList.remove('active');
    });
    
    dropdown.classList.toggle('active');
}

function applyFilter(type, value) {
    showLoader('Applying filter...');
    const url = new URL(window.location);
    url.searchParams.set(type, value);
    
    if (type === 'year') {
        url.searchParams.delete('month');
        url.searchParams.delete('week');
        url.searchParams.delete('day');
    } else if (type === 'month') {
        url.searchParams.delete('week');
        url.searchParams.delete('day');
    } else if (type === 'week') {
        url.searchParams.delete('day');
    }
    
    window.location.href = url.toString();
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.filter-btn') && !e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
    }
});

function openCloseStoreConfirm() {
    openPopup(
        'Confirm Store Closure',
        '<p style="font-size: 14px;">Are you sure you want to close the store? This action will log you out and mark the store as closed in the system.</p>',
        true,
        closeStore
    );
}

function closeStore() {
    showLoader('Closing store...');
    fetch('close_store.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'close_store' })
    })
    .then(response => response.json())
    .then(data => {
        hideLoader(); 
        if (data.success) {
            showGlobalToast('Store closed successfully. You will be logged out now.', 'success');
            setTimeout(() => { window.location.href = 'logout.php'; }, 1500);
        } else {
            showGlobalToast(data.message || 'Unknown error', 'error'); 
        }
    })
    .catch(error => {
        hideLoader();
        console.error('Error:', error);
        showGlobalToast('An error occurred while closing the store.', 'error'); 
    });
}

function stopScan() {
    const qrModal = document.getElementById('qrScannerModal');
    if (qrModal) {
        qrModal.style.display = 'none';
    }

    if (html5QrCode) {
        try {
            html5QrCode.stop().then(() => {
                console.log("QR Scanner stopped.");
                const qrReader = document.getElementById('qr-reader');
                if(qrReader) qrReader.innerHTML = '';
            }).catch(err => {
                console.warn('QR scanner stop failed:', err);
                const qrReader = document.getElementById('qr-reader');
                if(qrReader) qrReader.innerHTML = '';
            });
        } catch (e) {
            console.warn('Error stopping scanner:', e);
            const qrReader = document.getElementById('qr-reader');
            if(qrReader) qrReader.innerHTML = '';
        }
    }
}

// ===================================
// *** IMPORTANT: Define processScannedData FIRST ***
// ===================================
function processScannedData(qrCodeMessage) {
    console.log('========== PROCESSING QR CODE ==========');
    console.log('Raw QR Data:', qrCodeMessage);
    console.log('Type:', typeof qrCodeMessage);
    console.log('Length:', qrCodeMessage.length);
    console.log('========================================');
    
    showLoader('Verifying QR Code...');
    
    fetch('verify_qr.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qr_code: qrCodeMessage })
    })
    .then(response => {
        console.log('Response Status:', response.status);
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('========== SERVER RESPONSE ==========');
        console.log(JSON.stringify(data, null, 2));
        console.log('====================================');
        
        hideLoader();
        
        if (data.success && data.data) {
            openAppointmentDetailModal(data); 
        } else {
            showGlobalToast(data.message || 'Appointment not found', 'error');
        }
    })
    .catch(error => {
        hideLoader();
        console.error('========== FETCH ERROR ==========');
        console.error(error);
        console.error('=================================');
        showGlobalToast('Error: ' + error.message, 'error');
    });
}

// ===================================
// *** NOW define startScan AFTER processScannedData ***
// ===================================
function startScan() {
    const qrModal = document.getElementById('qrScannerModal');
    if (!qrModal) {
        console.error('QR Scanner Modal not found!');
        return;
    }
    qrModal.style.display = 'flex'; 

    const qrReaderId = "qr-reader";
    
    // Clear previous instance
    const qrReaderElement = document.getElementById(qrReaderId);
    if (qrReaderElement) {
        qrReaderElement.innerHTML = '';
    }
    
    html5QrCode = new Html5Qrcode(qrReaderId);

    console.log('Starting QR scanner...');

    Html5Qrcode.getCameras().then(cameras => {
        console.log('Available cameras:', cameras);
        
        if (cameras && cameras.length) {
            const cameraId = cameras[cameras.length - 1].id;
            console.log('Using camera:', cameraId);
            
            html5QrCode.start(
                cameraId,
                { 
                    fps: 10, 
                    qrbox: { width: 250, height: 250 }
                }, 
                // SUCCESS CALLBACK - Now processScannedData is already defined!
                qrCodeMessage => {
                    console.log('✅ QR Code SCANNED!');
                    console.log('Raw value:', qrCodeMessage);
                    
                    // Stop scanner first
                    if (html5QrCode) {
                        html5QrCode.stop().then(() => {
                            console.log('Scanner stopped');
                            // Close modal
                            qrModal.style.display = 'none';
                            // Process the data
                            processScannedData(qrCodeMessage);
                        }).catch(err => {
                            console.warn('Error stopping scanner:', err);
                            qrModal.style.display = 'none';
                            processScannedData(qrCodeMessage);
                        });
                    } else {
                        qrModal.style.display = 'none';
                        processScannedData(qrCodeMessage);
                    }
                },
                // ERROR CALLBACK
                errorMessage => {
                    if (!errorMessage.includes('NotFoundException')) {
                        console.warn('Scan error:', errorMessage);
                    }
                }
            ).catch(err => {
                console.error('Unable to start scanner:', err);
                stopScan(); 
                showGlobalToast('Unable to start camera: ' + err, 'error');
            });
        } else {
            console.error('No cameras found');
            showGlobalToast('No cameras found on this device.', 'error');
        }
    }).catch(err => {
        console.error('Unable to get cameras:', err);
        showGlobalToast('Camera access denied. Please check permissions.', 'error');
    });
}

// ======================================================================
// (Request #2 & #3) IN-UPDATE ANG FUNCTION NA ITO
// ======================================================================
function openAppointmentDetailModal(payload) {
    console.log("Opening detail modal for:", payload);
    
    const d = payload.data; 
    const preformatted = payload; 

    // --- 1. Set the hidden ID para sa "Complete" / "Cancel" buttons ---
    document.getElementById('modal_appointment_id').value = d.appointment_id; 

    // --- 2. Fill in header ---
    // REQUEST #3: I-set ang title
    document.getElementById('detail-title').textContent = d.service_name || 'Appointment Details';
    document.getElementById('detail-id').textContent = '#' + d.appointment_id;
    
    // --- 3. Build HTML Body ---
    const modalBody = document.getElementById('detailModalBody');
    modalBody.innerHTML = ''; // Linisin muna

    // Ito 'yung listahan ng lahat ng posibleng data
    const labels = {
        'full_name': 'Patient Name', 'status_name': 'Status', 'service_name': 'Service',
        'staff_name': 'Staff Assigned', 'appointment_date': 'Date', 'appointment_time': 'Time',
        'age': 'Age', 'gender': 'Gender', 'phone_number': 'Phone Number',
        'occupation': 'Occupation', 'suffix': 'Suffix', 'symptoms': 'Symptoms',
        'concern': 'Concern', 'wear_glasses': 'Wears Glasses', 'notes': 'Notes',
        'certificate_purpose': 'Certificate Purpose', 'certificate_other': 'Other Certificate',
        'ishihara_test_type': 'Ishihara Test Type', 'ishihara_purpose': 'Ishihara Purpose',
        'color_issues': 'Color Issues', 'previous_color_issues': 'Previous Color Issues',
        'ishihara_notes': 'Ishihara Notes', 'ishihara_reason': 'Ishihara Reason',
        'consent_info': 'Consent (Info)', 'consent_reminders': 'Consent (Reminders)', 'consent_terms': 'Consent (Terms)',
    };
    
    // Ito ang order kung paano sila lalabas
    const displayOrder = [
        'full_name', 'status_name', 'service_name', 'staff_name',
        'appointment_date', 'appointment_time', 'age', 'gender', 'phone_number',
        'occupation', 'suffix', 'symptoms', 'concern', 'wear_glasses', 'notes',
        'certificate_purpose', 'certificate_other', 'ishihara_test_type',
        'ishihara_purpose', 'color_issues', 'previous_color_issues', 'ishihara_notes', 'ishihara_reason',
        'consent_info', 'consent_reminders', 'consent_terms'
    ];
    
    let contentHtml = '<div class="detail-grid">';
    
    for (const key of displayOrder) {
        // Ipakita lang kung may laman ang data
        if (d.hasOwnProperty(key) && d[key] !== null && d[key] !== '' && d[key] !== '0') {
            let value = d[key];
            const label = labels[key] || key;
            let rowClass = 'detail-row';
            
            // Gawing full-width ang mahahabang text
            if (['notes', 'symptoms', 'concern', 'ishihara_notes'].includes(key)) {
                rowClass += ' full-width';
            }
            
            // I-format ang itsura ng data
            if (key === 'appointment_date') {
                value = preformatted.date; // Gamitin ang pre-formatted galing sa verify_qr
            } else if (key === 'appointment_time') {
                value = preformatted.time; // Gamitin ang pre-formatted galing sa verify_qr
            } else if (key === 'consent_info' || key === 'consent_reminders' || key === 'consent_terms') {
                value = value == 1 ? 'Yes' : 'No';
            } else if (key === 'status_name') {
                value = `<span class="badge ${value.toLowerCase()}">${value}</span>`;
            } else {
                value = `<b>${value}</b>`;
            }
            
            // Idagdag sa HTML
            contentHtml += `
                <div class="${rowClass}">
                    <span class="detail-label">${label}</span>
                    <div class="detail-value">${value}</div>
                </div>
            `;
        }
    }
    contentHtml += '</div>';
    
    modalBody.innerHTML = contentHtml;
    
    // --- 4. Show the modal ---
    document.getElementById('appointmentDetailModal').classList.add('show');
}


function closeAppointmentDetailModal() {
    document.getElementById('appointmentDetailModal').classList.remove('show');
}

// ======================================================================
// (Request #1 & #2) IN-UPDATE ANG FUNCTION NA ITO
// ======================================================================
function updateScannedStatus(newStatus, reason = null) { // BAGO: Tumatanggap na ng 'reason'
    const appointmentId = document.getElementById('modal_appointment_id').value;
    
    if (!appointmentId) {
        showGlobalToast('Error: No Appointment ID found.', 'error');
        return;
    }

    showLoader(`Update Status to ${newStatus}...`);

    const bodyParams = {
        action: 'updateStatus',
        id: appointmentId,
        status_name: newStatus 
    };

    // ======================================================================
    // **** REQUEST #1 (CANCEL BUTTON) FIX ****
    // Gagamitin ang 'reason' galing sa prompt, o ang default
    // ======================================================================
    if (newStatus === 'Cancel') { 
        bodyParams.reason = reason || 'Cancelled by Admin via QR Scan.';
    }

    fetch('appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams(bodyParams)
    })
    .then(res => res.json())
    .then(data => {
        hideLoader();
        if (data.success) {
            closeAppointmentDetailModal();
            
            // ======================================================================
            // **** REQUEST #2 (AUTO-UPDATE) FIX ****
            // ======================================================================
            showGlobalToast(`Appointment marked as ${newStatus}. Dashboard will refresh.`, 'success');
            setTimeout(() => {
                location.reload(); 
            }, 2000); // 2 second delay para mabasa ang toast

        } else {
            showGlobalToast(data.message || 'Failed to update status.', 'error');
        }
    })
    .catch(err => {
        hideLoader();
        console.error('Update Error:', err);
        showGlobalToast('Network error. Could not update status.', 'error');
    });
}

// ======================================================================
// **** REQUEST #1 (CANCEL BUTTON) FIX ****
// Idinagdag ang function na ito para tawagin ang reason modal
// ======================================================================
function promptScannedCancel() {
    const modal = document.getElementById('reasonModal');
    const reasonInput = document.getElementById('cancelReasonInput');
    const submitBtn = document.getElementById('reasonSubmit');
    const backBtn = document.getElementById('reasonBack');
    const appointmentId = document.getElementById('modal_appointment_id').value;

    if (!appointmentId) {
        showGlobalToast('Error: No Appointment ID found.', 'error');
        return;
    }

    reasonInput.value = ''; 
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');

    // TANGGALIN ang lumang listeners para iwas double-click
    // Gagamit tayo ng .onclick para sigurado
    let onKey; 

    function cleanUp() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', onKey);
    }

    submitBtn.onclick = () => {
        const reason = reasonInput.value.trim();
        if (reason === '') {
            showGlobalToast('A reason is required to cancel.', 'error');
            return;
        }
        cleanUp();
        // Tawagin ang ating main update function, na may kasamang reason
        updateScannedStatus('Cancel', reason); 
    };

    backBtn.onclick = () => cleanUp();
    
    onKey = (e) => { 
        if (e.key === 'Escape') {
            cleanUp();
            // Tanggalin ang listener
            document.removeEventListener('keydown', onKey);
        }
    };
    // Magdagdag ng bagong listener
    document.addEventListener('keydown', onKey);
}


// ======================================================================
// (Keyboard Scanner Listener)
// ======================================================================
(function() {
    let qrCodeChars = [];
    let lastKeystrokeTime = new Date();

    document.addEventListener('keydown', function(e) {
        
        const activeEl = document.activeElement;
        // BAGO: I-check din ang reason input
        if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.id === 'cancelReasonInput')) {
            return; 
        }
        
        const now = new Date();
        if (now - lastKeystrokeTime > 100) {
            qrCodeChars = []; 
        }
        lastKeystrokeTime = now;

        if (e.key === 'Enter' || e.keyCode === 13) {
            if (qrCodeChars.length > 0) { 
                e.preventDefault(); 
                const qrString = qrCodeChars.join('');
                
                console.log('Handheld Scanner Data:', qrString);
                
                processScannedData(qrString); 
            }
            qrCodeChars = []; 
        } else {
            if (e.key && e.key.length === 1) {
                qrCodeChars.push(e.key);
            }
        }
    });
    
    console.log('Handheld QR scanner listener is active.');
})();

</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const menuToggle = document.getElementById('menu-toggle');
  const mainNav = document.getElementById('main-nav');

  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', function() {
      mainNav.classList.toggle('show');
      
      if (mainNav.classList.contains('show')) {
        this.innerHTML = '✕'; 
        this.setAttribute('aria-label', 'Close navigation');
      } else {
        this.innerHTML = '☰';
        this.setAttribute('aria-label', 'Open navigation');
      }
    });

    mainNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', function() {
        mainNav.classList.remove('show');
        menuToggle.innerHTML = '☰';
        menuToggle.setAttribute('aria-label', 'Open navigation');
      });
    });
  }
});
</script>
<script>
    history.replaceState(null, null, location.href);
    history.pushState(null, null, location.href);
    window.onpopstate = function () {
        history.go(1);
    };
</script>


</body>
</html>