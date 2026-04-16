<?php
session_start();
require 'db.php';

$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        try {
            $stmt = $conn->prepare("SELECT id, email, password, name FROM admins WHERE email = :email LIMIT 1");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && ($password === $admin['password'] || password_verify($password, $admin['password']))) { 
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['role'] = 'admin'; 
                $_SESSION['logged_in'] = true;
                
                header("Location: admin-dashboard.php"); 
                exit(); 
            } else {
                $error_msg = "Invalid admin email or password.";
            }
        } catch(PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Login - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: radial-gradient(circle at top right, #22c55e, #15803d); min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px; }
        .back-btn { position: absolute; top: 40px; left: 30px; background: rgba(255, 255, 255, 0.2); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; justify-content: center; align-items: center; text-decoration: none; backdrop-filter: blur(5px); transition: 0.3s; }
        .login-card { background: white; width: 100%; max-width: 400px; border-radius: 24px; padding: 40px 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); text-align: center; }
        .login-card h2 { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 8px; }
        .login-card p { font-size: 13px; color: #64748b; margin-bottom: 30px; }
        .error-msg { background: #fee2e2; color: #dc2626; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; font-weight: 600; }
        .form-group { text-align: left; margin-bottom: 20px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; }
        .input-wrapper { position: relative; }
        .input-wrapper i.icon-left { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; }
        .input-wrapper i.icon-right { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; cursor: pointer; }
        .input-wrapper input { width: 100%; padding: 14px 15px 14px 45px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; outline: none; transition: 0.3s; color: #1e293b; }
        .input-wrapper input:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
        .btn-submit { width: 100%; background: #cbd5e1; color: white; border: none; padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; margin-top: 10px; transition: 0.3s; }
        .btn-submit:hover { background: #10b981; }
        
        /* Reset Link Styling */
        .reset-link { display: block; margin-top: 15px; font-size: 13px; color: #15803d; font-weight: 600; text-decoration: none; transition: 0.2s; }
        .reset-link:hover { text-decoration: underline; color: #10b981; }

        .footer-text { margin-top: 25px; font-size: 11px; color: rgba(255,255,255,0.8); letter-spacing: 0.5px; }
    </style>
</head>
<body>

    <a href="main.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>

    <div class="login-card">
        <h2>Welcome to FarmWise</h2>
        <p>Admin Portal - Sign in to continue</p>

        <?php if(!empty($error_msg)): ?>
            <div class="error-msg"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-regular fa-envelope icon-left"></i>
                    <input type="email" name="email" placeholder="admin@farmwise.com" required>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock icon-left"></i>
                    <input type="password" name="password" id="pwd" placeholder="Password" required>
                    <i class="fa-regular fa-eye icon-right" onclick="togglePwd('pwd', this)"></i>
                </div>
            </div>

            <button type="submit" class="btn-submit">Login</button>
            
            <a href="admin-reset-password.php" class="reset-link">Forgot Password?</a>
        </form>
    </div>

    <div class="footer-text">FarmWise Admin Portal • Secure Login</div>

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