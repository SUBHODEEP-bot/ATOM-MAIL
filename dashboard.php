<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$unread_count = getUnreadEmailCount($user_id);

// Get recent emails with categories
global $db;
$stmt = $db->prepare("SELECT e.id, e.subject, e.body, e.created_at, e.category, u.username 
                     FROM emails e 
                     JOIN users u ON e.sender_id = u.id 
                     WHERE e.recipient_id = ? 
                     ORDER BY e.created_at DESC 
                     LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_emails = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/ai-features.css">
    <?php if ($_SESSION['dark_mode']): ?>
        <link rel="stylesheet" href="assets/css/dark-mode.css">
    <?php endif; ?>
</head>
<body>
    <?php include 'templates/header.php'; ?>
    
    <div class="container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="content">
            <div class="dashboard-header">
                <h1>Welcome, <?php echo $_SESSION['full_name']; ?></h1>
                <p>You have <?php echo $unread_count; ?> unread emails</p>
            </div>
            
            <div class="dashboard-cards">
                <div class="card">
                    <h3>Compose Email</h3>
                    <p>Create a new email with AI assistance</p>
                    <a href="compose.php" class="btn btn-primary">Compose</a>
                </div>
                
                <div class="card">
                    <h3>Inbox</h3>
                    <p>View your incoming messages</p>
                    <a href="inbox.php" class="btn btn-secondary">Go to Inbox</a>
                </div>
                
                <div class="card">
                    <h3>AI Settings</h3>
                    <p>Configure your email assistant</p>
                    <a href="settings.php" class="btn btn-tertiary">Settings</a>
                </div>
            </div>
            
            <div class="recent-emails">
                <div class="section-header">
                    <h2>Recent Emails</h2>
                    <div class="category-filter">
                        <select id="categoryFilter">
                            <option value="all">All Categories</option>
                            <option value="Personal">Personal</option>
                            <option value="Work">Work</option>
                            <option value="Promotion">Promotion</option>
                            <option value="Social">Social</option>
                            <option value="Spam">Spam</option>
                        </select>
                    </div>
                </div>
                
                <?php if (count($recent_emails) > 0): ?>
                    <?php foreach ($recent_emails as $email): ?>
                        <div class="email-preview" data-category="<?php echo htmlspecialchars($email['category']); ?>">
                            <div class="email-header">
                                <h4><?php echo htmlspecialchars($email['subject']); ?></h4>
                                <span class="email-category"><?php echo htmlspecialchars($email['category']); ?></span>
                            </div>
                            <p class="email-sender">From: <?php echo htmlspecialchars($email['username']); ?></p>
                            <p class="email-preview-text"><?php echo substr(htmlspecialchars($email['body']), 0, 100); ?>...</p>
                            <div class="email-footer">
                                <small><?php echo formatDate($email['created_at']); ?></small>
                                <button class="summarize-btn" 
                                        data-emailid="<?php echo $email['id']; ?>" 
                                        data-content="<?php echo htmlspecialchars($email['body']); ?>">
                                    🧠 Summarize
                                </button>
                            </div>
                            <div class="email-summary" id="summary-<?php echo $email['id']; ?>"></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No recent emails found.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/darkmode.js"></script>
    <script src="assets/js/ai-features.js"></script>
</body>
</html>


<div class="card">
    <h3>Email Timeline</h3>
    <p>Visualize your conversations</p>
    <a href="views/timeline.php" class="btn btn-primary">
        🧭 View Timeline
    </a>
</div>