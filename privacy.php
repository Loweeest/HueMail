<?php
session_start(); // Start the session at the beginning of the page

// ** Check if the user has verified their PIN **
if (!isset($_SESSION['pin_verified']) || $_SESSION['pin_verified'] !== true) {
    // If PIN is not verified, redirect to inbox.php to enter PIN
    $_SESSION['show_modal'] = true;  // This will trigger the PIN modal to show on inbox.php
    header('Location: inbox.php');   // Redirect to inbox.php to enter PIN
    exit;
}

// ** Check if the user is logged in **
if (!isset($_SESSION['user_id'])) {
    // If user is not logged in, redirect to login page
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Database connection setup
$host = 'localhost';
$db   = 'HueMail';
$user = 'root';  // Change to your MySQL username
$pass = '';      // Change to your MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if the user has a PIN set
$stmt = $pdo->prepare('SELECT * FROM user_pins WHERE user_id = ?');
$stmt->execute([$user_id]);
$pin_row = $stmt->fetch(PDO::FETCH_ASSOC);

// If the user does not have a PIN, redirect them to create_pin.php
if (!$pin_row) {
    header('Location: create_pin.php');
    exit;
}

// Fetch user's background image from the 'users' table
$stmt = $pdo->prepare("SELECT background_image FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user_bg = $stmt->fetch(PDO::FETCH_ASSOC);

// Default background image path
$default_background = 'images/mainbg.jpg'; // Default image if none is set
$current_background = $user_bg['background_image'] ?: $default_background; // Use user image or default

// Cache busting: Add a timestamp to the image URL to avoid caching
$background_image_url = $current_background . '?v=' . time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon"> <!-- Adjust path if necessary -->

    <!-- Link to Bootstrap CSS (locally) -->
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    
    <!-- Link to Font Awesome CSS (locally) -->
    <link rel="stylesheet" href="fontawesome-free-6.6.0-web/css/all.min.css">

 <!-- Link to Bootstrap JS (locally) -->
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>    

    <body style="background: url('<?php echo $background_image_url; ?>') no-repeat center center fixed; background-size: cover;">

    
    <title>Privacy Policy</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('images/mainbg.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .container {
            position: relative;
            background: rgba(255, 255, 255, 0.9); /* Semi-transparent white background */
            border-radius: 12px;
            border: 5px solid white; /* Light gray border */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 20px;
            margin: 39px;
            width: auto; /* Allows the width to adjust to the content */
            text-align: center;
    margin: auto; /* Center the container */
    position: relative;
    margin-top: auto;
    overflow: hidden;
        }
        
        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            background-color: #ff4d4d; /* Red background color */
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close-btn:hover {
            background-color: #e60000; /* Darker red on hover */
        }
        h1 {
            margin-bottom: 20px;
            color: #333;
            font-size: 24px;
        }
        h2 {
            color: #555;
        }
        p {
            color: #666;
            font-size: 18px;
        }
        footer {
            background: rgba(0, 0, 0, 0.7);
            color: white;
            text-align: center;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='inbox.php'">X</button>
        <h1>Privacy Policy</h1>
        <p>Last updated: <?php echo date('F j, Y'); ?></p>
        <p>Your privacy is important to us. This policy explains how we collect, use, and protect your information.</p>
        
        <h2>1. Information We Collect</h2>
        <p>We collect information you provide directly, such as through forms. We also collect information about your usage of the project.</p>

        <h2>2. How We Use Your Information</h2>
        <p>We use your information to operate and improve our project, communicate with you, and for other purposes with your consent.</p>

        <h2>3. Data Protection</h2>
        <p>We take reasonable measures to protect your information from unauthorized access or disclosure.</p>

        <h2>4. Changes to Privacy Policy</h2>
        <p>We may update this policy. Changes will be posted here with a new date.</p>

        <h2>5. Contact Us</h2>
        <p>If you have questions about our privacy practices, please contact us at <a href="mailto:admin@huemail.com" style="color: #007bff;">admin@huemail.com</a>.</p>
    </div>
        
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Group 5. All rights reserved.</p>
    </footer>
</body>
</html>
