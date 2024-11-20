<?php
// Start the session
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection details
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

// Get the email ID from the URL
$email_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($email_id <= 0) {
    die('Invalid email ID.');
}

// Fetch the email details from the database
$stmt = $pdo->prepare('
    SELECT e.id, e.sender, e.recipient, e.subject, e.body, e.created_at, u.email AS sender_email
    FROM emails e
    JOIN register u ON e.user_id = u.id
    WHERE e.id = :id AND e.user_id = :user_id AND e.status = "archive"
');
$stmt->execute([':id' => $email_id, ':user_id' => $_SESSION['user_id']]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

// If the email doesn't exist, show an error
if (!$email) {
    die('Email not found.');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Archived Email - HueMail</title>
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
            background: url('images/huemail.jpg') no-repeat center center fixed;
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

        .profile-info {
            display: flex;
            align-items: center;
            margin-top: 20px;
        }

        .profile-info img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid #1a73e8;
            margin-right: 15px;
        }

        .profile-info .name {
            font-size: 16px;
            color: #555;
        }

        /* Restore Button */
        .restore-button {
            display: inline-flex;
            align-items: center;
            background-color: #4CAF50; /* Green color for restore */
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            border: 2px solid #4CAF50;
            margin-top: 20px;
        }

        .restore-button i {
            margin-right: 8px;
        }

        .restore-button:hover {
            background-color: #45a049;
            border-color: #45a049;
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

    </style>
</head>
<body>

<div class="email-container">
    <div class="email-header">
        <h2>Archived Email</h2>
        <a href="archive.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Archive</a>
    </div>

    <div class="email-details">
        <div class="recipient"><strong>From:</strong> <?= htmlspecialchars($email['sender']) ?></div>
        <div class="subject"><strong>Subject:</strong> <?= htmlspecialchars($email['subject']) ?></div>
        <div class="created_at"><strong>Archived On:</strong> <?= htmlspecialchars($email['created_at']) ?></div>
    </div>

    <div class="email-body"><?= nl2br(htmlspecialchars($email['body'])) ?></div>

    <!-- Restore Button -->
    <a href="restore_email.php?id=<?= $email_id ?>" class="restore-button"><i class="fas fa-undo"></i> Restore Email</a>

</div>

</body>
</html>
