<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$unread_count = getUnreadEmailCount($user_id);

// Mark email as read if viewing single email
if (isset($_GET['id'])) {
    $email_id = intval($_GET['id']);
    global $db;
    $stmt = $db->prepare("UPDATE emails SET is_read = TRUE WHERE id = ? AND recipient_id = ?");
    $stmt->bind_param("ii", $email_id, $user_id);
    $stmt->execute();
}

// Get emails
global $db;
$stmt = $db->prepare("SELECT e.id, e.subject, e.body, e.is_read, e.is_important, e.created_at, u.username 
                     FROM emails e 
                     JOIN users u ON e.sender_id = u.id 
                     WHERE e.recipient_id = ? 
                     ORDER BY e.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$emails = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox | <?php echo SITE_NAME; ?></title>
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
            <div class="inbox-header">
                <h1>Inbox</h1>
                <p><?php echo $unread_count; ?> unread messages</p>
            </div>
            
            <div class="email-list">
                <?php if ($emails->num_rows > 0): ?>
                    <?php while ($email = $emails->fetch_assoc()): ?>
                        <div class="email-item <?php echo $email['is_read'] ? '' : 'unread'; ?> <?php echo $email['is_important'] ? 'important' : ''; ?>">
                            <div class="email-sender"><?php echo htmlspecialchars($email['username']); ?></div>
                            <div class="email-subject">
                                <a href="inbox.php?id=<?php echo $email['id']; ?>">
                                    <?php echo htmlspecialchars($email['subject']); ?>
                                </a>
                            </div>
                            <div class="email-preview">
                                <?php echo substr(htmlspecialchars($email['body']), 0, 100); ?>...
                            </div>
                            <div class="email-date"><?php echo formatDate($email['created_at']); ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Your inbox is empty.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/darkmode.js"></script>
</body>
</html>