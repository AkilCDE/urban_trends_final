<?php
// Database configuration
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

// Start session
session_start();

class Auth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function register($email, $password, $firstname, $lastname, $address) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (email, password, firstname, lastname, address, is_admin) VALUES (?, ?, ?, ?, ?, 0)");
        return $stmt->execute([$email, $hashed_password, $firstname, $lastname, $address]);
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
}

$auth = new Auth($db);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = htmlspecialchars($_POST['email']);
    $password = htmlspecialchars($_POST['password']);
    $confirm_password = htmlspecialchars($_POST['confirm_password']);
    $firstname = htmlspecialchars($_POST['firstname']);
    $lastname = htmlspecialchars($_POST['lastname']);
    $address = htmlspecialchars($_POST['address']);

    // Validate inputs
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($firstname)) {
        $errors[] = 'First name is required';
    }

    if (empty($lastname)) {
        $errors[] = 'Last name is required';
    }

    if (empty($address)) {
        $errors[] = 'Address is required';
    }

    // Check if email exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Email already exists';
    }

    // If no errors, register user
    if (empty($errors)) {
        if ($auth->register($email, $password, $firstname, $lastname, $address)) {
            // Auto-login after registration
            if ($auth->login($email, $password)) {
                header("Location: index.php");
                exit;
            }
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Trends Apparel - Register</title>
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
        .input-group input,
        .input-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .input-group textarea {
            height: 80px;
            resize: vertical;
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
        .error-message p {
            margin: 5px 0;
            font-size: 14px;
            color: #ff3333;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Trends Wear</h1>
        <p>Urban trends apparel</p>
        
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form id="registerForm" method="POST">
            <div class="input-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?php echo $_POST['email'] ?? ''; ?>">
            </div>
            
            <div class="input-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="input-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="input-group">
                <label for="firstname">First Name:</label>
                <input type="text" id="firstname" name="firstname" required value="<?php echo $_POST['firstname'] ?? ''; ?>">
            </div>
            
            <div class="input-group">
                <label for="lastname">Last Name:</label>
                <input type="text" id="lastname" name="lastname" required value="<?php echo $_POST['lastname'] ?? ''; ?>">
            </div>
            
            <div class="input-group">
                <label for="address">Address:</label>
                <textarea id="address" name="address" required><?php echo $_POST['address'] ?? ''; ?></textarea>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="showPassword">
                <label for="showPassword">Show password</label>
            </div>
            
            <button type="submit" class="btn">Register</button>
            
            <div class="register-link">
                Already have an account? <a href="login.php">login here</a>
            </div>
        </form>
    </div>

    <script>
        // Show password toggle
        const showPassword = document.getElementById('showPassword');
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        
        showPassword.addEventListener('change', function() {
            const type = this.checked ? 'text' : 'password';
            passwordField.type = type;
            confirmPasswordField.type = type;
        });
    </script>
</body>
</html>