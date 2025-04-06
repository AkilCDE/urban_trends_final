<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'urban_trends');


try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

session_start();

class Auth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_firstname'] = $user['firstname'];
            $_SESSION['user_lastname'] = $user['lastname'];
            $_SESSION['user_address'] = $user['address'];
            $_SESSION['is_admin'] = $user['is_admin'];
            return true;
        }
        return false;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
}

$auth = new Auth($db);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = htmlspecialchars($_POST['email']);
    $password = htmlspecialchars($_POST['password']);
    
    if ($auth->login($email, $password)) {
        if ($auth->isAdmin()) {
            header("Location: admin/dashboard.php");
            exit;
        } else {
            header("Location: index.php");
            exit;
        }
    } else {
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Trends Apparel - Login</title>
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
        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            width: 350px;
            text-align: center;
        }
        .login-container h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        .login-container p {
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
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .checkbox-group input {
            width: auto;
            margin-right: 10px;
        }
        .checkbox-group label {
            color: #555;
            font-size: 14px;
            cursor: pointer;
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
        .forgot-password-link {
            margin-top: 10px;
            color: #555;
            font-size: 14px;
        }
        .forgot-password-link a {
            color: #333;
            text-decoration: none;
        }
        .forgot-password-link a:hover {
            text-decoration: underline;
        }
        .register-link {
            margin-top: 20px;
            color: #555;
            font-size: 14px;
        }
        .register-link a {
            color: #333;
            font-weight: bold;
            text-decoration: none;
        }
        .register-link a:hover {
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
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Trends Wear</h1>
        <p>Urban trends apparel</p>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form id="loginForm" method="POST">
            <div class="input-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="input-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="showPassword">
                <label for="showPassword">Show password</label>
            </div>
            
            <button type="submit" class="btn">Login</button>
            
            <div class="checkbox-group">
                <input type="checkbox" id="rememberMe" name="remember">
                <label for="rememberMe">Remember me</label>
            </div>
            
            <div class="forgot-password-link">
                <a href="forgot_password.php">Forgot password?</a>
            </div>
            
            <div class="register-link">
                Don't have an account? <a href="register.php">register here</a>
            </div>
        </form>
    </div>

    <script>
        // Show password toggle
        const showPassword = document.getElementById('showPassword');
        const passwordField = document.getElementById('password');
        
        showPassword.addEventListener('change', function() {
            passwordField.type = this.checked ? 'text' : 'password';
        });
    </script>
</body>
</html>