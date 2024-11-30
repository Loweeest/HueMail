<?php
session_start(); // Start the session at the beginning of the page

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'vendor/autoload.php'; // Ensure autoload for the WebSocket client is available
use WebSocket\Client;

$user_id = $_SESSION['user_id'];

// Database connection setup
$host = 'localhost';
$db   = 'HueMail';
$user = 'root';
$pass = '';

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

// Check if the user's account has been updated
$stmt = $pdo->prepare("SELECT account_updated FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !$user['account_updated']) {
    header('Location: account_settings.php');
    exit;
}

// CSRF Token Setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Check if the email ID is provided (for editing a draft)
$email_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$email = null;

// If an email ID is provided, fetch the draft email details
if ($email_id > 0) {
    $stmt = $pdo->prepare('
        SELECT id, sender, recipient, subject, body, cc, bcc, attachment
        FROM emails
        WHERE id = :id AND user_id = :user_id AND status = "draft"
    ');
    $stmt->execute([':id' => $email_id, ':user_id' => $_SESSION['user_id']]);
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$email) {
        die('Draft not found.');
    }
}

// Handle email form submission (saving or sending the email)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error_message' => 'Invalid CSRF token.']);
        exit;
    }

    // Sanitize and validate the form data
    $recipient = filter_var($_POST['recipient'], FILTER_SANITIZE_EMAIL);
    $cc = isset($_POST['cc']) ? filter_var($_POST['cc'], FILTER_SANITIZE_STRING) : '';
    $bcc = isset($_POST['bcc']) ? filter_var($_POST['bcc'], FILTER_SANITIZE_STRING) : '';
    $subject = htmlspecialchars($_POST['subject']);
    $body = htmlspecialchars($_POST['body']);
    $status = isset($_POST['save_draft']) ? 'draft' : 'sent';

    // Validate CC and BCC emails
    $ccArray = array_map('trim', explode(',', $cc));
    $bccArray = array_map('trim', explode(',', $bcc));

    $validCC = array_filter($ccArray, function($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    });
    $validBCC = array_filter($bccArray, function($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    });

    // Ensure valid recipient
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error-message' => 'Invalid recipient email address.']);
        exit;
    } elseif (empty($validCC) && empty($validBCC) && empty($recipient)) {
        echo json_encode(['success' => false, 'error-message' => 'You must provide at least one recipient, CC, or BCC.']);
        exit;
    }

    // Prepare CC and BCC for database insert
    $ccString = implode(',', $validCC);
    $bccString = implode(',', $validBCC);

    // Handle file attachments
    $attachmentPaths = [];
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
                    header('Location: compose.php');
                    exit;
                }

                // Move file to the upload directory
                $uploadDirectory = 'uploads/';
                $destPath = $uploadDirectory . $fileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $attachmentPaths[] = $destPath;
                }
            }
        }
    }

    try {
        // If updating draft, update the existing draft; otherwise, create a new one
        if ($email_id > 0) {
            // Update existing draft
            $attachments = !empty($attachmentPaths) ? implode(',', $attachmentPaths) : null;
            $stmt = $pdo->prepare('
                UPDATE emails
                SET recipient = ?, cc = ?, bcc = ?, subject = ?, body = ?, attachment = ?, status = ?
                WHERE id = ? AND user_id = ?
            ');
            $stmt->execute([$recipient, $ccString, $bccString, $subject, $body, $attachments, $status, $email_id, $_SESSION['user_id']]);
        } else {
            // Insert new email (draft or sent)
            $attachments = !empty($attachmentPaths) ? implode(',', $attachmentPaths) : null;
            $stmt = $pdo->prepare('
                INSERT INTO emails (sender, recipient, cc, bcc, subject, body, user_id, status, attachment)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$_SESSION['email'], $recipient, $ccString, $bccString, $subject, $body, $_SESSION['user_id'], $status, $attachments]);

            if ($status === 'sent') {
                // Find the recipient's user_id
                $recipientStmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $recipientStmt->execute([$recipient]);
                $recipientUser = $recipientStmt->fetch(PDO::FETCH_ASSOC);

                if ($recipientUser) {
                    $recipientId = $recipientUser['id'];
                    $receivedAt = date('Y-m-d H:i:s'); // Timestamp when the email was received
                    $status = 'unread'; // Set the initial status as unread

                    // Insert the email into the inbox_emails table for the recipient
                    $stmt = $pdo->prepare('
                        INSERT INTO inbox_emails (sender, recipient, cc, bcc, subject, body, user_id, status, attachment)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([$_SESSION['email'], $recipient, $ccString, $bccString, $subject, $body, $recipientId, $status, $attachments]);

                    // Insert into the sender's inbox as well
                    $stmt = $pdo->prepare('
                        INSERT INTO inbox_emails (sender, recipient, cc, bcc, subject, body, user_id, status, attachment)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([$_SESSION['email'], $recipient, $ccString, $bccString, $subject, $body, $_SESSION['user_id'], 'sent', $attachments]);
                }
            }
        }

        // Redirect or show success message
        $_SESSION['success_message'] = $email_id > 0 ? "Message successfully sent." : "Message successfully sent.";
        header('Location: compose.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header('Location: compose.php');
        exit;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose - HueMail</title>

    <link rel="icon" href="images/favicon.ico" type="image/x-icon"> <!-- Adjust path if necessary -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Link to Bootstrap CSS (locally) -->
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    
    <!-- Link to Font Awesome CSS (locally) -->
    <link rel="stylesheet" href="fontawesome-free-6.6.0-web/css/all.min.css">

     <!-- Link to Bootstrap JS (locally) -->
     <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

    <body style="background: url('<?php echo $background_image_url; ?>') no-repeat center center fixed; background-size: cover;">


</head>
<body>
<div class="container">

<?php
    // Display success message if set
    if (isset($_SESSION['success_message'])) {
        echo '<div class="success-message">' . $_SESSION['success_message'] . '</div>';
        unset($_SESSION['success_message']);  // Clear the message after displaying it
    }
    ?>
    
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="error-message">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
<?php endif; ?>

            <!-- Error Message -->
            <div id="error-message" class="error-message" style="display:none;"></div>

        <button class="close-btn" onclick="window.location.href='inbox.php';">&times;</button>
        <h1>Compose Email</h1>
        <form id="composeForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">


            <!-- Recipient Email -->
            <label for="recipient">To</label>
            <input type="email" id="recipient" name="recipient" value="<?php echo isset($email['recipient']) ? htmlspecialchars($email['recipient']) : ''; ?>" required>

            <!-- CC and BCC -->
            <div class="cc-bcc-toggle">
                <label><input type="checkbox" id="cc-toggle" <?php echo isset($email['cc']) && !empty($email['cc']) ? 'checked' : ''; ?>> Add CC</label>
                <input type="text" id="cc" name="cc" style="display:<?php echo isset($email['cc']) && !empty($email['cc']) ? 'block' : 'none'; ?>;" placeholder="CC email addresses" value="<?php echo isset($email['cc']) ? htmlspecialchars($email['cc']) : ''; ?>">
                <label><input type="checkbox" id="bcc-toggle" <?php echo isset($email['bcc']) && !empty($email['bcc']) ? 'checked' : ''; ?>> Add BCC</label>
                <input type="text" id="bcc" name="bcc" style="display:<?php echo isset($email['bcc']) && !empty($email['bcc']) ? 'block' : 'none'; ?>;" placeholder="BCC email addresses" value="<?php echo isset($email['bcc']) ? htmlspecialchars($email['bcc']) : ''; ?>">
            </div>

            <!-- Subject -->
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" value="<?php echo isset($email['subject']) ? htmlspecialchars($email['subject']) : ''; ?>" required>

            <!-- Message Body (Editable) -->
            <label for="body">Compose</label>
            <div id="editor" contenteditable="true">
                <?php echo isset($email['body']) ? htmlspecialchars($email['body']) : ''; ?>
            </div>

            <br>
            <!-- File Attachment -->
            <label for="attachment">Attach Files</label>
            <input type="file" id="attachment" name="attachment[]" multiple>

            <!-- Save Draft or Send -->
            <div>
                <button type="submit">Send Email</button>
            </div>

            <!-- Hidden Body Field -->
            <textarea id="body" name="body" style="display:none;"></textarea>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle CC/BCC toggle visibility
            document.getElementById('cc-toggle').addEventListener('change', function() {
                document.getElementById('cc').style.display = this.checked ? 'block' : 'none';
            });
            document.getElementById('bcc-toggle').addEventListener('change', function() {
                document.getElementById('bcc').style.display = this.checked ? 'block' : 'none';
            });

            // Copy the content from the editor to the hidden body field
            document.getElementById('composeForm').addEventListener('submit', function() {
                document.getElementById('body').value = document.getElementById('editor').innerHTML;
            });
        });
    </script>

    <script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('composeForm');
    let isDraftSaved = false;

    // Function to submit the form to save the draft automatically
    function saveDraft() {
        if (!isDraftSaved) {
            // Prevent multiple submissions by marking the draft as saved
            form.querySelector('[name="save_draft"]').value = '1';
            form.querySelector('[name="csrf_token"]').value = '<?php echo $_SESSION['csrf_token']; ?>'; // CSRF token for security
            form.submit();  // Submit the form
            isDraftSaved = true; // Prevent further submissions
        }
    }

    // Event listener for beforeunload to trigger save when user navigates away
    window.addEventListener('beforeunload', function (event) {
        saveDraft();
    });

    // If the form is manually submitted, mark it as saved to avoid redundant saves
    form.addEventListener('submit', function() {
        isDraftSaved = true;
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('composeForm');
    const recipientInput = document.getElementById('recipient');
    const subjectInput = document.getElementById('subject');
    const editor = document.getElementById('editor');
    let isDraftSaved = false;

    // Listen for the 'beforeunload' event to save the draft before the page is unloaded
    window.addEventListener('beforeunload', function (event) {
        // Only save the draft if it hasn't already been saved
        if (!isDraftSaved) {
            // Prevent the default action (e.g., page navigation)
            saveDraftToServer();
        }
    });

    // Function to save the draft to the server
    function saveDraftToServer() {
        const draft = {
            recipient: recipientInput.value,
            subject: subjectInput.value,
            body: editor.innerHTML,
            save_draft: true, // Flag to indicate that this is a draft save
            csrf_token: '<?php echo $csrf_token; ?>' // CSRF token for security
        };

        // Send the draft data to the server (via Fetch API)
        fetch('draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded', // Form data encoding
            },
            body: new URLSearchParams(draft).toString() // Convert the draft object to URL-encoded format
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mark as saved to prevent redundant saves
                isDraftSaved = true;
            }
        })
        .catch(error => console.error('Error saving draft:', error));
    }

    // Clear the draft if the user manually submits the form
    form.addEventListener('submit', function () {
        isDraftSaved = true; // Mark as saved
    });
});

