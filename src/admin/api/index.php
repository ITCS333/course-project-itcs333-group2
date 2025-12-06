<?php
/**
 * Student Management API
 * * This is a RESTful API that handles all CRUD operations for student management.
 * It uses PDO to interact with a MySQL database.
 * * Response Format: JSON
 */

// --- Configuration and Includes ---
// IMPORTANT: Include your database connection function here
// require_once 'db.php'; 

// --- Set Response Headers ---
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


// --- Helper Functions ---

/**
 * Helper function to send JSON response and terminate the script.
 * @param mixed $data - Data or error message
 * @param int $statusCode - HTTP status code
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

/**
 * Helper function to validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Helper function to sanitize input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    // Trim whitespace, strip HTML tags, and convert special characters
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}


// --- API Logic Functions ---

/**
 * Function: Get all students or search for specific students
 */
function getStudents($db) {
    $search = sanitizeInput($_GET['search'] ?? '');
    $sort = sanitizeInput($_GET['sort'] ?? 'name');
    $order = strtolower(sanitizeInput($_GET['order'] ?? 'asc'));

    $validSortFields = ['name', 'student_id', 'email'];

    // Input validation for sorting to prevent SQL injection
    $sortField = in_array($sort, $validSortFields) ? $sort : 'name';
    $sortOrder = ($order === 'desc') ? 'DESC' : 'ASC';
    
    $sql = "SELECT id, student_id, name, email, created_at FROM students";
    $params = [];
    
    if ($search) {
        $sql .= " WHERE name LIKE :search OR student_id LIKE :search OR email LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }
    
    $sql .= " ORDER BY {$sortField} {$sortOrder}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'count' => count($students),
        'data' => $students
    ]);
}


/**
 * Function: Get a single student by student_id
 */
function getStudentById($db, $studentId) {
    $sql = "SELECT id, student_id, name, email, created_at FROM students WHERE student_id = :student_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':student_id' => $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        sendResponse(['success' => true, 'data' => $student]);
    } else {
        sendResponse(['success' => false, 'message' => 'Student not found.'], 404);
    }
}


/**
 * Function: Create a new student
 */
function createStudent($db, $data) {
    $requiredFields = ['student_id', 'name', 'email', 'password'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            sendResponse(['success' => false, 'message' => "Missing required field: {$field}"], 400);
        }
    }

    $sanitizedData = sanitizeInput($data);
    $studentId = $sanitizedData['student_id'];
    $name = $sanitizedData['name'];
    $email = $sanitizedData['email'];
    $password = $sanitizedData['password'];

    if (!validateEmail($email)) {
        sendResponse(['success' => false, 'message' => 'Invalid email format.'], 400);
    }

    // Check for duplicates
    $sqlCheck = "SELECT student_id FROM students WHERE student_id = :student_id OR email = :email LIMIT 1";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute([':student_id' => $studentId, ':email' => $email]);
    if ($stmtCheck->fetch()) {
        sendResponse(['success' => false, 'message' => 'Student ID or email already exists.'], 409); // Conflict
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sqlInsert = "INSERT INTO students (student_id, name, email, password) VALUES (:student_id, :name, :email, :password)";
    $stmtInsert = $db->prepare($sqlInsert);
    
    $success = $stmtInsert->execute([
        ':student_id' => $studentId,
        ':name' => $name,
        ':email' => $email,
        ':password' => $hashedPassword
    ]);

    if ($success) {
        $newId = $db->lastInsertId();
        sendResponse(['success' => true, 'message' => 'Student created successfully.', 'id' => $newId], 201); // Created
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create student.'], 500);
    }
}


/**
 * Function: Update an existing student
 */
function updateStudent($db, $data) {
    if (empty($data['student_id'])) {
        sendResponse(['success' => false, 'message' => 'Student ID is required for update.'], 400);
    }
    
    $sanitizedData = sanitizeInput($data);
    $studentId = $sanitizedData['student_id'];
    $updates = [];
    $params = ['student_id' => $studentId];
    
    // Check if student exists
    $stmtExist = $db->prepare("SELECT id FROM students WHERE student_id = :student_id");
    $stmtExist->execute([':student_id' => $studentId]);
    $currentStudent = $stmtExist->fetch(PDO::FETCH_ASSOC);
    if (!$currentStudent) {
        sendResponse(['success' => false, 'message' => 'Student not found.'], 404);
    }

    if (isset($sanitizedData['name'])) {
        $updates[] = 'name = :name';
        $params[':name'] = $sanitizedData['name'];
    }

    if (isset($sanitizedData['email'])) {
        $email = $sanitizedData['email'];
        if (!validateEmail($email)) {
            sendResponse(['success' => false, 'message' => 'Invalid email format.'], 400);
        }

        // Check for duplicate email, excluding the current student
        $stmtCheck = $db->prepare("SELECT id FROM students WHERE email = :email AND student_id != :student_id LIMIT 1");
        $stmtCheck->execute([':email' => $email, ':student_id' => $studentId]);
        if ($stmtCheck->fetch()) {
            sendResponse(['success' => false, 'message' => 'Email already used by another student.'], 409);
        }
        $updates[] = 'email = :email';
        $params[':email'] = $email;
    }

    if (empty($updates)) {
        sendResponse(['success' => false, 'message' => 'No valid fields provided for update.'], 400);
    }
    
    $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE student_id = :student_id";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute($params)) {
        sendResponse(['success' => true, 'message' => 'Student updated successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update student.'], 500);
    }
}


