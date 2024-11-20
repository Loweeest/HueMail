<?php
session_start(); // Start the session at the beginning of the page

// Check if the user is logged in
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

// Capture the User-Agent and IP Address
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$ip_address = $_SERVER['REMOTE_ADDR'];

// Check if this device is registered
$stmt = $pdo->prepare('SELECT * FROM user_devices WHERE user_id = ? AND user_agent = ? AND ip_address = ?');
$stmt->execute([$user_id, $user_agent, $ip_address]);
$device_row = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if it's a new device
if (!$device_row) {
    $_SESSION['is_new_device'] = true;

    // Optionally, store the device in the database
    $stmt = $pdo->prepare('INSERT INTO user_devices (user_id, user_agent, ip_address, last_used) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$user_id, $user_agent, $ip_address]);
} else {
    $_SESSION['is_new_device'] = false;
}

// **Key Section: Check PIN Verification Status**
if (!isset($_SESSION['pin_verified']) || $_SESSION['pin_verified'] !== true) {
    // If the PIN is not verified, show the PIN modal
    $_SESSION['show_modal'] = true;
} else {
    // Otherwise, hide the PIN modal
    $_SESSION['show_modal'] = false;
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

// Check if the form has been submitted (PIN verification)
if (isset($_POST['pin'])) {
    $entered_pin = $_POST['pin']; // PIN entered by the user

    // Validate the entered PIN against the database
    if (password_verify($entered_pin, $pin_row['pin_code'])) {  // Assuming pin_code is stored as a hashed password
        // Correct PIN: Proceed
        $_SESSION['pin_verified'] = true;  // Mark the PIN as verified
        $_SESSION['show_modal'] = false;  // Hide the PIN modal

        // Send success response to frontend
        echo json_encode(['status' => 'success']);
    } else {
        // Invalid PIN, show error
        echo json_encode(['status' => 'error', 'message' => 'Wrong PIN. Please try again.']);
    }
    exit;
}

// If PIN exists, user can proceed to inbox or other sections
$valid_folders = ['inbox', 'unread', 'draft', 'sent', 'archive', 'trash', 'spam', 'starred'];
$folder = isset($_GET['folder']) ? $_GET['folder'] : 'inbox';
if (!in_array($folder, $valid_folders)) {
    $folder = 'inbox';
}

// Fetch emails based on the folder
if ($folder === 'starred') {
    // Fetch starred emails
    $stmt = $pdo->prepare('
        SELECT e.id, e.sender, e.recipient, e.subject, e.body, e.status, e.created_at, u.email AS sender_email
        FROM emails e
        JOIN starred_emails s ON e.id = s.email_id
        JOIN register u ON e.user_id = u.id
        WHERE s.user_id = ? 
        ORDER BY e.created_at DESC
    ');
    $stmt->execute([$user_id]);
} elseif ($folder === 'unread') {
    // Fetch unread emails
    $stmt = $pdo->prepare('
        SELECT e.id, e.sender, e.recipient, e.subject, e.body, e.status, e.created_at, u.email AS sender_email
        FROM emails e
        JOIN register u ON e.user_id = u.id
        WHERE e.user_id = ? AND e.status = "unread" 
        ORDER BY e.created_at DESC
    ');
    $stmt->execute([$user_id]);
} else {
    // Fetch emails for other folders
    $stmt = $pdo->prepare('
        SELECT e.id, e.sender, e.recipient, e.subject, e.body, e.status, e.created_at, u.email AS sender_email
        FROM emails e
        JOIN register u ON e.user_id = u.id
        WHERE e.user_id = ? AND e.status = ? 
        ORDER BY e.created_at DESC
    ');
    $stmt->execute([$user_id, $folder]);
}

$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's profile picture
$stmt = $pdo->prepare("SELECT profile_pic FROM register WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$default_profile_pic = 'images/pp.png'; // Default profile picture
$profile_pic_path = $user['profile_pic'] ?: $default_profile_pic;

// Fetch email counts for all folders
$email_counts = [];
$count_sql = '
    SELECT 
        COUNT(CASE WHEN e.status = "inbox" THEN 1 END) AS inbox,
        COUNT(CASE WHEN e.status = "unread" THEN 1 END) AS unread,
        COUNT(CASE WHEN e.status = "draft" THEN 1 END) AS draft,
        COUNT(CASE WHEN e.status = "sent" THEN 1 END) AS sent,
        COUNT(CASE WHEN e.status = "archive" THEN 1 END) AS archive,
        COUNT(CASE WHEN e.status = "spam" THEN 1 END) AS spam,
        (SELECT COUNT(*) FROM deleted_emails de WHERE de.user_id = e.user_id) AS trash,
        COUNT(s.email_id) AS starred
    FROM emails e
    LEFT JOIN starred_emails s ON e.id = s.email_id
    WHERE e.user_id = ?
';
$stmt = $pdo->prepare($count_sql);
$stmt->execute([$user_id]);
$email_counts = $stmt->fetch(PDO::FETCH_ASSOC);

// Search functionality
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
if ($search_query) {
    $stmt = $pdo->prepare('
        SELECT e.id, e.sender, e.recipient, e.subject, e.body, e.status, e.created_at, u.email AS sender_email
        FROM emails e
        JOIN register u ON e.user_id = u.id
        WHERE e.user_id = ? AND (e.subject LIKE ? OR e.sender LIKE ?)
        ORDER BY e.created_at DESC
    ');
    $stmt->execute([$user_id, "%$search_query%", "%$search_query%"]);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Link to Bootstrap CSS (locally) -->
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    
    <!-- Link to Font Awesome CSS (locally) -->
    <link rel="stylesheet" href="fontawesome-free-6.6.0-web/css/all.min.css">
    
    <title><?php echo ucfirst($folder); ?> - HueMail</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: url('images/mainbg.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            padding: 0;
            display: flex;
        }

        .side-panel {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            padding: 20px;
            width: 215px;
            max-width: 100%;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }

        /* Add hidden class to slide the side-panel out of view */
        .side-panel.hidden {
            transform: translateX(-100%);
        }

        /* Adjust the main content when the side panel is hidden */
        .main-content.shifted {
            margin-left: 0;
            max-width: 100%;
        }

        .side-panel .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .side-panel .header h1 {
            margin: 0;
            font-size: 2em;
            font-weight: bold;
        }

        .side-panel .navigation a {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            text-decoration: none;
            color: #1a73e8;
            /* Default link color */
            font-weight: 500;
            font-size: 25px;
            /* Increased font size */
        }

        .side-panel .navigation a i {
            margin-right: 8px;
            /* Space between icon and text */
            font-size: 1.2em;
            /* Adjust icon size */
        }

        .side-panel .navigation a.active {
            font-weight: bold;
            border-bottom: 2px solid black;
        }

        .side-panel .logout {
            background-color: #e53935;
            /* Red background color */
            color: #fff !important;
            /* White text color with !important */
            border: none;
            border-radius: 10px;
            padding: 2px 2px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1.5em;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .side-panel .logout:hover {
            background-color: #b71c1c;
            /* Darker red background color on hover */
        }

        .side-panel .logout i {
            margin-right: 1px;
            /* Space between icon and text */
        }

        .main-content {
            margin-left: 250px;
            /* Adjust based on the width of the side panel */
            padding: 5px;
            flex: 1;
            max-width: calc(100% - 270px);
            display: flex;
            flex-direction: column;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px 0;
            position: relative;
            /* Added for dropdown positioning */
        }

        .navbar .search-bar {
            flex: 1;
            margin: 0 20px;
        }

        .navbar .search-bar input {
            width: 98%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .navbar .profile {
            display: flex;
            align-items: center;
            position: relative;
            /* Added for dropdown positioning */
        }

        .navbar .profile img {
            /* Profile picture styling */
            border-radius: 50%;
            width: 70px;
            height: 70px;
            margin-right: 20px;
            cursor: pointer;
            border: 4px solid #1a73e8;
            /* Border color and width */
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            width: 200px;
        }

        .dropdown-menu a {
            display: block;
            padding: 10px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }

        .dropdown-menu a:hover {
            background-color: #f5f5f5;
        }

        .show {
            display: block;
        }

        /* Style the email table */
        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.9);
            /* Matches the modal background */
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            /* Ensures rounded corners are visible */
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        thead {
            background-color: #007bff;
            color: white;
        }

        th {
            font-weight: bold;
        }

        tbody tr:hover {
            background-color: #f5f5f5;
        }

        .email-subject {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        a {
            color: #007bff;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .fas {
            margin-right: 5px;
        }


        .email-body {
            color: #5f6368;
            font-size: 14px;
        }

        .no-emails-message {
            text-align: center;
            font-size: 1.2em;
            color: #555;
            margin-top: 20px;
        }

        .search-container {
            position: relative;
            width: 100%;
            /* Adjusted width to make it slightly larger */
            max-width: 500px;
            /* Optional: Set a max-width for better control on larger screens */
            margin: 0 auto;
            /* Center the search bar */
        }

        .search-container i {
            position: absolute;
            top: 50%;
            left: 15px;
            /* Adjusted for better spacing from the left edge */
            transform: translateY(-50%);
            font-size: 18px;
            /* Slightly larger icon */
            color: black;
            /* Icon color */
        }

        .search-container input {
            width: 100%;
            padding: 10px 20px 10px 40px;
            /* Adjusted padding for better balance */
            border: 2px solid black;
            /* Thinner border for a more modern look */
            border-radius: 8px;
            /* Increased border-radius for rounded corners */
            font-size: 16px;
            /* Larger font size for better readability */
            box-sizing: border-box;
            /* Ensure padding and border are included in the total width */
        }


        /* Basic Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: url('images/mainbg.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #333;
            line-height: 1.6;
            margin: 0;
        }

        /* Style for folder count next to the folder name */
        .folder-count {
            margin-left: 5px;
            /* Space between folder name and count */
            font-size: 1em;
            /* Adjust size as needed */
            color: #e53935;
            /* Color for the count */
            font-weight: bold;
            /* Make the count bold */
        }

        a:hover .folder-count {
            color: #007bff;
            /* Color on hover */
        }
    </style>
</head>

<body>
    <div class="side-panel">
        <div class="header">
            <h1><?php echo ucfirst($folder); ?></h1>
        </div>
        <div class="navigation">
            <a href="compose.php"><i class="fas fa-pencil-alt"></i> Compose</a>

            <a href="inbox.php" class="active">
                <i class="fas fa-inbox"></i> Inbox
                <span class="folder-count"> <?php echo $email_counts['inbox']; ?></span>
            </a>

            <a href="starred.php" class="<?php echo $folder === 'unread' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> Starred
                <span class="folder-count"> <?php echo $email_counts['starred']; ?></span>
            </a>

            <a href="unread.php" class="<?php echo $folder === 'unread' ? 'active' : ''; ?>">
                <i class="fas fa-envelope-open-text"></i> Unread
                <span class="folder-count"> <?php echo $email_counts['unread']; ?></span>
            </a>

            <a href="sent.php" class="<?php echo $folder === 'sent' ? 'active' : ''; ?>">
                <i class="fas fa-paper-plane"></i> Sent
                <span class="folder-count"> <?php echo $email_counts['sent']; ?></span>
            </a>

            <a href="draft.php" class="<?php echo $folder === 'draft' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Drafts
                <span class="folder-count"> <?php echo $email_counts['draft']; ?></span>
            </a>

            <a href="archive.php" class="<?php echo $folder === 'archive' ? 'active' : ''; ?>">
                <i class="fas fa-archive"></i> Archive
                <span class="folder-count"> <?php echo $email_counts['archive']; ?></span>
            </a>

            <a href="spam.php" class="<?php echo $folder === 'spam' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i> Spam
                <span class="folder-count"> <?php echo $email_counts['spam']; ?></span>
            </a>

            <a href="trash.php" class="<?php echo $folder === 'trash' ? 'active' : ''; ?>">
                <i class="fas fa-trash"></i> Trash
                <span class="folder-count"> <?php echo $email_counts['trash']; ?></span>
            </a>
        </div>

        <a href="logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="main-content">
        <div class="navbar">
        <i class="fas fa-arrow-left" id="toggle-icon" style="font-size: 1.5em; cursor: pointer; margin-right: 1em; color: white"></i>
            <div class="search-container">
                <form method="get" action="">
                    <input type="text" name="search" placeholder="Search emails..." id="search-bar" value="<?php echo htmlspecialchars($search_query); ?>">
                    <i class="fas fa-search"></i>
                </form>
            </div>
            <div class="profile">
                <img src="<?php echo htmlspecialchars($profile_pic_path); ?>" alt="Profile Picture">
                <div class="dropdown-menu" id="dropdown-menu">
                    <a href="add_profile.php">Profile Settings</a>
                    <a href="account_settings.php">Account Settings</a>
                    <a href="change_password.php">Change Password</a>
                    <a href="privacy.php">Privacy Policy</a>
                    <a href="terms.php">Terms of Service</a>
                    <a href="team.php">Meet The Team!</a>
                </div>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Actions</th>
                    <th>Sender</th>
                    <th>Subject</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody id="email-list">
                <?php
                if (count($emails) == 0) {
                    echo '<tr><td colspan="4" class="no-emails-message">Yay! No emails found.</td></tr>';
                } else {
                    foreach ($emails as $email) {
                        echo '<tr>';
                        echo '<td><a href="view.php?id=' . $email['id'] . '">View</a></td>';
                        echo '<td>' . htmlspecialchars($email['sender_email']) . '</td>';
                        echo '<td class="email-subject">' . htmlspecialchars($email['subject']) . '</td>';
                        echo '<td>' . htmlspecialchars($email['created_at']) . '</td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>


    <script>
        // Example JavaScript to handle dropdown
        document.querySelector('.profile img').addEventListener('click', function() {
            const dropdown = document.querySelector('.dropdown-menu');
            dropdown.classList.toggle('show');
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // WebSocket connection to receive new emails
            const ws = new WebSocket('ws://localhost:8081/email');

            // When WebSocket connection opens
            ws.onopen = function() {
                console.log("Connected to WebSocket server.");
            };

            // Handle WebSocket errors
            ws.onerror = function(error) {
                console.error("WebSocket error:", error);
            };

            // When a new email is received via WebSocket
            ws.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('Received data:', data);

                    if (data.type === 'new_email') {
                        const newEmail = data.message;
                        console.log('New email:', newEmail);

                        // Check that necessary data is available
                        if (!newEmail || !newEmail.id || !newEmail.subject || !newEmail.sender_email || !newEmail.created_at) {
                            console.error('Email data is missing expected fields:', newEmail);
                            return;
                        }

                        // Format date for displaying
                        const createdAt = new Date(newEmail.created_at).toLocaleString();

                        // Create a new row for the email
                        const emailList = document.getElementById('email-list');
                        const newRow = document.createElement('tr');

                        // Insert email data into the row
                        newRow.innerHTML = `
                    <td><a href="view.php?id=${newEmail.id}">View</a></td>
                    <td>${newEmail.sender_email}</td>
                    <td class="email-subject">${newEmail.subject}</td>
                    <td>${createdAt}</td>
                `;

                        // Insert the new row at the top of the email list (newest first)
                        emailList.insertBefore(newRow, emailList.firstChild);

                    }
                } catch (error) {
                    console.error('Error parsing WebSocket message:', error);
                }
            };
        });
    </script>
    <style>
      /* Basic Modal Styling */
.modal {
    display: none; 
    position: fixed;
    z-index: 999;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
    transition: all 0.3s ease-in-out;
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 30px;
    border-radius: 8px;
    width: 400px;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    text-align: center;
}

h2 {
    font-size: 20px;
    color: #333;
    text-align: center;
}

/* PIN Input Styling */
.pin-input-container {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}

.pin-input-container input {
    width: 50px;
    height: 50px;
    text-align: center;
    font-size: 24px;
    border: 2px solid #ccc;
    border-radius: 8px;
    background-color: #f9f9f9;
    outline: none;
}

.pin-input-container input:focus {
    border-color: #4CAF50;
}

/* Change input type to password to display asterisks */
.pin-input-container input[type="password"] {
    -webkit-text-security: circle; /* For WebKit-based browsers */
    -text-security: circle; /* For non-WebKit browsers */
}

/* Button Styling */
.submit-btn {
    width: 100%;
    padding: 12px;
    background-color: #4CAF50;
    border: none;
    border-radius: 4px;
    color: white;
    font-size: 16px;
    cursor: pointer;
    margin-top: 20px;
}

.submit-btn:hover {
    background-color: #45a049;
}

/* Error message styling */
.error-message {
    color: red;
    text-align: center;
    font-size: 14px;
    margin-top: 10px;
    display: none; /* Hidden by default */
}

/* Sync Modal Styling */
.sync-modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 30px;
    border-radius: 8px;
    width: 400px;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.sync-submit-btn {
    width: 100%;
    padding: 12px;
    background-color: #4CAF50;
    border: none;
    border-radius: 4px;
    color: white;
    font-size: 16px;
    cursor: pointer;
    margin-top: 20px;
}

.sync-submit-btn:hover {
    background-color: #45a049;
}

    </style>
    
<!-- PIN Modal HTML -->
<div id="pinModal" class="modal" role="dialog" aria-labelledby="pinModalTitle" aria-hidden="true">
    <div class="modal-content">
        <h2 id="pinModalTitle">Enter PIN to sync your messages</h2>
        <form method="POST" id="pinForm">
            <div class="pin-input-container">
                <input type="password" name="pin_digit_1" maxlength="1" id="pin_digit_1" class="pin-box" autocomplete="off" autofocus>
                <input type="password" name="pin_digit_2" maxlength="1" id="pin_digit_2" class="pin-box" autocomplete="off">
                <input type="password" name="pin_digit_3" maxlength="1" id="pin_digit_3" class="pin-box" autocomplete="off">
                <input type="password" name="pin_digit_4" maxlength="1" id="pin_digit_4" class="pin-box" autocomplete="off">
            </div>
            <button type="submit" class="submit-btn">Submit</button>
        </form>
        <p id="error-message" class="error-message"></p>
    </div>
</div>

<!-- Email Sync Modal HTML -->
<div id="syncModal" class="modal">
    <div class="sync-modal-content">
        <h2>Email Sync</h2>
        <p>Your messages are syncing now...</p>
        <button class="sync-submit-btn" onclick="closeSyncModal()">OK</button>
    </div>
</div>

<script>
window.onload = function() {
    const pinModal = document.getElementById('pinModal');
    if (<?php echo $_SESSION['show_modal'] ?? 'false'; ?>) {
        pinModal.style.display = "block";
    }
};

// Continuous typing for PIN input fields
document.querySelectorAll('.pin-box').forEach((input, index, inputs) => {
    input.addEventListener('input', function () {
        // If the current input has a value and it's not the last input field
        if (this.value.length === 1 && index < inputs.length - 1) {
            // Focus on the next input field
            inputs[index + 1].focus();
        }

        // If the user backspaces and it's not the first input field
        if (this.value.length === 0 && index > 0) {
            // Focus on the previous input field
            inputs[index - 1].focus();
        }
    });
});

// Handle form submission for PIN validation
document.getElementById('pinForm').addEventListener('submit', function(event) {
    event.preventDefault();  // Prevent the form from submitting normally

    // Gather the entered PIN from the inputs
    let enteredPin = '';
    for (let i = 1; i <= 4; i++) {
        enteredPin += document.getElementById('pin_digit_' + i).value;
    }

    // Send the PIN to the server for validation using AJAX
    fetch('inbox.php', {
        method: 'POST',
        body: new URLSearchParams({ pin: enteredPin }),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Hide the PIN modal and show the sync modal
            document.getElementById('pinModal').style.display = 'none';
            document.getElementById('syncModal').style.display = 'block';
        } else {
            // Show error message if PIN is invalid
            const errorMessage = document.getElementById('error-message');
            errorMessage.innerText = data.message; // Set the message
            errorMessage.style.display = 'block';  // Show the error message
        }
    })
    .catch(error => console.error('Error:', error));
});

// Close the sync modal
function closeSyncModal() {
    document.getElementById('syncModal').style.display = 'none';
}
</script>

 <!-- Link to Bootstrap JS (locally) -->
 <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

 <script>
    const toggleIcon = document.getElementById('toggle-icon');
    const sidePanel = document.querySelector('.side-panel');
    const mainContent = document.querySelector('.main-content'); // Adjust selector if needed

    toggleIcon.addEventListener('click', function () {
        sidePanel.classList.toggle('hidden');
        mainContent.classList.toggle('shifted');

        // Update the icon
        if (sidePanel.classList.contains('hidden')) {
            toggleIcon.classList.remove('fa-arrow-left');
            toggleIcon.classList.add('fa-arrow-right');
        } else {
            toggleIcon.classList.remove('fa-arrow-right');
            toggleIcon.classList.add('fa-arrow-left');
        }
    });
</script>

</body>
</html> 