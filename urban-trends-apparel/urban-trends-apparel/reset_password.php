<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'urban_trends');

// Create database connection
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
session_start();

$error = '';
$success = '';
$valid_token = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // Check if token is valid and not expired
        $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $valid_token = true;
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = trim($_POST['password']);
                $confirm_password = trim($_POST['confirm_password']);
                
                if (empty($password) || empty($confirm_password)) {
                    $error = 'Please fill in all fields';
                } elseif ($password !== $confirm_password) {
                    $error = 'Passwords do not match';
                } elseif (strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters';
                } else {
                    // Update password and clear reset token
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                    $stmt->execute([$hashed_password, $user['id']]);
                    
                    $success = 'Your password has been reset successfully. You can now <a href="login.php">login</a> with your new password.';
                    $valid_token = false; // Token has been used
                }
            }
        } else {
            $error = 'Invalid or expired reset link. Please request a new one.';
        }
    } catch(PDOException $e) {
        $error = 'Error processing your request. Please try again.';
    }
} else {
    $error = 'No reset token provided';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Urban Trends Apparel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        body {
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .reset-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            width: 350px;
            text-align: center;
        }
        .reset-container h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        .reset-container p {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .input-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-size: 14px;
        }
        .input-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 20px 0;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #555;
        }
        .back-to-login {
            margin-top: 20px;
            color: #555;
            font-size: 14px;
        }
        .back-to-login a {
            color: #333;
            text-decoration: none;
        }
        .back-to-login a:hover {
            text-decoration: underline;
        }
        .error-message {
            color: #ff3333;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #ffeeee;
            border-radius: 5px;
            font-size: 14px;
        }
        .success-message {
            color: #009900;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #eeffee;
            border-radius: 5px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h1>Reset Password</h1>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($valid_token): ?>
            <p>Please enter your new password</p>
            <form method="POST">
                <div class="input-group">
                    <label for="password">New Password:</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                
                <div class="input-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                
                <button type="submit" class="btn">Reset Password</button>
            </form>
        <?php else: ?>
            <div class="back-to-login">
                <a href="forgot_password.php">Request a new reset link</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>