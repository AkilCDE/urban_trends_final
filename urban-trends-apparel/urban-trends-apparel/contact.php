<?php
require_once 'Database/datab.php';

session_start();

$messageSent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $messageSent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Urban Trends Apparel</title>
    <style>
        :root {
            --dark-bg: #121212;
            --darker-bg: #1e1e1e;
            --dark-accent: #2d2d2d;
            --text-primary: #e0e0e0;
            --text-secondary:rgb(255, 255, 255);
            --accent-color:rgb(255, 0, 111);
            --accent-hover:rgb(248, 1, 91);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        body {
            background-color: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            flex: 1;
        }
        header {
            background-color: var(--darker-bg);
            color: var(--text-primary);
            padding: 30px 0;
            text-align: center;
            border-bottom: 1px solid var(--dark-accent);
        }
        h1 {
            margin-bottom: 10px;
            color: var(--accent-color);
            font-size: 2.5rem;
        }
        .contact-section {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin: 40px 0;
        }
        .contact-info, .contact-form {
            flex: 1;
            min-width: 300px;
            background: var(--darker-bg);
            padding: 30px;
            border-radius: 8px;
            border: 1px solid var(--dark-accent);
        }
        .contact-info h2, .contact-form h2 {
            color: var(--accent-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--dark-accent);
        }
        .info-item {
            margin-bottom: 20px;
            color: var(--text-secondary);
        }
        .info-item i {
            margin-right: 10px;
            color: var(--accent-color);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--text-primary);
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            background-color: var(--dark-bg);
            border: 1px solid var(--dark-accent);
            color: var(--text-primary);
            border-radius: 4px;
            font-size: 16px;
        }
        .form-group textarea {
            height: 150px;
            resize: vertical;
        }
        .btn {
            background-color: var(--accent-color);
            color: #121212;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 16px;
            border-radius: 4px;
            transition: background-color 0.3s;
            font-weight: bold;
        }
        .btn:hover {
            background-color: var(--accent-hover);
        }
        .success-message {
            color: #4caf50;
            background-color: rgba(76, 175, 80, 0.1);
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        .error-message {
            color: #f44336;
            background-color: rgba(244, 67, 54, 0.1);
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        footer {
            background-color: var(--darker-bg);
            color: var(--text-secondary);
            text-align: center;
            padding: 20px 0;
            margin-top: 40px;
            border-top: 1px solid var(--dark-accent);
        }
        @media (max-width: 768px) {
            .contact-section {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Urban Trends Apparel</h1>
            <p>Premium fashion for the urban lifestyle</p>
        </div>
    </header>

    <div class="container">
        <div class="contact-section">
            <div class="contact-info">
                <h2>Get In Touch</h2>
                <div class="info-item">
                    <i>📍</i> 123 Fashion Street, Trendy District, Metro City
                </div>
                <div class="info-item">
                    <i>📞</i> +1 (555) 123-4567
                </div>
                <div class="info-item">
                    <i>✉️</i> contact@urbantrendsapparel.com
                </div>
                <div class="info-item">
                    <i>🕒</i> Monday - Friday: 9AM - 6PM<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;Saturday: 10AM - 4PM<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;Sunday: Closed
                </div>
            </div>

            <div class="contact-form">
                <h2>Send Us a Message</h2>
                
                <?php if ($messageSent): ?>
                    <div class="success-message">
                        Thank you for your message! We'll get back to you soon.
                    </div>
                <?php elseif ($error): ?>
                    <div class="error-message">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <select id="subject" name="subject" required>
                            <option value="">Select a subject</option>
                            <option value="Order Inquiry">Order Inquiry</option>
                            <option value="Product Question">Product Question</option>
                            <option value="Returns & Exchanges">Returns & Exchanges</option>
                            <option value="Feedback">Feedback</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <button type="submit" class="btn">Send Message</button>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Urban Trends Apparel. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>