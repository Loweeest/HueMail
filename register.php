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

$error_message = '';
$success_message = '';

// Default values for form inputs
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and trim inputs
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $captcha_response = $_POST['g-recaptcha-response'];  // Get the CAPTCHA response

    // Google reCAPTCHA secret key
    $secret_key = '6Lc1ry0qAAAAAHiquPn9QDdYDLJqvuxQN7Bp8E4K';

    // Verify the CAPTCHA response with Google's API
    $captcha_verified = verifyCaptcha($captcha_response, $secret_key);

    if (!$email) {
        $error_message = 'Invalid email format.';
    } elseif (empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'Please fill out all required fields.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{7,}$/', $password)) {
        $error_message = 'Password must be at least 7 characters long, start with an uppercase letter, and include lowercase letters, numbers, and special characters.';
    } else {
        // Check if the email is already registered
        $stmt = $pdo->prepare('SELECT * FROM register WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $error_message = 'Email is already registered.';
        } else {
            // Hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user into 'register' table
            $stmt = $pdo->prepare('INSERT INTO register (email, password_hash) VALUES (?, ?)');
            if ($stmt->execute([$email, $password_hash])) {
                // Insert the email and password hash into 'users' table
                $stmt = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
                if ($stmt->execute([$email, $password_hash])) {
                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $_SESSION['email'] = $email;
                    $success_message = 'Registration successful! Redirecting to login...';
                    header('refresh:2;url=welcome.php');
                    exit;
                } else {
                    $error_message = 'Failed to insert user into users table.';
                }
            } else {
                $error_message = 'Registration failed. Please try again.';
            }
        }
    }
}

// Function to verify CAPTCHA
function verifyCaptcha($captcha_response, $secret_key) {
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret'   => $secret_key,
        'response' => $captcha_response,
    ];

    // Send POST request to Google reCAPTCHA API
    $options = [
        'http' => [
            'method'  => 'POST',
            'content' => http_build_query($data),
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        ],
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result);

    return $response->success;  // If true, CAPTCHA is valid
}

