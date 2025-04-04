<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$dark_mode = $_SESSION['dark_mode'] ?? false;
$success = '';
$error = '';

// Get email details
if (!isset($_GET['id'])) {
    header("Location: sent.php");
    exit();
}

$email_id = $_GET['id'];

// Verify the email belongs to the current user and is scheduled
$stmt = $db->prepare("SELECT * FROM emails WHERE id = ? AND sender_id = ? AND is_scheduled = TRUE AND is_sent = FALSE");
$stmt->bind_param("ii", $email_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$email = $result->fetch_assoc();

if (!$email) {
    header("Location: sent.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scheduled_time = sanitizeInput($_POST['scheduled_time']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate scheduled time
    if (strtotime($scheduled_time) <= time()) {
        $error = "Scheduled time must be in the future";
    } else {
        $update_stmt = $db->prepare("UPDATE emails SET scheduled_time = ?, is_schedule_active = ? WHERE id = ?");
        $update_stmt->bind_param("sii", $scheduled_time, $is_active, $email_id);
        
        if ($update_stmt->execute()) {
            $success = "Schedule updated successfully!";
            // Refresh email data
            $stmt->execute();
            $result = $stmt->get_result();
            $email = $result->fetch_assoc();
        } else {
            $error = "Failed to update schedule. Please try again.";
        }
    }
}

include 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Scheduled Email | <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css" id="dark-mode-stylesheet" <?php echo !$dark_mode ? 'disabled' : ''; ?>>
    <style>
        .edit-schedule-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .schedule-form {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--card-bg);
            color: var(--text-primary);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .email-preview {
            background-color: var(--hover-bg);
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .email-preview h4 {
            margin-top: 0;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <?php include 'templates/header.php'; ?>
    
    <div class="container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="content">
            <div class="edit-schedule-container">
                <h1>Edit Scheduled Email</h1>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="email-preview">
                    <h4>Email Preview</h4>
                    <p><strong>To:</strong> <?php echo htmlspecialchars($email['recipient_name']); ?></p>
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($email['subject']); ?></p>
                    <p><strong>Message:</strong> <?php echo nl2br(htmlspecialchars(substr($email['body'], 0, 200))); ?>...</p>
                </div>
                
                <form method="POST" class="schedule-form">
                    <div class="form-group">
                        <label for="scheduled_time">New Scheduled Time:</label>
                        <input type="datetime-local" id="scheduled_time" name="scheduled_time" 
                               class="form-control" required
                               value="<?php echo date('Y-m-d\TH:i', strtotime($email['scheduled_time'])); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" <?php echo $email['is_schedule_active'] ? 'checked' : ''; ?>>
                            Activate this schedule
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='sent.php'">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Schedule</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set minimum datetime for schedule (current time)
        const now = new Date();
        const timezoneOffset = now.getTimezoneOffset() * 60000;
        const localISOTime = new Date(now - timezoneOffset).toISOString().slice(0, 16);
        document.getElementById('scheduled_time').min = localISOTime;
        
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
    });
    </script>
</body>
</html>