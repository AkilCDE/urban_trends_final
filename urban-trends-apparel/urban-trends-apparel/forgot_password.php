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
} // Assuming you have a separate config file

session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        try {
            // Check if email exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate a simple token (you could make this more secure)
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expires, $user['id']]);
                
                // Simple email sending (in production, use a proper mailer)
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/urban-trends-apparel/urban-trends-apparel/reset_password.php?token=$token";
                $subject = "Password Reset Request";
                $message = "Click this link to reset your password: $reset_link\n\n";
                $message .= "This link will expire in 1 hour.";
                
                // In a real app, you would send this email properly
                // mail($email, $subject, $message);
                
                // For demo purposes, we'll just show the link
                $success = "Password reset link: <a href='$reset_link'>$reset_link</a> (Normally this would be emailed to you)";
            } else {
                $error = 'No account found with that email address';
            }
        } catch(PDOException $e) {
            $error = 'Error processing your request. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Urban Trends Apparel</title>
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
        .forgot-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            width: 350px;
            text-align: center;
        }
        .forgot-container h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        .forgot-container p {
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
    <div class="forgot-container">
        <h1>Forgot Password</h1>
        <p>Enter your email to receive a password reset link</p>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="input-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <button type="submit" class="btn">Send Reset Link</button>
            
            <div class="back-to-login">
                <a href="login.php">Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>