$page = 'signup';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon"> <!-- Adjust path if necessary -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up</title>
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

    .navbar {
        background: rgba(0, 0, 0, 0.7);
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
        background-color: #555;
        color: #fff;
    }

    .container {
        background: rgba(255, 255, 255, 0.9);
        border: 5px solid white;
        border-radius: 30px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        padding: 25px;
        max-width: 40rem;
        width: 80%;
        margin: auto; /* Center the container */
    position: relative;
    margin-top: auto;
    overflow: hidden;
    }

    h1 {
        margin-bottom: 20px;
        color: #333;
        font-size: 24px;
        text-align: center; /* Ensure the heading is centered */
    }

    .form-row {
        margin-bottom: 10px;
    }

    .form-column {
        width: 100%;  /* Full width for each form input */
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        color: #666;
        text-align: left; /* Left align label text */
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
        padding-right: 40px;
    }

    .password-wrapper .eye-icon {
        position: absolute;
        top: 22%;
        right: 10px;
        transform: translateY(-20%);
        cursor: pointer;
        color: #666;
        display: none;
    }

    .password-wrapper input[type="password"].has-text ~ .eye-icon {
        display: block;
    }

    .confirm_password-wrapper{
        position: relative;
    }

    .confirm_password-wrapper input[type="confirm_password"] {
        padding-right: 40px;
    }

    .confirm_password-wrapper .eye-icon {
        position: absolute;
        top: 50%;
        right: 10px;
        transform: translateY(-20%);
        cursor: pointer;
        color: #666;
        display: none;
    }

    .confirm_password-wrapper input[type="confirm_password"].has-text ~ .eye-icon {
        display: block;
    }

    button {
        width: 20%; /* Adjust button width */
        padding: 10px;
        background-color: #00a400;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 20px;
        cursor: pointer;
        transition: background-color 0.3s;
        display: inline-block;
    }

    button:hover {
        background-color: #3b6e22;
    }

    .error-message {
        color: #ff4081;
        margin-bottom: 20px;
        font-size: 14px;
        text-align: center; /* Center the link */

    }

    .success-message {
        color: #28a745;
        margin-bottom: 20px;
        font-size: 14px;
        text-align: center; /* Center the link */
    }

    .login-link-container {
        margin-top: 20px;
        font-size: 18px;
        color: #666;
        text-align: center; /* Center the link */
    }

    .login-link {
        color: #007bff;
        font-size: 20px;
        font-weight: bold;
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

    .auth-links .login-button {
        display: inline-block;
        background-color: #1877f2;
        color: white;
        text-decoration: none;
        padding: 5px 15px;
        border-radius: 8px;
        font-size: 18px;
        text-align: center;
        border: none;
        transition: background-color 0.3s ease;
    }

    .auth-links .login-button:hover {
        background-color: #45a049;
    }

    .caps-lock-warning {
        position: absolute;
        left: 0;
        top: 80px;
        color: red;
        font-size: 15px;
        display: none;
    }

    .password-requirements {
        margin-top: 10px;
        font-size: 14px;
        color: #777;
    }

    .password-requirements li {
        margin-bottom: 5px;
    }

    .password-requirements .valid {
        color: green;
    }

    .password-requirements .invalid {
        color: red;
    }
</style>

<script>
    // Real-time password validation
    function handlePasswordInput(event) {
        const password = event.target.value;
        const requirements = document.querySelectorAll('.password-requirements li');
        
        // Requirements for the password
        const requirementsArray = [
            { regex: /[A-Z]/, message: "At least one uppercase letter" },
            { regex: /[a-z]/, message: "At least one lowercase letter" },
            { regex: /\d/, message: "At least one number" },
            { regex: /[@$!%*?&]/, message: "At least one special character (@$!%*?&)" },
            { regex: /.{7,}/, message: "At least 7 characters" }
        ];

        requirements.forEach((item, index) => {
            if (requirementsArray[index].regex.test(password)) {
                item.classList.add('valid');
                item.classList.remove('invalid');
            } else {
                item.classList.add('invalid');
                item.classList.remove('valid');
            }
        });

        const eyeIcon = event.target.nextElementSibling;
        const capsLockWarning = document.getElementById(event.target.id + '-caps-lock-warning');

        if (event.target.value.length > 0) {
            eyeIcon.style.display = 'block';
        } else {
            eyeIcon.style.display = 'none';
        }

        checkCapsLock(event, event.target.id);
    }

    // Check if CapsLock is on
    function checkCapsLock(event, fieldId) {
        const key = event.getModifierState('CapsLock');
        const warning = document.getElementById(fieldId + '-caps-lock-warning');

        if (key) {
            warning.style.display = 'block';
        } else {
            warning.style.display = 'none';
        }
    }

    // Toggle password visibility
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

    // Email validation (example: must end with @huemail.com)
    function validateEmail() {
        const emailInput = document.getElementById('email');
        const emailError = document.getElementById('email-error');
        const emailPattern = /^[a-zA-Z0-9._%+-]+@huemail\.com$/;

        if (!emailPattern.test(emailInput.value)) {
            emailError.style.display = 'block';
            emailInput.setCustomValidity('Email must end with @huemail.com.');
        } else {
            emailError.style.display = 'none';
            emailInput.setCustomValidity('');
        }
    }
</script>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="nav-links">
            <a href="home.php" class="<?php echo $page == 'home' ? 'current' : ''; ?>">Home</a>
        </div>
        <div class="auth-links">
            <a href="login.php" class="login-button <?php echo $page == 'login' ? 'current' : ''; ?>">Log In</a>
            <a href="register.php" class="<?php echo $page == 'signup' ? 'current' : ''; ?>">Sign Up</a>
        </div>
    </div>

    <div class="container">
        <h1>Create a new account</h1>
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <form action="register.php" method="POST">
            <div class="form-row">
                <div class="form-column email-wrapper">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required oninput="validateEmail()">
                    <center><div id="email-error" style="color: red; display: none;">Email must end with @huemail.com.</div>
        </center></div>
            </div>
            
            <div class="form-row">
                <div class="form-column password-wrapper">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{7,}" title="Password must be at least 7 characters long, start with an uppercase letter, and include lowercase letters, numbers, and special characters." oninput="handlePasswordInput(event)" onkeydown="checkCapsLock(event, 'password')">
                    <i class="fa fa-eye-slash eye-icon" onclick="togglePasswordVisibility(event)"></i>
                    <div id="password-caps-lock-warning" class="caps-lock-warning">Caps Lock is ON</div>
                    <!-- Password Requirements -->
                    <ul class="password-requirements">
                        <li>At least one uppercase letter</li>
                        <li>At least one lowercase letter</li>
                        <li>At least one number</li>
                        <li>At least one special character (@$!%*?&)</li>
                        <li>At least 7 characters</li>
                    </ul>
                </div>
                <div class="form-column confirm_password-wrapper">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{7,}" title="Passwords must match." oninput="handlePasswordInput(event)" onkeydown="checkCapsLock(event, 'confirm_password')">
                    <i class="fa fa-eye-slash eye-icon" onclick="togglePasswordVisibility(event)"></i>
                    <div id="confirm_password-caps-lock-warning" class="caps-lock-warning">Caps Lock is ON</div>
                </div>
            </div>

           
            <center><p id="recaptcha-error" style="color: red; display: none;">Please verify that you are not a robot.</p>

<!-- Google reCAPTCHA -->
    <div class="form-group">
        <div class="g-recaptcha" data-sitekey="6Lc1ry0qAAAAAHiquPn9QDdYDLJqvuxQN7Bp8E4K"></div>
    </div>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<br>
<button type="submit" onclick="return validateCaptcha()">Sign Up</button>
</center>

<script>
    // JavaScript to validate Google reCAPTCHA without alert
    function validateCaptcha() {
        var captchaResponse = grecaptcha.getResponse();
        
        // Check if the reCAPTCHA is not filled
        if (captchaResponse.length === 0) {
            // You can display a message in your UI instead of using alert
            document.getElementById("recaptcha-error").style.display = "block";
            return false;  // Prevent form submission
        }
        
        // Hide error message if reCAPTCHA is valid
        document.getElementById("recaptcha-error").style.display = "none";
        return true;  // Allow form submission
    }
</script>

            <div class="login-link-container">
                <a href="login.php" class="login-link">Already have an account?</a>
            </div>
        </form>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Group 5. All rights reserved.</p>
    </footer>

 <!-- Link to Bootstrap JS (locally) -->
 <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
