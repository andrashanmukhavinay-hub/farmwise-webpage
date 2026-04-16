<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmWise - Loading...</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 50% 40%, #295935 0%, #15331c 70%, #0d1f11 100%);
            color: white;
            overflow: hidden;
        }

        .app-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 88px;
            height: 88px;
            background: linear-gradient(135deg, #f88b49 0%, #e86e24 100%);
            border-radius: 24px;
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
            margin-bottom: 24px;
        }

        .app-icon i {
            font-size: 38px;
        }

        h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        p { font-size: 10px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(255, 255, 255, 0.6); }

        .pagination { position: absolute; bottom: 60px; display: flex; gap: 8px; }
        .dot { width: 6px; height: 6px; border-radius: 50%; background-color: rgba(255, 255, 255, 0.3); }
        .dot.active { background-color: white; }
    </style>
</head>
<body>

    <div class="app-icon">
        <i class="fa-solid fa-tractor"></i>
    </div>
    <h1>FarmWise</h1>
    <p>AI-Powered Farming</p>

    <div class="pagination">
        <div class="dot active"></div>
        <div class="dot"></div>
        <div class="dot"></div>
    </div>

    <script>
        // Redirects to main.php exactly 3 seconds (3000ms) after the page loads
        setTimeout(function() {
            window.location.href = 'main.php';
        }, 3000);
    </script>

</body>
</html>