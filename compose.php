<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'ai/gemini-integration.php';

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

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
    
    $ai_response = $gemini->generateEmailResponse($prompt, '', $style);
    
    if (!$ai_response) {
        $error = "Failed to generate AI response. Please try again.";
    } else {
        $_POST['body'] = $ai_response; // Pre-fill the body with AI response
        $is_ai_generated = 1;
    }
}

// Handle email improvement
if (isset($_POST['improve_email'])) {
    $draft = sanitizeInput($_POST['body']);
    $style = sanitizeInput($_POST['writing_style']);
    
    $improved = $gemini->improveEmailDraft($draft, $style);
    
    if ($improved) {
        $_POST['body'] = $improved;
        $is_ai_generated = 1;
        $success = "Email improved successfully!";
    } else {
        $error = "Failed to improve email. Please try again.";
    }
}

// Handle final form submission (only if not an AI action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['generate_with_ai']) && !isset($_POST['improve_email'])) {
    $recipient = sanitizeInput($_POST['recipient']);
    $subject = sanitizeInput($_POST['subject']);
    $body = sanitizeInput($_POST['body']);
    $is_ai_generated = isset($_POST['is_ai_generated']) ? 1 : 0;
    
    // Get recipient ID with case-insensitive search
    $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)");
    $stmt->bind_param("ss", $recipient, $recipient);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $recipient_id = $result->fetch_assoc()['id'];
        
        // Insert email
        $stmt = $db->prepare("INSERT INTO emails (sender_id, recipient_id, subject, body, is_ai_generated) 
                             VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissi", $user_id, $recipient_id, $subject, $body, $is_ai_generated);
        
        if ($stmt->execute()) {
            $success = "Email sent successfully!";
            // Clear form after successful send
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
                
                <div class="ai-assistant-section">
                    <h3>AI Assistant</h3>
                    
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
            // Only validate if it's the final submission
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
    });
    </script>
</body>
</html>