<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = sanitizeInput($_POST['password']);
    $confirm_password = sanitizeInput($_POST['confirm_password']);
    $full_name = sanitizeInput($_POST['full_name']);
    $captcha = sanitizeInput($_POST['captcha']);
    
    if (!verifyCaptcha($captcha)) {
        $error = "Invalid CAPTCHA. Please try again.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        if ($auth->register($username, $email, $password, $full_name)) {
            $success = "Registration successful! You can now login.";
        } else {
            $error = "Registration failed. Username or email may already exist.";
        }
    }
}

$captcha_code = generateCaptcha();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="logo">
            <img src="assets/images/logo.png" alt="Atom Mail AI">
            <h1>Atom Mail AI</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="form-group captcha-group">
                <label for="captcha">CAPTCHA: <?php echo $captcha_code; ?></label>
                <input type="text" id="captcha" name="captcha" required>
                <input type="hidden" name="captcha_code" value="<?php echo $captcha_code; ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">Register</button>
        </form>
        
        <div class="login-footer">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>