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
    WHERE e.id = :id AND e.user_id = :user_id AND e.status = "spam"
');
$stmt->execute([':id' => $email_id, ':user_id' => $_SESSION['user_id']]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

// If the email doesn't exist, show an error
if (!$email) {
    die('Email not found.');
}

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


    <title>View Spam Email - HueMail</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Styles can be reused from view_archive.php */
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
            color: #f44336; /* Red for spam */
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

        /* Spam Action Buttons */
        .restore-button, .trash-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 20px;
        }

        .restore-button {
            background-color: #4CAF50;
            color: white;
            border: 2px solid #4CAF50;
        }

        .restore-button i {
            margin-right: 8px;
        }

        .restore-button:hover {
            background-color: #45a049;
            border-color: #45a049;
        }

        .trash-button {
            background-color: #f44336; /* Red for trash */
            color: white;
            border: 2px solid #f44336;
        }

        .trash-button i {
            margin-right: 8px;
        }

        .trash-button:hover {
            background-color: #e53935;
            border-color: #e53935;
        }
    </style>
</head>
<body>

<div class="email-container">
    <div class="email-header">
        <h2>Spam Email</h2>
        <a href="spam.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Spam</a>
    </div>

    <div class="email-details">
        <div class="recipient"><strong>From:</strong> <?= htmlspecialchars($email['sender']) ?></div>
        <div class="subject"><strong>Subject:</strong> <?= htmlspecialchars($email['subject']) ?></div>
        <div class="created_at"><strong>Spam Reported On:</strong> <?= htmlspecialchars($email['created_at']) ?></div>
    </div>

    <div class="email-body"><?= nl2br(htmlspecialchars($email['body'])) ?></div>

    <!-- Restore Button -->
    <a href="restore_from_spam.php?id=<?= $email_id ?>" class="restore-button"><i class="fas fa-undo"></i> Restore Email</a>

    <!-- Move to Trash Button -->
    <a href="move_to_trash.php?id=<?= $email_id ?>" class="trash-button"><i class="fas fa-trash"></i> Move to Trash</a>
</div>

</body>
</html>
