<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize auth object
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$dark_mode = $_SESSION['dark_mode'] ?? false;
$success = '';
$error = '';

// Handle single email deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_email'])) {
    $email_id = (int)$_POST['email_id'];
    
    $stmt = $db->prepare("DELETE FROM emails WHERE id = ? AND sender_id = ?");
    $stmt->bind_param("ii", $email_id, $user_id);
    
    if ($stmt->execute()) {
        $success = "Email deleted successfully!";
    } else {
        $error = "Failed to delete email. Please try again.";
    }
}

// Handle bulk email deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (!empty($_POST['selected_emails'])) {
        $selected_ids = array_map('intval', $_POST['selected_emails']);
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        
        $query = "DELETE FROM emails WHERE id IN ($placeholders) AND sender_id = ?";
        $stmt = $db->prepare($query);
        
        // Bind parameters
        $params = array_merge($selected_ids, [$user_id]);
        $stmt->bind_param(str_repeat('i', count($selected_ids)).'i', ...$params);
        
        if ($stmt->execute()) {
            $success = count($selected_ids) . " emails deleted successfully!";
        } else {
            $error = "Failed to delete selected emails. Please try again.";
        }
    } else {
        $error = "No emails selected for deletion";
    }
}

// Fetch sent and scheduled emails - Fixed column name from scheduled_time to scheduled_at
$query = "
    SELECT e.id, e.sender_id, e.recipient_id, 
           e.subject, e.body, e.created_at, e.is_ai_generated,
           e.is_scheduled, e.scheduled_at, e.is_schedule_active, e.is_sent,
           u.username as recipient_name, u.email as recipient_email 
    FROM emails e
    JOIN users u ON e.recipient_id = u.id
    WHERE e.sender_id = ? AND (e.is_sent = TRUE OR e.is_scheduled = TRUE)
    ORDER BY 
        CASE 
            WHEN e.is_scheduled = TRUE AND e.is_sent = FALSE THEN 0
            ELSE 1
        END,
        e.created_at DESC
