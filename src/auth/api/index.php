<?php
/**
 * Authentication Handler for Login Form
 * * This PHP script handles user authentication via POST requests from the Fetch API.
 * It validates credentials against a MySQL database using PDO,
 * creates sessions, and returns JSON responses.
 */

// NOTE: Include the file that defines getDBConnection() here
// require_once 'db.php'; 

/**
 * Helper function to send a JSON response and terminate the script.
 * @param array $data The associative array to encode as JSON.
 */
function sendJsonResponse(array $data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// --- Session Management ---
session_start();
// Sessions allow us to store user data across multiple pages


// --- Set Response Headers ---
header('Content-Type: application/json');
// This tells the browser that we're sending JSON data back


// --- CORS Headers (Uncomment and adjust if needed) ---
// header("Access-Control-Allow-Origin: *"); 
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");


// --- Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are accepted.'
    ]);
}


// --- Get POST Data ---
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Check for JSON decode errors or missing required fields
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['email'], $data['password'])) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Invalid or incomplete JSON data provided.'
    ]);
}

// Store the email and password in variables
$email = trim($data['email']);
$password = $data['password'];


// --- Server-Side Validation ---
// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Invalid email or password.' // Generic message for security
    ]);
}

// Validate password length
if (strlen($password) < 8) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Invalid email or password.' // Generic message for security
    ]);
}


// --- Database Connection and Authentication ---
try {
    // Replace this with your actual connection call if not using require_once
    $pdo = getDBConnection(); 
    
    // --- Prepare SQL Query ---
    $sql = "SELECT id, name, email, password FROM users WHERE email = :email LIMIT 1";

    // --- Prepare the Statement ---
    $stmt = $pdo->prepare($sql);

    // --- Execute the Query ---
    $stmt->execute([':email' => $email]);

    // --- Fetch User Data ---
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Verify User Exists and Password Matches ---
    if ($user && password_verify($password, $user['password'])) {
        
        // --- Handle Successful Authentication ---
        
        // 1. Store user information in session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['logged_in'] = true;

        // 2. Prepare a success response array
        $response = [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email']
            ]
        ];

        // 3. Encode the response array as JSON and echo it
        sendJsonResponse($response);
        
    } else {
        
        // --- Handle Failed Authentication ---
        
        // 1. Prepare an error response array (Generic message for security)
        $response = [
            'success' => false,
            'message' => 'Invalid email or password'
        ];

        // 2. Encode the error response as JSON and echo it
        sendJsonResponse($response);
    }

} catch (PDOException $e) {
    
    // --- Catch PDO exceptions in the catch block ---
    
    // 1. Log the error for debugging
    error_log("Database Error in Login: " . $e->getMessage());
    
    // 2. Return a generic error message to the client
    sendJsonResponse([
        'success' => false,
        'message' => 'An internal server error occurred during login.'
    ]);
}

// --- End of Script ---
?>
