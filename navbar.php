<?php
// navbar.php
$page = isset($page) ? $page : ''; // Ensure $page is set or default to empty
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
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 25px;
            max-width: 1150px; /* Adjust width as needed */
            width: 100%;
            text-align: center;
            margin: auto; /* Center the container */
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
</body>
</html>
