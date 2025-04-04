<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$unread_count = getUnreadEmailCount($user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
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
                <h2>Recent Emails</h2>
                <?php
                global $db;
                $stmt = $db->prepare("SELECT e.id, e.subject, e.body, e.created_at, u.username 
                                     FROM emails e 
                                     JOIN users u ON e.sender_id = u.id 
                                     WHERE e.recipient_id = ? 
                                     ORDER BY e.created_at DESC 
                                     LIMIT 5");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo '<div class="email-preview">';
                        echo '<h4>' . htmlspecialchars($row['subject']) . '</h4>';
                        echo '<p>From: ' . htmlspecialchars($row['username']) . '</p>';
                        echo '<p>' . substr(htmlspecialchars($row['body']), 0, 100) . '...</p>';
                        echo '<small>' . formatDate($row['created_at']) . '</small>';
                        echo '</div>';
                    }
                } else {
                    echo '<p>No recent emails found.</p>';
                }
                ?>
            </div>
        </main>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/darkmode.js"></script>
</body>
</html>