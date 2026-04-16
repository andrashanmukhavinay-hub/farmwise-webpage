<?php
// Start the session to verify the user is logged in
session_start();
require 'db.php'; // Ensure your database connection file is included

// Check if the user is logged in; if not, redirect them back to the login page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: farmer-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Profile Update (When the Edit Modal form is submitted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $location = trim($_POST['location']);
    $farm_size = trim($_POST['farm_size']);

    // Update the database
    $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, location = ?, farm_size = ? WHERE id = ?");
    $stmt->execute([$name, $phone, $location, $farm_size, $user_id]);
    
    // Refresh the page to show the new data
    header("Location: settings.php?success=1");
    exit();
}

// Handle Account Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    session_destroy();
    header("Location: farmer-login.php?deleted=1");
    exit();
}

// Fetch the latest User Data from the database
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Safely assign variables (fallback to defaults if columns are empty)
$user_email = $user['email'] ?? "farmer@example.com";
$user_name = $user['name'] ?? "Farmer User";
$user_phone = $user['phone'] ?? "+91 00000 00000";
$user_location = $user['location'] ?? "Not Set";
$user_farm_size = $user['farm_size'] ?? "Not Set";

// Generate Initials for the Avatar
$words = explode(" ", trim($user_name));
$initials = "";
foreach ($words as $w) {
    if(!empty($w)) $initials .= strtoupper($w[0]);
}
if (strlen($initials) > 2) $initials = substr($initials, 0, 2);
if (empty($initials)) $initials = strtoupper(substr($user_email, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        /* Base Reset & Typography */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        /* Fully Responsive Body */
        body { background-color: #f1f5f9; color: #1e293b; min-height: 100vh; transition: background-color 0.3s, color 0.3s; overflow-x: hidden; }
        
        /* The App Container */
        .app-container { width: 100%; max-width: 1400px; margin: 0 auto; min-height: 100vh; display: flex; flex-direction: column; }

        /* --- Header --- */
        .header { background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; padding: 30px 50px; display: flex; align-items: center; justify-content: space-between; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; box-shadow: 0 10px 30px rgba(5, 150, 105, 0.15); }
        .header-left { display: flex; align-items: center; gap: 20px; }
        .back-btn { color: white; text-decoration: none; font-size: 20px; transition: 0.2s; }
        .back-btn:hover { opacity: 0.8; transform: translateX(-3px); }
        .header-title h1 { font-size: 24px; font-weight: 800; margin-bottom: 4px; }
        .header-title p { font-size: 14px; opacity: 0.9; }

        /* --- Main Content Area --- */
        .content { padding: 40px 50px; display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 30px; align-items: start; }

        /* --- UI Cards --- */
        .section-card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; transition: 0.3s; }
        .section-card:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.05); transform: translateY(-3px); }
        .section-title { font-size: 16px; font-weight: 800; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; transition: color 0.3s; }
        .section-subtitle { font-size: 13px; color: #64748b; margin-top: -15px; margin-bottom: 20px; display: block; }

        /* --- Specific Cards --- */
        .card-profile { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 30px; align-items: center; justify-content: space-between; background: #f0fdf4; border-color: #dcfce7; }
        .profile-info-row { display: flex; gap: 20px; align-items: center; }
        .avatar { width: 80px; height: 80px; background: #10b981; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 28px; font-weight: 800; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); }
        .user-details h3 { font-size: 20px; color: #1e293b; margin-bottom: 5px; transition: color 0.3s; }
        .user-details p { font-size: 14px; color: #64748b; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        
        .farm-stats-wrapper { display: flex; gap: 20px; flex: 1; min-width: 300px; justify-content: center; }
        .stat-box { flex: 1; max-width: 200px; background: white; padding: 15px 20px; border-radius: 12px; border: 1px solid #dcfce7; transition: 0.3s; }
        .stat-box p { font-size: 12px; color: #166534; font-weight: 700; margin-bottom: 5px; text-transform: uppercase; }
        .stat-box h4 { font-size: 16px; color: #1e293b; transition: color 0.3s; }
        
        .btn-edit { background: #059669; color: white; border: none; padding: 14px 25px; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; transition: 0.3s; white-space: nowrap;}
        .btn-edit:hover { background: #047857; }

        /* --- Lists & Links --- */
        .link-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #f1f5f9; text-decoration: none; }
        .link-item:last-child { border-bottom: none; padding-bottom: 0; }
        .l-left { display: flex; gap: 15px; align-items: center; }
        .l-icon { width: 40px; height: 40px; background: #f8fafc; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-size: 16px; flex-shrink: 0; transition: 0.3s;}
        .l-text h4 { font-size: 15px; color: #1e293b; margin-bottom: 4px; transition: color 0.3s; }
        .l-text p { font-size: 13px; color: #64748b; }
        
        /* Toggles */
        .switch { position: relative; display: inline-block; width: 50px; height: 28px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #10b981; }
        input:checked + .slider:before { transform: translateX(22px); }

        /* --- Preferences --- */
        .sub-heading { font-size: 14px; font-weight: 800; color: #1e293b; margin: 20px 0 15px 0; transition: color 0.3s; }
        .theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .theme-btn { background: #f8fafc; border: 2px solid transparent; padding: 20px; border-radius: 16px; text-align: center; cursor: pointer; transition: 0.3s; }
        .theme-btn.active { border: 2px solid #10b981; background: #f0fdf4; color: #059669; }
        .theme-btn i { font-size: 24px; margin-bottom: 10px; color: #64748b; transition: color 0.3s;}
        .theme-btn.active i { color: #10b981; }
        .theme-btn span { display: block; font-size: 14px; font-weight: 700; color: #475569; transition: color 0.3s;}
        .theme-btn.active span { color: #059669; }

        .sync-list { display: flex; flex-direction: column; gap: 10px; }
        .sync-item { background: #f8fafc; border: 2px solid transparent; padding: 15px 20px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: 0.3s;}
        .sync-item.active { border: 2px solid #10b981; background: #f0fdf4; }
        .sync-item .s-left { display: flex; gap: 15px; align-items: center; }
        .sync-item .s-left i { font-size: 18px; color: #64748b; transition: color 0.3s;}
        .sync-item.active .s-left i { color: #10b981; }
        .sync-item .s-text h4 { font-size: 14px; color: #1e293b; transition: color 0.3s; }
        .sync-item.active .s-text h4 { color: #059669; }
        .sync-item .s-text p { font-size: 12px; color: #64748b; }
        .sync-check { color: #10b981; font-size: 18px; opacity: 0; transition: opacity 0.3s;}
        .sync-item.active .sync-check { opacity: 1; }

        /* --- Actions --- */
        .btn-logout { width: 100%; background: #ea580c; color: white; border: none; padding: 16px; border-radius: 12px; font-size: 15px; font-weight: 800; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 20px; text-decoration: none; transition: 0.3s;}
        .btn-logout:hover { background: #c2410c; transform: translateY(-2px); }
        .delete-box { background: #fef2f2; border: 1px solid #fecaca; padding: 20px; border-radius: 16px; transition: 0.3s; }
        .delete-box h4 { font-size: 15px; color: #dc2626; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .delete-box p { font-size: 13px; color: #7f1d1d; line-height: 1.5; margin-bottom: 15px; }
        .btn-delete { width: 100%; background: white; color: #dc2626; border: 1px solid #fecaca; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 800; cursor: pointer; transition: 0.3s;}
        .btn-delete:hover { background: #dc2626; color: white; }

        /* --- Modals --- */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100; justify-content: center; align-items: center; padding: 20px; backdrop-filter: blur(5px); }
        .modal-content { background: white; width: 100%; max-width: 500px; padding: 30px; border-radius: 24px; transition: background-color 0.3s; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        .modal-title { font-size: 22px; font-weight: 800; margin-bottom: 20px; color: #1e293b; transition: color 0.3s;}
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 700; color: #64748b; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 15px; outline: none; transition: 0.3s;}
        .form-group input:focus { border-color: #10b981; }
        .modal-actions { display: flex; gap: 15px; margin-top: 30px; }
        .btn-cancel { flex: 1; padding: 14px; background: #f1f5f9; color: #475569; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: 0.3s;}
        .btn-cancel:hover { background: #e2e8f0; }
        .btn-save { flex: 1; padding: 14px; background: #059669; color: white; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: 0.3s;}
        .btn-save:hover { background: #047857; transform: translateY(-2px); }

        /* =========================================
           DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #121212; }
        html.dark-mode .app-container { background-color: #121212; }
        html.dark-mode .section-card,
        html.dark-mode .l-icon,
        html.dark-mode .theme-btn,
        html.dark-mode .sync-item,
        html.dark-mode .modal-content { background-color: #1e1e1e; border-color: #333; }
        
        html.dark-mode .card-profile { background-color: #064e3b; border-color: #065f46; }
        html.dark-mode .stat-box { background-color: #022c22; border-color: #065f46; }
        html.dark-mode .stat-box p { color: #6ee7b7; }
        
        html.dark-mode .section-title,
        html.dark-mode .user-details h3,
        html.dark-mode .stat-box h4,
        html.dark-mode .l-text h4,
        html.dark-mode .sub-heading,
        html.dark-mode .sync-item .s-text h4,
        html.dark-mode .modal-title { color: #f8fafc; }
        
        html.dark-mode .theme-btn span,
        html.dark-mode .btn-cancel { color: #cbd5e1; }
        html.dark-mode .btn-cancel { background-color: #333; }
        html.dark-mode .theme-btn.active { background-color: #064e3b; border-color: #10b981; }
        html.dark-mode .sync-item.active { background-color: #064e3b; border-color: #10b981; }
        html.dark-mode .form-group input { background: #333; color: white; border-color: #555; }
        
        html.dark-mode .delete-box { background-color: #450a0a; border-color: #7f1d1d; }
        html.dark-mode .btn-delete { background: #7f1d1d; color: #fecaca; border-color: #991b1b; }

        @media (max-width: 900px) {
            .header { padding: 20px; border-radius: 0 0 20px 20px; }
            .content { padding: 20px; grid-template-columns: 1fr; }
            .card-profile { flex-direction: column; align-items: flex-start; }
            .farm-stats-wrapper { width: 100%; justify-content: flex-start; }
            .btn-edit { width: 100%; }
        }
    </style>
</head>
<body>

<div class="app-container">
    
    <div class="header">
        <div class="header-left">
            <a href="farmer-dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="header-title">
                <h1>Settings</h1>
                <p>Manage your account and preferences</p>
            </div>
        </div>
        <i class="fa-solid fa-gear" style="font-size: 24px; opacity: 0.8;"></i>
    </div>

    <div class="content">
        
        <div class="section-card card-profile">
            <div style="width: 100%;">
                <div class="section-title"><i class="fa-regular fa-user"></i> Profile Management</div>
            </div>
            
            <div class="profile-info-row">
                <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($user_name); ?></h3>
                    <p><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($user_phone); ?></p>
                    <p><i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($user_email); ?></p>
                </div>
            </div>

            <div class="farm-stats-wrapper">
                <div class="stat-box">
                    <p>Location</p>
                    <h4><?php echo htmlspecialchars($user_location); ?></h4>
                </div>
                <div class="stat-box">
                    <p>Farm Size</p>
                    <h4><?php echo htmlspecialchars($user_farm_size); ?></h4>
                </div>
            </div>

            <button class="btn-edit" onclick="openModal('editProfileModal')"><i class="fa-solid fa-pen-to-square"></i> Edit Profile</button>
        </div>

        <div class="section-card">
            <div class="section-title"><i class="fa-regular fa-bell"></i> Notification Preferences</div>
            <span class="section-subtitle">Control what alerts you receive</span>
            
            <div class="link-item">
                <div class="l-left">
                    <div class="l-icon" style="color:#0284c7;"><i class="fa-solid fa-cloud-sun-rain"></i></div>
                    <div class="l-text"><h4>Weather Alerts</h4><p>Rain forecasts and extreme conditions</p></div>
                </div>
                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
            <div class="link-item">
                <div class="l-left">
                    <div class="l-icon" style="color:#dc2626;"><i class="fa-solid fa-bug"></i></div>
                    <div class="l-text"><h4>Pest Alerts</h4><p>Outbreaks and disease risks</p></div>
                </div>
                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
        </div>

        <div class="section-card">
            <div class="section-title"><i class="fa-solid fa-shield-halved"></i> Privacy & Security</div>
            <span class="section-subtitle">Manage your credentials</span>
            
            <a href="reset-password.php" class="link-item">
                <div class="l-left">
                    <div class="l-icon" style="color:#9333ea;"><i class="fa-solid fa-lock"></i></div>
                    <div class="l-text"><h4>Change Password</h4><p>Update your account password</p></div>
                </div>
                <i class="fa-solid fa-chevron-right" style="color:#cbd5e1;"></i>
            </a>
            <a href="privacy.php" class="link-item">
                <div class="l-left">
                    <div class="l-icon" style="color:#9333ea;"><i class="fa-regular fa-eye"></i></div>
                    <div class="l-text"><h4>Data Privacy Information</h4><p>Learn how we protect your data</p></div>
                </div>
                <i class="fa-solid fa-chevron-right" style="color:#cbd5e1;"></i>
            </a>
        </div>

        <div class="section-card">
            <div class="section-title"><i class="fa-solid fa-globe"></i> App Preferences</div>
            
            <div class="sub-heading">Theme Mode</div>
            <div class="theme-grid">
                <div class="theme-btn active" id="btn-light" onclick="setTheme('light')">
                    <i class="fa-regular fa-sun"></i><span>Light Mode</span>
                </div>
                <div class="theme-btn" id="btn-dark" onclick="setTheme('dark')">
                    <i class="fa-regular fa-moon"></i><span>Dark Mode</span>
                </div>
            </div>

            <div class="sub-heading">Data Sync Settings</div>
            <div class="sync-list">
                <div class="sync-item active" id="sync-wifi" onclick="setSync('wifi')">
                    <div class="s-left"><i class="fa-solid fa-wifi"></i><div class="s-text"><h4>Wi-Fi Only</h4><p>Save mobile data</p></div></div>
                    <i class="fa-regular fa-circle-check sync-check"></i>
                </div>
                <div class="sync-item" id="sync-mobile" onclick="setSync('mobile')">
                    <div class="s-left"><i class="fa-solid fa-mobile-screen"></i><div class="s-text"><h4>Mobile Data Only</h4><p>Sync on the go</p></div></div>
                    <i class="fa-regular fa-circle-check sync-check"></i>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-title"><i class="fa-solid fa-triangle-exclamation"></i> Account Actions</div>
            
            <a href="logout.php" class="btn-logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Secure Logout</a>

            <div class="delete-box">
                <h4><i class="fa-solid fa-triangle-exclamation"></i> Delete Account</h4>
                <p>Permanently delete your account and all associated data. This action cannot be undone.</p>
                <form method="POST" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete your account? All your data will be lost.');">
                    <input type="hidden" name="action" value="delete_account">
                    <button type="submit" class="btn-delete"><i class="fa-regular fa-trash-can"></i> Delete My Account</button>
                </form>
            </div>
        </div>
        
        <div style="grid-column: 1 / -1; text-align: center; padding: 20px; color:#94a3b8; font-size:12px;">
            <p>FarmWise Web App</p>
            <h4 style="color:#64748b; margin: 5px 0;">Version 2.4.1</h4>
            <p>© 2026 FarmWise. All rights reserved.</p>
        </div>
    </div>
</div>

<div id="editProfileModal" class="modal">
    <div class="modal-content">
        <h3 class="modal-title">Edit Profile</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($user_name); ?>" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($user_phone); ?>" required>
            </div>
            <div class="form-group">
                <label>Location (State, Country)</label>
                <input type="text" name="location" value="<?php echo htmlspecialchars($user_location); ?>" required>
            </div>
            <div class="form-group">
                <label>Farm Size (e.g., 5 acres)</label>
                <input type="text" name="farm_size" value="<?php echo htmlspecialchars($user_farm_size); ?>" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('editProfileModal')">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Modal Logic
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    // Theme Logic (Light / Dark Mode)
    function setTheme(theme) {
        document.getElementById('btn-light').classList.remove('active');
        document.getElementById('btn-dark').classList.remove('active');
        document.getElementById('btn-' + theme).classList.add('active');

        if (theme === 'dark') {
            document.documentElement.classList.add('dark-mode');
        } else {
            document.documentElement.classList.remove('dark-mode');
        }
        localStorage.setItem('farmwise_theme', theme);
    }

    // Data Sync Logic
    function setSync(mode) {
        document.getElementById('sync-wifi').classList.remove('active');
        document.getElementById('sync-mobile').classList.remove('active');
        document.getElementById('sync-both')?.classList.remove('active'); // Optional depending on HTML
        
        if(document.getElementById('sync-' + mode)) {
            document.getElementById('sync-' + mode).classList.add('active');
        }
        localStorage.setItem('farmwise_sync', mode);
    }

    // Restore Settings on Load
    window.onload = function() {
        const savedTheme = localStorage.getItem('farmwise_theme') || 'light';
        setTheme(savedTheme);

        const savedSync = localStorage.getItem('farmwise_sync') || 'wifi';
        setSync(savedSync);
    };
</script>

</body>
</html>