<?php
session_start(); // Start the session at the beginning of the page

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];  // Get user ID from session

// Database connection setup
$host = 'localhost';
$db   = 'HueMail';
$user = 'root';
$pass = '';

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  // Set error mode to exceptions
} catch (PDOException $e) {
    // If there’s a database connection issue, terminate the script
    die("Database connection failed: " . $e->getMessage());
}

// Query to get the latest email ID from both the 'emails' and 'inbox_emails' tables
$sql = "
    SELECT MAX(id) AS latest_id 
    FROM (
        SELECT id FROM emails
        UNION ALL
        SELECT id FROM inbox_emails
    ) AS combined_emails;
";

$stmt = $pdo->query($sql);  // Execute the query using the PDO instance

// Check if the query was successful
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);  // Fetch the result as an associative array
    $latestId = $row['latest_id'];  // Get the latest ID
    
    // If no emails exist yet, start from 1
    $emailId = $latestId ? $latestId + 1 : 1;
    
    // Return the next email ID as JSON
    echo json_encode(['email_id' => $emailId]);
} else {
    // Return an error message if the query failed
    echo json_encode(['error' => 'Unable to fetch email ID.']);
}

$pdo = null;  // Close the database connection
?>
