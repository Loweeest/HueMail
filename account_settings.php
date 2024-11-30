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

$error_message = '';
$success_message = '';

// Check if user profile is updated
$stmt = $pdo->prepare('SELECT account_updated, first_name, middle_name, last_name, gender, birthdate, email FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('User not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input manually (avoiding deprecated FILTER_SANITIZE_STRING)
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $gender = trim($_POST['gender']);
    $birthdate = trim($_POST['birthdate']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

    // Validation
    if (!preg_match("/^[a-zA-Z]+(?: [a-zA-Z]+)*$/", $first_name)) {
        $error_message = 'First name must contain only letters.';
    } elseif (!preg_match("/^[a-zA-Z]+(?: [a-zA-Z]+)*$/", $last_name)) {
        $error_message = 'Last name must contain only letters.';
    } elseif (!empty($middle_name) && !preg_match("/^[a-zA-Z]+(?: [a-zA-Z]+)*$/", $middle_name)) {
        $error_message = 'Middle name must contain only letters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format.';
    } elseif (empty($first_name) || empty($last_name) || empty($gender) || empty($birthdate)) {
        $error_message = 'Please fill out all required fields.';
    } else {
        // Birthdate Validation
        try {
            $birthdate = new DateTime($birthdate);
            $today = new DateTime('today');
            $age = $birthdate->diff($today)->y;
            
            if ($age < 13) {
                $error_message = 'You must be at least 13 years old to register.';
            } elseif ($birthdate->format('Y-m-d') === $today->format('Y-m-d')) {
                $error_message = 'Birthdate cannot be today.';
            }
        } catch (Exception $e) {
            $error_message = 'Invalid birthdate format.';
        }

        // Check for email uniqueness
        if (!$error_message && $user['email'] !== $email) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = 'Email address is already in use.';
            }
        }
        if (!$error_message) {
            $pdo->beginTransaction();
            try {
                // Update user details, including gender and profile picture
                $stmt = $pdo->prepare('UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, gender = ?, birthdate = ?, account_updated = TRUE WHERE id = ?');
                $stmt->execute([$first_name, $middle_name, $last_name, $gender, $birthdate->format('Y-m-d'), $_SESSION['user_id']]);

                // Determine profile picture based on gender
                $profile_pic_path = 'images/pp.png'; // Default
                if ($gender === 'Male') {
                    $profile_pic_path = 'images/male.png';
                } elseif ($gender === 'Female') {
                    $profile_pic_path = 'images/female.png';
                } elseif ($gender === 'Other') {
                    $profile_pic_path = 'images/pp.png';
                }

                // Check and update profile picture if necessary
                if ($user['profile_pic'] !== $profile_pic_path) {
                    $stmt = $pdo->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
                    $stmt->execute([$profile_pic_path, $_SESSION['user_id']]);

                    // Optionally update 'register' table if needed
                    $stmt = $pdo->prepare('UPDATE register SET profile_pic = ? WHERE id = ?');
                    $stmt->execute([$profile_pic_path, $_SESSION['user_id']]);
                }

                // Update email if necessary
                if ($user['email'] !== $email) {
                    $stmt = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
                    $stmt->execute([$email, $_SESSION['user_id']]);

                    $stmt = $pdo->prepare('UPDATE register SET email = ? WHERE id = ?');
                    $stmt->execute([$email, $_SESSION['user_id']]);
                }

                // Commit transaction and redirect
                $pdo->commit();
                $_SESSION['success_message'] = 'Account settings updated successfully!';
                header('Location: account_settings.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Error updating account settings: ' . $e->getMessage());
                $error_message = 'Failed to update account settings. Please try again later.';
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
    <title>Account Settings</title>
    <!-- Link to Bootstrap CSS (locally) -->
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    
    <!-- Link to Font Awesome CSS (locally) -->
    <link rel="stylesheet" href="fontawesome-free-6.6.0-web/css/all.min.css">

 <!-- Link to Bootstrap JS (locally) -->
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

    <body style="background: url('<?php echo $background_image_url; ?>') no-repeat center center fixed; background-size: cover;">

        
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
    background: rgba(255, 255, 255, 0.9);
    border-radius: 12px;
    border: 5px solid white; /* Light gray border */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    padding: 25px;
    max-width: 800px;
    width: 100%;
    text-align: center;
    margin: auto;
    margin-top: auto;
    overflow: hidden;
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

.close-btn:hover {
    background: #ff2d2d;
}

h1 {
    margin-bottom: 20px;
    color: #333;
    font-size: 28px;
    font-weight: bold;
}

.default-message {
    color: #d9534f; /* Red for warning */
    font-size: 16px;
    margin-bottom: 15px;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    margin-bottom: 20px;
}

.form-column {
    flex: 1;
    min-width: calc(50% - 10px);
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
    font-weight: 600;
}

input[type="text"],
input[type="email"],
input[type="date"],
select {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-sizing: border-box;
    font-size: 16px;
    background-color: #f9f9f9;
    transition: border 0.3s ease;
}

input[type="text"]:focus,
input[type="email"]:focus,
input[type="date"]:focus,
select:focus {
    border-color: #66afe9;
    outline: none;
}

button {
    width: 15%;
    padding: 12px;
    background-color: #28a745;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 20px;
    cursor: pointer;
    transition: background-color 0.3s;
    margin-top: 15px;
}

button:hover {
    background-color: #218838;
}

button:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

.error-message,
.success-message {
    font-size: 18px;
    margin-bottom: 15px;
}

.error-message {
    color: #ff4081; /* Pink/red */
}

.success-message {
    background-color: #d4edda;
            color: #155724;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
}}

@media (max-width: 768px) {
    .container {
        width: 90%;
        margin-top: 30px;
        padding: 20px;
    }

    .form-column {
        min-width: 100%;
        margin-right: 0;
    }

    button {
        width: 50%;
    }
}
    </style>

<script>
// Validate the entire form
function validateForm(event) {
    let valid = true;

    // Run individual field validations and aggregate the results
    if (!validateFirstName()) valid = false;
    if (!validateMiddleName()) valid = false;
    if (!validateLastName()) valid = false;
    if (!validateBirthdate()) valid = false;
    if (!validateEmail()) valid = false;

    // If any validation failed, prevent form submission
    if (!valid) {
        event.preventDefault(); // Stop form submission
    }
    return valid;
}

// Validate First Name
function validateFirstName() {
    const firstNameInput = document.getElementById('first_name');
    const firstNameError = document.getElementById('first-name-error');
    const firstNameValue = firstNameInput.value.trim();
    const firstNamePattern = /^[a-zA-Z]+(?:\s[a-zA-Z]+)*$/;

    // Validate: Length check and pattern match (only letters and spaces)
    if (firstNameValue.length < 2 || !firstNamePattern.test(firstNameValue)) {
        firstNameError.style.display = 'block';
        firstNameError.textContent = 'Must be at least 2 characters long and contain only letters.';
        return false; // Invalid
    } else {
        firstNameError.style.display = 'none';
        firstNameError.textContent = '';
        return true; // Valid
    }
}

// Validate Middle Name
function validateMiddleName() {
    const middleNameInput = document.getElementById('middle_name');
    const middleNameError = document.getElementById('middle-name-error');
    const middleNameValue = middleNameInput.value.trim();
    const middleNamePattern = /^[a-zA-Z]+(?:\s[a-zA-Z]+)*$/;

    // If middle name is filled, check length and pattern
    if (middleNameValue && (middleNameValue.length < 2 || !middleNamePattern.test(middleNameValue))) {
        middleNameError.style.display = 'block';
        middleNameError.textContent = 'Must be at least 2 characters long and contain only letters.';
        return false; // Invalid
    } else {
        middleNameError.style.display = 'none';
        middleNameError.textContent = '';
        return true; // Valid
    }
}


// Validate Last Name
function validateLastName() {
    const lastNameInput = document.getElementById('last_name');
    const lastNameError = document.getElementById('last-name-error');
    const lastNameValue = lastNameInput.value.trim();
    const lastNamePattern = /^[a-zA-Z]+(?:\s[a-zA-Z]+)*$/;

    // Validate: Length check and pattern match (only letters and spaces)
    if (lastNameValue.length < 2 || !lastNamePattern.test(lastNameValue)) {
        lastNameError.style.display = 'block';
        lastNameError.textContent = 'Must be at least 2 characters long and contain only letters.';
        return false; // Invalid
    } else {
        lastNameError.style.display = 'none';
        lastNameError.textContent = '';
        return true; // Valid
    }
}

// Validate Birthdate (Check for minimum age and 4-digit year)
function validateBirthdate() {
    const birthdateInput = document.getElementById('birthdate');
    const birthdateError = document.getElementById('birthdate-error');
    const birthdateValue = birthdateInput.value.trim();

    // Only validate if the field is not empty
    if (birthdateValue) {
        const birthdate = new Date(birthdateValue);
        const today = new Date();
        const age = today.getFullYear() - birthdate.getFullYear();

        // Check if the year is not more than 4 digits
        const year = birthdate.getFullYear();
        if (year.toString().length !== 4) {
            birthdateError.style.display = 'block';
            birthdateError.textContent = 'Please enter a valid year with 4 digits.';
            return false;
        }

        // Minimum age check (13 years old)
        if (age < 13) {
            birthdateError.style.display = 'block';
            birthdateError.textContent = 'You must be 13 years or older to use this email app.';
            return false;
        } else {
            birthdateError.style.display = 'none';
            birthdateError.textContent = '';
            return true; // Valid date and age
        }
    }

    // If birthdate is empty, clear error
    birthdateError.style.display = 'none';
    birthdateError.textContent = '';
    return true;
}


// Validate Email
function validateEmail() {
    const emailInput = document.getElementById('email');
    const emailError = document.getElementById('email-error');
    const emailValue = emailInput.value.trim();

    // Email validation pattern for `@huemail.com` domain
    const emailPattern = /^[a-zA-Z0-9._%+-]+@huemail\.com$/i;

    if (!emailValue || !emailPattern.test(emailValue)) {
        emailError.style.display = 'block';
        emailError.textContent = 'Email must end with @huemail.com.';
        return false; // Invalid
    } else {
        emailError.style.display = 'none';
        emailError.textContent = '';
        return true; // Valid
    }
}

// Focus handler for input fields
function handleFocus(event) {
    const errorElement = document.getElementById(event.target.id + '-error');
    errorElement.style.display = 'none'; // Hide the error message when the user focuses on the input field
}

    </script>

</head>
<body>

<div class="container">
    <button class="close-btn" onclick="window.location.href='index.php';">&times;</button>

    <h1>Account Settings</h1>

    <!-- Default Profile Update Message -->
    <?php if (!$user['account_updated']): ?>
        <div class="default-message">
            <p>Update your profile account first, so that you can send email to anyone.</p>
        </div>
    <?php endif; ?>

    <!-- Success Message Display -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="success-message"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
        <?php unset($_SESSION['success_message']); // Clear the message after displaying it ?>
    <?php endif; ?>

    <!-- Error Message Display -->
    <?php if ($error_message): ?>
        <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <form method="post" onsubmit="return validateForm(event)">
    <div class="form-row">
        <div class="form-column">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required oninput="handleInput(event)" onfocus="handleFocus(event)">
            <div id="first-name-error" class="error-message" style="display: none;"></div>
        </div>
        <div class="form-column">
            <label for="middle_name">Middle Name</label>
            <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($user['middle_name']); ?>" oninput="handleInput(event)" onfocus="handleFocus(event)">
            <div id="middle-name-error" class="error-message" style="display: none;"></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-column">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required oninput="handleInput(event)" onfocus="handleFocus(event)">
            <div id="last-name-error" class="error-message" style="display: none;"></div>
        </div>
        <div class="form-column">
            <label for="gender">Gender</label>
            <select id="gender" name="gender" required>
                <option value="Male" <?php echo $user['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo $user['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo $user['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-column">
            <label for="birthdate">Birthdate</label>
            <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($user['birthdate']); ?>" required oninput="validateBirthdate()" onfocus="handleFocus(event)">
            <div id="birthdate-error" class="error-message" style="display: none;"></div>
        </div>
        <div class="form-column">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required oninput="validateEmail()" onfocus="handleFocus(event)">
            <div id="email-error" class="error-message" style="display: none;"></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-column">
            <button type="submit">Submit</button>
        </div>
    </div>
</form>

<div class="form-row">
        <div class="form-column">
            <p>Need to change your password? <a href="change_password.php">Click here.</a></p>
            <p>Bored of the background? <a href="background_images.php">Change here.</a></p>
        </div>
    </div>
</form>

<script>
// Function to capitalize the first letter of each word in the input
function capitalizeFirstLetterOfEachWord(inputId) {
    const inputField = document.getElementById(inputId);
    let value = inputField.value;

    // Split the input string into words, capitalize the first letter of each word
    value = value.split(' ').map(word => {
        if (word.length > 0) {
            return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        }
        return word;
    }).join(' ');

    // Set the updated value back to the input field
    inputField.value = value;
}

// Attach the capitalize function to the input fields
function handleInput(event) {
    capitalizeFirstLetterOfEachWord(event.target.id);
}
</script>


</body>
</html>
