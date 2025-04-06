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

$teamMembers = [
    [
        'name' => 'ALISBO, JOHN ANGELITO, A.',
        'position' => 'Founder & CEO',
        'bio' => 'Visionary leader with a passion for urban fashion trends'
    ],
    [
        'name' => 'BAQUERO, LIZA MAE, P.',
        'position' => 'Head of Design',
        'bio' => 'Creative director ensuring our products stay on trend'
    ],
    [
        'name' => 'CHIU, ASHLEY LOUISE',
        'position' => 'Marketing Director',
        'bio' => 'Digital marketing expert connecting with our urban audience'
    ],
    [
        'name' => 'DANGEL, AKIL CYRYLLE',
        'position' => 'Operations Manager',
        'bio' => 'Ensuring smooth logistics and customer satisfaction'
    ],
    [
        'name' => 'LACEA, CRISTEL MAE, R.',
        'position' => 'Customer Experience',
        'bio' => 'Dedicated to providing exceptional service to our customers'
    ],
    [
        'name' => 'ROBLEZA, PAULA ANDREA, C.',
        'position' => 'Product Developer',
        'bio' => 'Innovating new designs that define urban fashion'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Urban Trends Apparel</title>
    <style>
        :root {
            --dark-bg: #121212;
            --darker-bg: #1e1e1e;
            --dark-accent: #2d2d2d;
            --text-primary: #e0e0e0;
            --text-secondary: #a0a0a0;
            --accent-color:rgb(249, 42, 107);
            --accent-hover:rgb(241, 28, 96);
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
        h2 {
            color: var(--accent-color);
            margin-bottom: 20px;
            font-size: 1.8rem;
        }
        .about-section {
            background: var(--darker-bg);
            padding: 40px;
            border-radius: 8px;
            border: 1px solid var(--dark-accent);
            margin: 40px 0;
        }
        .about-section p {
            margin-bottom: 20px;
            line-height: 1.6;
            color: var(--text-secondary);
        }
        .team-section, .values-section {
            margin: 40px 0;
        }
        .team-grid, .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .team-member {
            background: var(--darker-bg);
            padding: 25px;
            border-radius: 8px;
            border: 1px solid var(--dark-accent);
            text-align: center;
            transition: transform 0.3s;
        }
        .team-member:hover {
            transform: translateY(-5px);
        }
        .team-member img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid var(--accent-color);
        }
        .team-member h3 {
            color: var(--accent-color);
            margin-bottom: 5px;
        }
        .team-member p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .team-member .position {
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--text-primary);
        }
        .value-card {
            background: var(--darker-bg);
            padding: 30px;
            border-radius: 8px;
            border: 1px solid var(--dark-accent);
        }
        .value-card h3 {
            color: var(--accent-color);
            margin-bottom: 15px;
        }
        .value-card p {
            color: var(--text-secondary);
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
            .team-grid, .values-grid {
                grid-template-columns: 1fr;
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
        <div class="about-section">
            <h2>Our Story</h2>
            <p>Founded in 2023, Urban Trends Apparel is a collective effort by six passionate individuals who shared a common vision for revolutionizing urban fashion. Our team brings together diverse expertise in design, marketing, and operations to create a brand that truly understands the urban lifestyle.</p>
            
            <p>What began as a classroom project has evolved into a promising fashion venture, with each member contributing their unique skills to build a brand that stands for quality, innovation, and authentic urban style.</p>
            
            <p>We're committed to creating apparel that not only looks great but also tells the story of the vibrant urban culture we represent. Every stitch, every design, and every collection is a testament to our shared passion for fashion that moves with the times.</p>
        </div>

        <div class="team-section">
            <h2>Meet Our Team</h2>
            <div class="team-grid">
                <?php foreach ($teamMembers as $member): ?>
                <div class="team-member">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($member['name']) ?>&background=random&color=fff&size=200" alt="<?= htmlspecialchars($member['name']) ?>">
                    <h3><?= htmlspecialchars($member['name']) ?></h3>
                    <p class="position"><?= htmlspecialchars($member['position']) ?></p>
                    <p><?= htmlspecialchars($member['bio']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="values-section">
            <h2>Our Values</h2>
            <div class="values-grid">
                <div class="value-card">
                    <h3>Collaborative Design</h3>
                    <p>Every collection is a team effort, combining our diverse perspectives to create truly unique urban fashion.</p>
                </div>
                <div class="value-card">
                    <h3>Academic Excellence</h3>
                    <p>We apply our classroom knowledge to real-world fashion challenges, ensuring innovative solutions.</p>
                </div>
                <div class="value-card">
                    <h3>Urban Authenticity</h3>
                    <p>Our designs come from genuine urban experiences, creating apparel that resonates with city life.</p>
                </div>
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