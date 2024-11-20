<?php

require (__DIR__) . '/vendor/autoload.php';  // Ensure the path is correct

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\App;

class EmailNotificationServer implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
    
        // Check if the message is valid JSON
        if ($data === null) {
            $from->send(json_encode(['error' => 'Invalid JSON format']));
            return;
        }

        // Log incoming data for debugging
        error_log("Received message: " . print_r($data, true));

        // Validate that all required fields are present
        if (!isset($data['user_id'], $data['sender_email'], $data['recipient'], $data['subject'], $data['body'])) {
            $from->send(json_encode(['error' => 'Missing required fields in the email message (user_id, sender_email, recipient, subject, body).']));
            return;
        }

        // Now proceed with the database insertion (all required fields should be available)
        try {
            // Connect to the database
            $pdo = new PDO('mysql:host=localhost;dbname=HueMail', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
            // Prepare SQL statement to insert new email
            $stmt = $pdo->prepare('INSERT INTO emails (user_id, sender, recipient, subject, body, status, created_at, cc, bcc, attachment) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    
            // Handle optional fields (cc, bcc, attachment) safely
            $cc = isset($data['cc']) ? $data['cc'] : null;
            $bcc = isset($data['bcc']) ? $data['bcc'] : null;
            $attachment = isset($data['attachment']) ? $data['attachment'] : null;
    
            // Execute the statement with provided data
            $stmt->execute([
                $data['user_id'],           // user_id
                $data['sender_email'],      // sender_email
                $data['recipient'],         // recipient
                $data['subject'],           // subject
                $data['body'],              // body
                'pending',                  // Default status
                date('Y-m-d H:i:s'),        // Created at timestamp
                $cc,                        // cc (optional)
                $bcc,                       // bcc (optional)
                $attachment                 // attachment (optional)
            ]);
    
            // Send success response
            $from->send(json_encode(['type' => 'email_saved', 'status' => 'success']));
    
        } catch (PDOException $e) {
            // Handle database errors
            $from->send(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
        }
    
        // Broadcast the new email message to all connected clients except the sender
        $this->broadcastMessage($from, $data['message']);
    }

    // Broadcast the new email to all clients except the sender
    private function broadcastMessage(ConnectionInterface $from, $message) {
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send(json_encode([
                    'type' => 'new_email',
                    'message' => $message
                ]));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Initialize the WebSocket server
$server = new App('localhost', 8081);
$server->route('/email', new EmailNotificationServer, array('*'));
$server->run();
