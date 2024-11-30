<?php
session_start();

// Check if the error message is received via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Set the error message into the session
    if (isset($input['error_message'])) {
        $_SESSION['error_message'] = $input['error_message'];
    }

    // Respond with success
    echo json_encode(['success' => true, 'message' => 'Error message saved']);
}
