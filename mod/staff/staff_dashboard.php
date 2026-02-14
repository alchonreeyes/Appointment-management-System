<?php
session_start();
// Location: EYE MASTER/staff/staff_dashboard.php

// INCLUDE ACTION FILE
// Ensure these paths are correct for your directory structure
require_once __DIR__ . '/staff_dashboard-action.php';
require_once __DIR__ . '/../../config/encryption_util.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Eye Master Clinic - Admin Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>
<link rel="stylesheet" href="staff_dashboard.css?v=<?php echo @filemtime(__DIR__ . '/staff_dashboard.css') ?: time(); ?>">

<style>
    /* =========================================
       LOADER & TRANSITION STYLES (From Patient Record)
       ========================================= */
    #loader-overlay {
        position: fixed;
        inset: 0;
        background: #ffffff;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: opacity 0.3s ease;
    }

    #loader-overlay.hidden {
        opacity: 0;
        pointer-events: none;
    }

    .loader-spinner {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #991010;
        animation: spin 1s linear infinite;
    }

    .loader-text {
        margin-top: 15px;
        font-size: 16px;
        font-weight: 600;
        color: #5a6c7d;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

    /* Main Content Fade In */
    #main-content {
        display: none; /* Hidden by default until loader finishes */
    }

    /* Action Loader (Processing...) */
    #actionLoader { 
        display: none; 
        position: fixed; 
        inset: 0; 
        background: rgba(2, 12, 20, 0.6); 
        z-index: 9990; 
        align-items: center; 
        justify-content: center; 
        backdrop-filter: blur(4px); 
    }
    #actionLoader.show { 
        display: flex; 
        animation: fadeIn .2s ease; 
    }

    /* Toast Styles */
    .toast-overlay { position: fixed; inset: 0; background: rgba(34, 49, 62, 0.6); z-index: 9998; display: flex; align-items: center; justify-content: center; opacity: 1; transition: opacity 0.3s ease-out; backdrop-filter: blur(4px); display: none; }
    .toast-overlay.show { display: flex; }
    .toast { background: #fff; color: #1a202c; padding: 24px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 9999; display: flex; align-items: center; gap: 16px; font-weight: 600; min-width: 300px; max-width: 450px; text-align: left; animation: slideUp .3s ease; }
    .toast-icon { font-size: 24px; font-weight: 800; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff; }
    .toast.success { border-top: 4px solid #16a34a; } .toast.success .toast-icon { background: #16a34a; }
    .toast.error { border-top: 4px solid #dc2626; } .toast.error .toast-icon { background: #dc2626; }
    @keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }

    /* =========================================
       EXISTING DASHBOARD STYLES
       ========================================= */
    .recent-item .status {
        font-weight: 800;
        font-size: 13px;
        text-transform: uppercase;
        background: none !important;
        background-color: transparent !important;
        padding: 0 !important;
        border-radius: 0 !important;
        border: none !important;
        box-shadow: none !important;
    }

    .status.confirmed { color: #27ae60 !important; }
    .status.completed { color: #16a34a !important; }
    .status.pending { color: #d35400 !important; }
    .status.cancel, .status.cancelled { color: #c0392b !important; }

    /* QR Camera Controls */
    .qr-controls { margin-bottom: 15px; display: flex; justify-content: center; gap: 10px; align-items: center; }
    #camera-select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; font-size: 14px; max-width: 200px; background: white; }
    #swap-camera-btn { padding: 8px 12px; background-color: #34495e; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background 0.3s; }
    #swap-camera-btn:hover { background-color: #2c3e50; }

    /* Export Button */
    .btn-export {
        background-color: #1D6F42; color: white !important; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 14px; border: 1px solid #145c32; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.2s ease; cursor: pointer;
    }
    .btn-export:hover { background-color: #145c32; box-shadow: 0 4px 6px rgba(0,0,0,0.15); transform: translateY(-1px); }
    .btn-export:active { transform: translateY(0); box-shadow: 0 1px 2px rgba(0,0,0,0.1); }

    /* Charts Grid */
    .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 20px; }

    /* Print Styles */
    @media print {
        header, #menu-toggle, .filter-group, .qr-section, .bottom-section .scan-btn, footer, .toast-overlay, .popup-overlay, #loader-overlay, .welcome-section .welcome-text, .detail-overlay, .qr-modal-overlay, .filter-btn, .btn-export, #actionLoader { display: none !important; }
        body { background-color: white !important; color: black !important; font-family: 'Times New Roman', Times, serif; font-size: 12pt; }
        .dashboard { padding: 0; margin: 0; width: 100%; max-width: 100%; background: white !important; box-shadow: none !important; }
        .print-header { display: block !important; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; }
        .print-header h1 { margin: 0; font-size: 24px; color: #000; text-transform: uppercase; }
        .print-header p { margin: 5px 0 0; font-size: 14px; color: #333; }
        .stats { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 15px; margin-bottom: 30px; }
        .stats .card { border: 1px solid #000 !important; background: #fff !important; box-shadow: none !important; color: #000 !important; padding: 15px; break-inside: avoid; }
        .charts-grid { display: block !important; margin-top: 20px; }
        .chart-box { border: 1px solid #ddd; page-break-inside: avoid; margin-bottom: 30px; box-shadow: none !important; background: #fff !important; }
        .bottom-section { display: block !important; margin-top: 20px; }
        .right-section, .weekly-box { width: 100% !important; box-shadow: none !important; border: 1px solid #ddd; margin-bottom: 20px; }
        .recent-item { border-bottom: 1px solid #ccc; color: #000 !important; }
        #main-content { display: block !important; }
    }
    .print-header { display: none; }
</style>
</head>
<body>

<div id="loader-overlay">
    <div class="loader-spinner"></div>
    <p class="loader-text">Loading Dashboard...</p>
</div>

<div id="main-content">
    
    <div id="toast-overlay-global" class="toast-overlay" style="z-index: 10000;"></div>
    <div class="popup-overlay" onclick="closePopup()"></div>

    <header>
        <div class="logo-section">
            <img src="../photo/LOGO.jpg" alt="Logo"> <strong>EYE MASTER CLINIC</strong>
        </div>
        <button id="menu-toggle" aria-label="Open navigation">☰</button>
        <nav id="main-nav">
            <a href="staff_dashboard.php" class="active">🏠 Dashboard</a>
            <a href="appointment.php">📅 Appointments</a>
            <a href="patient_record.php">📘 Patient Record</a>
            <a href="product.php">💊 Product & Services</a>
            <a href="profile.php">🔍 Profile</a>
        </nav>
    </header>

    <div class="dashboard">

        <div class="print-header">
            <h1>EYE MASTER CLINIC - MANAGEMENT REPORT</h1>
            <p><strong>Generated By:</strong> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></p>
            <p><strong>Date of Report:</strong> <?php echo date('F j, Y, g:i A'); ?></p>
            <p style="margin-top: 5px; font-style: italic;">
                <strong>Filter Applied:</strong> 
                Year: <?= $filterYear ?> | Month: <?= $filterMonth ?> | Week: <?= $filterWeek ?> | Day: <?= $filterDay ?>
            </p>
        </div>

        <div class="welcome-section">
            <div class="welcome-text">
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></h1>
                <p>Here's what's happening at your clinic.</p>
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

                    <a href="staff_dashboard_export.php?<?php echo $_SERVER['QUERY_STRING']; ?>" 
                       class="btn-export" 
                       title="Download Excel Report">
                        <span>📥</span> Download Excel
                    </a>
                </div>
            </div>
        </div>

        <div class="stats">
            <div class="card">
                <p>Total Appointments</p>
                <h2><?= $totalAppointmentsToday ?></h2>
            </div>
            
            <div class="card">
                <p>Pending Appointments</p>
                <h2><?= $pendingAppointments ?></h2>
            </div>

            <div class="card">
                <p>Confirmed Appointments</p>
                <h2><?= $confirmedAppointments ?></h2>
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

            <div class="chart-box">
                <h3>Top Services Availment</h3>
                <div class="chart-wrapper">
                    <canvas id="servicesChart"></canvas>
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
                        <div class="empty-state">No recent appointments for this filter.</div>
                    <?php else: ?>
                        <?php foreach ($recentAppointments as $apt): ?>
                        <div class="recent-item">
                            <div class="recent-item-info">
                                <h4><?= htmlspecialchars(decrypt_data($apt['full_name'])) ?></h4>
                                <p><?= htmlspecialchars($apt['service_name']) ?> - <?= date('g:i A', strtotime($apt['appointment_time'])) ?></p>
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
                            $qrData = "123"; 
                            if ($qrTestResult && $qrTestResult->num_rows > 0) {
                                $qrData = $qrTestResult->fetch_assoc()['appointment_id'];
                            }
                        ?>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= $qrData ?>" alt="QR Code for Appt #<?= $qrData ?>">
                        <p style="font-size:12px; color:#666; margin-top:5px;">Scan to test (ID: <?= $qrData ?>)</p>
                    </div>
                    <button class="scan-btn" onclick="startScan()">Click to Scan</button>
                </div>
            </div>
        </div>
    </div>

    <div id="popup">
        <div id="popup-header"><h3>Confirmation</h3></div>
        <div id="popup-content"></div>
        <div id="popup-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
            <button id="confirmActionBtn" style="display: none; background: #27ae60;">Confirm</button>
            <button onclick="closePopup()">Close</button>
        </div>
    </div>

    <div class="detail-overlay" id="appointmentDetailModal">
        <div class="detail-card">
            <div class="detail-header">
                <div class="detail-title" id="detail-title">Appointment Details</div>
                <span class="detail-id" id="detail-id">#0</span>
            </div>
            <div id="detailModalBody" style="padding: 24px 28px; max-height: 70vh; overflow-y: auto; font-size: 15px;"></div>
            <div class="detail-actions">
                <input type="hidden" id="modal_appointment_id" value="">
                <button class="btn-small btn-close" onclick="closeAppointmentDetailModal()">Back</button>
                <button class="btn-small btn-cancel" style="background: #dc2626; color: white;" onclick="promptScannedCancel()">Cancel</button>
                <button class="btn-small btn-accept" style="background: #16a34a; color: white;" onclick="updateScannedStatus('Completed')">Complete</button>
            </div>
        </div>
    </div>

    <div class="qr-modal-overlay" id="qrScannerModal" style="display: none;">
        <div class="qr-modal-content">
            <button class="qr-modal-close" onclick="stopScan()">✕</button>
            <h3>Scan Appointment QR Code</h3>
            
            <div class="qr-controls">
                <select id="camera-select" onchange="onCameraSelectChange()"></select>
                <button id="swap-camera-btn" onclick="swapCamera()" title="Switch Camera">↻</button>
            </div>

            <p>Hold the QR code steady in the center of the frame.</p>
            <div id="qr-reader-container">
                <div id="qr-reader"></div>
            </div>
        </div>
    </div>

    <div id="reasonModal" class="confirm-modal" aria-hidden="true" style="z-index: 3001;">
        <div class="confirm-card" role="dialog" aria-modal="true">
            <div class="confirm-header">
                <div class="confirm-icon danger">!</div>
                <div class="confirm-title">Reason for Cancellation</div>
            </div>
            <div class="confirm-msg" id="confirmMsg">Please provide a reason for cancelling this appointment.</div>
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
        &copy; <?= date('Y') ?> EyeMaster. All rights reserved.
    </footer>

</div> <div id="actionLoader" class="detail-overlay" style="z-index: 9990;" aria-hidden="true">
    <div class="loader-card" style="background: #fff; border-radius: 12px; padding: 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <div class="loader-spinner" style="border-top-color: #991010; width: 32px; height: 32px; border-width: 4px; flex-shrink: 0;"></div>
        <p id="actionLoaderText" style="font-weight: 600; color: #334155; font-size: 15px;">Processing...</p>
    </div>
</div>

<script>
// ===================================
// === LOADER FUNCTIONS (Updated) ====
// ===================================
const pageLoader = document.getElementById('loader-overlay');
const mainContent = document.getElementById('main-content');
const actionLoader = document.getElementById('actionLoader');
const actionLoaderText = document.getElementById('actionLoaderText');

function hidePageLoader() {
    if(pageLoader) {
        pageLoader.classList.add('hidden'); 
        setTimeout(() => { 
            pageLoader.style.display = 'none'; 
        }, 300); 
    }
    if(mainContent) {
        mainContent.style.display = 'block';
        mainContent.style.animation = 'fadeIn 0.2s ease';
    }
}

// Initial Page Load Timer (Matches patient_record.php feel)
setTimeout(hidePageLoader, 1500);

function showActionLoader(message = 'Processing...') {
    if (actionLoaderText) actionLoaderText.textContent = message;
    if (actionLoader) {
        actionLoader.classList.add('show');
        actionLoader.setAttribute('aria-hidden', 'false');
    }
}

function hideActionLoader() {
    if (actionLoader) {
        actionLoader.classList.remove('show');
        actionLoader.setAttribute('aria-hidden', 'true');
    }
}

// ===================================
// CHART DATA
// ===================================
const dailyData = <?php echo json_encode($dailyData); ?>;
const statusData = <?php echo json_encode($statusData); ?>;
const weeklyData = <?php echo json_encode($weeklyData); ?>;

// --- SERVICES DATA (New) ---
const serviceLabels = <?php echo json_encode($serviceLabels); ?>;
const serviceValues = <?php echo json_encode($serviceValues); ?>;

const dailyLabels = dailyData.length > 0 ? dailyData.map(d => {
    const date = new Date(d.date);
    return (date.getMonth() + 1) + '/' + date.getDate();
}) : ['No Data'];
const dailyValues = dailyData.length > 0 ? dailyData.map(d => parseInt(d.count)) : [0];

const statusLabels = statusData.length > 0 ? statusData.map(s => s.status_name) : ['No Data'];
const statusValues = statusData.length > 0 ? statusData.map(s => parseInt(s.count)) : [1];

const statusColors = statusData.length > 0 ? statusData.map(s => {
    const status = s.status_name.toLowerCase(); 
    if (status.includes('completed') || status.includes('approved')) return '#27ae60'; // Green
    if (status.includes('pending')) return '#f39c12'; // Orange
    if (status.includes('confirmed')) return '#8bc34a'; // Light Green
    if (status.includes('cancel') || status.includes('missed')) return '#e74c3c'; // Red
    return '#95a5a6';
}) : ['#e0e0e0'];

const dayMap = { 'Monday': 'Mon', 'Tuesday': 'Tue', 'Wednesday': 'Wed', 'Thursday': 'Thu', 'Friday': 'Fri', 'Saturday': 'Sat', 'Sunday': 'Sun' };
const fixedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const weeklyMap = {};
weeklyData.forEach(item => { weeklyMap[item.day] = parseInt(item.count); });
const weeklyLabels = fixedDays.map(day => dayMap[day]);
const weeklyValues = fixedDays.map(day => weeklyMap[day] || 0);
const weeklyColors = weeklyValues.map((val, idx) => idx % 2 === 0 ? '#e74c3c' : '#27ae60');

// Line Chart
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
            borderWidth: 2, fill: true, tension: 0.4,
            pointRadius: 4, pointBackgroundColor: '#e74c3c'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

// Status Chart
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
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 15, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' } }
        }
    }
});

// ==========================================
// TOP SERVICES CHART (Pie/Doughnut)
// ==========================================
const ctxServices = document.getElementById('servicesChart').getContext('2d');

// GENERATED GREEN PALETTE (Dark to Light)
const serviceColors = [
    '#145A32', // Darkest Green
    '#196F3D',
    '#1E8449',
    '#229954',
    '#27AE60',
    '#2ECC71',
    '#52BE80',
    '#7DCEA0',
    '#A9DFBF',
    '#C3E6CB'  // Lightest Green
];

const finalServiceColors = (serviceLabels[0] === 'No Availments') 
    ? ['#e0e0e0'] // Grey for empty
    : serviceColors;

new Chart(ctxServices, {
    type: 'doughnut', 
    data: {
        labels: serviceLabels,
        datasets: [{
            data: serviceValues,
            backgroundColor: finalServiceColors,
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'right', 
                labels: { 
                    padding: 10, 
                    boxWidth: 12,
                    font: { size: 11 }, 
                    usePointStyle: true, 
                    pointStyle: 'circle' 
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.parsed;
                        let total = context.chart._metasets[context.datasetIndex].total;
                        let percentage = ((value / total) * 100).toFixed(1) + "%";
                        return label + ": " + value + " (" + percentage + ")";
                    }
                }
            }
        }
    }
});

// Weekly Bar Chart
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
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { font: { size: 10 } } },
            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});

// ... Helper functions ...
const popup = document.getElementById('popup');
const popupHeader = document.getElementById('popup-header');
const popupContent = document.getElementById('popup-content');
const popupOverlay = document.querySelector('.popup-overlay');
const confirmBtn = document.getElementById('confirmActionBtn');

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
    toast.innerHTML = `<div class="toast-icon">${type === 'success' ? '✓' : '✕'}</div><div class="toast-message">${msg}</div>`;
    overlay.innerHTML = '';
    overlay.appendChild(toast);
    overlay.classList.add('show');
    const timer = setTimeout(() => { if(overlay) overlay.classList.remove('show'); }, 2500);
    overlay.addEventListener('click', () => { clearTimeout(timer); if(overlay) overlay.classList.remove('show'); }, { once: true });
}

function openPopup(header, content, isConfirmation = false, callback = null) {
    popupHeader.innerHTML = `<h3>${header}</h3>`;
    popupContent.innerHTML = content;
    if (isConfirmation) {
        confirmBtn.style.display = 'block';
        confirmBtn.onclick = () => { if (callback) callback(); closePopup(); };
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
    if (qrModal && qrModal.style.display !== 'none') stopScan();
}

function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    document.querySelectorAll('.dropdown').forEach(d => { if (d.id !== id) d.classList.remove('active'); });
    dropdown.classList.toggle('active');
}

function applyFilter(type, value) {
    showActionLoader('Applying filter...');
    const url = new URL(window.location);
    url.searchParams.set(type, value);
    if (type === 'year') { url.searchParams.delete('month'); url.searchParams.delete('week'); url.searchParams.delete('day'); } 
    else if (type === 'month') { url.searchParams.delete('week'); url.searchParams.delete('day'); } 
    else if (type === 'week') { url.searchParams.delete('day'); }
    window.location.href = url.toString();
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.filter-btn') && !e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
    }
});

// ==========================================
// UPDATED QR SCANNER LOGIC WITH CAMERA SWAP
// ==========================================

let html5QrCode = null;
let currentCameraId = null;
let allCameras = [];
let isScanning = false;

function stopScan() {
    const qrModal = document.getElementById('qrScannerModal');
    if (qrModal) qrModal.style.display = 'none';

    if (html5QrCode && isScanning) {
        html5QrCode.stop().then(() => {
            isScanning = false;
            const qrReader = document.getElementById('qr-reader');
            if(qrReader) qrReader.innerHTML = '';
        }).catch(err => {
            console.error("Failed to stop", err);
            isScanning = false;
        });
    }
}

function startScan() {
    const qrModal = document.getElementById('qrScannerModal');
    if (!qrModal) return;
    
    // Show Modal
    qrModal.style.display = 'flex'; 
    const qrReaderId = "qr-reader";

    // Initialize if not already done
    if(!html5QrCode) {
        html5QrCode = new Html5Qrcode(qrReaderId);
    }

    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            allCameras = devices;
            const cameraSelect = document.getElementById('camera-select');
            cameraSelect.innerHTML = ""; // Clear options

            // 1. Populate the Dropdown
            let backCameraId = null;

            devices.forEach((device, index) => {
                const option = document.createElement('option');
                option.value = device.id;
                option.text = device.label || `Camera ${index + 1}`;
                cameraSelect.appendChild(option);

                // Try to auto-detect the "back" camera to use as default
                if (device.label.toLowerCase().includes('back') || device.label.toLowerCase().includes('environment')) {
                    backCameraId = device.id;
                }
            });

            // 2. Select the best initial camera (Back prefered, otherwise last one)
            currentCameraId = backCameraId ? backCameraId : devices[devices.length - 1].id;
            cameraSelect.value = currentCameraId;

            // 3. Start the scan
            runCamera(currentCameraId);

        } else {
            showGlobalToast('No cameras found.', 'error');
        }
    }).catch(err => {
        showGlobalToast('Camera access denied.', 'error');
    });
}

