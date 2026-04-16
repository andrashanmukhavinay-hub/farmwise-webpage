<?php
session_start();
require 'db.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { 
    header("Location: farmer-login.php"); 
    exit(); 
}

$user_id = $_SESSION['user_id'];

// --- FETCH NOTIFICATION COUNT (For Real-Time Badge) ---
try {
    $stmt_count = $conn->prepare("
        SELECT COUNT(*) as total FROM (
            SELECT id FROM announcements
            UNION ALL
            SELECT id FROM pest_diagnoses WHERE user_id = ?
        ) as combined
    ");
    $stmt_count->execute([$user_id]);
    $alert_data = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $unread_count = $alert_data['total'] ?? 0;
} catch(PDOException $e) {
    $unread_count = 0;
}

// --- FETCH GOVERNMENT SCHEMES ---
try {
    $stmt = $conn->query("SELECT * FROM govt_schemes ORDER BY type ASC");
    $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $schemes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Govt Schemes - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { background-color: #f1f5f9; height: 100vh; overflow: hidden; transition: background-color 0.3s, color 0.3s; }
        .app-container { width: 100%; max-width: 1400px; margin: 0 auto; height: 100vh; display: flex; flex-direction: column; }

        /* Header */
        .header { background: #059669; color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: background-color 0.3s; }
        .back-link { color: white; text-decoration: none; font-weight: 700; display: flex; align-items: center; gap: 10px; font-size: 18px; transition: 0.2s; }
        .back-link:hover { opacity: 0.8; }
        
        /* Nav Icons & Notification Badge */
        .nav-icons { display: flex; gap: 20px; font-size: 22px; align-items: center; }
        .nav-icons a { color: white; text-decoration: none; position: relative; transition: 0.2s; }
        .nav-icons a:hover { opacity: 0.8; }
        
        #unread-count-badge { 
            position: absolute; top: -8px; right: -10px; 
            background-color: #ef4444; border-radius: 50%; 
            min-width: 18px; height: 18px; font-size: 11px; 
            display: <?php echo ($unread_count > 0) ? 'flex' : 'none'; ?>; 
            align-items: center; justify-content: center; 
            border: 2px solid #059669; font-weight: bold; padding: 0 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .main-content { display: grid; grid-template-columns: 350px 1fr; flex: 1; overflow: hidden; }

        /* Left Side: Sidebar & Search */
        .sidebar { background: white; border-right: 1px solid #e2e8f0; padding: 30px; overflow-y: auto; transition: 0.3s; }
        .search-box { width: 100%; padding: 14px 15px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 25px; outline: none; background: #f8fafc; font-size:14px; transition: 0.3s; color: inherit; }
        .search-box:focus { border-color: #059669; background: white; }
        
        .filter-group { display: flex; flex-direction: column; gap: 10px; margin-bottom: 30px; }
        .filter-btn { padding: 12px 15px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; text-align: left; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 10px; transition: 0.2s; }
        .filter-btn.active { background: #059669; color: white; border-color: #059669; }

        .guidance-box { background: #eff6ff; padding: 20px; border-radius: 12px; font-size: 13px; color: #1e40af; border-left: 4px solid #3b82f6; line-height: 1.5; transition: 0.3s; }

        /* Right Side: Schemes Grid */
        .schemes-pane { padding: 40px; overflow-y: auto; transition: 0.3s; }
        .scheme-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 20px; }
        
        .scheme-card { background: white; border-radius: 16px; padding: 25px; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px rgba(0,0,0,0.02); transition: 0.3s; position: relative; display: flex; flex-direction: column; }
        .scheme-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); border-color: #059669; }
        
        .type-tag { font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 5px 12px; border-radius: 20px; margin-bottom: 15px; display: inline-block; align-self: flex-start; }
        .type-Subsidy { background: #dcfce7; color: #166534; }
        .type-Loan { background: #dbeafe; color: #1e40af; }
        
        .scheme-name { font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 10px; transition: color 0.3s; }
        .scheme-desc { font-size: 14px; color: #64748b; line-height: 1.5; margin-bottom: 20px; height: 45px; overflow: hidden; transition: color 0.3s; }
        
        .benefit-box { background: #f8fafc; padding: 12px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; transition: 0.3s; }
        .benefit-val { font-weight: 800; color: #059669; font-size: 16px; }
        
        .view-details-btn { width: 100%; background: #059669; color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 15px; }
        .view-details-btn:hover { background: #047857; }

        /* AI Eligibility Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-content { background: white; width: 600px; padding: 35px; border-radius: 20px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); transition: 0.3s; }
        .ai-response { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 25px; border-radius: 12px; margin-top: 20px; font-size: 15px; line-height: 1.6; color: #166534; transition: 0.3s; }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #121212; color: #f8fafc; }
        html.dark-mode .header { background-color: #064e3b; }
        html.dark-mode #unread-count-badge { border-color: #064e3b; }

        html.dark-mode .sidebar { background-color: #121212 !important; border-color: #333 !important; }
        html.dark-mode .search-box { background-color: #1e1e1e !important; border-color: #333 !important; color: #f8fafc !important; }
        
        html.dark-mode .filter-btn { background-color: #1e1e1e !important; border-color: #333 !important; color: #94a3b8 !important; }
        html.dark-mode .filter-btn.active { background-color: #059669 !important; color: white !important; }
        
        html.dark-mode .guidance-box { background-color: #1e3a8a !important; color: #bfdbfe !important; border-color: #3b82f6 !important; }

        html.dark-mode .schemes-pane { background-color: #121212 !important; }
        html.dark-mode .scheme-card { background-color: #1e1e1e !important; border-color: #333 !important; box-shadow: none !important; }
        html.dark-mode .scheme-card:hover { border-color: #059669 !important; }
        
        html.dark-mode .scheme-name,
        html.dark-mode h3, 
        html.dark-mode h2 { color: #f8fafc !important; }
        html.dark-mode .scheme-desc { color: #94a3b8 !important; }
        
        html.dark-mode .benefit-box { background-color: #121212 !important; }

        html.dark-mode .modal-content { background-color: #1e1e1e !important; color: #f8fafc !important; }
        html.dark-mode .modal-content p { color: #94a3b8 !important; }
        html.dark-mode .ai-response { background-color: #064e3b !important; border-color: #059669 !important; color: #dcfce7 !important; }

        @media (max-width: 900px) { .main-content { grid-template-columns: 1fr; } .sidebar { display: none; } }
    </style>
</head>
<body>

<div class="app-container">
    <div class="header">
        <a href="farmer-dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Government Schemes</a>
        <div style="font-weight: 800; letter-spacing: 1px;">CENTRAL & STATE PROGRAMS</div>
        <div class="nav-icons">
            <a href="alerts.php">
                <i class="fa-regular fa-bell">
                    <div id="unread-count-badge"><?php echo $unread_count; ?></div>
                </i>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="sidebar">
            <h3 style="margin-bottom: 15px; color:inherit;">Find Schemes</h3>
            <input type="text" id="schemeSearch" class="search-box" placeholder="Search by name (e.g. KISAN)..." onkeyup="filterSchemes()">
            
            <div class="filter-group">
                <button class="filter-btn active" onclick="filterType('All', this)"><i class="fa-solid fa-layer-group"></i> All Schemes</button>
                <button class="filter-btn" onclick="filterType('Subsidy', this)"><i class="fa-solid fa-hand-holding-dollar"></i> Subsidies</button>
                <button class="filter-btn" onclick="filterType('Loan', this)"><i class="fa-solid fa-building-columns"></i> Loan Schemes</button>
            </div>

            <div class="guidance-box">
                <strong><i class="fa-solid fa-robot"></i> AI Help & Guidance:</strong><br>
                Use our AI tool embedded in each scheme card to check your specific eligibility instantly based on your land and location.
            </div>
        </div>

        <div class="schemes-pane">
            <h2 id="paneTitle" style="margin-bottom: 25px; color:inherit;">All Schemes</h2>
            
            <div class="scheme-grid" id="schemeGrid">
                <?php foreach($schemes as $s): ?>
                    <div class="scheme-card" data-type="<?php echo htmlspecialchars($s['type']); ?>" data-name="<?php echo strtolower(htmlspecialchars($s['name'])); ?>">
                        <span class="type-tag type-<?php echo htmlspecialchars($s['type']); ?>"><?php echo htmlspecialchars($s['type']); ?></span>
                        
                        <div class="scheme-name"><?php echo htmlspecialchars($s['name']); ?></div>
                        <p class="scheme-desc"><?php echo htmlspecialchars($s['description']); ?></p>
                        
                        <div class="benefit-box">
                            <span style="font-size: 12px; color: #64748b; font-weight: 600; letter-spacing: 1px;">BENEFIT</span>
                            <span class="benefit-val"><?php echo htmlspecialchars($s['benefit_amount']); ?></span>
                        </div>
                        
                        <button class="view-details-btn" onclick="openAIModal('<?php echo addslashes(htmlspecialchars($s['name'])); ?>')">
                            Check Eligibility with AI
                        </button>
                        
                        <div style="margin-top: 15px; font-size: 12px; color: #10b981; font-weight: 700;">
                            <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($s['status']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="aiModal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 id="modalTitle" style="color: inherit; font-size: 22px;">Scheme Details</h3>
            <i class="fa-solid fa-xmark" style="cursor: pointer; font-size: 24px; color: #94a3b8;" onclick="closeModal()"></i>
        </div>
        <p style="font-size: 14px; margin-bottom: 25px;">Ask our AI about eligibility, documents, or how to apply for this scheme.</p>
        
        <textarea id="aiQuery" class="search-box" style="height: 120px; resize: none; margin-bottom: 15px;" placeholder="Example: I have 5 acres of land in Bihar, am I eligible?"></textarea>
        
        <button id="aiBtn" class="view-details-btn" style="background: #2563eb; display: flex; justify-content: center; align-items: center; gap: 10px;" onclick="askAI()">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Ask AI Consultant
        </button>

        <div id="aiLoading" style="display: none; text-align: center; margin-top: 25px; color: #6366f1; font-weight: 600;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px;"></i><br>
            Consulting Government Guidelines...
        </div>

        <div id="aiResponse" class="ai-response" style="display: none;"></div>
    </div>
</div>

<script>
    let currentScheme = "";

    function filterType(type, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        const cards = document.querySelectorAll('.scheme-card');
        cards.forEach(card => {
            if (type === 'All' || card.getAttribute('data-type') === type) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
        document.getElementById('paneTitle').innerText = type === 'All' ? "All Schemes" : type + " Schemes";
    }

    function filterSchemes() {
        const val = document.getElementById('schemeSearch').value.toLowerCase();
        const cards = document.querySelectorAll('.scheme-card');
        cards.forEach(card => {
            if (card.getAttribute('data-name').includes(val)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function openAIModal(name) {
        currentScheme = name;
        document.getElementById('modalTitle').innerText = "AI Consultant: " + name;
        document.getElementById('aiModal').style.display = 'flex';
        document.getElementById('aiResponse').style.display = 'none';
        document.getElementById('aiQuery').value = "";
    }

    function closeModal() {
        document.getElementById('aiModal').style.display = 'none';
    }

    function askAI() {
        const query = document.getElementById('aiQuery').value.trim();
        if(!query) return alert("Please type a question!");

        const btn = document.getElementById('aiBtn');
        btn.disabled = true;
        btn.style.opacity = "0.7";
        
        document.getElementById('aiLoading').style.display = 'block';
        document.getElementById('aiResponse').style.display = 'none';

        const fd = new FormData();
        fd.append('query', query);
        fd.append('scheme_name', currentScheme);

        fetch('schemes-api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            document.getElementById('aiLoading').style.display = 'none';
            document.getElementById('aiResponse').style.display = 'block';
            
            let formattedReply = data.reply ? data.reply.replace(/\n/g, "<br>") : "No response generated.";
            document.getElementById('aiResponse').innerHTML = "<strong><i class='fa-solid fa-robot'></i> AI Advisor says:</strong><br><br>" + formattedReply;
        })
        .catch(err => {
            document.getElementById('aiLoading').style.display = 'none';
            alert("Connection error. Please try again.");
        })
        .finally(() => {
            btn.disabled = false;
            btn.style.opacity = "1";
        });
    }

    function updateBadge() {
        fetch('check-alerts.php')
            .then(res => res.json())
            .then(data => {
                const badge = document.getElementById('unread-count-badge');
                if (badge && data.count !== undefined) {
                    if (data.count > 0) {
                        badge.innerText = data.count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }).catch(e => console.log('Alert check silent failure'));
    }
    setInterval(updateBadge, 10000);
</script>

</body>
</html>