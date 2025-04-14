<?php
require_once 'includes/config.php';
require_once 'includes/google_auth.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['is_admin']) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

$error = '';
$success = '';

// Get Google user info if available
$google_user = isset($_SESSION['google_user']) ? $_SESSION['google_user'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $terms_accepted = isset($_POST['terms']) ? true : false;
    
    // Validate input
    if (empty($firstname)) {
        $error = "First name is required";
    } elseif (empty($lastname)) {
        $error = "Last name is required";
    } elseif (empty($email)) {
        $error = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (empty($password)) {
        $error = "Password is required";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (empty($address)) {
        $error = "Address is required";
    } elseif (empty($phone)) {
        $error = "Phone number is required";
    } elseif (!preg_match("/^[0-9]{10,15}$/", $phone)) {
        $error = "Invalid phone number format";
    } elseif (!$terms_accepted) {
        $error = "You must accept the terms and conditions";
    } else {
        // Check if email already exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $error = "Email already registered";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $db->prepare("INSERT INTO users (email, password, firstname, lastname, address, phone, is_admin, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
            if ($stmt->execute([$email, $hashed_password, $firstname, $lastname, $address, $phone])) {
                // Get the user ID of the newly created user
                $user_id = $db->lastInsertId();
                
                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_firstname'] = $firstname;
                $_SESSION['user_lastname'] = $lastname;
                $_SESSION['user_address'] = $address;
                $_SESSION['user_phone'] = $phone;
                $_SESSION['is_admin'] = 0;
                
                // Log the registration
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address) VALUES (?, 'register', 'users', ?, ?, ?)");
                $new_values = json_encode([
                    'email' => $email,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'address' => $address,
                    'phone' => $phone
                ]);
                $stmt->execute([$user_id, $user_id, $new_values, $ip_address]);
                
                // Redirect to welcome page or dashboard
                header('Location: index.php?registered=1');
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --secondary-color: #121212;
            --accent-color: #ff6b6b;
            --light-color: #f8f9fa;
            --dark-color: #0d0d0d;
            --text-color: #e0e0e0;
            --text-muted: #b0b0b0;
            --success-color: #4bb543;
            --error-color: #ff3333;
            --warning-color: #ffcc00;
            --border-radius: 8px;
            --box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background */
        .background-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            opacity: 0.1;
        }

        .background-animation::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: url('assets/images/pattern.png') repeat;
            animation: backgroundMove 20s linear infinite;
        }

        @keyframes backgroundMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-50%, -50%); }
        }

        .register-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 500px;
            position: relative;
            z-index: 2;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.5s ease forwards;
            max-height: 90vh;
            overflow-y: auto;
        }

        /* Custom Scrollbar */
        .register-container::-webkit-scrollbar {
            width: 8px;
        }

        .register-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .register-container::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 10px;
        }

        .register-container::-webkit-scrollbar-thumb:hover {
            background: #ff5252;
        }

        @keyframes slideUp {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeIn 0.5s ease 0.3s forwards;
            opacity: 0;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        .logo h1 {
            color: var(--accent-color);
            font-size: 2rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .logo p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .error-message {
            background: rgba(255, 51, 51, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: none;
            animation: shake 0.5s ease;
        }

        .error-message.show {
            display: block;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .success-message {
            background: rgba(75, 181, 67, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: none;
        }

        .success-message.show {
            display: block;
        }

        .input-group {
            margin-bottom: 20px;
            position: relative;
            animation: fadeIn 0.5s ease 0.5s forwards;
            opacity: 0;
        }

        .input-group label {
            display: block;
            color: var(--text-color);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 1rem;
            transition: var(--transition);
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 40px;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--accent-color);
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-row .input-group {
            flex: 1;
            margin-bottom: 0;
        }

        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .terms-checkbox input {
            margin-right: 10px;
            margin-top: 3px;
        }

        .terms-checkbox a {
            color: var(--accent-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .terms-checkbox a:hover {
            color: #ff5252;
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            animation: fadeIn 0.5s ease 0.9s forwards;
            opacity: 0;
        }

        .btn:hover {
            background: #ff5252;
            transform: translateY(-2px);
        }

        .btn-google {
            background: white;
            color: #333;
            border: 1px solid #ddd;
            margin-top: 15px;
        }

        .btn-google:hover {
            background: #f8f9fa;
            border-color: #ccc;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: var(--text-muted);
            font-size: 0.9rem;
            animation: fadeIn 0.5s ease 1.1s forwards;
            opacity: 0;
        }

        .login-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .login-link a:hover {
            color: #ff5252;
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .register-container {
                padding: 30px 20px;
                max-height: 85vh;
            }
            
            .form-row {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="background-animation"></div>
    
    <div class="register-container">
        <div class="logo">
            <h1><i class="fas fa-tshirt"></i> Urban Trends</h1>
            <p>Create your account</p>
        </div>
        
        <div class="error-message <?php echo $error ? 'show' : ''; ?>">
            <?php echo $error; ?>
        </div>
        
        <div class="success-message <?php echo $success ? 'show' : ''; ?>">
            <?php echo $success; ?>
        </div>
        
        <form id="registerForm" method="POST">
            <div class="form-row">
                <div class="input-group">
                    <label for="firstname">First Name</label>
                    <input type="text" id="firstname" name="firstname" required 
                           value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ($google_user ? htmlspecialchars(explode(' ', $google_user['name'])[0]) : ''); ?>">
                </div>
                
                <div class="input-group">
                    <label for="lastname">Last Name</label>
                    <input type="text" id="lastname" name="lastname" required 
                           value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ($google_user && count(explode(' ', $google_user['name'])) > 1 ? htmlspecialchars(explode(' ', $google_user['name'])[1]) : ''); ?>">
                </div>
            </div>
            
            <div class="input-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ($google_user ? htmlspecialchars($google_user['email']) : ''); ?>">
            </div>
            
            <div class="input-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" required 
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                       placeholder="e.g., 09123456789">
            </div>
            
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <i class="fas fa-eye password-toggle" id="togglePassword"></i>
            </div>
            
            <div class="input-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
            </div>
            
            <div class="input-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" required 
                       value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
            </div>
            
            <div class="terms-checkbox">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a></label>
            </div>
            
            <button type="submit" class="btn">Create Account</button>
            
            <div class="login-link" style="margin-bottom: 20px;">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </form>
    </div>

    <script>
        // Password visibility toggle
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const phone = document.getElementById('phone').value;
            const terms = document.getElementById('terms').checked;
            
            let isValid = true;
            const errorMessage = document.querySelector('.error-message');
            
            if (password !== confirmPassword) {
                errorMessage.textContent = 'Passwords do not match';
                errorMessage.classList.add('show');
                isValid = false;
            } else if (!/^[0-9]{10,15}$/.test(phone)) {
                errorMessage.textContent = 'Please enter a valid phone number';
                errorMessage.classList.add('show');
                isValid = false;
            } else if (!terms) {
                errorMessage.textContent = 'You must accept the terms and conditions';
                errorMessage.classList.add('show');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>