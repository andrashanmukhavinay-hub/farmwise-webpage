<?php
session_start();
require 'db.php';

$message = "";
$messageType = ""; // 'error' or 'success'

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (!empty($email) && !empty($new_pass) && !empty($confirm_pass)) {
        if ($new_pass === $confirm_pass) {
            if (strlen($new_pass) >= 6) {
                try {
                    // FIXED: Checking the 'admins' table specifically
                    $stmt = $conn->prepare("SELECT id FROM admins WHERE email = :email LIMIT 1");
                    $stmt->execute([':email' => $email]);
                    
                    if ($stmt->fetch()) {
                        // FIXED: Updating 'admins' table. 
                        // Note: Using plain text to match your current database screenshot (Image 7)
                        $update = $conn->prepare("UPDATE admins SET password = :pass WHERE email = :email");
                        $update->execute([':pass' => $new_pass, ':email' => $email]);
                        
                        $message = "Admin password reset successfully!";
                        $messageType = "success";
                    } else {
                        $message = "No admin account found with that email.";
                        $messageType = "error";
                    }
                } catch(PDOException $e) {
                    $message = "Database Error: " . $e->getMessage();
                    $messageType = "error";
                }
            } else {
                $message = "Password must be at least 6 characters.";
                $messageType = "error";
            }
        } else {
            $message = "Passwords do not match.";
            $messageType = "error";
        }
    } else {
        $message = "Please fill in all fields.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reset Password - FarmWise Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        body { 
            background: radial-gradient(circle at top right, #22c55e, #15803d); 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            padding: 20px;
        }

        .back-link { 
            position: absolute; 
            top: 40px; 
            left: 30px; 
            color: white; 
            font-size: 14px; 
            font-weight: 700; 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 8px;
            transition: 0.3s;
        }
        .back-link:hover { opacity: 0.8; transform: translateX(-3px); }

        .login-card { 
            background: white; 
            width: 100%; 
            max-width: 400px; 
            border-radius: 24px; 
            padding: 40px 30px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.2); 
            text-align: center;
        }

        .login-card h2 { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 8px; }
        .login-card p { font-size: 13px; color: #64748b; margin-bottom: 30px; }

        .alert-error { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; font-weight: 600; border: 1px solid #fecaca; }
        .alert-success { background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; font-weight: 600; border: 1px solid #bbf7d0; }

        .form-group { text-align: left; margin-bottom: 20px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; }
        
        .input-wrapper { position: relative; }
        .input-wrapper i.icon-left { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; }
        .input-wrapper i.icon-right { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; cursor: pointer; }
        
        .input-wrapper input { 
            width: 100%; 
            padding: 14px 15px 14px 45px; 
            border: 1px solid #e2e8f0; 
            border-radius: 12px; 
            font-size: 14px; 
            outline: none; 
            transition: 0.3s; 
            color: #1e293b;
        }
        .input-wrapper input:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }

        .btn-submit { 
            width: 100%; 
            background: #cbd5e1; 
            color: white; 
            border: none; 
            padding: 15px; 
            border-radius: 12px; 
            font-size: 15px; 
            font-weight: 700; 
            cursor: pointer; 
            margin-top: 10px; 
            transition: 0.3s; 
        }
        .btn-submit:hover { background: #10b981; }
        
        .divider { display: flex; align-items: center; text-align: center; color: #cbd5e1; font-size: 12px; margin: 25px 0; }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid #e2e8f0; }
        .divider:not(:empty)::before { margin-right: .5em; }
        .divider:not(:empty)::after { margin-left: .5em; }

        .signup-text { font-size: 13px; color: #64748b; }
        .signup-text a { color: #10b981; font-weight: 700; text-decoration: none; }

        .footer-text { margin-top: 25px; font-size: 11px; color: rgba(255,255,255,0.8); letter-spacing: 0.5px; }
    </style>
</head>
<body>

    <a href="admin-login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>

    <div class="login-card">
        <h2>Reset Password</h2>
        <p>Admin Recovery - Enter credentials</p>

        <?php if($messageType === 'error'): ?>
            <div class="alert-error"><?php echo $message; ?></div>
        <?php elseif($messageType === 'success'): ?>
            <div class="alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Admin Email</label>
                <div class="input-wrapper">
                    <i class="fa-regular fa-envelope icon-left"></i>
                    <input type="email" name="email" placeholder="admin@farmwise.com" required>
                </div>
            </div>

            <div class="form-group">
                <label>New Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock icon-left"></i>
                    <input type="password" name="new_password" id="pwd1" placeholder="Minimum 6 characters" required>
                    <i class="fa-regular fa-eye icon-right" onclick="togglePwd('pwd1', this)"></i>
                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock icon-left"></i>
                    <input type="password" name="confirm_password" id="pwd2" placeholder="Confirm Password" required>
                    <i class="fa-regular fa-eye icon-right" onclick="togglePwd('pwd2', this)"></i>
                </div>
            </div>

            <button type="submit" class="btn-submit">Update Password</button>
            
            <div class="divider">or</div>
            
            <p class="signup-text">Remembered? <a href="admin-login.php">Login Now</a></p>
        </form>
    </div>

    <div class="footer-text">FarmWise Admin Portal • Security Management</div>

    <script>
        function togglePwd(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.replace("fa-eye-slash", "fa-eye");
            }
        }
    </script>
</body>
</html>