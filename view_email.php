<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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
    die("Database connection failed: " . $e->getMessage());
}

// Get email ID and folder from query string
$email_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$folder = isset($_GET['folder']) ? htmlspecialchars($_GET['folder']) : 'inbox';

// Fetch email details along with attachments
$stmt = $pdo->prepare('
    SELECT e.sender, e.recipient, e.subject, e.body, e.created_at, u.email AS sender_email, 
           a.file_name, a.file_path
    FROM emails e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN attachments a ON e.id = a.email_id  -- Join attachments table
    WHERE e.id = ? AND e.user_id = ?
');
$stmt->execute([$email_id, $_SESSION['user_id']]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$email) {
    die("Email not found or you do not have permission to view it.");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>View Email - HueMail</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: url('images/huemail.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            width: 80%;
            max-width: 900px;
            margin-top: 20px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 1.8em;
            color: black;
        }

        .email-details {
            margin-bottom: 20px;
        }

        .email-details p {
            margin: 8px 0;
            color: black;
            font-size: 16px;
        }

        .email-body {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 10px;
            border: 2px solid #000;
            color: black;
            white-space: pre-wrap; /* Allows for line breaks */
        }

        .button-container {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-btn, .delete-btn {
            background-color: #1a73e8;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none;
        }

        .back-btn:hover, .delete-btn:hover {
            background-color: #155ab6;
        }

        .delete-btn {
            background-color: #d93025;
        }

        .delete-btn:hover {
            background-color: #c62828;
        }

        .close-btn {
            background-color: #ff4d4d;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
            font-size: 1.2em;
        }

        .close-btn:hover {
            background-color: #d43f3f;
        }

        .attachment-links {
            margin-top: 10px;
            font-size: 16px;
            color: #007bff;
        }

        .attachment-links a {
            text-decoration: none;
            color: #007bff;
        }

        .attachment-links a:hover {
            text-decoration: underline;
        }

    </style>
    <script>
        function confirmDelete() {
            return confirm("Are you sure you want to delete this email?");
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Email Details</h1>
        </div>
        <div class="email-details">
            <p><strong>From:</strong> <?php echo htmlspecialchars($email['sender']); ?> (<?php echo htmlspecialchars($email['sender_email']); ?>)</p>
            <p><strong>To:</strong> <?php echo htmlspecialchars($email['recipient']); ?></p>
            <p><strong>Subject:</strong> <?php echo htmlspecialchars($email['subject']); ?></p>
            <p><strong>Date:</strong> <?php echo htmlspecialchars($email['created_at']); ?></p>
        </div>

        <p><strong>Message:</strong>
        <div class="email-body">
            <?php echo nl2br(htmlspecialchars($email['body'])); ?>
        </div>

        <?php if (!empty($email['file_name'])): ?>
            <div class="attachment-links">
                <strong>Attachments:</strong><br>
                <?php 
                    $attachments = explode(',', $email['file_name']);
                    foreach ($attachments as $attachment):
                        $file_path = 'uploads/' . htmlspecialchars($attachment);  // Adjust if your files are stored elsewhere
                ?>
                    <a href="<?php echo $file_path; ?>" target="_blank"><?php echo htmlspecialchars($attachment); ?></a><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="button-container">
            <a href="delete_email.php?id=<?php echo $email_id; ?>&folder=<?php echo urlencode($folder); ?>" class="delete-btn" onclick="return confirmDelete()">
                <i class="fas fa-trash"></i> Delete
            </a>
            <a href="inbox.php?folder=<?php echo urlencode($folder); ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Inbox
            </a>
        </div>
    </div>
</body>
</html>

