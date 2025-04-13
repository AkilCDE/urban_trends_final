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
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (email, password, firstname, lastname, address, is_admin) VALUES (?, ?, ?, ?, ?, 0)");
            return $stmt->execute([$email, $hashed_password, $firstname, $lastname, $address]);
        } catch(PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return false;
        }
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_firstname'] = $user['firstname'];
                $_SESSION['user_lastname'] = $user['lastname'];
                $_SESSION['user_address'] = $user['address'];
                $_SESSION['is_admin'] = $user['is_admin'];
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function redirectIfLoggedIn() {
        if ($this->isLoggedIn()) {
            header("Location: index.php");
            exit;
        }
    }
}

$auth = new Auth($db);
$auth->redirectIfLoggedIn();

$errors = [];
$formData = [
    'email' => '',
    'firstname' => '',
    'lastname' => '',
    'address' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'email' => trim($_POST['email']),
        'password' => trim($_POST['password']),
        'confirm_password' => trim($_POST['confirm_password']),
        'firstname' => trim($_POST['firstname']),
        'lastname' => trim($_POST['lastname']),
        'address' => trim($_POST['address'])
    ];

    // Validate inputs
    if (empty($formData['email'])) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (empty($formData['password'])) {
        $errors[] = 'Password is required';
    } elseif (strlen($formData['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }

    if ($formData['password'] !== $formData['confirm_password']) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($formData['firstname'])) {
        $errors[] = 'First name is required';
    }

    if (empty($formData['lastname'])) {
        $errors[] = 'Last name is required';
    }

    if (empty($formData['address'])) {
        $errors[] = 'Address is required';
    }

    // Check if email exists
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$formData['email']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Email already exists';
            }
        } catch(PDOException $e) {
            $errors[] = 'Database error. Please try again.';
            error_log("Email check error: " . $e->getMessage());
        }
    }

    // If no errors, register user
    if (empty($errors)) {
        if ($auth->register(
            $formData['email'],
            $formData['password'],
            $formData['firstname'],
            $formData['lastname'],
            $formData['address']
        )) {
            // Auto-login after registration
            if ($auth->login($formData['email'], $formData['password'])) {
                header("Location: index.php");
                exit;
            } else {
                $errors[] = 'Registration successful but login failed. Please login manually.';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: rgb(228, 6, 80);
            --secondary-color: rgb(200, 0, 60);
            --light-color:rgb(121, 162, 203);
            --dark-color: #333;
            --text-color: #555;
            --error-color: #ff3333;
            --error-bg: #ffeeee;
            --border-color: #ddd;
            --border-radius: 8px;
            --box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: var(--primary-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .register-container {
            background-color: white;
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        
        .logo {
            margin-bottom: 25px;
        }
        
        .logo h1 {
            color: var(--dark-color);
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: var(--text-color);
            font-size: 14px;
        }
        
        .error-message {
            color: var(--error-color);
            background-color: var(--error-bg);
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left;
        }
        
        .error-message p {
            margin: 5px 0;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .input-group {
            margin-bottom: 15px;
            text-align: left;
        }
        
        .input-group.full-width {
            grid-column: span 2;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-size: 14px;
            font-weight: 500;
        }
        
        .input-group input,
        .input-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .input-group input:focus,
        .input-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(228, 6, 80, 0.1);
        }
        
        .input-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .password-container {
        position: relative;
    }
    
    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: var(--text-color);
        background: none;
        border: none;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .input-group input {
        padding-right: 40px; /* Make room for the eye icon */
    }
       
    
     
        .options {
            display: flex;
            align-items: center;
            margin: 20px 0;
            font-size: 14px;
        }
        
        .options input {
            margin-right: 8px;
            accent-color: var(--primary-color);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-bottom: 20px;
        }
        
        .btn:hover {
            background-color: var(--secondary-color);
        }
        
        .login-link {
            color: var(--text-color);
            font-size: 14px;
        }
        
        .login-link a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .login-link a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }
        
        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .input-group.full-width {
                grid-column: span 1;
            }
            
            .register-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>Urban Trends Apparel</h1>
            <p>Create your account</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form id="registerForm" method="POST">
            <div class="form-grid">
                <div class="input-group">
                    <label for="firstname">First Name</label>
                    <input type="text" id="firstname" name="firstname" required 
                           value="<?php echo htmlspecialchars($formData['firstname']); ?>">
                </div>
                
                <div class="input-group">
                    <label for="lastname">Last Name</label>
                    <input type="text" id="lastname" name="lastname" required 
                           value="<?php echo htmlspecialchars($formData['lastname']); ?>">
                </div>
                
                <div class="input-group full-width">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($formData['email']); ?>">
                </div>
                
                <div class="input-group">
    <label for="password">Password</label>
    <div class="password-container">
        <input type="password" id="password" name="password" required>
        <button type="button" class="password-toggle" id="togglePassword">
            <i class="fas fa-eye"></i>
        </button>
    </div>
</div>
                
<div class="input-group">
    <label for="confirm_password">Confirm Password</label>
    <div class="password-container">
        <input type="password" id="confirm_password" name="confirm_password" required>
        <button type="button" class="password-toggle" id="toggleConfirmPassword">
            <i class="fas fa-eye"></i>
        </button>
    </div>
</div>
                
                <div class="input-group full-width">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" required><?php echo htmlspecialchars($formData['address']); ?></textarea>
                </div>
            </div>
            
            
            
            <button type="submit" class="btn">Register</button>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </form>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const showPasswordsCheckbox = document.getElementById('showPasswords');
        
        function togglePasswordVisibility(inputElement, iconElement) {
            const type = inputElement.getAttribute('type') === 'password' ? 'text' : 'password';
            inputElement.setAttribute('type', type);
            iconElement.classList.toggle('fa-eye-slash');
        }
        
        togglePassword.addEventListener('click', function() {
            togglePasswordVisibility(passwordInput, this);
        });
        
        toggleConfirmPassword.addEventListener('click', function() {
            togglePasswordVisibility(confirmPasswordInput, this);
        });
        
        showPasswordsCheckbox.addEventListener('change', function() {
            const type = this.checked ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            confirmPasswordInput.setAttribute('type', type);
            
            if (this.checked) {
                togglePassword.classList.add('fa-eye-slash');
                toggleConfirmPassword.classList.add('fa-eye-slash');
            } else {
                togglePassword.classList.remove('fa-eye-slash');
                toggleConfirmPassword.classList.remove('fa-eye-slash');
            }
        });
        
        // Form validation
        const registerForm = document.getElementById('registerForm');
        const errorMessage = document.querySelector('.error-message');
        
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                if (!errorMessage) {
                    const newError = document.createElement('div');
                    newError.className = 'error-message';
                    newError.innerHTML = '<p>Passwords do not match</p>';
                    registerForm.insertBefore(newError, registerForm.firstChild);
                }
            }
        });
    </script>
</body>
</html>