// Helper function to actually start/restart the stream
function runCamera(cameraId) {
    if(!html5QrCode) return;

    // If already scanning, stop first, then start new
    if (isScanning) {
        html5QrCode.stop().then(() => {
            isScanning = false;
            startQrCodeInstance(cameraId);
        }).catch(err => console.error(err));
    } else {
        startQrCodeInstance(cameraId);
    }
}

function startQrCodeInstance(cameraId) {
    html5QrCode.start(
        cameraId, 
        { 
            fps: 10, 
            qrbox: { width: 250, height: 250 } // Standard Box
        },
        (qrCodeMessage) => { 
            // SUCCESS CALLBACK
            stopScan(); 
            processScannedData(qrCodeMessage); 
        },
        (errorMessage) => { 
            // IGNORE SCAN ERRORS (common while moving camera)
        }
    ).then(() => {
        isScanning = true;
        currentCameraId = cameraId;
    }).catch(err => {
        showGlobalToast('Error starting camera', 'error');
        isScanning = false;
    });
}

// Triggered when user selects from dropdown
function onCameraSelectChange() {
    const select = document.getElementById('camera-select');
    const newId = select.value;
    if(newId !== currentCameraId) {
        runCamera(newId);
    }
}

// Triggered when user clicks the "Cycle/Swap" button
function swapCamera() {
    if (allCameras.length < 2) {
        showGlobalToast('Only one camera available', 'warning');
        return;
    }

    // Find current index
    let currentIndex = allCameras.findIndex(c => c.id === currentCameraId);
    
    // Calculate next index (cycle)
    let nextIndex = (currentIndex + 1) % allCameras.length;
    let nextCameraId = allCameras[nextIndex].id;

    // Update Dropdown UI
    document.getElementById('camera-select').value = nextCameraId;

    // Run
    runCamera(nextCameraId);
}

