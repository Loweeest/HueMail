<?php
session_start();

// Redirect to inbox if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Check if user has a PIN set
    $user_id = $_SESSION['user_id'];
    
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

    // Check if the user has already set a PIN
    $stmt = $pdo->prepare('SELECT pin_code FROM user_pins WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $pin_code = $stmt->fetchColumn(); // Fetch the PIN

    // If no PIN is found, the user must create one
    if (!$pin_code) {
        // Redirect to PIN creation page or show modal (you can choose to either use a separate page or a modal here)
        $_SESSION['pin_required'] = true;  // Set a session flag for PIN creation
        header('Location: create_pin.php'); // Redirect to the PIN creation page
        exit;
    } else {
        // User has a PIN, continue to the inbox
        header('Location: inbox.php');
        exit;
    }
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

$error_message = '';

// Default value for email
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Basic validation
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill out all fields.';
    } else {
        // Fetch user data from the database
        $stmt = $pdo->prepare('SELECT * FROM register WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify password and start session
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email']; // Store email in session if needed

            // Check if the user has set a PIN
            $stmt = $pdo->prepare('SELECT pin_code FROM user_pins WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $pin_code = $stmt->fetchColumn();

            if (!$pin_code) {
                // If no PIN is set, redirect to the PIN creation page
                $_SESSION['pin_required'] = true;
                header('Location: create_pin.php'); // This will trigger the PIN creation process
                exit;
            }

            // If the PIN exists, redirect to inbox
            header('Location: inbox.php');
            exit;
        } else {
            $error_message = 'Invalid email or password.';
        }
    }
}
$page = 'login'; // Set the current page identifier
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in to HueMail</title>
    <!-- Link to Bootstrap CSS (locally) -->
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    
    <!-- Link to Font Awesome CSS (locally) -->
    <link rel="stylesheet" href="fontawesome-free-6.6.0-web/css/all.min.css">

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

        img {
            display: block;
            margin: 0 auto; /* Centers the image horizontally */
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
    color: #fff; /* Ensure text color is white for better readability */
}

.container {
    background: rgba(255, 255, 255, 0.9); /* Semi-transparent white background */
    border: 5px solid white; /* Light gray border */
    border-radius: 30px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    padding: 25px;
    max-width: 60rem; /* Adjust width as needed */
    width: 50%;
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
        .form-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .form-column {
            flex: 1;
            min-width: calc(50% - 10px); /* Two columns with space in between */
            margin-right: 10px;
        }
        .form-column:last-child {
            margin-right: 0;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #666;
            text-align: left;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="date"],
        select {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
        }
        .password-wrapper {
    position: relative;
}

.password-wrapper input[type="password"] {
    padding-right: 40px; /* Space for the eye icon */
}
.password-wrapper .eye-icon {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-20%);
    cursor: pointer;
    color: #666;
    display: none; /* Hide by default */
}
.password-wrapper input[type="password"].has-text ~ .eye-icon {
    display: block; /* Show when input has text */
}
.caps-lock-message {
    position: absolute;
    left: 0; /* Align to the left of the parent container */
    top: 80px;
    color: red;
    font-size: 15px;
    display: none; /* Hide by default */
}
        .email-wrapper {
            position: relative;
        }
        .email-wrapper input {
            padding-right: 120px; /* Space for the suffix */
        }
        .email-wrapper .suffix {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            color: #666;
            pointer-events: none;
        }
        button {
            width: 15%;
            padding: 10px;
            background-color: #1877f2;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 20px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #4267b2;
        }
        .error-message {
            color: #ff4081;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .success-message {
            color: #28a745;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .login-link-container {
    margin-top: 20px; /* Increased margin to provide more space above */
    font-size: 18px; /* Increased font size for better visibility */
    color: #666;
}

.login-link {
    color: #007bff;
    font-size: 20px; /* Larger font size for the link */
    font-weight: bold; /* Bold font for emphasis */
    text-decoration: none;
}

.login-link:hover {
    text-decoration: underline;
}

        footer {
            background: rgba(0, 0, 0, 0.7);
            color: white;
            text-align: center;
            position: sticky;
            bottom: 0;
            width: 100%;
        }
        p {
            color: #666;
            font-size: 18px;
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
<script>
// Function to toggle password visibility
function togglePasswordVisibility(event) {
    const passwordField = event.target.previousElementSibling;
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        event.target.classList.remove('fa-eye-slash');
        event.target.classList.add('fa-eye');
    } else {
        passwordField.type = 'password';
        event.target.classList.remove('fa-eye');
        event.target.classList.add('fa-eye-slash');
    }
}
</script>
<script>
// Function to toggle password visibility
function togglePasswordVisibility(event) {
    const passwordField = event.target.previousElementSibling;
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        event.target.classList.remove('fa-eye-slash');
        event.target.classList.add('fa-eye');
    } else {
        passwordField.type = 'password';
        event.target.classList.remove('fa-eye');
        event.target.classList.add('fa-eye-slash');
    }
}

// Function to show/hide eye icon based on input
function handlePasswordInput(event) {
    const eyeIcon = event.target.nextElementSibling;
    if (event.target.value.length > 0) {
        eyeIcon.style.display = 'block';
    } else {
        eyeIcon.style.display = 'none';
    }
}

// Function to handle Caps Lock detection specifically for the password field
function handleCapsLock(event) {
    const capsLockMessage = document.getElementById('caps-lock-message');
    if (event.getModifierState && event.getModifierState('CapsLock')) {
        capsLockMessage.style.display = 'block';
    } else {
        capsLockMessage.style.display = 'none';
    }
}

// Add event listeners for Caps Lock detection to the password field
document.addEventListener('DOMContentLoaded', () => {
    const passwordField = document.getElementById('password');
    if (passwordField) {
        passwordField.addEventListener('keydown', handleCapsLock);
        passwordField.addEventListener('keyup', handleCapsLock);
    }
});
</script>

</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="nav-links">
        <a href="home.php" class="<?php echo $page == 'home' ? 'current' : ''; ?>">
    <img src="images/textlogo.png" alt="Home" width="200" height="50"></a>
       </div>
        <div class="auth-links">
            <a href="login.php" class="<?php echo $page == 'login' ? 'current' : ''; ?>">Log In</a>
            <a href="register.php" class="signup-button <?php echo $page == 'signup' ? 'current' : ''; ?>">Sign Up</a>
        </div>
    </div>
    <div class="container">
        <img src="images/logo.png"width="250" height="250">
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>

            <div class="password-wrapper">
    <label for="password">Password:</label>
    <input type="password" id="password" name="password" required oninput="handlePasswordInput(event)">
    <i class="fa fa-eye-slash eye-icon" onclick="togglePasswordVisibility(event)"></i>
    <div id="caps-lock-message" class="caps-lock-message">
        Caps Lock is ON
    </div>
            </div>

            <br>
            <button type="submit">Log In</button>
            <div class="login-link-container">
                Don't have an account? <a href="register.php" class="login-link">Sign Up</a>
            </div>
        </form>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Group 5. All rights reserved.</p>
    </footer>

 <!-- Link to Bootstrap JS (locally) -->
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script></body>
</html>