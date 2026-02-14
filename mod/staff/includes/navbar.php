<!-- GLOBAL STYLES FOR STAFF DASHBOARD -->
<style>
    * { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    body { background:#f8f9fa; color:#223; }
    
    /* SIDEBAR & LAYOUT */
    .vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
    .vertical-bar .circle { width:70px; height:70px; background:#b91313; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #5a0a0a; }
    
    header { display:flex; align-items:center; background:#fff; padding:12px 20px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; }
    .logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
    .logo-section img { height:32px; border-radius:4px; object-fit:cover; }
    
    nav { display:flex; gap:8px; align-items:center; }
    nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; font-size:14px; }
    nav a:hover { background:#f1f5f9; }
    nav a.active { background:#dc3545; color:#fff; }

    /* CONTAINER & TABLES */
    .container { padding:20px 20px 40px 75px; max-width:1400px; margin:0 auto; }
    .header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
    .header-row h2 { font-size:20px; color:#2c3e50; }
    
    /* BUTTONS & TOGGLES */
    .table-toggle { display:flex; gap:8px; margin-bottom:16px; flex-wrap: wrap; }
    .toggle-btn { padding:10px 20px; border-radius:8px; border:2px solid #e6e9ee; background:#fff; cursor:pointer; font-weight:700; transition:all .2s; }
    .toggle-btn.active { background:#dc3545; color:#fff; border-color:#dc3545; }
    .toggle-btn:hover:not(.active) { background:#f8f9fa; border-color:#dc3545; }

    .add-btn { background:#28a745; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
    .action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; }
    .view { background:#1d4ed8; }
    .edit { background:#f59e0b; }
    .remove { background:#dc3545; }

    /* FORMS & FILTERS */
    .filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
    select, input[type="text"], input[type="date"], input[type="time"] { padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; font-size: 14px; }
    
    /* MODALS */
    .detail-overlay, .form-overlay, .remove-overlay { display:none; position:fixed; inset:0; background:rgba(2,12,20,0.6); z-index:3000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
    .detail-overlay.show, .form-overlay.show, .remove-overlay.show { display:flex; animation:fadeIn .2s ease; }
    .detail-card, .form-card { width:600px; max-width:96%; background:#fff; border-radius:16px; padding:0; box-shadow:0 20px 60px rgba(8,15,30,0.25); animation:slideUp .3s ease; }
    .detail-header { background:linear-gradient(135deg, #991010 0%, #6b1010 100%); padding:20px 24px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; }
    .detail-title { font-weight:800; color:#fff; font-size:20px; }
    .form-body { padding:24px; }
    .form-group { margin-bottom:15px; }
    .form-group label { display:block; font-weight:700; color:#4a5568; font-size:12px; text-transform:uppercase; margin-bottom:6px; }
    .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px; border:1px solid #dde3ea; border-radius:8px; }
    .form-actions { padding:20px; background:#f8f9fb; border-top:1px solid #e8ecf0; border-radius:0 0 16px 16px; display:flex; justify-content:flex-end; gap:10px; }
    
    /* BADGES */
    .badge { display:inline-block; padding:4px 10px; border-radius:12px; font-weight:700; font-size:11px; text-transform:uppercase; }
    .badge.benefit { background:#dcfce7; color:#166534; }
    .badge.disease { background:#fee2e2; color:#991b1b; }
    .badge.extra { background:#e0f2fe; color:#075985; }

    /* TOAST & LOADER */
    .toast-overlay { position:fixed; inset:0; background:rgba(34,49,62,0.6); z-index:9998; display:none; align-items:center; justify-content:center; }
    .toast-overlay.show { display:flex; }
    .toast { background:#fff; padding:20px; border-radius:12px; display:flex; align-items:center; gap:15px; font-weight:600; min-width:300px; border-top:4px solid #333; }
    .toast.success { border-color:#28a745; } .toast.error { border-color:#dc3545; }
    
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    @keyframes slideUp { from{transform:translateY(20px)} to{transform:translateY(0)} }

    /* RESPONSIVE */
    @media (max-width: 1000px) {
        .vertical-bar { display:none; }
        header { padding:12px 20px; }
        .container { padding:20px; }
        #menu-toggle { display:block; }
    }
</style>

<!-- LOADER -->
<div id="loader-overlay" style="display:none; position:fixed; inset:0; background:rgba(255,255,255,0.8); z-index:9999; align-items:center; justify-content:center;">
    <div style="width:40px; height:40px; border:4px solid #f3f3f3; border-top:4px solid #991010; border-radius:50%; animation:spin 1s linear infinite;"></div>
</div>
<style>@keyframes spin { 0% { transform:rotate(0deg); } 100% { transform:rotate(360deg); } }</style>

<!-- MAIN HEADER NAVIGATION -->
<header>
    <div class="logo-section">
        <!-- Adjust path if needed based on where you include this -->
        <img src="../photo/LOGO.jpg" alt="Logo"> 
        <strong>EYE MASTER CLINIC</strong>
    </div>
    
    <!-- Mobile Toggle (Hidden on Desktop) -->
    <button id="menu-toggle" style="display:none; font-size:24px; background:none; border:none;">☰</button>

    <nav id="main-nav">
        <!-- We use PHP to check filename for 'active' class -->
        <?php $cur = basename($_SERVER['PHP_SELF']); ?>
        <a href="staff_dashboard.php" class="<?= $cur == 'staff_dashboard.php' ? 'active' : '' ?>">🏠 Dashboard</a>
        <a href="appointment.php" class="<?= $cur == 'appointment.php' ? 'active' : '' ?>">📅 Appointments</a>
        <a href="patient_record.php" class="<?= $cur == 'patient_record.php' ? 'active' : '' ?>">📘 Patient Record</a>
        <a href="product.php" class="<?= ($cur == 'product.php') ? 'active' : '' ?>">💊 Product & Services</a>
        <a href="profile.php" class="<?= $cur == 'profile.php' ? 'active' : '' ?>">🔍 Profile</a>
    </nav>
</header>