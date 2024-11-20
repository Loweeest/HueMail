<?php
// Start the session
session_start();

// Ensure the user is logged in
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

// Get the email ID from the URL
$email_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($email_id <= 0) {
    die('Invalid email ID.');
}

// Fetch the draft details from the database
$stmt = $pdo->prepare('
    SELECT e.id, e.sender, e.recipient, e.subject, e.body, e.created_at, e.attachment, u.email AS sender_email
    FROM emails e
    JOIN users u ON e.user_id = u.id
    WHERE e.id = :id AND e.status = "draft" AND e.user_id = :user_id
');
$stmt->execute([':id' => $email_id, ':user_id' => $_SESSION['user_id']]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

// If the email doesn't exist, show an error
if (!$email) {
    die('Draft not found.');
}

// Split the attachment string into an array if available
$attachments = !empty($email['attachment']) ? explode(',', $email['attachment']) : [];

// Function to format file size into KB/MB
function formatFileSize($size) {
    if ($size >= 1048576) {
        return number_format($size / 1048576, 2) . ' MB';
    } elseif ($size >= 1024) {
        return number_format($size / 1024, 2) . ' KB';
    } else {
        return $size . ' bytes';
    }
}

// If the form is submitted, update the draft in the database
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = $_POST['recipient'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    
    // Handle file uploads if any
    $newAttachments = [];
    if (isset($_FILES['attachment']) && !empty($_FILES['attachment']['name'][0])) {
        foreach ($_FILES['attachment']['error'] as $key => $error) {
            if ($error === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['attachment']['tmp_name'][$key];
                $fileName = $_FILES['attachment']['name'][$key];
                $fileSize = $_FILES['attachment']['size'][$key];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Check file size (limit to 25MB)
                if ($fileSize > 25 * 1024 * 1024) {
                    $_SESSION['error_message'] = "File size exceeds the 25MB limit for file $fileName.";
                    header('Location: view_draft.php?id=' . $email_id);
                    exit;
                }

                // Move file to the upload directory
                $uploadDirectory = 'uploads/';
                $destPath = $uploadDirectory . $fileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $newAttachments[] = $destPath;
                }
            }
        }
    }

    // Combine new attachments with existing ones
    $allAttachments = array_merge($attachments, $newAttachments);

    // Convert the attachments array to a comma-separated string
    $attachmentString = implode(',', $allAttachments);

    // Update the draft in the database
    $updateStmt = $pdo->prepare('
        UPDATE emails SET recipient = ?, subject = ?, body = ?, attachment = ?, status = "draft"
        WHERE id = ? AND user_id = ?
    ');
    $updateStmt->execute([$recipient, $subject, $body, $attachmentString, $email_id, $_SESSION['user_id']]);

    // Redirect back to the compose page to continue editing
    header("Location: compose.php?id=$email_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Draft - HueMail</title>
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
        }

        .email-container {
            max-width: 900px;
            width: 70%;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 5px solid black;
            margin-top: 50px;
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
            margin-bottom: 20px;
        }

        .email-details label {
            font-weight: bold;
            color: #333;
        }

        .email-details input[type="email"], 
        .email-details input[type="text"], 
        .email-details textarea {
            width: 100%;
            padding: 12px;
            margin-top: 8px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-size: 16px;
            color: #333;
        }

        .email-details textarea {
            height: 200px;
            resize: none;
        }

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

        .button-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }

        .save-button, .continue-button {
            padding: 12px 24px;
            background-color: #1a73e8;
            color: white;
            font-size: 16px;
            border-radius: 5px;
            border: none;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .save-button:hover, .continue-button:hover {
            background-color: #0d5bbd;
        }

        .delete-button {
            display: inline-flex;
            align-items: center;
            background-color: #ff4d4d;
            color: white;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            border: 2px solid #ff4d4d;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .delete-button:hover {
            background-color: #ff1a1a;
            border-color: #ff1a1a;
        }
    </style>
</head>
<body>

<div class="email-container">
    <div class="email-header">
        <h2>Edit Draft</h2>
        <a href="draft.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Drafts</a>
    </div>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="email-details">
            <label for="recipient">To:</label>
            <input type="email" name="recipient" id="recipient" value="<?= htmlspecialchars($email['recipient']) ?>" required>

            <label for="subject">Subject:</label>
            <input type="text" name="subject" id="subject" value="<?= htmlspecialchars($email['subject']) ?>" required>

            <label for="body">Message:</label>
            <textarea name="body" id="body" required><?= htmlspecialchars($email['body']) ?></textarea>
        </div>

        <div class="attachments">
            <strong>Attachments:</strong>
            <ul>
                <?php foreach ($attachments as $attachment): ?>
                    <li><a href="<?= htmlspecialchars($attachment) ?>" target="_blank"><?= basename($attachment) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <label for="attachment">Add new attachment:</label>
            <input type="file" name="attachment[]" id="attachment" multiple>
        </div>

        <div class="button-container">
            <button type="submit" class="save-button">Save Draft</button>
            <a href="compose.php?id=<?= $email_id ?>" class="continue-button">Continue Composing</a>
        </div>
    </form>
</div>

</body>
</html>
