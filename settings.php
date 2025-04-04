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

// Get current settings
global $db;
$stmt = $db->prepare("SELECT * FROM ai_settings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$settings = $result->num_rows > 0 ? $result->fetch_assoc() : null;

// Get user's current language preference
$stmt = $db->prepare("SELECT preferred_language, dark_mode FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $writing_style = sanitizeInput($_POST['writing_style']);
    $response_speed = sanitizeInput($_POST['response_speed']);
    $auto_reply_enabled = isset($_POST['auto_reply_enabled']) ? 1 : 0;
    $auto_reply_message = sanitizeInput($_POST['auto_reply_message']);
    $dark_mode = isset($_POST['dark_mode']) ? 1 : 0;
    $preferred_language = sanitizeInput($_POST['preferred_language']);
    
    // Update session variables
    $_SESSION['dark_mode'] = $dark_mode;
    $_SESSION['preferred_language'] = $preferred_language;
    
    // Update user settings in database
    $stmt = $db->prepare("UPDATE users SET dark_mode = ?, preferred_language = ? WHERE id = ?");
    $stmt->bind_param("isi", $dark_mode, $preferred_language, $user_id);
    $stmt->execute();
    
    if ($settings) {
        // Update existing AI settings
        $stmt = $db->prepare("UPDATE ai_settings 
                             SET writing_style = ?, response_speed = ?, auto_reply_enabled = ?, auto_reply_message = ?
                             WHERE user_id = ?");
        $stmt->bind_param("ssisi", $writing_style, $response_speed, $auto_reply_enabled, $auto_reply_message, $user_id);
    } else {
        // Insert new AI settings
        $stmt = $db->prepare("INSERT INTO ai_settings 
                             (user_id, writing_style, response_speed, auto_reply_enabled, auto_reply_message)
                             VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issis", $user_id, $writing_style, $response_speed, $auto_reply_enabled, $auto_reply_message);
    }
    
    if ($stmt->execute()) {
        $success = "Settings saved successfully!";
    } else {
        $error = "Failed to save settings. Please try again.";
    }
    
    // Refresh settings
    $stmt = $db->prepare("SELECT * FROM ai_settings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = $result->fetch_assoc();
}

// Generate auto-reply suggestion if empty
if (isset($_POST['generate_auto_reply']) && empty($_POST['auto_reply_message'])) {
    $sample_email = "I'm currently out of office and will respond to your email when I return.";
    $generated_reply = $gemini->generateAutoReply($sample_email, $user_id);
    $_POST['auto_reply_message'] = $generated_reply;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | <?php echo SITE_NAME; ?></title>
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
            <div class="settings-header">
                <h1>Settings</h1>
                <p>Configure your account and AI preferences</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="settings.php" class="settings-form">
                <div class="settings-section">
                    <h2>Appearance</h2>
                    
                    <div class="form-group">
                        <input type="checkbox" id="dark_mode" name="dark_mode" <?php echo $_SESSION['dark_mode'] ? 'checked' : ''; ?>>
                        <label for="dark_mode">Dark Mode</label>
                    </div>
                    
                    <div class="form-group">
                        <label for="preferred_language">Preferred Language for AI:</label>
                        <select id="preferred_language" name="preferred_language" required>
                            <option value="en" <?php echo ($user['preferred_language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="bn" <?php echo ($user['preferred_language'] ?? '') === 'bn' ? 'selected' : ''; ?>>বাংলা (Bengali)</option>
                            <option value="hi" <?php echo ($user['preferred_language'] ?? '') === 'hi' ? 'selected' : ''; ?>>हिन्दी (Hindi)</option>
                            <option value="es" <?php echo ($user['preferred_language'] ?? '') === 'es' ? 'selected' : ''; ?>>Español (Spanish)</option>
                            <option value="fr" <?php echo ($user['preferred_language'] ?? '') === 'fr' ? 'selected' : ''; ?>>Français (French)</option>
                        </select>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h2>AI Email Assistant</h2>
                    
                    <div class="form-group">
                        <label for="writing_style">Default Writing Style:</label>
                        <select id="writing_style" name="writing_style">
                            <option value="professional" <?php echo ($settings['writing_style'] ?? 'professional') === 'professional' ? 'selected' : ''; ?>>Professional</option>
                            <option value="friendly" <?php echo ($settings['writing_style'] ?? '') === 'friendly' ? 'selected' : ''; ?>>Friendly</option>
                            <option value="formal" <?php echo ($settings['writing_style'] ?? '') === 'formal' ? 'selected' : ''; ?>>Formal</option>
                            <option value="casual" <?php echo ($settings['writing_style'] ?? '') === 'casual' ? 'selected' : ''; ?>>Casual</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="response_speed">Response Speed:</label>
                        <select id="response_speed" name="response_speed">
                            <option value="fast" <?php echo ($settings['response_speed'] ?? 'balanced') === 'fast' ? 'selected' : ''; ?>>Fast (lower quality)</option>
                            <option value="balanced" <?php echo ($settings['response_speed'] ?? 'balanced') === 'balanced' ? 'selected' : ''; ?>>Balanced</option>
                            <option value="thorough" <?php echo ($settings['response_speed'] ?? '') === 'thorough' ? 'selected' : ''; ?>>Thorough (higher quality)</option>
                        </select>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h2>Auto-Reply Settings</h2>
                    
                    <div class="form-group">
                        <input type="checkbox" id="auto_reply_enabled" name="auto_reply_enabled" <?php echo ($settings['auto_reply_enabled'] ?? 0) ? 'checked' : ''; ?>>
                        <label for="auto_reply_enabled">Enable Auto-Reply</label>
                    </div>
                    
                    <div class="form-group">
                        <label for="auto_reply_message">Auto-Reply Message:</label>
                        <textarea id="auto_reply_message" name="auto_reply_message" rows="5"><?php echo htmlspecialchars($settings['auto_reply_message'] ?? ''); ?></textarea>
                        <button type="submit" name="generate_auto_reply" class="btn btn-small"></button>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </main>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/darkmode.js"></script>
</body>
</html>