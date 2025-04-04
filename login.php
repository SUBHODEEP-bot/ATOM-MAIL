<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    <style>
        .captcha-refresh {
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 16px;
            margin-left: 10px;
        }
        .captcha-refresh:hover {
            color: #333;
        }
        .captcha-container {
            display: flex;
            align-items: center;
        }
        #captcha-display {
            font-weight: bold;
            letter-spacing: 2px;
            color: #333;
        }
    </style>
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
            
            <div class="form-group">
                <div class="captcha-container">
                    <label for="captcha">CAPTCHA: <span id="captcha-display"><?php echo $captcha_code; ?></span></label>
                    <button type="button" id="refresh-captcha" class="captcha-refresh" title="Refresh CAPTCHA">
                        &#x21bb; <!-- Refresh icon -->
                    </button>
                </div>
                <input type="text" id="captcha" name="captcha" required autocomplete="off">
                <input type="hidden" id="captcha_code" name="captcha_code" value="<?php echo $captcha_code; ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        
        <div class="login-footer">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="#">Forgot password?</a></p>
        </div>
    </div>
    
    <script>
        document.getElementById('refresh-captcha').addEventListener('click', function() {
            // Add timestamp to prevent caching
            const timestamp = new Date().getTime();
            
            // Show loading state
            const refreshBtn = document.getElementById('refresh-captcha');
            refreshBtn.innerHTML = '...';
            
            // AJAX request to refresh CAPTCHA
            fetch('includes/refresh_captcha.php?t=' + timestamp)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('captcha-display').textContent = data.captcha;
                        document.getElementById('captcha_code').value = data.captcha;
                    } else {
                        console.error('Server error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing CAPTCHA:', error);
                    // Fallback: reload the page
                    window.location.reload();
                })
                .finally(() => {
                    refreshBtn.innerHTML = '&#x21bb;';
                });
        });
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>