function processScannedData(qrCodeMessage) {
    showActionLoader('Verifying QR Code...');
    fetch('verify_qr.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qr_code: qrCodeMessage })
    }).then(r => r.json()).then(data => {
        hideActionLoader();
        if (data.success && data.data) openAppointmentDetailModal(data);
        else showGlobalToast(data.message || 'Appointment not found', 'error');
    }).catch(e => { hideActionLoader(); showGlobalToast('Error: ' + e.message, 'error'); });
}

function openAppointmentDetailModal(payload) {
    const d = payload.data; 
    const preformatted = payload; 
    document.getElementById('modal_appointment_id').value = d.appointment_id; 
    document.getElementById('detail-title').textContent = d.service_name || 'Appointment Details';
    document.getElementById('detail-id').textContent = '#' + d.appointment_id;
    const modalBody = document.getElementById('detailModalBody');
    modalBody.innerHTML = ''; 
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
    const displayOrder = ['full_name', 'status_name', 'service_name', 'staff_name', 'appointment_date', 'appointment_time', 'age', 'gender', 'phone_number', 'occupation', 'suffix', 'symptoms', 'concern', 'wear_glasses', 'notes', 'certificate_purpose', 'certificate_other', 'ishihara_test_type', 'ishihara_purpose', 'color_issues', 'previous_color_issues', 'ishihara_notes', 'ishihara_reason', 'consent_info', 'consent_reminders', 'consent_terms'];
    
    let contentHtml = '<div class="detail-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">';
    for (const key of displayOrder) {
        if (d.hasOwnProperty(key) && d[key] !== null && d[key] !== '' && d[key] !== '0') {
            let value = d[key];
            const label = labels[key] || key;
            let rowClass = 'detail-row';
            let style = 'background: #f8f9fb; padding: 12px 14px; border-radius: 8px; border: 1px solid #e8ecf0;';
            if (['notes', 'symptoms', 'concern', 'ishihara_notes'].includes(key)) style += ' grid-column: 1 / -1;';
            
            if (key === 'appointment_date') value = preformatted.date;
            else if (key === 'appointment_time') value = preformatted.time;
            else if (key.includes('consent')) value = value == 1 ? 'Yes' : 'No';
            else if (key === 'status_name') value = `<span class="badge ${value.toLowerCase()}" style="display: inline-block; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 13px; text-transform: uppercase;">${value}</span>`;
            else value = `<b>${value}</b>`;
            
            contentHtml += `<div class="${rowClass}" style="${style}"><span class="detail-label" style="font-size: 11px; font-weight: 700; color: #4a5568; text-transform: uppercase; display: block; margin-bottom: 6px;">${label}</span><div class="detail-value" style="color: #1a202c; font-weight: 500; font-size: 15px;">${value}</div></div>`;
        }
    }
    contentHtml += '</div>';
    modalBody.innerHTML = contentHtml;
    document.getElementById('appointmentDetailModal').classList.add('show');
}

