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

// Get POST data
$to = isset($_POST['to']) ? trim($_POST['to']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$original_email_id = isset($_POST['original_email_id']) ? intval($_POST['original_email_id']) : 0;

// Basic validation: check if fields are empty
if (empty($to) || empty($subject) || empty($message)) {
    header('Location: reply_email.php?id=' . $original_email_id . '&error=Please fill in all the fields.');
    exit;
}

// Validate email format
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    header('Location: reply_email.php?id=' . $original_email_id . '&error=Invalid email address.');
    exit;
}

// Insert the sent email into the 'sent_emails' table (for sent items)
try {
    // Prepare the insert statement for the sender's sent email
    $stmt = $pdo->prepare('
        INSERT INTO sent_emails (user_id, sender, recipient, subject, body, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([$_SESSION['user_id'], $_SESSION['email'], $to, $subject, $message]);
} catch (PDOException $e) {
    // Error handling if inserting into sent_emails fails
    header('Location: reply_email.php?id=' . $original_email_id . '&error=Error sending email: ' . urlencode($e->getMessage()));
    exit;
}

// Optionally, you may want to keep a copy in the inbox of the recipient.
try {
    // Prepare the insert statement for the recipient's inbox email
    $stmt = $pdo->prepare('
        INSERT INTO inbox_emails (user_id, sender, recipient, subject, body, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([$_SESSION['user_id'], $_SESSION['email'], $to, $subject, $message]);
} catch (PDOException $e) {
    // Error handling if inserting into inbox_emails fails
    header('Location: reply_email.php?id=' . $original_email_id . '&error=Error saving email to recipient inbox: ' . urlencode($e->getMessage()));
    exit;
}

// WebSocket Message Preparation
$websocketMessage = [
    'type' => 'reply_email',   // Define the type as 'reply_email' for WebSocket
    'user_id' => $_SESSION['user_id'],
    'sender_email' => $_SESSION['email'],
    'recipient' => $to,
    'subject' => $subject,
    'body' => $message,
    'original_email' => [
        'id' => $original_email_id,       // ID of the original email being replied to
        'subject' => $subject,            // Subject of the reply (can be the same or prefixed 'Re:')
        'body' => $message,               // Body of the reply email
        'sender_email' => $_SESSION['email'],  // Original sender's email
        'created_at' => date("Y-m-d H:i:s") // Current timestamp
    ],
    'message' => [
        'id' => $original_email_id,      // Use the ID fetched from the backend
        'sender_email' => $_SESSION['email'],  // Sender email
        'subject' => $subject,           // Subject of the email
        'created_at' => date("Y-m-d H:i:s")  // Current timestamp
    ]
];

// Open WebSocket connection using JavaScript (Here I am assuming you are handling WebSocket on the client-side)
?>

<script>
// WebSocket connection
const ws = new WebSocket('ws://localhost:8081/email');

// WebSocket onopen handler
ws.onopen = function() {
    console.log("Connected to WebSocket server.");
    
    // Send the WebSocket message once the connection is established
    const message = <?php echo json_encode($websocketMessage); ?>;  // Inject the PHP message data
    ws.send(JSON.stringify(message));  // Send the message to the WebSocket server
};

// WebSocket error handler
ws.onerror = function(error) {
    console.error("WebSocket error:", error);
};
</script>

<?php
// After sending the WebSocket message, redirect to the sent folder (or another page)
header('Location: sent.php');
exit;
?>
