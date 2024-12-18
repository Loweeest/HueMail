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


    <title>Team Members</title>
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
            position: relative;
            margin-top: auto;

            margin: auto; /* Center the container */
    overflow: hidden;
        }
        h1 {
            margin-bottom: 20px;
            color: #333;
            font-size: 25px;
        }
        h2 {
            color: #555;
        }
        p {
            color: #666;
            font-size: 18px;
        }
        .members {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px; /* Space between columns */
        }
        .member {
            flex: 1 1 calc(50% - 20px); /* Each column takes up 50% of the width minus the gap */
            box-sizing: border-box;
        }
        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            background-color: #ff4d4d;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
        }
        .close-btn:hover {
            background-color: #e03e3e;
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
        <button class="close-btn" onclick="window.location.href='inbox.php';">X</button>
        <h1>Meet Our Team</h1>
        <p>We are Group 5 and we have 8 dedicated members:</p>
        
        <div class="members">
            <div class="member">
                <h2>Member 1: Edig, John Louise G.</h2>
                <p>Front End Developer.</p>
            </div>
            <div class="member">
                <h2>Member 2: Entera, Michael Angelo E.</h2>
                <p>Lead Developer.</p>
            </div>
            <div class="member">
                <h2>Member 3: Bayubay, Christian Jay</h2>
                <p>QA/Tester.</p>
            </div>
            <div class="member">
                <h2>Member 4: Acosta, Alije</h2>
                <p>Back End Developer.</p>
            </div>
            <div class="member">
                <h2>Member 5: Duran, Mitch</h2>
                <p>Documentation.</p>
            </div>
            <div class="member">
                <h2>Member 6: Maramara, Riza Mae</h2>
                <p>Documentation.</p>
            </div>
            <div class="member">
                <h2>Member 7: Sestual, Jefred</h2>
                <p>Flowchart.</p>
            </div>
            <div class="member">
                <h2>Member 8: Veral, Kirk Arby</h2>
                <p>Documentation.</p>
            </div>
        </div>
    </div>
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Group 5. All rights reserved.</p>
    </footer>
</body>
</html>
