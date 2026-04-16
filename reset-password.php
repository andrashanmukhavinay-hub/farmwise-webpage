<?php
// Include the database connection
require 'db.php'; 

$error_msg = "";
$success_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error_msg = "Passwords do not match!";
    } else {
        try {
            // 1. Check if the user exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();

            if ($check_stmt->rowCount() == 0) {
                $error_msg = "No account found with that email address.";
            } else {
                // 2. Update the password in the database
                $update_stmt = $conn->prepare("UPDATE users SET password = :password WHERE email = :email");
                $update_stmt->bindParam(':password', $new_password);
                $update_stmt->bindParam(':email', $email);
                
                if ($update_stmt->execute()) {
                    $success_msg = "Password reset successfully! You can now login.";
                } else {
                    $error_msg = "Something went wrong. Please try again.";
                }
            }
        } catch(PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        body {
            background: linear-gradient(rgba(13, 31, 17, 0.6), rgba(27, 141, 68, 0.6)), 
                        url('https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&q=80&w=1200&blur=10') center center / cover no-repeat fixed;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .top-bar { position: absolute; top: 40px; left: 40px; width: 100%; z-index: 20; }
        .back-link { color: white; text-decoration: none; font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: opacity 0.3s; }
        .back-link:hover { opacity: 0.8; }

        .reset-card { background: white; width: 90%; max-width: 420px; padding: 40px 35px; border-radius: 24px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); text-align: center; z-index: 10; }
        .reset-card h2 { font-size: 24px; color: #1e293b; margin-bottom: 10px; }
        .subtitle { font-size: 13px; color: #64748b; margin-bottom: 25px; line-height: 1.5; }

        .alert { padding: 12px 15px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; text-align: left; }
        .alert-error { background-color: #fee2e2; color: #ef4444; }
        .alert-success { background-color: #dcfce7; color: #16a34a; }

        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 8px; }
        
        .input-wrapper { position: relative; }
        .input-wrapper i.left-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; }
        .input-wrapper input { width: 100%; padding: 15px 15px 15px 48px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 14px; outline: none; transition: border-color 0.3s, box-shadow 0.3s; }
        .input-wrapper input:focus { border-color: #1b8d44; box-shadow: 0 0 0 4px rgba(27, 141, 68, 0.1); }
        .toggle-password { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; font-size: 16px; }

        .submit-btn { width: 100%; background-color: #1b8d44; color: white; border: none; padding: 16px; border-radius: 14px; font-size: 15px; font-weight: 700; cursor: pointer; margin-top: 10px; transition: background 0.3s, transform 0.2s; box-shadow: 0 10px 20px rgba(27, 141, 68, 0.2); }
        .submit-btn:hover { background-color: #146b33; transform: translateY(-2px); }

        .login-text { font-size: 13px; color: #64748b; margin-top: 25px; }
        .login-text a { color: #1b8d44; font-weight: 700; text-decoration: none; }

        .bottom-decoration { display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 40px; position: absolute; bottom: 40px; width: 100%; }
        .line { height: 1px; width: 30px; background-color: rgba(255,255,255,0.4); }
        .bottom-decoration i { color: rgba(255,255,255,0.8); font-size: 14px; }

        @media (max-width: 600px) {
            .top-bar { top: 25px; left: 20px; }
            .reset-card { padding: 35px 25px; width: 95%; }
            .bottom-decoration { bottom: 25px; }
        }
    </style>
</head>
<body>

    <div class="top-bar">
        <a href="farmer-login.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Login
        </a>
    </div>

    <div class="reset-card">
        <h2>Reset Password</h2>
        <p class="subtitle">Enter your email and new password to reset</p>
        
        <?php if(!empty($error_msg)): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>
        
        <?php if(!empty($success_msg)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-regular fa-envelope left-icon"></i>
                    <input type="email" name="email" placeholder="example@example.com" required>
                </div>
            </div>

            <div class="form-group">
                <label>New Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock left-icon"></i>
                    <input type="password" name="new_password" id="newPasswordInput" placeholder="Minimum 6 characters" required>
                    <i class="fa-regular fa-eye toggle-password" onclick="togglePassword('newPasswordInput', this)"></i>
                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock left-icon"></i>
                    <input type="password" name="confirm_password" id="confirmPasswordInput" placeholder="Confirm Password" required>
                    <i class="fa-regular fa-eye toggle-password" onclick="togglePassword('confirmPasswordInput', this)"></i>
                </div>
            </div>

            <button type="submit" class="submit-btn">Reset Password</button>
        </form>

        <p class="login-text">Remember your password? <a href="farmer-login.php">Login</a></p>
    </div>

    <div class="bottom-decoration">
        <div class="line"></div>
        <i class="fa-solid fa-seedling"></i>
        <div class="line"></div>
    </div>

    <script>
        function togglePassword(inputId, iconElement) {
            const pwdInput = document.getElementById(inputId);
            if (pwdInput.type === "password") {
                pwdInput.type = "text";
                iconElement.classList.remove("fa-eye");
                iconElement.classList.add("fa-eye-slash");
            } else {
                pwdInput.type = "password";
                iconElement.classList.remove("fa-eye-slash");
                iconElement.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>