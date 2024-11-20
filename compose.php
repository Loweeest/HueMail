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
    $_SESSION['error_message'] = "Please update your account settings before sending an email.";
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
        echo json_encode(['success' => false, 'error_message' => 'Invalid recipient email address.']);
        exit;
    } elseif (empty($validCC) && empty($validBCC) && empty($recipient)) {
        echo json_encode(['success' => false, 'error_message' => 'You must provide at least one recipient, CC, or BCC.']);
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
        }

        // Redirect or show success message
        $_SESSION['success_message'] = $email_id > 0 ? "Draft updated successfully!" : "Message saved or sent successfully!";
        header('Location: inbox.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header('Location: compose.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose - HueMail</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: url('images/huemail.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            position: relative;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 20px;
            width: 100%;
            max-width: 800px;
            box-sizing: border-box;
            overflow: hidden;
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #ff4d4d;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #fff;
            font-size: 20px;
            transition: background 0.3s, transform 0.3s;
        }

        .close-btn:hover {
            background: #ff2d2d;
            transform: scale(1.1);
        }

        .close-btn:active {
            transform: scale(0.95);
        }

        h1 {
            margin-bottom: 20px;
            color: #444;
            font-size: 24px;
            font-weight: bold;
        }

        /* Error Message */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            display: none; /* Hide by default */
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }

        /* Form Styles */
        form {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        /* Input and Textarea Styles */
        input[type="email"], input[type="text"], textarea, input[type="file"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }

        input[type="email"]:focus, input[type="text"]:focus, textarea:focus, input[type="file"]:focus {
            border-color: #007bff;
            outline: none;
        }

        /* Message Editor */
        #editor {
            width: 100%;
            min-height: 300px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fff;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            overflow-y: auto;
        }

        #editor[contenteditable="true"]:empty:before {
            content: "Write your message...";
            color: #aaa;
        }

        /* Submit Buttons */
        button, .btn {
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            transition: background-color 0.3s;
        }

        button:hover, .btn:hover {
            background-color: #0056b3;
        }

        /* CC/BCC Toggle */
        .cc-bcc-toggle {
            align-items: center;
            margin-bottom: 15px;
        }

        .cc-bcc-toggle label {
            margin-right: 10px;
        }

        /* Responsiveness */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
                max-width: 100%;
            }

            h1 {
                font-size: 20px;
            }

            input[type="email"], input[type="text"], textarea, input[type="file"], #editor {
                font-size: 14px;
            }

            button, .btn {
                font-size: 14px;
                padding: 8px 16px;
            }

            .close-btn {
                font-size: 18px;
                width: 30px;
                height: 30px;
            }
        }
    </style>
</head>
<body>
<div class="container">
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
                <button type="submit" name="save_draft" value="1">Save Draft</button>
                <button type="submit">Send Email</button>
            </div>

            <!-- Error Message -->
            <div id="error-message" class="error-message" style="display:none;"></div>

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
    document.addEventListener('DOMContentLoaded', function() {
    let form = document.getElementById('composeForm');
    let recipientInput = document.getElementById('recipient');
    let subjectInput = document.getElementById('subject');
    let editor = document.getElementById('editor');

    // Check if there's saved draft in localStorage
    if (localStorage.getItem('draft')) {
        const draft = JSON.parse(localStorage.getItem('draft'));
        recipientInput.value = draft.recipient || '';
        subjectInput.value = draft.subject || '';
        editor.innerHTML = draft.body || '';
    }

    // Save the draft automatically as the user types
    form.addEventListener('input', function() {
        const draft = {
            recipient: recipientInput.value,
            subject: subjectInput.value,
            body: editor.innerHTML,
        };
        localStorage.setItem('draft', JSON.stringify(draft));
    });

    // Listen for the beforeunload event
    let isDraftSaved = false;
    window.addEventListener('beforeunload', function(event) {
        if (!isDraftSaved) {
            // If there's any unsaved draft, submit it
            form.querySelector('[name="save_draft"]').value = '1'; // Set the flag to save as draft
            form.submit();
        }
    });

    // Mark as saved once the form is submitted
    form.addEventListener('submit', function() {
        isDraftSaved = true;
        localStorage.removeItem('draft'); // Clear saved draft after submit
    });
});
</script>

<script>
        document.getElementById("composeForm").addEventListener("submit", function(event) {
    let attachments = document.getElementById("attachment").files;
    if (attachments.length > 0) {
        for (let i = 0; i < attachments.length; i++) {
            let file = attachments[i];
            if (file.size > 25 * 1024 * 1024) {
                event.preventDefault();
                alert("The file size exceeds the 25MB limit.");
                return;
            }
            if (["exe", "bat"].includes(file.name.split('.').pop().toLowerCase())) {
                event.preventDefault();
                alert("Executable files are not allowed.");
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
