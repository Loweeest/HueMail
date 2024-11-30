<?php
// Ensure the user is authenticated
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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

$user_id = $_SESSION['user_id'];
$email_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($email_id <= 0) {
    // Invalid email ID
    header('Location: unread.php');
    exit;
}

// Query to fetch the unread email
$stmt = $pdo->prepare('
    SELECT e.id, e.sender, e.recipient, e.subject, e.body, e.created_at, 
           u1.email AS sender_email, u1.first_name AS sender_first_name, u1.middle_name AS sender_middle_name, u1.last_name AS sender_last_name,
           u2.first_name AS recipient_first_name, u2.middle_name AS recipient_middle_name, u2.last_name AS recipient_last_name,
           e.attachment
    FROM inbox_emails e
    JOIN users u1 ON e.sender = u1.email
    JOIN users u2 ON e.recipient = u2.email
    WHERE e.id = :email_id AND e.user_id = :user_id AND e.status = "unread"
');
$stmt->execute([':email_id' => $email_id, ':user_id' => $user_id]);

$email = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$email) {
    // Email not found or already marked as read
    header('Location: unread.php');
    exit;
}

// Mark the email as read
$stmt = $pdo->prepare('UPDATE inbox_emails SET status = "read" WHERE id = :email_id');
$stmt->execute([':email_id' => $email_id]);

// Fetch the updated profile picture
$stmt = $pdo->prepare('SELECT profile_pic FROM register WHERE id = :user_id');
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$default_profile_pic = 'images/pp.png';
$profile_pic_path = $user['profile_pic'] ?: $default_profile_pic;

$stmt = $pdo->prepare("SELECT background_image FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
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
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.6.0-web/css/all.min.css">
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

    <body style="background: url('<?php echo $background_image_url; ?>') no-repeat center center fixed; background-size: cover;">

    
    <title>View Email - HueMail</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Reset Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: url('images/mainbg.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            height: 100vh;
            padding-top: 40px;
        }

        .email-container {
            max-width: 900px;
            width: 100%;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 5px solid black;
            margin-top: 120px;
        }

        .email-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 20px;
        }

        .email-header h2 {
            font-size: 1.8em;
            color: #1a73e8;
            font-weight: 600;
        }

        .back-button {
            background-color: #1a73e8;
            color: white;
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .back-button:hover {
            background-color: #0d5bbd;
        }

        .back-button i {
            margin-right: 8px;
        }

        .email-details {
            font-size: 16px;
            line-height: 1.6;
        }

        .email-details strong {
            color: #555;
        }

        .email-details .recipient,
        .email-details .subject {
            margin-bottom: 15px;
        }

        .email-body {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin-top: 30px;
        }

        .email-body p {
            margin-bottom: 20px;
        }

        /* Attachments Section */
        .attachments {
            margin-top: 20px;
        }

        .attachments a {
            color: #1a73e8;
            text-decoration: none;
        }

        .attachments a:hover {
            text-decoration: underline;
        }

        /* Delete Button Styles */
        .delete-button {
            display: inline-flex;
            align-items: center;
            background-color: #ff4d4d; /* Red color for delete action */
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 20px;
            margin-left: 5px; /* Space between buttons */

            border: 2px solid #ff4d4d;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .delete-button i {
            margin-right: 8px;
        }

        .delete-button:hover {
            background-color: #ff1a1a;
            border-color: #ff1a1a;
        }

        /* Archive Button Styles */
        .archive-btn {
            display: inline-flex;
            align-items: center;
            background-color: #1a73e8; /* Blue color for archive action */
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            margin-left: 5px; /* Space between buttons */
            border: 2px solid #1a73e8; /* Matching border */
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .archive-btn i {
            margin-right: 8px; /* Spacing between icon and text */
        }

        .archive-btn:hover {
            background-color: #0d5bbd; /* Darker blue on hover */
            border-color: #0d5bbd; /* Darker border on hover */
        }

        /* Spam Button Styles */
        .spam-btn {
            display: inline-flex;
            align-items: center;
            background-color: #ff9900; /* Orange color for spam action */
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            margin-left: 5px; /* Space between buttons */
            border: 2px solid #ff9900; /* Matching border */
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .spam-btn i {
            margin-right: 8px; /* Spacing between icon and text */
        }

        .spam-btn:hover {
            background-color: #e68a00; /* Darker orange on hover */
            border-color: #e68a00; /* Darker border on hover */
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            padding-top: 60px;
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 40%;
            border: 2px solid #ddd;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .close-btn {
            color: white;
            font-size: 28px;
            position: absolute;
            top: 10px;
            right: 5px;
            cursor: pointer;
            background-color: red;
            border-radius: 70%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            border: none;
            padding: 0;
        }

        .close-btn:hover {
            background-color: #cc0000;
        }

        .modal-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .cancel-btn {
            background-color: #ccc;
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }

        .cancel-btn:hover {
            background-color: #aaa;
        }

        .delete-btn {
            background-color: #ff4d4d;
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            border: 2px solid #ff4d4d;
        }

        .delete-btn:hover {
            background-color: #ff1a1a;
            border-color: #ff1a1a;
        }

        .email-body p {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>


    <div class="email-container">
<div class="email-header">
<h2><?php echo htmlspecialchars($email['subject']); ?></h2>
<div style="display: flex; align-items: center;">
    <a href="starred.php?id=<?= $email_id ?>" class="star-button" style="margin-right: 20px;">
    <i class="fas fa-star"></i> <!-- Star Icon -->
</a>
        </a>
        <a href="inbox.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Inbox</a>

    </div>
</div>


    <div class="email-details">
        <p><strong>From:</strong> <?php echo htmlspecialchars($email['sender_first_name'] . ' ' . $email['sender_middle_name'] . ' ' . $email['sender_last_name']); ?></p>
        <p><strong>To:</strong> <?php echo htmlspecialchars($email['recipient_first_name'] . ' ' . $email['recipient_middle_name'] . ' ' . $email['recipient_last_name']); ?></p>
        <p><strong>Subject:</strong> <?php echo htmlspecialchars($email['subject']); ?></p>

        <p><strong>Sent at:</strong> <?php echo htmlspecialchars($email['created_at']); ?></p>
    </div>

    <br>
        <strong>Message:</strong>
        <div class="email-body">
    <?= isset($email['body']) ? htmlspecialchars($email['body']) : 'No content available.' ?>
</div>


    <div class="attachments">
        <strong>Attachments:</strong>
        <ul>
            <?php if (!empty($email['attachment'])): ?>
                <li><a href="attachments/<?php echo htmlspecialchars($email['attachment']); ?>" download><?php echo htmlspecialchars($email['attachment']); ?></a></li>
            <?php else: ?>
                <li>No attachments</li>
            <?php endif; ?>
        </ul>
    </div>

    <a href="#" class="delete-button" onclick="confirmDelete(event, <?php echo $email_id; ?>)"><i class="fas fa-trash-alt"></i> Delete</a>
    <a href="archive.php?id=<?php echo $email_id; ?>" class="archive-btn"><i class="fas fa-archive"></i> Archive</a>
    <a href="mark_as_spam.php?id=<?php echo $email_id; ?>" class="spam-btn"><i class="fas fa-exclamation-triangle"></i> Mark as Spam</a>
</div>

<script>
    function confirmDelete(event, emailId) {
        event.preventDefault();
        if (confirm('Are you sure you want to delete this email?')) {
            window.location.href = 'delete_email.php?id=' + emailId;
        }
    }
</script>

</body>
</html>
