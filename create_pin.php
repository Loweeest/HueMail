<?php
session_start(); // Start the session to manage user state

// Check if user is logged in and needs to set a PIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['pin_required'])) {
    header('Location: login.php'); // If no user is logged in or no PIN is required, redirect to login
    exit;
}

$success_message = '';  // Variable to store success message
$error_message = '';    // Variable to store error message

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $pin_code = trim($_POST['pin_code']); // Get the PIN code submitted from the form

    // Validate PIN (4 digits, numeric)
    if (strlen($pin_code) != 4 || !is_numeric($pin_code)) {
        $error_message = 'PIN must be a 4-digit number.';
    } else {
        // Hash the PIN and save to the database
        $hashed_pin = password_hash($pin_code, PASSWORD_DEFAULT);
        
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

        // Insert or update the user's PIN in the database
        $stmt = $pdo->prepare('INSERT INTO user_pins (user_id, pin_code) VALUES (?, ?) ON DUPLICATE KEY UPDATE pin_code = ?');
        $stmt->execute([$user_id, $hashed_pin, $hashed_pin]);

        // Set success message to display on the page
        $_SESSION['pin_success_message'] = 'Your PIN has been successfully created!';

        // Mark that the PIN has been created (this is what you're checking in logout.php)
        $_SESSION['pin_created'] = true;

        // No redirect, just stay on the page
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon"> <!-- Adjust path if necessary -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Create PIN</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('images/mainbg.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        h1 {
            color: black;
            font-size: 28px;
            margin-bottom: 20px;
            text-align: center;
        }

        .container {
            background: rgba(255, 255, 255, 0.5);
            padding: 25px;
            border: 3px solid white;

            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 400px;
            max-width: 90%;
            text-align: center;
            margin: auto; /* Center the container */
    position: relative;
    margin-top: auto;
    overflow: hidden;
        }

        .form-row {
            margin-bottom: 20px;
        }

        .pin-input {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .pin-input input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 24px;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin: 0 5px;
            background-color: #f9f9f9;
        }

        .pin-input input:focus {
            border-color: #1877f2;
            outline: none;
        }

        button {
            width: 50%;
            padding: 12px;
            background-color: #1877f2;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            margin-top: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #4267b2;
        }

        .error-message {
            color: #ff4081;
            font-size: 14px;
            margin-top: 10px;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            width: 400px;
        }

        .modal-header {
            font-size: 20px;
            margin-bottom: 20px;
        }

        .modal-footer {
            margin-top: 20px;
        }

        .modal-button {
            width: 25%;
            background-color: #1877f2;
            color: #fff;
            border: none;
            padding: 12px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
        }

        .modal-button:hover {
            background-color: #4267b2;
        }

        .note {
            font-size: 20px;
            color: black;
            margin-top: 20px;
            font-style: italic;
        }

        .note strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create a 4-Digit PIN</h1>

        <!-- Display error message if there's any -->
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <!-- PIN Input Form -->
        <form method="POST" id="pin-form">
        <div class="form-row pin-input">
    <input type="password" name="pin_digit_1" maxlength="1" id="pin_digit_1" class="pin-box" autocomplete="off" autofocus>
    <input type="password" name="pin_digit_2" maxlength="1" id="pin_digit_2" class="pin-box" autocomplete="off">
    <input type="password" name="pin_digit_3" maxlength="1" id="pin_digit_3" class="pin-box" autocomplete="off">
    <input type="password" name="pin_digit_4" maxlength="1" id="pin_digit_4" class="pin-box" autocomplete="off">
</div>

            <button type="submit">Create PIN</button>
        </form>

        <!-- Informational Note -->
        <p class="note">
            <strong>Note:</strong> To ensure your account is secure, please create a PIN as this is your first time logging in. <strong>Please make sure to remember your PIN, as it will be required for future logins</strong>.
        </p>
    </div>

    <!-- Modal for Success Message -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Your PIN has been successfully created!</h2>
                <p>NOTE: After setting your PIN, <italic>you will be logged out for security purposes</italic>.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-button" onclick="closeModalAndProceed()">Okay</button>
            </div>
        </div>
    </div>

    <script>
// JavaScript to automatically focus the next input when a digit is entered
document.querySelectorAll('.pin-box').forEach((input, index, inputs) => {
    input.addEventListener('input', function () {
        if (this.value.length === 1 && index < inputs.length - 1) {
            inputs[index + 1].focus();  // Move to next input after 1 digit
        } else if (this.value.length === 0 && index > 0) {
            inputs[index - 1].focus();  // Move to previous input if the current is backspaced
        }
    });
});

// Optional: Combine the values into a single string before form submission
document.getElementById('pin-form').addEventListener('submit', function (e) {
    const pin = Array.from(document.querySelectorAll('.pin-box'))
                             .map(input => input.value)
                             .join('');
    // Add the combined PIN value to the form before submission
    const pinInput = document.createElement('input');
    pinInput.type = 'hidden';
    pinInput.name = 'pin_code';
    pinInput.value = pin;
    this.appendChild(pinInput);
});

// Automatically focus on the first input field
document.getElementById('pin_digit_1').focus();

        // Show modal if the PIN is created successfully
        <?php if (isset($_SESSION['pin_success_message'])): ?>
            document.getElementById('successModal').style.display = 'flex';
            <?php unset($_SESSION['pin_success_message']); ?>
        <?php endif; ?>

        // Close the modal and redirect to inbox
        function closeModalAndProceed() {
            // Hide modal
            document.getElementById('successModal').style.display = 'none';
            // Redirect to logout.php
            window.location.href = 'logout.php';
        }
    </script>
</body>
</html>