</script>


<script>
    document.getElementById("composeForm").addEventListener("submit", function(event) {
        let attachments = document.getElementById("attachment").files;
        let errorMessageDiv = document.querySelector(".error-message"); // The div where error messages are displayed
        if (errorMessageDiv) {
            errorMessageDiv.style.display = "none"; // Hide the previous error message
        }

        // Clear any previous session-based error message
        <?php unset($_SESSION['error_message']); ?>

        if (attachments.length > 0) {
            for (let i = 0; i < attachments.length; i++) {
                let file = attachments[i];
                
                // Check for file size limit
                if (file.size > 25 * 1024 * 1024) {
                    event.preventDefault();
                    // Show error message without alert
                    errorMessageDiv.innerHTML = "The file size exceeds the 25MB limit.";
                    errorMessageDiv.style.display = "block";
                    return;
                }

                // Check for forbidden file types (exe, bat)
                if (["exe", "bat"].includes(file.name.split('.').pop().toLowerCase())) {
                    event.preventDefault();
                    // Show error message without alert
                    errorMessageDiv.innerHTML = "Executable files are not allowed.";
                    errorMessageDiv.style.display = "block";
                    return;
                }
            }
        }
    });
</script>


<!--
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get user_id and sender_email from PHP session
        const userId = <?php echo json_encode($_SESSION['user_id']); ?>;
        const senderEmail = <?php echo json_encode($_SESSION['email']); ?>;

        const ws = new WebSocket('ws://localhost:8081/email'); 

        ws.onopen = function() {
            console.log("Connected to WebSocket server.");
        };

        ws.onerror = function(error) {
            console.log("WebSocket Error: ", error);
        };

        document.getElementById('composeForm').addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent form submission

            // Ensure required fields are sent in the WebSocket message
            const message = {
                type: 'new_email',
                user_id: userId,  // Injected from PHP session
                sender_email: senderEmail,  // Injected from PHP session
                recipient: document.getElementById('recipient').value,
                subject: document.getElementById('subject').value,
                body: document.getElementById('editor').innerHTML,  // Assuming the body is in the 'editor' div
                message: {
                    id: Math.floor(Math.random() * 1000),  // Generate a random ID for the email
                    sender_email: senderEmail,  // Sender email (same as above)
                    subject: document.getElementById('subject').value,
                    created_at: new Date().toISOString()  // Current timestamp in ISO format
                }
            };

            // Send the message to the WebSocket server
            ws.send(JSON.stringify(message)); 

            // Optionally, submit the form after sending WebSocket message (if needed)
            this.submit();
        });

    });
</script>

-->


</body>
</html>
