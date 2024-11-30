<?php
// cookies.php
$page = 'cookies'; // Set the current page identifier
include 'navbar.php'; // Include the navbar
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
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>    <title>Cookies Policy</title>
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
            background: rgba(255, 255, 255, 0.9); /* Semi-transparent white background */
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 20px;
            margin: 39px;
            width: auto; /* Allows the width to adjust to the content */
            text-align: center;
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
        <h1>Cookies Policy</h1>
        <p>Last updated: <?php echo date('F j, Y'); ?></p>
        <p>We use cookies to improve your experience. By using this project, you agree to our use of cookies.</p>
        
        <h2>1. What Are Cookies?</h2>
        <p>Cookies are small files placed on your device that help us understand how you use our project.</p>

        <h2>2. How We Use Cookies</h2>
        <p>We use cookies to remember your preferences and track usage to improve our project.</p>

        <h2>3. Managing Cookies</h2>
        <p>You can manage or disable cookies through your browser settings. Note that disabling cookies may affect the functionality of the project.</p>

        <h2>4. Changes to Cookies Policy</h2>
        <p>We may update this policy. Changes will be posted here with a new date.</p>

        <h2>5. Contact Us</h2>
        <p>If you have questions about our cookies policy, please contact us at <a href="mailto:admin@huemail.com" style="color: #007bff;">admin@huemail.com</a>.</p>

    </div>
        
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Group 5. All rights reserved.</p>
    </footer>
</body>
</html>
