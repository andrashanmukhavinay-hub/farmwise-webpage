<?php
// 1. Start session and include database connection
session_start();
require 'db.php'; 

// Initialize an empty variable to hold any error messages
$error_msg = ""; 

// 2. Process the form if it was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Check if fields are not empty
    if (!empty($email) && !empty($password)) {
        try {
            // FIX: Removed 'role' from the SELECT query since it no longer exists in the users table
            $stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = :email LIMIT 1");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify user exists
            // Logic: Checks plain-text for your current test, but also allows password_verify for future security
            if ($user && ($password === $user['password'] || password_verify($password, $user['password']))) { 
                
                // Login Success! Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = 'farmer'; // FIX: Automatically set role to 'farmer'
                $_SESSION['logged_in'] = true;
                
                // Redirect to dashboard
                header("Location: farmer-dashboard.php");
                exit(); 
            } else {
                // Login Failed: Set error message
                $error_msg = "Invalid email or password. Please try again.";
            }

        } catch(PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill in both email and password fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Login - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Reset */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
        }
        
        body {
            background-color: #f0f4f8;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Full Page Container */
        .page-container {
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        /* Expansive Top Green Header */
        .header-bg {
            background: linear-gradient(135deg, #1b8d44 0%, #0d4a21 100%);
            width: 100%;
            height: 45vh; 
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            color: white;
            border-bottom-left-radius: 5vw;
            border-bottom-right-radius: 5vw;
            padding-top: 6vh;
            position: relative;
            overflow: hidden; 
        }

        .bg-icon {
            position: absolute;
            color: rgba(255, 255, 255, 0.05);
            font-size: 250px;
            top: -20px;
            right: -20px;
            z-index: 1;
            transform: rotate(15deg);
        }

        .back-btn {
            position: absolute;
            top: 30px;
            left: 30px;
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 18px;
            text-decoration: none;
            transition: background 0.3s, transform 0.2s;
            z-index: 10;
        }
        .back-btn:hover { 
            background: rgba(255, 255, 255, 0.3); 
            transform: translateX(-3px);
        }

        .logo-circle {
            width: 75px;
            height: 75px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            z-index: 5;
        }
        .logo-circle i { font-size: 32px; color: #1b8d44; }
        .header-bg h1 { font-size: 32px; font-weight: 800; margin-bottom: 5px; z-index: 5; }
        .header-bg p { font-size: 14px; opacity: 0.9; z-index: 5; }

        .login-card {
            background: white;
            width: 90%;
            max-width: 480px; 
            position: absolute;
            top: 50%; 
            transform: translateY(-40%);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            z-index: 10;
        }

        .login-card h2 { 
            font-size: 26px; 
            color: #1e293b; 
            margin-bottom: 25px; 
            line-height: 1.3; 
        }

        .error-alert {
            background-color: #fee2e2;
            color: #ef4444;
            padding: 15px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 8px; }
        
        .input-wrapper { position: relative; }
        .input-wrapper i.left-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 16px 15px 16px 50px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .input-wrapper input:focus { 
            border-color: #1b8d44; 
            box-shadow: 0 0 0 4px rgba(27, 141, 68, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            font-size: 16px;
        }

        .submit-btn {
            width: 100%;
            background-color: #1b8d44;
            color: white;
            border: none;
            padding: 16px;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 15px;
            transition: background 0.3s, transform 0.2s;
        }
        .submit-btn:hover { 
            background-color: #146b33; 
            transform: translateY(-2px);
        }

        .links-area { text-align: center; margin-top: 25px; }
        .forgot-link { color: #1b8d44; font-size: 14px; font-weight: 600; text-decoration: none; }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #cbd5e1;
            font-size: 13px;
            margin: 25px 0;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; border-bottom: 1px solid #e2e8f0;
        }
        .divider:not(:empty)::before { margin-right: .5em; }
        .divider:not(:empty)::after { margin-left: .5em; }

        .signup-text { font-size: 14px; color: #64748b; }
        .signup-text a { color: #1b8d44; font-weight: 600; text-decoration: none; }

        @media (max-width: 600px) {
            .header-bg { height: 40vh; border-bottom-left-radius: 10vw; border-bottom-right-radius: 10vw; padding-top: 8vh; }
            .login-card { position: relative; top: auto; transform: none; margin-top: -10vh; margin-bottom: 40px; padding: 30px 25px; width: 95%; }
            .back-btn { top: 20px; left: 20px; width: 40px; height: 40px; }
            .bg-icon { font-size: 180px; right: -50px; }
        }
    </style>
</head>
<body>

    <div class="page-container">
        <div class="header-bg">
            <a href="main.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
            
            <i class="fa-solid fa-seedling bg-icon"></i>
            
            <div class="logo-circle">
                <i class="fa-solid fa-seedling"></i>
            </div>
            <h1>FarmWise</h1>
            <p>Smart Solutions for Modern Agriculture</p>
        </div>

        <div class="login-card">
            <h2>Welcome Back,<br>Farmer 👋</h2>
            
            <?php if(!empty($error_msg)): ?>
                <div class="error-alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo $error_msg; ?></span>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope left-icon"></i>
                        <input type="email" name="email" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock left-icon"></i>
                        <input type="password" name="password" id="passwordInput" placeholder="Enter your password" required>
                        <i class="fa-regular fa-eye toggle-password" onclick="togglePassword()"></i>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Login</button>
            </form>

            <div class="links-area">
                <a href="reset-password.php" class="forgot-link">Forgot Password?</a>
                
                <div class="divider">or</div>
                
               <p class="signup-text">Don't have an account? <a href="signup.php">Sign Up</a></p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const pwdInput = document.getElementById("passwordInput");
            const icon = document.querySelector(".toggle-password");
            
            if (pwdInput.type === "password") {
                pwdInput.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                pwdInput.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>