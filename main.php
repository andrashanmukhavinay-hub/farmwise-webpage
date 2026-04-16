<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmWise - Web Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: #f0f4f8; 
            height: 100vh; /* Forces body to exactly screen height */
            overflow-x: hidden; 
        }

        /* Full Page Container */
        .page-container {
            width: 100%;
            height: 100vh; /* Fits strictly to screen */
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        /* Expansive Top Green Header */
        .header-bg {
            background: linear-gradient(135deg, #1b8d44 0%, #0d4a21 100%);
            width: 100%;
            height: 50vh; /* Takes up exactly half the screen */
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            color: white;
            border-bottom-left-radius: 5vw;
            border-bottom-right-radius: 5vw;
            padding-top: 5vh; /* Pushes content down slightly */
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .logo-circle i {
            font-size: 36px;
            color: #1b8d44;
        }

        .header-bg h1 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .header-bg p {
            font-size: 16px;
            font-weight: 400;
            opacity: 0.9;
            letter-spacing: 0.5px;
        }

        /* Overlapping Main Card */
        .login-card {
            background: white;
            width: 90%;
            max-width: 800px;
            /* Absolutely center the card overlapping the two halves */
            position: absolute;
            top: 55%;
            transform: translateY(-50%);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            z-index: 10;
            text-align: center;
        }

        .login-card h2 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 30px;
        }

        /* Role Selection Grid */
        .role-grid {
            display: flex;
            gap: 30px; 
            margin-bottom: 30px;
        }

        .role-option {
            flex: 1;
            padding: 20px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .role-option:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
        }

        /* Farmer Theme */
        .role-farmer {
            background-color: #f2fcf5;
            border: 2px solid #c9f0d6;
        }
        .role-farmer:hover { border-color: #00a650; }
        .role-farmer .badge { background-color: #00a650; }
        .role-farmer .subtitle { color: #00a650; }

        /* Admin Theme */
        .role-admin {
            background-color: #f2f7ff;
            border: 2px solid #d4e3ff;
        }
        .role-admin:hover { border-color: #1a6cf0; }
        .role-admin .badge { background-color: #1a6cf0; }
        .role-admin .subtitle { color: #1a6cf0; }

        /* Role Card Internals */
        .image-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9; 
            border-radius: 16px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .role-option:hover .image-wrapper img {
            transform: scale(1.05);
        }

        .badge {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            border: 4px solid white;
            font-size: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .role-title {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .subtitle {
            font-size: 14px;
            font-weight: 600;
        }

        .hint-text {
            font-size: 15px;
            color: #94a3b8;
        }

        .bottom-decoration {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .line {
            height: 2px;
            width: 50px;
            background-color: #e2e8f0;
        }

        .bottom-decoration i {
            color: #1b8d44;
            font-size: 18px;
        }

        /* Mobile Responsiveness */
        @media (max-width: 600px) {
            body { height: auto; overflow-x: hidden; }
            .page-container { height: auto; min-height: 100vh; }
            .header-bg { height: 40vh; border-bottom-left-radius: 10vw; border-bottom-right-radius: 10vw; }
            .login-card { position: relative; top: auto; transform: none; margin-top: -10vh; margin-bottom: 50px; padding: 30px 20px; }
            .role-grid { flex-direction: column; gap: 20px; }
        }
    </style>
</head>
<body>

    <div class="page-container">
        <div class="header-bg">
            <div class="logo-circle">
                <i class="fa-solid fa-seedling"></i>
            </div>
            <h1>FarmWise</h1>
            <p>Smart Solutions for Modern Agriculture</p>
        </div>

        <div class="login-card">
            <h2>Select Your Role</h2>
            
            <div class="role-grid">
                <div class="role-option role-farmer" onclick="window.location.href='farmer-login.php'">
                    <div class="image-wrapper">
                       <img src="https://images.unsplash.com/photo-1605000797499-95a51c5269ae?auto=format&fit=crop&q=80&w=800&h=500" alt="Tractor in field">
                        <div class="badge">
                            <i class="fa-regular fa-user"></i>
                        </div>
                    </div>
                    <div class="role-title">Farmer</div>
                    <div class="subtitle">Field Operations</div>
                </div>

                <div class="role-option role-admin" onclick="window.location.href='admin-login.php'">
                    <div class="image-wrapper">
                        <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=800&h=500" alt="Admin reviewing documents">
                        <div class="badge">
                            <i class="fa-solid fa-user-gear"></i>
                        </div>
                    </div>
                    <div class="role-title">Admin</div>
                    <div class="subtitle">Management Portal</div>
                </div>
            </div>

            <p class="hint-text">Choose your account type to access the dashboard</p>
            
            <div class="bottom-decoration">
                <div class="line"></div>
                <i class="fa-solid fa-seedling"></i>
                <div class="line"></div>
            </div>
        </div>
    </div>

</body>
</html>