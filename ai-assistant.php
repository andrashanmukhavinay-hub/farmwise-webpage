<?php
session_start();
require 'db.php';

// Check if logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: farmer-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch chat history for this user
$history = [];
try {
    $stmt = $conn->prepare("SELECT * FROM chat_history WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Handle error silently for UI
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ask AI - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f1f5f9; color: #1e293b; display: flex; justify-content: center; align-items: center; height: 100vh; overflow: hidden; transition: background-color 0.3s, color 0.3s; }

        .app-container { width: 100%; max-width: 1200px; background-color: #f8fafc; height: 100vh; display: flex; flex-direction: column; position: relative; box-shadow: 0 0 30px rgba(0,0,0,0.08); transition: background-color 0.3s; }
        @media (min-width: 1024px) { .app-container { height: 95vh; border-radius: 20px; overflow: hidden; } }

        .header { background: linear-gradient(135deg, #9333ea 0%, #7e22ce 100%); color: white; padding: 30px 40px 10px 40px; flex-shrink: 0; }
        .top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .back-btn { color: white; text-decoration: none; font-size: 20px; display: flex; align-items: center; gap: 10px; transition: opacity 0.3s; }
        .back-btn:hover { opacity: 0.8; }
        .back-btn span { font-size: 16px; font-weight: 600; }
        .title-area { text-align: center; }
        .title-area h1 { font-size: 28px; font-weight: 800; }
        .title-area p { font-size: 14px; opacity: 0.9; margin-top: 5px; }
        .header-actions a { color: white; font-size: 20px; cursor: pointer; text-decoration: none; transition: 0.2s; }
        .header-actions a:hover { opacity: 0.8; }

        .ai-banner { background: rgba(255,255,255,0.15); padding: 15px 25px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; backdrop-filter: blur(10px); }
        .ai-banner-left { display: flex; align-items: center; gap: 15px; }
        .ai-icon { width: 50px; height: 50px; background: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: #9333ea; font-size: 24px; position: relative; }
        .status-dot { position: absolute; bottom: 2px; right: 2px; width: 12px; height: 12px; background: #22c55e; border-radius: 50%; border: 2px solid white; }
        .ai-banner-text h3 { font-size: 18px; font-weight: 700; }
        .ai-banner-text p { font-size: 13px; opacity: 0.9; }

        .tabs { display: flex; background: white; border-radius: 12px; padding: 5px; transform: translateY(20px); max-width: 500px; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s; }
        .tab { flex: 1; padding: 12px; text-align: center; font-size: 15px; font-weight: 600; cursor: pointer; border-radius: 8px; color: #64748b; transition: all 0.3s; }
        .tab i { margin-right: 8px; }
        .tab.active { background: #f3e8ff; color: #9333ea; }

        .tab-content { flex: 1; overflow-y: auto; padding: 40px 40px 120px 40px; display: none; background-color: #f8fafc; transition: background-color 0.3s; }
        .tab-content.active { display: block; }

        .chat-area { display: flex; flex-direction: column; gap: 20px; max-width: 900px; margin: 0 auto; }
        .msg-bubble { max-width: 75%; padding: 20px 25px; border-radius: 20px; font-size: 15px; line-height: 1.6; }
        .msg-ai { background: white; border: 1px solid #e2e8f0; align-self: flex-start; border-bottom-left-radius: 4px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: 0.3s; }
        .msg-ai h4 { color: #9333ea; font-size: 15px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .msg-user { background: #10b981; color: white; align-self: flex-end; border-bottom-right-radius: 4px; box-shadow: 0 4px 15px rgba(16,185,129,0.2); }

        .history-container { max-width: 900px; margin: 0 auto; }
        .history-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; font-size: 18px; font-weight: 700; color: #334155; transition: color 0.3s; }
        .history-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .history-card { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; transition: 0.3s; }
        .history-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); border-color: #9333ea; }
        .history-card h3 { font-size: 15px; color: #1e293b; margin-bottom: 8px; transition: color 0.3s; }
        .history-card p { font-size: 13px; color: #64748b; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 15px; transition: color 0.3s; }
        .history-meta { display: flex; justify-content: space-between; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 12px; transition: border-color 0.3s; }

        .input-area { position: absolute; bottom: 0; left: 0; width: 100%; background: white; padding: 20px 40px; display: flex; justify-content: center; border-top: 1px solid #e2e8f0; z-index: 20; transition: background-color 0.3s, border-color 0.3s; }
        .input-wrapper-inner { display: flex; gap: 15px; align-items: center; width: 100%; max-width: 900px; }
        .input-box { flex: 1; background: #f1f5f9; border-radius: 24px; display: flex; align-items: center; padding: 8px 20px; border: 1px solid transparent; transition: 0.3s; }
        .input-box:focus-within { border-color: #d8b4fe; background: white; box-shadow: 0 0 0 4px rgba(147, 51, 234, 0.05); }
        .input-box input { flex: 1; border: none; background: transparent; padding: 12px 10px; font-size: 15px; outline: none; color: inherit; }
        .input-box i { color: #94a3b8; font-size: 20px; cursor: pointer; padding: 0 5px; transition: color 0.2s; }
        .input-box i:hover { color: #9333ea; }
        .send-btn { width: 55px; height: 55px; background: #9333ea; color: white; border: none; border-radius: 50%; font-size: 20px; cursor: pointer; display: flex; justify-content: center; align-items: center; transition: transform 0.2s; flex-shrink: 0; }
        .send-btn:hover { background: #7e22ce; transform: scale(1.05); }

        .typing-indicator { display: none; align-self: flex-start; background: white; padding: 18px 25px; border-radius: 20px; border-bottom-left-radius: 4px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-top: 10px; border: 1px solid #e2e8f0; transition: 0.3s; }
        .typing-indicator span { display: inline-block; width: 8px; height: 8px; background: #9333ea; border-radius: 50%; margin-right: 4px; animation: typing 1s infinite; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; margin-right: 0; }
        @keyframes typing { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #121212; color: #f8fafc; }
        html.dark-mode .app-container { background-color: #121212; box-shadow: none; }
        html.dark-mode .tab-content { background-color: #121212; }
        
        html.dark-mode .tabs { background-color: #1e1e1e; box-shadow: none; }
        html.dark-mode .tab.active { background-color: #3b0764; color: #d8b4fe; }
        html.dark-mode .tab { color: #94a3b8; }
        
        html.dark-mode .msg-ai, 
        html.dark-mode .typing-indicator { 
            background-color: #1e1e1e !important; 
            border-color: #333 !important; 
            color: #f8fafc !important; 
            box-shadow: none !important;
        }
        
        html.dark-mode .input-area { background-color: #121212; border-top-color: #333; }
        html.dark-mode .input-box { background-color: #1e1e1e; }
        html.dark-mode .input-box input { color: #f8fafc; }
        html.dark-mode .input-box:focus-within { background-color: #1e1e1e; border-color: #9333ea; }
        
        html.dark-mode .history-header { color: #f8fafc; }
        html.dark-mode .history-header span:last-child { background: #1e1e1e; color: #94a3b8; }
        
        html.dark-mode .history-card { background-color: #1e1e1e; border-color: #333; }
        html.dark-mode .history-card:hover { border-color: #9333ea; }
        html.dark-mode .history-card h3 { color: #f8fafc; }
        html.dark-mode .history-meta { border-top-color: #333; }
        
        html.dark-mode #attachment-preview { background-color: #3b0764 !important; border-color: #6b21a8 !important; color: #d8b4fe !important; }

        @media (max-width: 768px) {
            .header { padding: 20px 20px 5px 20px; }
            .back-btn span { display: none; }
            .title-area h1 { font-size: 22px; }
            .tab-content { padding: 30px 20px 120px 20px; }
            .msg-bubble { max-width: 90%; padding: 15px 20px; }
            .history-grid { grid-template-columns: 1fr; }
            .input-area { padding: 15px 20px; }
            .send-btn { width: 45px; height: 45px; font-size: 16px; }
        }
    </style>
</head>
<body>

    <div class="app-container">
        <div class="header">
            <div class="top-nav">
                <a href="farmer-dashboard.php" class="back-btn">
                    <i class="fa-solid fa-arrow-left"></i><span>Dashboard</span>
                </a>
                <div class="title-area">
                    <h1>Ask AI</h1>
                    <p>Get instant AI-powered farming advice</p>
                </div>
                <div class="header-actions">
                    <a href="alerts.php"><i class="fa-regular fa-bell"></i></a>
                </div>
            </div>

            <div class="ai-banner">
                <div class="ai-banner-left">
                    <div class="ai-icon"><i class="fa-solid fa-robot"></i><div class="status-dot"></div></div>
                    <div class="ai-banner-text">
                        <h3>AI Assistant Active</h3>
                        <p>Ready to help you optimize your farm</p>
                    </div>
                </div>
                <i class="fa-solid fa-wand-magic-sparkles" style="font-size: 24px; opacity: 0.8;"></i>
            </div>

            <div class="tabs">
                <div class="tab active" onclick="switchTab('chat')"><i class="fa-solid fa-sparkles"></i> Ask AI</div>
                <div class="tab" onclick="switchTab('history')"><i class="fa-solid fa-clock-rotate-left"></i> History</div>
            </div>
        </div>

        <div id="chat-tab" class="tab-content active">
            <div class="chat-area" id="chat-box">
                <div class="msg-bubble msg-ai">
                    <h4><i class="fa-solid fa-robot"></i> FarmWise AI</h4>
                    Hello! I'm your agricultural assistant. You can type a question, attach a photo of your crops, or use the microphone to ask me anything!
                </div>
            </div>
            
            <div class="chat-area" style="margin-top: -15px;">
                <div class="typing-indicator" id="typing"><span></span><span></span><span></span></div>
            </div>
        </div>

        <div id="history-tab" class="tab-content">
            <div class="history-container">
                <div class="history-header">
                    <span>Query History</span>
                    <span style="color: #64748b; font-weight: normal; font-size: 14px; background: white; padding: 4px 12px; border-radius: 20px; transition: 0.3s;"><?php echo count($history); ?> queries</span>
                </div>
                <div id="history-list" class="history-grid">
                    <?php if(count($history) == 0): ?>
                        <div style="grid-column: 1 / -1; text-align: center; color: #94a3b8; margin-top: 40px; padding: 40px; background: transparent; border-radius: 16px;">
                            <i class="fa-solid fa-inbox" style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px;"></i>
                            <p>No history yet. Start asking questions!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($history as $row): ?>
                            <div class="history-card">
                                <h3><?php echo htmlspecialchars(substr($row['user_query'], 0, 60)) . (strlen($row['user_query']) > 60 ? '...' : ''); ?></h3>
                                <p><?php echo htmlspecialchars($row['user_query']); ?></p>
                                <div class="history-meta">
                                    <span><i class="fa-regular fa-clock"></i> <?php echo date("M d, Y", strtotime($row['created_at'])); ?></span>
                                    <span><?php echo date("h:i A", strtotime($row['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="attachment-preview" style="display: none; position: absolute; bottom: 100px; left: 50%; transform: translateX(-50%); background: #f3e8ff; border: 1px solid #d8b4fe; padding: 10px 20px; border-radius: 20px; font-size: 14px; color: #9333ea; font-weight: 600; z-index: 10; align-items: center; gap: 10px; max-width: 900px; width: 90%; box-shadow: 0 4px 10px rgba(0,0,0,0.1); transition: 0.3s;">
            <i class="fa-solid fa-file-circle-check"></i>
            <span id="attachment-name">File attached</span>
            <i class="fa-solid fa-xmark" style="cursor: pointer; margin-left: auto;" onclick="clearAttachment()"></i>
        </div>

        <div class="input-area" id="input-area">
            <div class="input-wrapper-inner">
                
                <input type="file" id="file-upload" accept="image/*,audio/*" style="display: none;" onchange="handleFileSelect(event)">
                
                <i class="fa-solid fa-paperclip" onclick="document.getElementById('file-upload').click()" style="color: #94a3b8; font-size: 22px; cursor: pointer; transition: color 0.2s;" onmouseover="this.style.color='#9333ea'" onmouseout="this.style.color='#94a3b8'" title="Attach Photo or Audio"></i>
                
                <div class="input-box">
                    <input type="text" id="user-input" placeholder="Ask anything or attach a photo..." onkeypress="handleEnter(event)" autocomplete="off">
                    <i class="fa-solid fa-microphone" id="mic-btn" onclick="toggleRecording()" style="transition: color 0.3s;" title="Hold to Record Audio"></i>
                </div>
                
                <button class="send-btn" onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script>
        let selectedMediaFile = null;
        let mediaRecorder = null;
        let audioChunks = [];

        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            if(tabName === 'chat') {
                document.querySelectorAll('.tab')[0].classList.add('active');
                document.getElementById('chat-tab').classList.add('active');
                document.getElementById('input-area').style.display = 'flex';
                const chatTab = document.getElementById('chat-tab');
                chatTab.scrollTop = chatTab.scrollHeight;
            } else {
                document.querySelectorAll('.tab')[1].classList.add('active');
                document.getElementById('history-tab').classList.add('active');
                document.getElementById('input-area').style.display = 'none';
                document.getElementById('attachment-preview').style.display = 'none';
            }
        }

        function handleFileSelect(event) {
            if (event.target.files.length > 0) {
                selectedMediaFile = event.target.files[0];
                showAttachmentUI(selectedMediaFile.name);
            }
        }

        async function toggleRecording() {
            const micBtn = document.getElementById('mic-btn');
            
            // Stop recording if active
            if (mediaRecorder && mediaRecorder.state === "recording") {
                mediaRecorder.stop();
                micBtn.style.color = '#94a3b8'; // Reset Color
                micBtn.classList.remove('fa-beat-fade');
                return;
            }

            // Start recording
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                mediaRecorder.start();
                audioChunks = [];
                
                micBtn.style.color = '#ef4444'; // Red to show recording
                micBtn.classList.add('fa-beat-fade');

                mediaRecorder.addEventListener("dataavailable", event => {
                    audioChunks.push(event.data);
                });

                mediaRecorder.addEventListener("stop", () => {
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    selectedMediaFile = new File([audioBlob], "Voice_Note.webm", { type: 'audio/webm' });
                    showAttachmentUI("Voice Note Recorded");
                    stream.getTracks().forEach(track => track.stop()); // Stop mic usage in browser
                });
            } catch (err) {
                alert("Microphone access denied. Please allow permissions in your browser.");
            }
        }

        function showAttachmentUI(filename) {
            document.getElementById('attachment-name').innerText = filename;
            document.getElementById('attachment-preview').style.display = 'flex';
        }

        function clearAttachment() {
            selectedMediaFile = null;
            document.getElementById('file-upload').value = ""; 
            document.getElementById('attachment-preview').style.display = 'none';
        }

        function handleEnter(e) {
            if (e.key === 'Enter') { sendMessage(); }
        }

        function sendMessage() {
            const inputField = document.getElementById('user-input');
            const message = inputField.value.trim();
            
            if (message === '' && !selectedMediaFile) return;

            const chatBox = document.getElementById('chat-box');
            const typingIndicator = document.getElementById('typing');
            const chatTab = document.getElementById('chat-tab');

            // 1. Add User Message to UI
            let displayMessage = message;
            if (selectedMediaFile) {
                let icon = selectedMediaFile.type.includes('audio') ? 'fa-microphone-lines' : 'fa-image';
                displayMessage = `<i class="fa-solid ${icon}"></i> <strong>Media Attached</strong> <br>` + message;
            }
            
            chatBox.insertAdjacentHTML('beforeend', `<div class="msg-bubble msg-user">${displayMessage}</div>`);
            
            inputField.value = '';
            const fileToSend = selectedMediaFile; 
            clearAttachment();
            chatTab.scrollTop = chatTab.scrollHeight;

            // 2. Show Typing Indicator
            typingIndicator.style.display = 'block';
            chatTab.scrollTop = chatTab.scrollHeight;

            // 3. Prepare FormData for sending files + text
            const formData = new FormData();
            formData.append('query', message);
            if (fileToSend) {
                formData.append('media', fileToSend);
            }

            // 4. Send to Backend
            fetch('chat-api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                typingIndicator.style.display = 'none';
                chatBox.insertAdjacentHTML('beforeend', `
                    <div class="msg-bubble msg-ai">
                        <h4><i class="fa-solid fa-robot"></i> FarmWise AI</h4>
                        ${data.response}
                    </div>`);
                chatTab.scrollTop = chatTab.scrollHeight;
            })
            .catch(error => {
                typingIndicator.style.display = 'none';
                chatBox.insertAdjacentHTML('beforeend', `<div class="msg-bubble msg-ai" style="color:red;">Sorry, I encountered an error connecting to the AI. Please try again.</div>`);
                chatTab.scrollTop = chatTab.scrollHeight;
            });
        }
    </script>
</body>
</html>