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

// Get email ID from query string
$email_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch email details for reply
$stmt = $pdo->prepare('
    SELECT e.id, e.sender, e.subject, e.body, u.first_name AS sender_first_name, u.middle_name AS sender_middle_name, u.last_name AS sender_last_name, u.email AS sender_email
    FROM inbox_emails e
    JOIN users u ON e.sender = u.email
    WHERE e.id = ? AND e.user_id = ?
');
$stmt->execute([$email_id, $_SESSION['user_id']]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$email) {
    // Email not found or you do not have permission to reply
    header('Location: inbox.php?error=Email not found or you do not have permission to reply.');
    exit;
}

// Prepare variables for pre-filling the reply form
$sender_email = $email['sender_email'];
$original_subject = 'Re: ' . $email['subject'];  // Prefix the subject with 'Re:'
$original_body = $email['body'];  // Include original email body
$sender_name = $email['sender_first_name'] . ' ' . $email['sender_middle_name'] . ' ' . $email['sender_last_name'];

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
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.6.0-web/css/all.min.css">
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

    <body style="background: url('<?php echo $background_image_url; ?>') no-repeat center center fixed; background-size: cover;">
    <title>Reply to Email - HueMail</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Custom styles for the reply form */
        body {
            font-family: 'Roboto', sans-serif;
            background: url('images/mainbg.jpg') no-repeat center center fixed;
            background-size: cover;
            padding-top: 40px;
            display: flex;
            justify-content: center;
        }

        .reply-container {
            position: relative;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 20px;
            width: 100%;
            max-width: 950px;
            box-sizing: border-box;
            overflow: hidden;
            border: 5px solid white;
            margin: auto; /* Center the container */
            position: relative;
            margin-top: auto;
            overflow: hidden;
        }

        h2 {
            font-size: 2em;
            color: #1a73e8;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-size: 1.1em;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            font-size: 1em;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 8px;
        }

        .form-group textarea {
            height: 200px;
        }

        .form-group .original-body {
            font-size: 0.9em;
            color: #555;
            margin-top: 10px;
            padding: 30px;
            background: #f4f4f4;
            border-left: 2px solid #1a73e8;
            text-align: left; /* Align the text to the left */
        }

        /* Back Button Style */
        .back-button {
            background-color: #1a73e8;
            color: white;
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-top: 20px;
        }

        .back-button:hover {
            background-color: #0d5bbd;
        }

        .back-button i {
            margin-right: 8px;
        }

    </style>
</head>
<body>
<div class="reply-container">
    <!-- Back Button -->
    <a href="view_email.php?id=<?php echo $email_id; ?>" class="back-button"><i class="fas fa-arrow-left"></i> View Email</a>

    <?php
    if (isset($_GET['error'])) {
        echo '<div class="error-message" style="color: red; font-size: 14px;">' . htmlspecialchars($_GET['error']) . '</div>';
    }
    ?>

    <h2>Reply Email</h2>

    <div class="form-group">
        <label>Original Message:</label>
        <div class="original-body">
            <?php echo nl2br(htmlspecialchars($original_body)); ?>
        </div>
    </div>

    <form action="send_reply.php" method="POST">
        <div class="form-group">
            <label for="to">To:</label>
            <input type="email" id="to" name="to" value="<?php echo htmlspecialchars($sender_email); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="subject">Subject:</label>
            <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($original_subject); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="message">Your Message:</label>
            <textarea id="message" name="message" required></textarea>
        </div>

        <button type="submit" class="reply-btn"><i class="fas fa-paper-plane"></i> Send Reply</button>
    </form>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Get user_id and sender_email from PHP session
    const userId = <?php echo json_encode($_SESSION['user_id']); ?>;
    const senderEmail = <?php echo json_encode($_SESSION['email']); ?>;

    // Get the original email details from PHP session or the GET parameters (for reply)
    const originalEmailId = <?php echo json_encode($original_email_id); ?>;
    const originalSubject = <?php echo json_encode($original_subject); ?>;
    const originalBody = <?php echo json_encode($original_body); ?>;

    // Create a WebSocket connection
    const ws = new WebSocket('ws://localhost:8081/email'); 

    // WebSocket open event: Log when the connection is successful
    ws.onopen = function() {
        console.log("Connected to WebSocket server.");
    };

    // WebSocket error event: Log any connection errors
    ws.onerror = function(error) {
        console.error("WebSocket error:", error);
    };

    // When the form is submitted to reply to an email
    document.getElementById('replyForm').addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent form submission (avoids page reload)

        // Get the reply data from the form
        const recipient = document.getElementById('to').value;  // Recipient (sender's email from original email)
        const subject = document.getElementById('subject').value;  // Subject (original subject or prefixed 'Re:')
        const body = document.getElementById('message').value;  // Body from textarea (the reply message)

        // Check if the WebSocket is open before proceeding
        if (ws.readyState === WebSocket.OPEN) {

            // Fetch the next email ID from the backend
            fetch('getEmailId.php')  // Fetching the email ID from the server-side
                .then(response => response.json())  // Parsing the JSON response
                .then(data => {
                    if (data.email_id) {  // If a valid email ID is returned
                        const emailId = data.email_id;

                        // Prepare the message object for WebSocket
                        const message = {
                            type: 'reply_email',
                            user_id: userId,  // Sender user ID (from PHP session)
                            sender_email: senderEmail,  // Sender email (from PHP session)
                            recipient: recipient,  // Recipient from form input
                            subject: subject,  // Subject from form input
                            body: body,  // Body of the reply email
                            original_email: {
                                id: originalEmailId,  // The original email being replied to
                                subject: originalSubject,  // The original subject
                                body: originalBody,  // The original email body
                                sender_email: <?php echo json_encode($sender_email); ?>,  // Original sender's email
                                created_at: new Date().toISOString()  // Current timestamp for reply
                            },
                            message: {
                                id: emailId,  // New email ID (generated from the backend)
                                sender_email: senderEmail,  // Sender email
                                subject: subject,  // Subject of the email
                                created_at: new Date().toISOString()  // Current timestamp
                            }
                        };

                        // Send the message to the WebSocket server
                        ws.send(JSON.stringify(message));

                        // Optionally, submit the form after the WebSocket message has been sent
                        // This can be uncommented if you want to submit the form as well
                        this.submit();  // Submit the form (if necessary)

                    } else {
                        console.error('Error: Could not retrieve email ID');
                    }
                })
                .catch(error => {
                    console.error('Error fetching email ID:', error);
                });

        } else {
            console.error('Error: WebSocket is not open.');
        }
    });
});
</script>


</div>
</body>
</html>
