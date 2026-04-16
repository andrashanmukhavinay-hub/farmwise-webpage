<?php
session_start();
require 'db.php';
if (!isset($_SESSION['logged_in'])) { header("Location: farmer-login.php"); exit(); }

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

// Fetch Consultation History (Grouped by expert)
$history = [];
try {
    // Pulling 'message_text' to match the database
    $stmt = $conn->prepare("
        SELECT expert_name, message_text, created_at, sender_type 
        FROM expert_chats 
        WHERE user_id = :uid 
        AND id IN (
            SELECT MAX(id) FROM expert_chats WHERE user_id = :uid GROUP BY expert_name
        )
        ORDER BY created_at DESC
    ");
    $stmt->bindParam(':uid', $user_id);
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) { /* silent */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expert Connect - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background-color: #f1f5f9; height: 100vh; overflow: hidden; transition: background-color 0.3s, color 0.3s; }
        .app-container { width: 100%; max-width: 1400px; margin: 0 auto; height: 100vh; display: flex; flex-direction: column; }

        /* Header */
        .header { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; transition: 0.3s; }
        .back-link { color: white; text-decoration: none; display: flex; align-items: center; gap: 10px; font-weight: 600; transition: 0.2s;}
        .back-link:hover { opacity: 0.8; }
        
        .tab-switcher { display: flex; background: rgba(255,255,255,0.2); padding: 4px; border-radius: 12px; gap: 5px; }
        .t-btn { padding: 8px 20px; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; transition: 0.3s; border: none; background: none; color: white; }
        .t-btn.active { background: white; color: #4f46e5; }
        
        /* Notification Badge */
        .bell-link { color:white; text-decoration:none; font-weight: 700; position: relative; transition: 0.2s;}
        .bell-link:hover { opacity: 0.8; }
        .notification-badge { 
            position: absolute; top: -5px; right: -8px; 
            background-color: #ef4444; border-radius: 50%; 
            width: 16px; height: 16px; font-size: 10px; 
            display: flex; align-items: center; justify-content: center; 
            border: 2px solid #4f46e5; font-weight: bold; color: white;
        }

        .main-content { display: grid; grid-template-columns: 450px 1fr; flex: 1; overflow: hidden; }

        /* Left Pane: List & History */
        .left-pane { background: #f8fafc; border-right: 1px solid #e2e8f0; overflow-y: auto; padding: 20px; transition: 0.3s; }
        .search-bar { background: white; border-radius: 12px; padding: 12px 15px; display: flex; align-items: center; gap: 10px; border: 1px solid #e2e8f0; margin-bottom: 20px; transition: 0.3s; }
        .search-bar input { border: none; outline: none; flex: 1; font-size: 14px; background: transparent; color: inherit; }

        /* Expert Cards */
        .expert-card { background: white; border-radius: 16px; padding: 15px; margin-bottom: 15px; cursor: pointer; border: 2px solid transparent; transition: 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .expert-card:hover { border-color: #6366f1; }
        .expert-card.active { border-color: #6366f1; background: #eef2ff; }
        .ex-top { display: flex; gap: 15px; align-items: center; margin-bottom: 10px; }
        .ex-avatar { width: 50px; height: 50px; border-radius: 50%; background: #6366f1; color: white; display: flex; justify-content: center; align-items: center; font-weight: 700; font-size: 18px; position: relative; transition: 0.3s;}
        .online-dot { width: 12px; height: 12px; background: #22c55e; border: 2px solid white; border-radius: 50%; position: absolute; bottom: 0; right: 0; transition: 0.3s;}
        .ex-info h4 { font-size: 15px; color: #1e293b; transition: color 0.3s;}
        .ex-info p { font-size: 12px; color: #6366f1; font-weight: 600; }

        /* History Cards */
        .history-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; transition: 0.3s; }
        .history-card:hover { border-color: #6366f1; }
        .h-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .h-title { font-size: 15px; font-weight: 800; color: #1e293b; transition: color 0.3s;}
        .h-status { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #16a34a; transition: 0.3s;}
        .h-expert { font-size: 13px; color: #64748b; margin-bottom: 10px; display: flex; align-items: center; gap: 5px; transition: color 0.3s;}
        .h-summary { background: #f8fafc; padding: 12px; border-radius: 10px; font-size: 13px; color: #475569; line-height: 1.4; margin-bottom: 15px; border-left: 3px solid #6366f1; transition: 0.3s; }
        .h-footer { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #94a3b8; transition: color 0.3s;}
        .view-btn { color: #6366f1; font-weight: 700; text-decoration: none; cursor: pointer; transition: 0.2s;}
        .view-btn:hover { opacity: 0.8; }

        /* Chat Pane */
        .chat-pane { background: white; display: flex; flex-direction: column; position: relative; transition: 0.3s; }
        .chat-header { padding: 15px 25px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px; transition: border-color 0.3s;}
        .chat-messages { flex: 1; overflow-y: auto; padding: 25px; display: flex; flex-direction: column; gap: 15px; background: #f8fafc; transition: 0.3s; }
        
        .bubble { max-width: 70%; padding: 12px 18px; border-radius: 16px; font-size: 14px; line-height: 1.5; word-wrap: break-word; transition: 0.3s;}
        .user-msg { align-self: flex-end; background: #4f46e5; color: white; border-bottom-right-radius: 2px; }
        .expert-msg { align-self: flex-start; background: white; border: 1px solid #e2e8f0; border-bottom-left-radius: 2px; color: #1e293b; }
        
        .chat-input-area { padding: 20px; border-top: 1px solid #f1f5f9; display: flex; gap: 15px; align-items: center; background: white; transition: 0.3s;}
        .chat-input-area input { flex: 1; padding: 14px 20px; border-radius: 25px; border: 1px solid #e2e8f0; outline: none; background: #f1f5f9; transition: 0.3s; color: inherit;}
        .chat-input-area input:focus { border-color: #6366f1; background: transparent; }
        
        .send-btn { width: 45px; height: 45px; border-radius: 50%; background: #4f46e5; color: white; border: none; cursor: pointer; display: flex; justify-content: center; align-items: center; transition: 0.2s; }
        .send-btn:hover { background: #4338ca; }
        .send-btn:disabled { background: #94a3b8; cursor: not-allowed; }

        .empty-chat { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #94a3b8; transition: color 0.3s;}
        .empty-chat i { font-size: 60px; margin-bottom: 15px; }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #121212; color: #f8fafc; }
        html.dark-mode .header { background: linear-gradient(135deg, #2e1065 0%, #1e1b4b 100%); }
        html.dark-mode .notification-badge { border-color: #2e1065 !important; }
        
        html.dark-mode .left-pane { background-color: #121212 !important; border-color: #333 !important; }
        html.dark-mode .search-bar { background-color: #1e1e1e !important; border-color: #333 !important; }
        
        html.dark-mode .expert-card, 
        html.dark-mode .history-card { background-color: #1e1e1e !important; border-color: #333 !important; box-shadow: none !important; }
        html.dark-mode .expert-card:hover,
        html.dark-mode .history-card:hover { border-color: #6366f1 !important; }
        
        html.dark-mode .expert-card.active { background-color: #312e81 !important; border-color: #6366f1 !important; }
        html.dark-mode .online-dot { border-color: #1e1e1e !important; }
        
        html.dark-mode .ex-info h4,
        html.dark-mode .h-title,
        html.dark-mode #activeName { color: #f8fafc !important; }
        
        html.dark-mode .h-status { background-color: #064e3b !important; color: #6ee7b7 !important; }
        html.dark-mode .h-summary { background-color: #121212 !important; color: #94a3b8 !important; }

        html.dark-mode .chat-pane { background-color: #121212 !important; }
        html.dark-mode .chat-header { border-color: #333 !important; }
        html.dark-mode .chat-messages { background-color: #121212 !important; }
        html.dark-mode .expert-msg { background-color: #1e1e1e !important; border-color: #333 !important; color: #f8fafc !important; }
        
        html.dark-mode .chat-input-area { background-color: #121212 !important; border-color: #333 !important; }
        html.dark-mode .chat-input-area input { background-color: #1e1e1e !important; border-color: #333 !important; }
        html.dark-mode .chat-input-area input:focus { border-color: #6366f1 !important; }

        @media (max-width: 900px) {
            .main-content { grid-template-columns: 1fr; }
            .chat-pane { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 100; display: none; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <div class="header">
        <a href="farmer-dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        <div class="tab-switcher">
            <button class="t-btn active" id="btn-experts" onclick="switchMainTab('experts')">Experts</button>
            <button class="t-btn" id="btn-history" onclick="switchMainTab('history')">History</button>
        </div>
        <a href="alerts.php" class="bell-link">
            <i class="fa-regular fa-bell">
                <div class="notification-badge" id="unread-count-badge" style="display: <?php echo ($unread_count > 0) ? 'flex' : 'none'; ?>;">
                    <?php echo $unread_count; ?>
                </div>
            </i>
        </a>
    </div>

    <div class="main-content">
        <div class="left-pane">
            
            <div id="view-experts">
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass" style="color:#94a3b8;"></i>
                    <input type="text" placeholder="Search experts...">
                </div>

                <div class="expert-card" onclick="openChat('Dr. Rajesh Kumar', 'Crop Management', 'RK')">
                    <div class="ex-top">
                        <div class="ex-avatar">RK <div class="online-dot"></div></div>
                        <div class="ex-info"><h4>Dr. Rajesh Kumar</h4><p>Crop Management</p></div>
                    </div>
                    <div style="display:flex; gap:15px; font-size:11px; font-weight:600; color:#64748b;">
                        <span><i class="fa-solid fa-star" style="color:#f59e0b"></i> 4.8</span>
                        <span>&lt; 5 min Reply</span>
                    </div>
                </div>

                <div class="expert-card" onclick="openChat('Dr. Priya Sharma', 'Pest & Disease Control', 'PS')">
                    <div class="ex-top">
                        <div class="ex-avatar" style="background:#ec4899">PS <div class="online-dot"></div></div>
                        <div class="ex-info"><h4>Dr. Priya Sharma</h4><p>Pest Control</p></div>
                    </div>
                    <div style="display:flex; gap:15px; font-size:11px; font-weight:600; color:#64748b;">
                        <span><i class="fa-solid fa-star" style="color:#f59e0b"></i> 4.9</span>
                        <span>&lt; 10 min Reply</span>
                    </div>
                </div>
            </div>

            <div id="view-history" style="display:none;">
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass" style="color:#94a3b8;"></i>
                    <input type="text" placeholder="Search history...">
                </div>

                <?php if(empty($history)): ?>
                    <p style="text-align:center; color:#94a3b8; margin-top:30px;">No previous consultations.</p>
                <?php else: ?>
                    <?php foreach($history as $row): ?>
                        <div class="history-card">
                            <div class="h-top">
                                <div class="h-title">Expert Consultation</div>
                                <div class="h-status">Recorded</div>
                            </div>
                            <div class="h-expert"><i class="fa-solid fa-user-doctor"></i> <?php echo htmlspecialchars($row['expert_name']); ?></div>
                            <div class="h-summary">
                                <strong>Last Msg:</strong> <?php echo htmlspecialchars(substr($row['message_text'] ?? '', 0, 50)); ?>...
                            </div>
                            <div class="h-footer">
                                <span><i class="fa-regular fa-calendar"></i> <?php echo date("M d, Y", strtotime($row['created_at'])); ?></span>
                                <span class="view-btn" onclick="openChat('<?php echo htmlspecialchars($row['expert_name']); ?>', 'Advisor', 'EX')">Resume Chat ></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-pane" id="chatPane">
            <div id="noChatSelected" class="empty-chat">
                <i class="fa-regular fa-comments"></i>
                <p>Select an expert to begin a secure consultation</p>
            </div>

            <div id="chatActive" style="display:none; flex:1; flex-direction:column;">
                <div class="chat-header">
                    <div class="ex-avatar" id="activeAvatar" style="width:40px; height:40px; font-size:14px;"></div>
                    <div>
                        <h4 id="activeName" style="color:#1e293b; font-size:15px; margin-bottom:2px; transition:color 0.3s;">Expert Name</h4>
                        <p id="activeSpecialty" style="font-size:12px; color:#6366f1; font-weight:600;"></p>
                    </div>
                    <i class="fa-solid fa-xmark" style="margin-left:auto; cursor:pointer; font-size:20px; color:#94a3b8; display:none;" id="closeChatBtn" onclick="closeChat()"></i>
                </div>
                
                <div class="chat-messages" id="chatBox">
                    <div class="bubble expert-msg">Hello! I am ready to assist you. How can I help with your farm today?</div>
                </div>
                
                <div class="chat-input-area">
                    <input type="text" id="msgInput" placeholder="Type your farming question here..." autocomplete="off" onkeypress="if(event.key==='Enter') sendMessage()">
                    <button class="send-btn" id="sendBtn" onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentExpert = "";
    let currentSpecialty = "";

    // Fix for mobile close button
    if(window.innerWidth <= 900) {
        document.getElementById('closeChatBtn').style.display = 'block';
    }

    function switchMainTab(tab) {
        document.getElementById('btn-experts').classList.toggle('active', tab === 'experts');
        document.getElementById('btn-history').classList.toggle('active', tab === 'history');
        document.getElementById('view-experts').style.display = tab === 'experts' ? 'block' : 'none';
        document.getElementById('view-history').style.display = tab === 'history' ? 'block' : 'none';
    }

    function openChat(name, specialty, initials) {
        currentExpert = name;
        currentSpecialty = specialty;

        document.getElementById('noChatSelected').style.display = 'none';
        document.getElementById('chatActive').style.display = 'flex';
        document.getElementById('activeName').innerText = name;
        document.getElementById('activeSpecialty').innerText = specialty;
        document.getElementById('activeAvatar').innerText = initials;
        
        const chatBox = document.getElementById('chatBox');
        chatBox.innerHTML = `<div class="bubble expert-msg">Hello! I am ${name}. How can I assist with your ${specialty} needs today?</div>`;

        // Show chat pane on mobile
        if(window.innerWidth <= 900) {
            document.getElementById('chatPane').style.display = 'flex';
        }
    }

    function closeChat() {
        if(window.innerWidth <= 900) {
            document.getElementById('chatPane').style.display = 'none';
        }
    }

    function sendMessage() {
        const input = document.getElementById('msgInput');
        const sendBtn = document.getElementById('sendBtn');
        const text = input.value.trim();
        const chatBox = document.getElementById('chatBox');
        
        if(!text || !currentExpert) return;

        // Display user message
        chatBox.innerHTML += `<div class="bubble user-msg">${text}</div>`;
        input.value = "";
        chatBox.scrollTop = chatBox.scrollHeight;
        
        // Disable input while waiting for API
        input.disabled = true;
        sendBtn.disabled = true;
        
        // Show typing indicator
        const typingId = "typing_" + Date.now();
        chatBox.innerHTML += `<div class="bubble expert-msg" id="${typingId}"><i class="fa-solid fa-ellipsis fa-fade"></i></div>`;
        chatBox.scrollTop = chatBox.scrollHeight;

        // Prepare data for backend
        const formData = new FormData();
        formData.append('expert', currentExpert);
        formData.append('specialty', currentSpecialty);
        formData.append('message', text);

        fetch('expert-api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById(typingId).remove();

            if (data.reply) {
                chatBox.innerHTML += `<div class="bubble expert-msg">${data.reply.replace(/\n/g, '<br>')}</div>`;
            } else if (data.error) {
                chatBox.innerHTML += `<div class="bubble expert-msg" style="color:red;">Error: ${data.error}</div>`;
            }
            chatBox.scrollTop = chatBox.scrollHeight;
        })
        .catch(error => {
            document.getElementById(typingId).remove();
            chatBox.innerHTML += `<div class="bubble expert-msg" style="color:red;">Connection lost. Please check your internet.</div>`;
            console.error("Fetch Error:", error);
        })
        .finally(() => {
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        });
    }

    // Live Notification Polling
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