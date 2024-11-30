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

if (!$pin_row) {
    header('Location: create_pin.php');
    exit;
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and trim inputs
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Fetch the current password hash from both the 'users' and 'register' tables
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare('SELECT password_hash FROM register WHERE id = ?');
    $stmt2->execute([$user_id]);
    $register = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$register) {
        $error_message = 'User not found in one of the tables.';
    } else {
        // Verify the current password using password_verify
        if (!password_verify($current_password, $user['password_hash']) || !password_verify($current_password, $register['password_hash'])) {
            $error_message = 'Current password is incorrect.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New password and confirmation do not match.';
        } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{7,}$/', $new_password)) {
            $error_message = 'Password must be at least 7 characters long, start with an uppercase letter, and include lowercase letters, numbers, and special characters.';
        } else {
            // Hash the new password
            $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);

            // Update password in the 'users' table
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $user_updated = $stmt->execute([$new_password_hash, $user_id]);

            // Also update the password_hash in the 'register' table
            if ($user_updated) {
                $stmt = $pdo->prepare('UPDATE register SET password_hash = ? WHERE id = ?');
                $register_updated = $stmt->execute([$new_password_hash, $user_id]);

                if ($register_updated) {
                    $success_message = 'Password changed successfully!';
                } else {
                    $error_message = 'Failed to update password in the register table.';
                }
            } else {
                $error_message = 'Failed to change password. Please try again.';
            }
        }
    }
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

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <!-- Link to Bootstrap CSS (locally) -->
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    
    <!-- Link to Font Awesome CSS (locally) -->
    <link rel="stylesheet" href="fontawesome-free-6.6.0-web/css/all.min.css">

     <!-- Link to Bootstrap JS (locally) -->
     <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>


     <body style="background: url('<?php echo $background_image_url; ?>') no-repeat center center fixed; background-size: cover;">

    </body>

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
        background: rgba(255, 255, 255, 0.9);
        border: 5px solid white;
        border-radius: 30px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        padding: 25px;
        max-width: 40rem;
        width: 80%;
        margin: auto;
        position: relative;
        margin-top: auto;
        overflow: hidden;
    }

    h1 {
        margin-bottom: 20px;
        color: #333;
        font-size: 24px;
        text-align: center;
    }

    .form-row {
        margin-bottom: 10px;
    }

    .form-column {
        width: 100%;
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        color: #666;
        text-align: left;
    }

    input[type="password"] {
        width: 100%;
        padding: 12px;
        margin-bottom: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-sizing: border-box;
    }

    button {
        width: 25%;
        padding: 10px;
        background-color: #00a400;
        color: #fff;
        border: none;
        border-radius: 5px;
        font-size: 15px;
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
        text-align: center;
    }

    .success-message {
        color: #28a745;
        margin-bottom: 20px;
        font-size: 14px;
        text-align: center;
    }

    .caps-lock-warning {
        position: absolute;
        left: 0;
        top: 50px;
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

    .close-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        background: #ff4d4d;
        border: none;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #fff;
        font-size: 18px;
        transition: background 0.3s;
    }
</style>

<script>
    // Real-time password validation
    function handlePasswordInput(event) {
        const password = event.target.value;
        const requirements = document.querySelectorAll('.password-requirements li');

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

        const capsLockWarning = document.getElementById(event.target.id + '-caps-lock-warning');
        checkCapsLock(event, event.target.id);
    }

    function checkCapsLock(event, fieldId) {
        const key = event.getModifierState('CapsLock');
        const warning = document.getElementById(fieldId + '-caps-lock-warning');

        if (key) {
            warning.style.display = 'block';
        } else {
            warning.style.display = 'none';
        }
    }
</script>

</head>
<body>
    <div class="container">
    <button class="close-btn" onclick="window.location.href='index.php';">&times;</button>
        <h1>Change Your Password</h1>


        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form action="change_password.php" method="POST">
            <div class="form-row">
                <div class="form-column password-wrapper">
                    <label for="current_password">Current Password:</label>
                    <div class="password-container">
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-column password-wrapper">
                    <label for="new_password">New Password:</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{7,}" title="Password must be at least 7 characters long, start with an uppercase letter, and include lowercase letters, numbers, and special characters." oninput="handlePasswordInput(event)" onkeydown="checkCapsLock(event, 'password')">
                       <!-- <div id="password-caps-lock-warning" class="caps-lock-warning">Caps Lock is ON</div> -->
                    </div>
                    <ul class="password-requirements">
                        <li>At least one uppercase letter</li>
                        <li>At least one lowercase letter</li>
                        <li>At least one number</li>
                        <li>At least one special character (@$!%*?&)</li>
                        <li>At least 7 characters</li>
                    </ul>
                </div>
            </div>

            <div class="form-row">
                <div class="form-column password-wrapper">
                    <label for="confirm_password">Confirm New Password:</label>
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
            </div>

            <center><button type="submit">Change Password</button>
        <br>
    <div class="form-row">
        <div class="form-column">
            <br>
            <p>Need to update your profile picture? <a href="add_profile.php">Click here.</a></p>
            <p>Need to update your account? <a href="account_settings.php">Click here.</a></p>
        </div>
    </div>
    </div>
</body>
</html>
