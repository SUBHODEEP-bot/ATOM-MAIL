<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = sanitizeInput($_POST['password']);
    $captcha = sanitizeInput($_POST['captcha']);
    
    if (!verifyCaptcha($captcha)) {
        $error = "Invalid CAPTCHA. Please try again.";
    } elseif ($auth->login($username, $password)) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}

$captcha_code = generateCaptcha();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo SITE_NAME; ?></title>
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
        
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group captcha-group">
                <label for="captcha">CAPTCHA: <?php echo $captcha_code; ?></label>
                <input type="text" id="captcha" name="captcha" required>
                <input type="hidden" name="captcha_code" value="<?php echo $captcha_code; ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        
        <div class="login-footer">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="#">Forgot password?</a></p>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>