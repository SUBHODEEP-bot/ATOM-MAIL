<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'ai/gemini-integration.php';

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Function to send email (added at the top)
function send_email($sender_id, $recipient_id, $subject, $body) {
    global $db;
    
    // Get sender and recipient details
    $sender_stmt = $db->prepare("SELECT email, username FROM users WHERE id = ?");
    $sender_stmt->bind_param("i", $sender_id);
    $sender_stmt->execute();
    $sender = $sender_stmt->get_result()->fetch_assoc();
    
    $recipient_stmt = $db->prepare("SELECT email, username FROM users WHERE id = ?");
    $recipient_stmt->bind_param("i", $recipient_id);
    $recipient_stmt->execute();
    $recipient = $recipient_stmt->get_result()->fetch_assoc();
    
    // Email headers
    $headers = "From: " . $sender['email'] . "\r\n";
    $headers .= "Reply-To: " . $sender['email'] . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Create HTML email
    $html_message = "
    <html>
    <head>
        <title>{$subject}</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .email-container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .email-footer { margin-top: 20px; font-size: 0.9em; color: #666; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <p>Hello {$recipient['username']},</p>
            <div>" . nl2br(htmlspecialchars($body)) . "</div>
            <div class='email-footer'>
                <p>Best regards,<br>{$sender['username']}</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email
    $mail_sent = mail($recipient['email'], $subject, $html_message, $headers);
    
    // Update database if email was sent
    if ($mail_sent) {
        $update_stmt = $db->prepare("UPDATE emails SET is_sent = 1, sent_at = NOW() 
                                   WHERE sender_id = ? AND recipient_id = ? AND subject = ?
                                   ORDER BY created_at DESC LIMIT 1");
        $update_stmt->bind_param("iis", $sender_id, $recipient_id, $subject);
        $update_stmt->execute();
        return true;
    }
    
    return false;
}

// Main application logic continues...
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$available_users = [];
$ai_response = '';
$is_ai_generated = 0;

// Get list of available users (for suggestions)
global $db;
$users_stmt = $db->prepare("SELECT username, email FROM users WHERE id != ?");
$users_stmt->bind_param("i", $user_id);
$users_stmt->execute();
$users_result = $users_stmt->get_result();

while ($user = $users_result->fetch_assoc()) {
    $available_users[] = $user;
}

// Handle AI generation requests FIRST
if (isset($_POST['generate_with_ai'])) {
    $prompt = sanitizeInput($_POST['ai_prompt']);
    $style = sanitizeInput($_POST['writing_style']);
    
    $ai_response = $gemini->generateEmailResponse($prompt, '', $style, $user_id);
    
    if (!$ai_response) {
        $error = "Failed to generate AI response. Please try again.";
    } else {
        $_POST['body'] = $ai_response;
        $is_ai_generated = 1;
    }
}

// Handle email improvement
if (isset($_POST['improve_email'])) {
    $draft = sanitizeInput($_POST['body']);
    $style = sanitizeInput($_POST['writing_style']);
    
    $improved = $gemini->improveEmailDraft($draft, $style, $user_id);
    
    if ($improved) {
        $_POST['body'] = $improved;
        $is_ai_generated = 1;
        $success = "Email improved successfully!";
    } else {
        $error = "Failed to improve email. Please try again.";
    }
}

// Handle final form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['generate_with_ai']) && !isset($_POST['improve_email'])) {
    $recipient = sanitizeInput($_POST['recipient']);
    $subject = sanitizeInput($_POST['subject']);
    $body = sanitizeInput($_POST['body']);
    $is_ai_generated = isset($_POST['is_ai_generated']) ? 1 : 0;
    $is_scheduled = isset($_POST['enable_schedule']) ? 1 : 0;
    $scheduled_at = $is_scheduled ? sanitizeInput($_POST['scheduled_at']) : null;
    $is_schedule_active = $is_scheduled && isset($_POST['activate_schedule']) ? 1 : 0;
    
    // Get recipient ID
    $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)");
    $stmt->bind_param("ss", $recipient, $recipient);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $recipient_id = $result->fetch_assoc()['id'];
        
        // Insert email into database
        $stmt = $db->prepare("INSERT INTO emails (sender_id, recipient_id, subject, body, is_ai_generated, is_scheduled, scheduled_at, is_schedule_active) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissiisi", $user_id, $recipient_id, $subject, $body, $is_ai_generated, $is_scheduled, $scheduled_at, $is_schedule_active);
        
        if ($stmt->execute()) {
            if ($is_scheduled && $is_schedule_active) {
                $success = "Email scheduled successfully! It will be sent on " . date('F j, Y, g:i a', strtotime($scheduled_at));
            } else {
                // Send immediately if not scheduled
                if (send_email($user_id, $recipient_id, $subject, $body)) {
                    $success = "Email sent successfully!";
                } else {
                    $error = "Email saved but failed to send. Please try again.";
                }
            }
            // Clear form
            $_POST = array();
            $ai_response = '';
        } else {
            $error = "Failed to send email. Please try again.";
        }
    } else {
        $error = "Recipient not found. Available users: ";
        foreach ($available_users as $user) {
            $error .= $user['username'] . " (" . $user['email'] . "), ";
        }
        $error = rtrim($error, ', ');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Email | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if ($_SESSION['dark_mode']): ?>
        <link rel="stylesheet" href="assets/css/dark-mode.css">
    <?php endif; ?>
    <style>
        .ai-generated-response {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #4285f4;
            border-radius: 4px;
        }
        .use-ai-response {
            margin-top: 10px;
        }
        .language-hint {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        #schedule_fields {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        .schedule-active-toggle {
            margin-top: 10px;
        }
        <?php if ($_SESSION['dark_mode']): ?>
            #schedule_fields {
                background: #333;
                border: 1px solid #444;
            }
            .ai-generated-response {
                background: #2d2d2d;
                border-left-color: #5a9cf8;
            }
        <?php endif; ?>
    </style>
