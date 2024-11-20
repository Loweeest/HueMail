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
    SELECT e.id, e.sender, e.recipient, e.subject, e.body, e.created_at, e.deleted_at, u.email AS sender_email
    FROM deleted_emails e
    JOIN register u ON e.user_id = u.id
    WHERE e.id = :id AND e.user_id = :user_id
');
$stmt->execute([':id' => $email_id, ':user_id' => $_SESSION['user_id']]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

// If the email doesn't exist, show an error
if (!$email) {
    die('Email not found.');
}

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

// Split the attachment string into an array if available
$attachments = !empty($email['attachment']) ? explode(',', $email['attachment']) : [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Deleted Email - HueMail</title>
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
            margin-top: 20px; /* Adds spacing above the button */
            border: 2px solid #ff4d4d; /* Matching border */
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .delete-button i {
            margin-right: 8px; /* Spacing between icon and text */
            font-size: 18px; /* Icon size */
        }

        .delete-button:hover {
            background-color: #ff1a1a; /* Darker red on hover */
            border-color: #ff1a1a; /* Darker border on hover */
            color: #fff; /* Ensure text stays white */
        }

        .delete-button:focus {
            outline: 2px solid #ff1a1a;
            outline-offset: 2px;
        }

/* Modal Background */
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
    position: relative;  /* Positioning context for the close button */
}

/* Close Button Styling */
.close-btn {
    color: white;  /* White text color */
    font-size: 28px;
    position: absolute;  /* Absolute position within the modal-cont ent */
    top: 10px;  /* Distance from top of the modal content */
    right: 5px;  /* Distance from the right of the modal content */
    cursor: pointer;
    z-index: 2;  /* Ensure it stays on top of modal content */
    background-color: red;  /* Red background */
    border-radius: 70%;  /* Make the button round */
    width: 25px;  /* Set width of the button */
    height: 25px;  /* Set height of the button */
    display: flex;  /* Use flexbox to center the "X" */
    align-items: center;  /* Vertically center the "X" */
    justify-content: center;  /* Horizontally center the "X" */
    text-align: center;  /* Align text */
    border: none;  /* Remove the default border */
    padding: 0;  /* Remove any padding */
}

.close-btn:hover,
.close-btn:focus {
    background-color: #cc0000;  /* Darker red on hover */
    color: white;
    text-decoration: none;
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
        <h2>Deleted Email</h2>
        <a href="trash.php" class="back-button"><i class="fas fa-arrow-left"></i>Back to Trash</a>
    </div>

    <div class="email-details">
        <div class="recipient"><strong>To:</strong> <?= htmlspecialchars($email['recipient']) ?></div>
        <div class="subject"><strong>Subject:</strong> <?= htmlspecialchars($email['subject']) ?></div>
        <div class="created_at"><strong>Deleted On:</strong> <?= htmlspecialchars($email['deleted_at']) ?></div>
    </div>

    <div class="email-body"><?= nl2br(htmlspecialchars($email['body'])) ?></div>


    <?php if (!empty($attachments)): ?>
    <div class="attachments">
        <strong>Attachments:</strong>
        <ul>
            <?php foreach ($attachments as $attachment): ?>
                <?php
                // Debugging output to check attachment name and file path
                echo "<p>Attachment: $attachment</p>"; // Shows the file name

                // Construct the full file path
                $filePath = "uploads/" . $attachment; // Adjust this path to where your files are stored

                // Check if file exists
                if (file_exists($filePath)) {
                    $fileSize = filesize($filePath); // Get the file size
                    $fileSizeFormatted = formatFileSize($fileSize); // Format the size
                    echo "<p>File exists: $filePath</p>"; // Debugging line to check file path
                    ?>
                    <li><a href="<?= htmlspecialchars($filePath) ?>" target="_blank"><?= basename($attachment) ?></a> (<?= $fileSizeFormatted ?>)</li>
                    <?php
                } else {
                    echo "<p>File not found: $filePath</p>"; // Shows if file is not found
                }
                ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>


    <!-- Restore Button that triggers the restore function -->
    <a href="restore_email.php?id=<?= $email_id ?>" class="restore-button"><i class="fas fa-undo"></i>Restore Email</a>

    <!-- Permanently Delete Button that triggers Modal -->
    <a href="#" class="delete-button" onclick="showModal(<?= $email_id ?>)"><i class="fas fa-trash"></i> Permanently Delete</a>
</div>

<!-- Modal for Confirmation -->
<div id="myModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <h2>Are you sure you want to permanently delete this email?</h2>
        <div class="modal-actions">
            <a href="#" class="cancel-btn" onclick="closeModal()">Cancel</a>
            <a href="delete_email.php?id=<?= $email_id ?>&permanent=true" class="delete-btn">Delete Permanently</a>
        </div>
    </div>
</div>

<script>
// JavaScript to handle modal visibility
function showModal(emailId) {
    document.getElementById('myModal').style.display = "block";
    document.querySelector('.delete-btn').href = 'delete_email.php?id=' + emailId + '&permanent=true';
}

function closeModal() {
    document.getElementById('myModal').style.display = "none";
}
</script>

</body>
</html>