<?php
session_start();
require 'db.php';

// Force Login
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

// Initial fetch of posts to render on page load
try {
    $stmt = $conn->query("SELECT cp.*, u.email FROM community_posts cp 
                          JOIN users u ON cp.user_id = u.id 
                          ORDER BY cp.created_at DESC");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $posts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Forum - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        /* Global Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f1f5f9; height: 100vh; overflow: hidden; color: #1e293b; transition: background-color 0.3s, color 0.3s; }
        
        .app-container { width: 100%; max-width: 1400px; margin: 0 auto; height: 100vh; display: flex; flex-direction: column; }

        /* Header */
        .header { background: #1b8d44; color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 10; }
        .back-link { color: white; text-decoration: none; font-weight: 700; display: flex; align-items: center; gap: 10px; font-size: 18px; transition: 0.2s; }
        .back-link:hover { opacity: 0.8; }
        .logo-text { font-weight: 800; font-size: 20px; letter-spacing: 0.5px; }

        /* Notification Badge */
        .nav-icons { display: flex; gap: 20px; font-size: 22px; align-items: center; }
        .nav-icons a { color: white; text-decoration: none; position: relative; display: flex; align-items: center; transition: opacity 0.2s; }
        .nav-icons a:hover { opacity: 0.8; }
        .notification-badge { 
            position: absolute; top: -5px; right: -8px; 
            background-color: #ef4444; border-radius: 50%; 
            width: 18px; height: 18px; font-size: 11px; 
            display: flex; align-items: center; justify-content: center; 
            border: 2px solid #1b8d44; font-weight: bold; color: white;
        }

        /* Main Grid Layout */
        .main-content { display: grid; grid-template-columns: 420px 1fr; flex: 1; overflow: hidden; }

        /* --- LEFT SIDE: CREATE POST --- */
        .create-pane { background: white; border-right: 1px solid #e2e8f0; padding: 30px; overflow-y: auto; transition: 0.3s; }
        .create-pane h2 { font-size: 22px; font-weight: 800; margin-bottom: 25px; color: #1e293b; transition: color 0.3s;}
        
        .cat-group { display: flex; gap: 10px; margin-bottom: 20px; }
        .cat-btn { flex: 1; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; text-align: center; font-size: 13px; font-weight: 700; transition: 0.2s; color: #64748b; }
        .cat-btn.active { background: #2563eb; color: white; border-color: #2563eb; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2); }
        
        .field-label { font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px; display: block; transition: color 0.3s;}
        .input-box { width: 100%; padding: 14px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 20px; outline: none; font-size: 14px; background: #f8fafc; transition: 0.3s; color: inherit;}
        .input-box:focus { border-color: #1b8d44; background: white; }
        textarea.input-box { height: 150px; resize: none; line-height: 1.5; }
        
        .upload-area { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 30px 20px; text-align: center; color: #64748b; cursor: pointer; margin-bottom: 25px; transition: 0.3s; }
        .upload-area:hover { border-color: #1b8d44; background: #f0fdf4; color: #1b8d44; }
        .upload-area i { font-size: 32px; margin-bottom: 10px; }

        .pub-btn { width: 100%; background: #1b8d44; color: white; border: none; padding: 16px; border-radius: 12px; font-weight: 800; font-size: 16px; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(27, 141, 68, 0.3); }
        .pub-btn:hover { background: #156d35; transform: translateY(-2px); }
        .pub-btn:disabled { background: #94a3b8; cursor: not-allowed; box-shadow: none; transform: none; }

        .guidelines-box { background: #f0fdf4; border-left: 4px solid #1b8d44; padding: 20px; border-radius: 12px; margin-top: 30px; font-size: 13px; color: #166534; line-height: 1.5; transition: 0.3s;}

        /* --- RIGHT SIDE: FEED --- */
        .feed-pane { padding: 40px; overflow-y: auto; background: #f8fafc; position: relative; transition: 0.3s; }
        .feed-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .feed-header h3 { font-size: 20px; font-weight: 800; transition: color 0.3s;}
        .live-indicator { font-size: 12px; color: #22c55e; font-weight: 700; display: flex; align-items: center; gap: 6px; }
        .live-dot { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; animation: pulse 1.5s infinite; }

        .post-card { background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; transition: 0.3s; }
        .post-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.04); }
        
        .post-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .user-meta { display: flex; align-items: center; gap: 12px; }
        .avatar { width: 45px; height: 45px; border-radius: 50%; background: #1b8d44; color: white; display: flex; justify-content: center; align-items: center; font-weight: 800; font-size: 18px; box-shadow: 0 4px 10px rgba(27,141,68,0.2); text-transform: uppercase; }
        .user-details h4 { font-size: 15px; font-weight: 700; color: #1e293b; transition: color 0.3s;}
        .user-details small { color: #94a3b8; font-weight: 600; }
        
        .post-tag { font-size: 11px; padding: 5px 12px; border-radius: 30px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .tag-Question { background: #dbeafe; color: #1e40af; }
        .tag-Tip { background: #fef9c3; color: #854d0e; }
        .tag-Success { background: #dcfce7; color: #166534; }
        
        .post-title { font-size: 19px; font-weight: 800; margin-bottom: 12px; color: #1e293b; line-height: 1.4; transition: color 0.3s;}
        .post-text { color: #475569; line-height: 1.6; font-size: 15px; margin-bottom: 20px; white-space: pre-line; transition: color 0.3s;}
        .post-img { width: 100%; max-height: 450px; object-fit: cover; border-radius: 16px; margin-bottom: 20px; border: 1px solid #f1f5f9; transition: 0.3s;}
        
        .post-actions { display: flex; gap: 25px; border-top: 1px solid #f1f5f9; padding-top: 18px; color: #64748b; font-size: 14px; font-weight: 600; transition: border-color 0.3s;}
        .action-item { cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .action-item:hover { color: #1b8d44; }
        .action-item i { font-size: 18px; }

        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #121212; color: #f8fafc; }
        html.dark-mode .app-container { background-color: #121212; }
        
        html.dark-mode .create-pane,
        html.dark-mode .feed-pane { background-color: #121212 !important; border-color: #333 !important; }

        html.dark-mode .post-card { background-color: #1e1e1e !important; border-color: #333 !important; box-shadow: none !important; }
        html.dark-mode .post-card:hover { border-color: #1b8d44 !important; }

        html.dark-mode h2, html.dark-mode h3, 
        html.dark-mode .post-title, 
        html.dark-mode .user-details h4 { color: #f8fafc !important; }
        
        html.dark-mode .post-text, 
        html.dark-mode .field-label { color: #94a3b8 !important; }

        html.dark-mode .input-box { background-color: #1e1e1e !important; border-color: #333 !important; color: #f8fafc !important; }
        html.dark-mode .input-box:focus { border-color: #1b8d44 !important; }
        
        html.dark-mode .cat-btn { background-color: #1e1e1e !important; border-color: #333 !important; color: #94a3b8 !important; }
        html.dark-mode .cat-btn.active { background-color: #2563eb !important; color: white !important; border-color: #2563eb !important; }

        html.dark-mode .upload-area { border-color: #333 !important; }
        html.dark-mode .upload-area:hover { border-color: #1b8d44 !important; background-color: #064e3b !important; }

        html.dark-mode .guidelines-box { background-color: #064e3b !important; border-color: #10b981 !important; color: #6ee7b7 !important; }
        
        html.dark-mode .post-actions { border-top-color: #333 !important; }
        html.dark-mode .post-img { border-color: #333 !important; }

        /* Responsive Mobile */
        @media (max-width: 1024px) {
            .main-content { grid-template-columns: 1fr; }
            .create-pane { display: none; }
            .header { padding: 15px 20px; }
            .feed-pane { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <div class="header">
        <a href="farmer-dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
        <div class="logo-text">FarmWise Community</div>
        <div class="nav-icons">
            <a href="alerts.php">
                <i class="fa-regular fa-bell">
                    <div class="notification-badge" id="unread-count-badge" style="display: <?php echo ($unread_count > 0) ? 'flex' : 'none'; ?>;">
                        <?php echo $unread_count; ?>
                    </div>
                </i>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="create-pane">
            <h2>Start a Discussion</h2>
            
            <label class="field-label">Post Category</label>
            <div class="cat-group">
                <button class="cat-btn active" onclick="setCat('Question', this)">Question</button>
                <button class="cat-btn" onclick="setCat('Tip', this)">Tip</button>
                <button class="cat-btn" onclick="setCat('Success', this)">Success</button>
            </div>
            
            <label class="field-label">Headline</label>
            <input type="text" id="pTitle" class="input-box" placeholder="What's on your mind?">
            
            <label class="field-label">Description</label>
            <textarea id="pContent" class="input-box" placeholder="Describe your situation, share a tip, or tell your story..."></textarea>
            
            <label class="field-label">Attach Media</label>
            <div class="upload-area" onclick="document.getElementById('pImg').click()" id="uploadZone">
                <i class="fa-solid fa-cloud-arrow-up"></i><br>
                <span id="uploadText">Click to add farm photos</span>
                <input type="file" id="pImg" style="display:none" accept="image/*" onchange="updateUploadText()">
            </div>
            
            <button class="pub-btn" id="pubBtn" onclick="publishPost()">
                <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i> Publish to Community
            </button>
            
            <div class="guidelines-box">
                <strong>Community Tip:</strong> Posts with clear photos get 4x more helpful responses from other farmers!
            </div>
        </div>

        <div class="feed-pane">
            <div class="feed-header">
                <h3>Latest Discussions</h3>
                <div class="live-indicator">
                    <div class="live-dot"></div> Live Updates Enabled
                </div>
            </div>

            <div id="posts-container">
                <?php if(empty($posts)): ?>
                    <div style="text-align:center; padding:100px 0; color:#94a3b8;">
                        <i class="fa-solid fa-comments" style="font-size:60px; margin-bottom:20px; opacity:0.3;"></i>
                        <p>No posts yet. Be the first to start the conversation!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($posts as $p): ?>
                        <div class="post-card">
                            <div class="post-header">
                                <div class="user-meta">
                                    <div class="avatar"><?php echo substr($p['email'], 0, 1); ?></div>
                                    <div class="user-details">
                                        <h4><?php echo htmlspecialchars(explode('@', $p['email'])[0]); ?></h4>
                                        <small><i class="fa-regular fa-clock"></i> <?php echo date('M d, Y • h:i A', strtotime($p['created_at'])); ?></small>
                                    </div>
                                </div>
                                <span class="post-tag tag-<?php echo $p['category']; ?>"><?php echo $p['category']; ?></span>
                            </div>

                            <h3 class="post-title"><?php echo htmlspecialchars($p['title']); ?></h3>
                            <p class="post-text"><?php echo htmlspecialchars($p['content']); ?></p>

                            <?php if(!empty($p['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($p['image_path']); ?>" class="post-img" loading="lazy">
                            <?php endif; ?>

                            <div class="post-actions">
                                <div class="action-item"><i class="fa-regular fa-thumbs-up"></i> <?php echo $p['likes']; ?> Likes</div>
                                <div class="action-item"><i class="fa-regular fa-comment"></i> Comment</div>
                                <div class="action-item"><i class="fa-solid fa-share-nodes"></i> Share</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    let activeCategory = 'Question';

    // 1. Toggle Post Categories
    function setCat(cat, btn) {
        activeCategory = cat;
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    // 2. Change text when an image file is selected
    function updateUploadText() {
        const file = document.getElementById('pImg').files[0];
        if(file) {
            document.getElementById('uploadText').innerText = "Selected: " + file.name;
            document.getElementById('uploadZone').style.borderColor = "#1b8d44";
        }
    }

    // 3. Publish the post to the backend API
    function publishPost() {
        const title = document.getElementById('pTitle').value.trim();
        const content = document.getElementById('pContent').value.trim();
        const img = document.getElementById('pImg').files[0];
        const btn = document.getElementById('pubBtn');

        if(!title || !content) {
            alert("Please enter both a headline and a description.");
            return;
        }

        // Show loading state
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Publishing...';

        const formData = new FormData();
        formData.append('category', activeCategory);
        formData.append('title', title);
        formData.append('content', content);
        if(img) {
            formData.append('image', img);
        }

        // Send data to the new API file
        fetch('community-api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                // Clear fields
                document.getElementById('pTitle').value = "";
                document.getElementById('pContent').value = "";
                document.getElementById('pImg').value = "";
                document.getElementById('uploadText').innerText = "Click to add farm photos";
                document.getElementById('uploadZone').style.borderColor = "#cbd5e1";
                
                // Refresh the feed immediately
                fetchFeed();
            } else {
                alert(data.error || "Failed to publish post.");
            }
        })
        .catch(err => {
            console.error(err);
            alert("Connection error. Try again.");
        })
        .finally(() => {
            // Restore button
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i> Publish to Community';
        });
    }

    // 4. REAL-TIME POLLING: Auto-updates the feed & Notification Badge
    function fetchFeed() {
        // Fetch new posts
        fetch('community.php')
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.getElementById('posts-container').innerHTML;
                document.getElementById('posts-container').innerHTML = newContent;
            })
            .catch(e => console.warn("Live feed update paused."));

        // Fetch new alerts for the badge
        fetch('check-alerts.php')
            .then(res => res.json())
            .then(data => {
                const badge = document.getElementById('unread-count-badge');
                if (data.count !== undefined) {
                    if (data.count > 0) {
                        badge.innerText = data.count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }).catch(e => console.log('Alert check silent failure'));
    }

    setInterval(fetchFeed, 10000); // Check for new posts & alerts every 10s
</script>

</body>
</html>