/**
 * Function: Delete a student
 */
function deleteStudent($db, $studentId) {
    if (empty($studentId)) {
        sendResponse(['success' => false, 'message' => 'Student ID is required for deletion.'], 400);
    }
    
    $studentId = sanitizeInput($studentId);

    // Check if student exists
    $stmtExist = $db->prepare("SELECT id FROM students WHERE student_id = :student_id");
    $stmtExist->execute([':student_id' => $studentId]);
    if (!$stmtExist->fetch()) {
        sendResponse(['success' => false, 'message' => 'Student not found.'], 404);
    }

    $sql = "DELETE FROM students WHERE student_id = :student_id";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute([':student_id' => $studentId])) {
        sendResponse(['success' => true, 'message' => 'Student deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete student.'], 500);
    }
}


/**
 * Function: Change password
 */
function changePassword($db, $data) {
    $requiredFields = ['student_id', 'current_password', 'new_password'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            sendResponse(['success' => false, 'message' => "Missing required field: {$field}"], 400);
        }
    }

    $sanitizedData = sanitizeInput($data);
    $studentId = $sanitizedData['student_id'];
    $currentPassword = $data['current_password'];
    $newPassword = $data['new_password'];
    
    // Validate new password strength
    if (strlen($newPassword) < 8) {
        sendResponse(['success' => false, 'message' => 'New password must be at least 8 characters.'], 400);
    }
    
    // Retrieve current password hash
    $sql = "SELECT password FROM students WHERE student_id = :student_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':student_id' => $studentId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse(['success' => false, 'message' => 'Student not found.'], 404);
    }

    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        sendResponse(['success' => false, 'message' => 'Incorrect current password.'], 401); // Unauthorized
    }

    // Hash the new password
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password in database
    $sqlUpdate = "UPDATE students SET password = :password WHERE student_id = :student_id";
    $stmtUpdate = $db->prepare($sqlUpdate);
    
    if ($stmtUpdate->execute([':password' => $newHashedPassword, ':student_id' => $studentId])) {
        sendResponse(['success' => true, 'message' => 'Password updated successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update password.'], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$data = [];
$db = null;

// Get the request body for POST/PUT
if ($method === 'POST' || $method === 'PUT') {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true) ?? [];
}

try {
    // Get the PDO database connection (Assuming getDBConnection is defined)
    // $db = getDBConnection(); 
    
    // --- Temporarily mock the database connection if db.php is missing ---
    if (!function_exists('getDBConnection')) {
         sendResponse(['success' => false, 'message' => 'Database connection function (getDBConnection) is missing.'], 500);
    }
    $db = getDBConnection(); 
    // --- End Mock ---

    if ($method === 'GET') {
        $studentId = sanitizeInput($_GET['student_id'] ?? null);
        
        if ($studentId) {
            getStudentById($db, $studentId);
        } else {
            getStudents($db);
        }
        
    } elseif ($method === 'POST') {
        // Check for specific action (e.g., action=change_password)
        $action = sanitizeInput($_GET['action'] ?? null);
        
        if ($action === 'change_password') {
            changePassword($db, $data);
        } else {
            createStudent($db, $data);
        }
        
    } elseif ($method === 'PUT') {
        updateStudent($db, $data);
        
    } elseif ($method === 'DELETE') {
        // Try getting ID from query parameter first
        $studentId = sanitizeInput($_GET['student_id'] ?? null);
        // If not found, try getting it from JSON body
        if (!$studentId && isset($data['student_id'])) {
            $studentId = sanitizeInput($data['student_id']);
        }
        
        deleteStudent($db, $studentId);
        
    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed.'], 405);
    }
    
} catch (PDOException $e) {
    // Log the error message
    error_log("Database Error in Student API: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'A database error occurred.'], 500);
    
} catch (Exception $e) {
    // Log the general error
    error_log("General Error in Student API: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An unexpected server error occurred.'], 500);
}

?>