";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$sent_emails = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sent Emails | <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css" id="dark-mode-stylesheet" <?php echo !$dark_mode ? 'disabled' : ''; ?>>
    <style>
        :root {
            --primary-color: #1a73e8;
            --danger-color: #d93025;
            --success-color: #188038;
            --hover-bg: rgba(26, 115, 232, 0.08);
            --border-color: #dadce0;
            --text-primary: #202124;
            --text-secondary: #5f6368;
            --card-bg: #ffffff;
            --card-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
            --button-text: #ffffff;
        }
        
        .dark-mode {
            --primary-color: #8ab4f8;
            --danger-color: #f28b82;
            --success-color: #81c995;
            --hover-bg: rgba(138, 180, 248, 0.1);
            --border-color: #5f6368;
            --text-primary: #e8eaed;
            --text-secondary: #9aa0a6;
            --card-bg: #2d2d2d;
            --card-shadow: 0 1px 2px 0 rgba(0,0,0,0.3), 0 1px 3px 1px rgba(0,0,0,0.15);
            --button-text: #202124;
        }

        body {
            background-color: var(--card-bg);
            color: var(--text-primary);
            transition: background-color 0.3s, color 0.3s;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid transparent;
            min-width: 80px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--button-text) !important;
            border: 1px solid var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-color);
            opacity: 0.9;
            box-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .btn-icon {
            padding: 8px 12px;
            min-width: 40px;
            background-color: var(--card-bg);
            color: var(--primary-color) !important;
            border: 1px solid var(--border-color);
        }

        .btn-icon:hover {
            background-color: var(--hover-bg);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: var(--button-text) !important;
        }

        .btn-danger:hover {
            opacity: 0.9;
        }

        .email-actions {
            display: flex;
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--card-bg);
            gap: 10px;
            align-items: center;
        }

        .email-actions .btn {
            margin-right: 8px;
        }

        .icon {
            color: var(--primary-color);
            font-size: 18px;
            margin-right: 8px;
        }

        .dark-mode .icon {
            color: var(--primary-color);
        }

        .email-container {
            display: flex;
            height: calc(100vh - 120px);
        }

        .email-content {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background-color: var(--card-bg);
        }

        .email-list {
            margin-top: 10px;
        }

        .email-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s;
            background-color: var(--card-bg);
            position: relative;
        }

        .email-item:hover {
            background-color: var(--hover-bg);
            box-shadow: var(--card-shadow);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .ai-tag {
            background: var(--primary-color);
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.75em;
            margin-left: 8px;
        }

        .scheduled-tag {
            background: #fbbc04;
            color: #202124;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.75em;
            margin-left: 8px;
        }

        .email-checkbox {
            margin-right: 15px;
        }

        .email-sender {
            width: 200px;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .email-subject {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding-right: 15px;
        }

        .email-preview {
            flex: 2;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .email-status {
            display: flex;
            flex-direction: column;
            width: 120px;
        }

        .email-time {
            text-align: right;
            color: var(--text-secondary);
            font-size: 0.85em;
        }

        .scheduled-time {
            font-size: 0.85em;
            color: var(--text-secondary);
            margin-top: 3px;
        }

        .scheduled-inactive {
            opacity: 0.7;
        }

        .alert {
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .dark-mode .alert-success {
            background-color: #1e3a1e;
            color: #d4edda;
            border-color: #155724;
        }

        .dark-mode .alert-error {
            background-color: #3a1e1e;
            color: #f8d7da;
            border-color: #721c24;
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <?php include 'templates/header.php'; ?>
    
    <div class="container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>Sent Emails</h1>
                <div class="page-actions">
                    <button onclick="window.location.href='compose.php'" class="btn btn-primary">
                        <i class="icon-plus"></i> Compose New
                    </button>
                </div>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="email-container">
                <div class="email-content">
                    <div class="email-actions">
                        <button class="btn btn-icon" title="Refresh" onclick="window.location.reload()">
                            <i class="icon-refresh"></i> Refresh
                        </button>
                        <button class="btn btn-icon" id="select-all-btn" title="Select All">
                            <i class="icon-check-square"></i> Select All
                        </button>
                        <button class="btn btn-danger" id="delete-selected-btn" title="Delete Selected" disabled>
                            <i class="icon-trash"></i> Delete All
                        </button>
                    </div>
                    
                    <?php if (empty($sent_emails)): ?>
                        <div class="empty-state">
                            <i class="icon-paper-plane" style="font-size: 48px; opacity: 0.5;"></i>
                            <h3>No sent emails yet</h3>
                            <p>Your sent emails will appear here once you send messages</p>
                            <button onclick="window.location.href='compose.php'" class="btn btn-primary">
                                <i class="icon-edit"></i> Compose New Email
                            </button>
                        </div>
                    <?php else: ?>
                        <form id="bulk-delete-form" method="POST" action="sent.php">
                            <input type="hidden" name="bulk_delete" value="1">
                            <div class="email-list">
                                <?php foreach ($sent_emails as $email): ?>
                                    <div class="email-item <?php echo $email['is_scheduled'] && !$email['is_sent'] && !$email['is_schedule_active'] ? 'scheduled-inactive' : ''; ?>">
                                        <div class="email-checkbox">
                                            <input type="checkbox" name="selected_emails[]" value="<?= $email['id'] ?>" 
                                                   class="email-checkbox-input" onclick="event.stopPropagation()">
                                        </div>
                                        <div class="email-sender" onclick="window.location.href='view_email.php?id=<?= $email['id'] ?>&type=sent'">
                                            <?= htmlspecialchars($email['recipient_name']) ?>
                                            <?php if (!empty($email['is_ai_generated'])): ?>
                                                <span class="ai-tag">AI</span>
                                            <?php endif; ?>
                                            <?php if ($email['is_scheduled'] && !$email['is_sent']): ?>
                                                <span class="scheduled-tag">Scheduled</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="email-subject" onclick="window.location.href='view_email.php?id=<?= $email['id'] ?>&type=sent'">
                                            <?= htmlspecialchars($email['subject']) ?>
                                        </div>
                                        <div class="email-preview" onclick="window.location.href='view_email.php?id=<?= $email['id'] ?>&type=sent'">
                                            <?= htmlspecialchars(substr($email['body'], 0, 100)) ?>
                                            <?php if (strlen($email['body']) > 100): ?>...<?php endif; ?>
                                        </div>
                                        <div class="email-status">
                                            <?php if ($email['is_scheduled'] && !$email['is_sent']): ?>
                                                <div class="scheduled-time">
                                                    <?php if ($email['is_schedule_active']): ?>
                                                        Will send on <?= date('M j, g:i a', strtotime($email['scheduled_at'])) ?>
                                                    <?php else: ?>
                                                        Scheduled (inactive)
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="email-time">
                                                    <?= date('M j', strtotime($email['created_at'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <form method="POST" action="sent.php" style="display: inline;">
                                            <input type="hidden" name="email_id" value="<?= $email['id'] ?>">
                                            <button type="submit" name="delete_email" class="btn btn-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this email?')">
                                                <i class="icon-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Make email items clickable (except for specific elements)
        document.querySelectorAll('.email-item > div:not(.email-checkbox)').forEach(item => {
            item.addEventListener('click', function(e) {
                if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
                    return;
                }
                const emailId = this.closest('.email-item').querySelector('.email-checkbox-input').value;
                window.location.href = `view_email.php?id=${emailId}&type=sent`;
            });
        });

        // Dark mode toggle synchronization
        const darkModeToggle = document.getElementById('dark_mode_toggle');
        const darkModeStylesheet = document.getElementById('dark-mode-stylesheet');
        
        if (darkModeToggle) {
            darkModeToggle.addEventListener('change', function() {
                document.body.classList.toggle('dark-mode', this.checked);
                darkModeStylesheet.disabled = !this.checked;
                
                // Save preference via AJAX
                fetch('update_darkmode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ dark_mode: this.checked })
                });
            });
        }

        // Select all emails functionality
        const selectAllBtn = document.getElementById('select-all-btn');
        const deleteSelectedBtn = document.getElementById('delete-selected-btn');
        const checkboxes = document.querySelectorAll('.email-checkbox-input');
        
        selectAllBtn.addEventListener('click', function() {
            const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            updateDeleteButtonState();
        });

        // Update delete button state based on selections
        function updateDeleteButtonState() {
            const anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
            deleteSelectedBtn.disabled = !anyChecked;
        }

        // Add event listeners to all checkboxes
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateDeleteButtonState);
        });

        // Delete selected emails
        deleteSelectedBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete the selected emails?')) {
                document.getElementById('bulk-delete-form').submit();
            }
        });
    });
    </script>
</body>
</html>