function closeAppointmentDetailModal() { document.getElementById('appointmentDetailModal').classList.remove('show'); }

function updateScannedStatus(newStatus, reason = null) {
    const appointmentId = document.getElementById('modal_appointment_id').value;
    if (!appointmentId) return showGlobalToast('Error: No Appointment ID.', 'error');
    showActionLoader(`Updating to ${newStatus}...`);
    const bodyParams = { action: 'updateStatus', id: appointmentId, status_name: newStatus };
    if (newStatus === 'Cancel') bodyParams.reason = reason || 'Cancelled by Admin via QR Scan.';
    fetch('appointment.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(bodyParams)
    }).then(res => res.json()).then(data => {
        hideActionLoader();
        if (data.success) {
            closeAppointmentDetailModal();
            showGlobalToast(`Marked as ${newStatus}. Refreshing...`, 'success');
            setTimeout(() => { location.reload(); }, 2000);
        } else showGlobalToast(data.message || 'Update failed.', 'error');
    }).catch(err => { hideActionLoader(); showGlobalToast('Network error.', 'error'); });
}

function promptScannedCancel() {
    const modal = document.getElementById('reasonModal');
    const reasonInput = document.getElementById('cancelReasonInput');
    const submitBtn = document.getElementById('reasonSubmit');
    const backBtn = document.getElementById('reasonBack');
    reasonInput.value = ''; 
    modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false');
    let onKey; 
    function cleanUp() { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); document.removeEventListener('keydown', onKey); }
    submitBtn.onclick = () => {
        const reason = reasonInput.value.trim();
        if (reason === '') return showGlobalToast('Reason required.', 'error');
        cleanUp(); updateScannedStatus('Cancel', reason); 
    };
    backBtn.onclick = () => cleanUp();
    onKey = (e) => { if (e.key === 'Escape') cleanUp(); };
    document.addEventListener('keydown', onKey);
}

// Handheld Scanner Listener
(function() {
    let qrCodeChars = []; let lastKeystrokeTime = new Date();
    document.addEventListener('keydown', function(e) {
        const activeEl = document.activeElement;
        if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) return;
        const now = new Date();
        if (now - lastKeystrokeTime > 100) qrCodeChars = [];
        lastKeystrokeTime = now;
        if (e.key === 'Enter' || e.keyCode === 13) {
            if (qrCodeChars.length > 0) { 
                e.preventDefault(); 
                processScannedData(qrCodeChars.join('')); 
            }
            qrCodeChars = [];
        } else if (e.key && e.key.length === 1) qrCodeChars.push(e.key);
    });
})();

document.addEventListener('DOMContentLoaded', function() {
  const menuToggle = document.getElementById('menu-toggle');
  const mainNav = document.getElementById('main-nav');
  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', function() {
      mainNav.classList.toggle('show');
      this.innerHTML = mainNav.classList.contains('show') ? '✕' : '☰'; 
    });
  }
});
</script>
</body>
</html>