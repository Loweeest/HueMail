<?php
session_start();

// Redirect to inbox if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: inbox.php');
    exit;
}

// Database connection
$host = 'localhost';
$db   = 'HueMail';
$user = 'root';  // Change to your MySQL username
$pass = '';      // Change to your MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}
    
// Handle cookie consent
if (isset($_POST['accept_cookies'])) {
    setcookie('cookie_consent', 'accepted', time() + (86400 * 30), "/"); // 30 days expiration
    header("Location: home.php");
    exit;
} elseif (isset($_POST['reject_cookies'])) {
    setcookie('cookie_consent', 'rejected', time() + (86400 * 30), "/"); // 30 days expiration
    header("Location: home.php");
    exit;
}

// Check if the user has already made a choice
$cookie_consent = isset($_COOKIE['cookie_consent']) ? $_COOKIE['cookie_consent'] : null;
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
    
    <title>HueMail</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('images/pp.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .navbar {
            background: rgba(0, 0, 0, 0.7); /* Dark background for navbar */
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            font-size: 18px;
            padding: 10px;
            border-radius: 5px;
        }
        .navbar a:hover, .navbar a.current {
            background-color: #555; /* Darker background on hover and for the current page */
        }
        .container {
    background: rgba(255, 255, 255, 0.9); /* Semi-transparent white background */
    border: 5px solid white; /* Light gray border */
    border-radius: 30px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    padding: 25px;
    max-width: 60rem; /* Adjust width as needed */
    width: 90%;
    text-align: center;
    margin: auto; /* Center the container */
    position: relative;
    margin-top: auto;
    overflow: hidden;
}
        h1 {
            margin-bottom: 20px;
            color: #333;
            font-size: 24px;
        }
        h2 {
            color: #555;
            margin-bottom: 10px;
            font-size: 20px;
        }
        p {
            color: #666;
            font-size: 17px;
            line-height: 1.5;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            color: #333;
        }
        ul li {
            margin: 5px 0;
            padding: 7px;
            border-radius: 8px;
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
        /* Modal (popup) styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7); /* Black background with opacity */
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fff;
            margin: auto;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 80%;
            max-width: 500px;
            text-align: center;
        }
        .modal-content p {
            color: #333;
            margin-bottom: 20px;
        }
        .modal-content button {
            margin: 10px;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .accept-button {
            background-color: #4CAF50;
            color: white;
        }
        .reject-button {
            background-color: #f44336;
            color: white;
        }
        .auth-links .signup-button {
    display: inline-block;
    background-color: #4CAF50; /* Green background */
    color: white;
    text-decoration: none;
    padding: 5px 15px; /* Padding for button size */
    border-radius: 8px; /* Rounded corners */
    font-size: 18px;
    text-align: center;
    border: none;
    transition: background-color 0.3s ease; /* Smooth background color transition */
}

.auth-links .signup-button:hover {
    background-color: #45a049; /* Darker green on hover */
}

    </style>
</head>
<body>
    <div class="navbar">
        <div class="nav-links">
            <a href="home.php" class="<?php echo $page == 'home' ? 'current' : ''; ?>">Home</a>
                        </div>
        <div class="auth-links">
    <a href="login.php" class="<?php echo $page == 'login' ? 'current' : ''; ?>">Log In</a>
    <a href="register.php" class="signup-button <?php echo $page == 'signup' ? 'current' : ''; ?>">Sign Up</a>
</div>

    </div>
    
    <div class="container">
        <h1>About Us</h1>
        <p><strong>Welcome to HueMail!</strong></p>
        <p>At HueMail, we’re dedicated to making email simple and enjoyable. Our goal is to provide you with an email service that’s easy to use, secure, and packed with features to make your life easier.</p>
        
        <h2>Our Mission</h2>
        <p>We aim to improve your email experience by offering a user-friendly platform with advanced security and useful features. We believe in technology that adapts to you, not the other way around.</p>
        
        <h2>Why Choose HueMail?</h2>
        <ul>
            <li><strong>Easy to Use:</strong> Our clean and intuitive design makes managing your emails straightforward and stress-free.</li>
            <li><strong>Secure:</strong> We prioritize your privacy with strong security measures to keep your information safe.</li>
            <li><strong>Helpful Features:</strong> From smart organization tools to powerful search functions, we’ve built HueMail with features that enhance your productivity.</li>
            <li><strong>Support:</strong> Our friendly support team is here to help with any questions or issues you might have.</li>
        </ul>

        <h2>Our Story</h2>
        <p>HueMail was created to offer a better email experience. We wanted a service that combines the latest technology with a focus on user needs. We’re always working to improve and make sure HueMail works best for you.</p>
    </div>
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Group 5. All rights reserved.</p>
    </footer>

    <?php if ($cookie_consent === null): ?>
        <div id="cookieModal" class="modal">
            <div class="modal-content">
                <p>We use cookies to ensure you get the best experience. By continuing to browse or by clicking "Accept," you agree to our <a href="cookies.php" target="_blank" style="color: #007bff; text-decoration: underline;">Cookies Policy</a>.</p>
                <form method="post" action="">
                    <button type="submit" name="accept_cookies" class="accept-button">Accept</button>
                    <button type="submit" name="reject_cookies" class="reject-button">Reject</button>
                </form>
            </div>
        </div>

        <script>
            // Show the modal
            window.onload = function() {
                document.getElementById('cookieModal').style.display = 'flex';
            }
        </script>
    <?php endif; ?>
</body>
</html>