</head>
<body>
    <?php include 'templates/header.php'; ?>
    
    <div class="container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="content">
            <div class="compose-header">
                <h1>Compose Email</h1>
                <p>Write a new email with AI assistance</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="compose.php" class="compose-form" id="emailForm">
                <div class="form-group">
                    <label for="recipient">To:</label>
                    <input type="text" id="recipient" name="recipient" required 
                           list="userSuggestions" value="<?php echo isset($_POST['recipient']) ? htmlspecialchars($_POST['recipient']) : ''; ?>">
                    <datalist id="userSuggestions">
                        <?php foreach ($available_users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['username']); ?>">
                            <option value="<?php echo htmlspecialchars($user['email']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <small class="hint">Start typing to see available users</small>
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject:</label>
                    <input type="text" id="subject" name="subject" required 
                           value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="body">Message:</label>
                    <textarea id="body" name="body" rows="10" required><?php echo isset($_POST['body']) ? htmlspecialchars($_POST['body']) : ''; ?></textarea>
                </div>
                
                <!-- Schedule Email Section -->
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="enable_schedule" id="enable_schedule" 
                               <?php echo isset($_POST['enable_schedule']) ? 'checked' : ''; ?>> Schedule Email
                    </label>
                </div>
                
                <div id="schedule_fields">
                    <div class="form-group">
                        <label for="scheduled_at">Schedule Date & Time:</label>
                        <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="form-control"
                               value="<?php echo isset($_POST['scheduled_at']) ? htmlspecialchars($_POST['scheduled_at']) : ''; ?>">
                    </div>
                    
                    <div class="form-group schedule-active-toggle">
                        <label>
                            <input type="checkbox" name="activate_schedule" 
                                   <?php echo !isset($_POST['enable_schedule']) || isset($_POST['activate_schedule']) ? 'checked' : ''; ?>> Activate Schedule
                        </label>
                        <small class="hint">Email will only be sent if this is checked</small>
                    </div>
                </div>
                
                <div class="ai-assistant-section">
                    <h3>AI Assistant</h3>
                    
                    <div class="language-hint">
                        <?php
                        $stmt = $db->prepare("SELECT preferred_language FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                        $current_language = $user['preferred_language'] ?? 'en';
                        
                        $language_names = [
                            'en' => 'English',
                            'bn' => 'বাংলা',
                            'hi' => 'हिन्दी',
                            'es' => 'Español',
                            'fr' => 'Français'
                        ];
                        
                        echo "AI will generate emails in: <strong>" . ($language_names[$current_language] ?? 'English') . "</strong>";
                        ?>
                        <br>
                        <small>Change language in <a href="settings.php">Settings</a></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="writing_style">Writing Style:</label>
                        <select id="writing_style" name="writing_style">
                            <option value="professional" <?php echo (isset($_POST['writing_style']) && $_POST['writing_style'] === 'professional') ? 'selected' : ''; ?>>Professional</option>
                            <option value="friendly" <?php echo (isset($_POST['writing_style']) && $_POST['writing_style'] === 'friendly') ? 'selected' : ''; ?>>Friendly</option>
                            <option value="formal" <?php echo (isset($_POST['writing_style']) && $_POST['writing_style'] === 'formal') ? 'selected' : ''; ?>>Formal</option>
                            <option value="casual" <?php echo (isset($_POST['writing_style']) && $_POST['writing_style'] === 'casual') ? 'selected' : ''; ?>>Casual</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ai_prompt">Generate Email From Prompt:</label>
                        <textarea id="ai_prompt" name="ai_prompt" rows="3" 
                                  placeholder="E.g., 'Write a follow-up email about the project deadline'"><?php echo isset($_POST['ai_prompt']) ? htmlspecialchars($_POST['ai_prompt']) : ''; ?></textarea>
                        <button type="submit" name="generate_with_ai" class="btn btn-secondary">Generate with AI</button>
                    </div>
                    
                    <?php if (isset($_POST['generate_with_ai']) && $ai_response): ?>
                        <div class="ai-generated-response">
                            <h4>AI Generated Response:</h4>
                            <p><?php echo nl2br(htmlspecialchars($ai_response)); ?></p>
                            <button type="button" class="btn btn-small use-ai-response" 
                                    onclick="document.getElementById('body').value = `<?php echo addslashes($ai_response); ?>`">
                                Use This
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <button type="submit" name="improve_email" class="btn btn-tertiary">Improve Current Email</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <input type="checkbox" id="is_ai_generated" name="is_ai_generated" value="1" 
                           <?php echo isset($is_ai_generated) && $is_ai_generated ? 'checked' : ''; ?>>
                    <label for="is_ai_generated">Mark as AI-generated</label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" name="send_email">Send Email</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='inbox.php'">Cancel</button>
                </div>
            </form>
        </main>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/darkmode.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enhanced recipient validation
        document.getElementById('emailForm').addEventListener('submit', function(e) {
            if (e.submitter && e.submitter.name === 'send_email') {
                const recipientInput = document.getElementById('recipient');
                const availableUsers = <?php echo json_encode($available_users); ?>;
                let valid = false;
                
                const recipientValue = recipientInput.value.trim().toLowerCase();
                for (const user of availableUsers) {
                    if (user.username.toLowerCase() === recipientValue || 
                        user.email.toLowerCase() === recipientValue) {
                        valid = true;
                        break;
                    }
                }
                
                if (!valid) {
                    e.preventDefault();
                    alert('Please select a valid recipient from the suggestions');
                    recipientInput.focus();
                }

                // Validate scheduled time if enabled
                const isScheduled = document.getElementById('enable_schedule').checked;
                if (isScheduled) {
                    const scheduledTime = document.getElementById('scheduled_at').value;
                    if (!scheduledTime) {
                        e.preventDefault();
                        alert('Please select a schedule date and time');
                        document.getElementById('scheduled_at').focus();
                        return;
                    }

                    const now = new Date();
                    const scheduledDate = new Date(scheduledTime);
                    if (scheduledDate <= now) {
                        e.preventDefault();
                        alert('Scheduled time must be in the future');
                        document.getElementById('scheduled_at').focus();
                        return;
                    }
                }
            }
        });

        // Auto-focus prompt field when Generate button is clicked
        const generateBtn = document.querySelector('button[name="generate_with_ai"]');
        if (generateBtn) {
            generateBtn.addEventListener('click', function() {
                const promptField = document.getElementById('ai_prompt');
                if (promptField && !promptField.value.trim()) {
                    promptField.focus();
                }
            });
        }

        // Show/hide schedule fields
        const enableSchedule = document.getElementById('enable_schedule');
        const scheduleFields = document.getElementById('schedule_fields');
        
        if (enableSchedule && scheduleFields) {
            if (enableSchedule.checked) {
                scheduleFields.style.display = 'block';
            }
            
            enableSchedule.addEventListener('change', function() {
                scheduleFields.style.display = this.checked ? 'block' : 'none';
            });
        }

        // Set minimum datetime for schedule (current time)
        const now = new Date();
        const timezoneOffset = now.getTimezoneOffset() * 60000;
        const localISOTime = new Date(now - timezoneOffset).toISOString().slice(0, 16);
        document.getElementById('scheduled_at').min = localISOTime;
    });
    </script>
</body>
</html>