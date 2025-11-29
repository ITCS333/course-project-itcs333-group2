<?php

/**
 * Weekly Course Breakdown API
 * haleema khamis – Task 3
 */


header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================
require_once "../config/Database.php";

$database = new Database();
$db = $database->getConnection();

// ============================================================================
// BASIC REQUEST DATA
// ============================================================================
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);
$resource = isset($_GET["resource"]) ? $_GET["resource"] : "weeks";


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function sendError($message, $statusCode = 400) {
    sendResponse([
        "success" => false,
        "error" => $message,
    ], $statusCode);
}

function validateDate($date) {
    $d = DateTime::createFromFormat("Y-m-d", $date);
    return $d && $d->format("Y-m-d") === $date;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function isValidSortField($field, $allowed) {
    return in_array($field, $allowed);
}


// ============================================================================
// WEEKS FUNCTIONS
// ============================================================================
function getAllWeeks($db) {
    $search = isset($_GET["search"]) ? "%".sanitizeInput($_GET["search"])."%" : null;
    $sort = isset($_GET["sort"]) ? $_GET["sort"] : "start_date";
    $order = isset($_GET["order"]) ? strtolower($_GET["order"]) : "asc";

    $allowedSort = ["title", "start_date", "created_at"];
    if (!in_array($sort, $allowedSort)) $sort = "start_date";
    if (!in_array($order, ["asc","desc"])) $order = "asc";

    $sql = "SELECT week_id, title, start_date, description, links, created_at FROM weeks";

    if ($search) {
        $sql .= " WHERE title LIKE ? OR description LIKE ?";
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);

    if ($search)
        $stmt->execute([$search, $search]);
    else
        $stmt->execute();

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($result as &$w) {
        $w["links"] = json_decode($w["links"], true);
    }

    sendResponse(["success" => true, "data" => $result]);
}

function getWeekById($db, $weekId) {
    if (!$weekId) return sendError("week_id is required", 400);

    $stmt = $db->prepare("SELECT * FROM weeks WHERE week_id = ?");
    $stmt->execute([$weekId]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$week) return sendError("Week not found", 404);

    $week["links"] = json_decode($week["links"], true);

    sendResponse(["success" => true, "data" => $week]);
}

function createWeek($db, $data) {
    if (!isset($data["week_id"], $data["title"], $data["start_date"], $data["description"])) {
        return sendError("Missing required fields", 400);
    }

    $week_id = sanitizeInput($data["week_id"]);
    $title = sanitizeInput($data["title"]);
    $start_date = sanitizeInput($data["start_date"]);
    $description = sanitizeInput($data["description"]);
    $links = isset($data["links"]) && is_array($data["links"]) ? json_encode($data["links"]) : json_encode([]);

    if (!validateDate($start_date)) return sendError("Invalid date format (Y-m-d)", 400);

    // Check duplicate
    $check = $db->prepare("SELECT id FROM weeks WHERE week_id = ?");
    $check->execute([$week_id]);
    if ($check->fetch()) return sendError("Week ID already exists", 409);

    $stmt = $db->prepare("INSERT INTO weeks (week_id, title, start_date, description, links) VALUES (?, ?, ?, ?, ?)");
    $ok = $stmt->execute([$week_id, $title, $start_date, $description, $links]);

    if ($ok) {
        sendResponse(["success" => true, "message" => "Week created successfully"], 201);
    }

    sendError("Failed to create week", 500);
}

function updateWeek($db, $data) {
    if (!isset($data["week_id"])) return sendError("week_id is required", 400);

    $weekId = sanitizeInput($data["week_id"]);

    $check = $db->prepare("SELECT * FROM weeks WHERE week_id = ?");
    $check->execute([$weekId]);
    if (!$check->fetch()) return sendError("Week not found", 404);

    $fields = [];
    $values = [];

    if (isset($data["title"])) {
        $fields[] = "title = ?";
        $values[] = sanitizeInput($data["title"]);
    }

    if (isset($data["start_date"])) {
        if (!validateDate($data["start_date"])) return sendError("Invalid date format", 400);
        $fields[] = "start_date = ?";
        $values[] = sanitizeInput($data["start_date"]);
    }

    if (isset($data["description"])) {
        $fields[] = "description = ?";
        $values[] = sanitizeInput($data["description"]);
    }

    if (isset($data["links"])) {
        $fields[] = "links = ?";
        $values[] = json_encode($data["links"]);
    }

    if (empty($fields)) return sendError("No fields provided to update", 400);

    $fields[] = "updated_at = CURRENT_TIMESTAMP";

    $sql = "UPDATE weeks SET " . implode(", ", $fields) . " WHERE week_id = ?";
    $values[] = $weekId;

    $stmt = $db->prepare($sql);
    $ok = $stmt->execute($values);

    if ($ok) sendResponse(["success" => true, "message" => "Week updated successfully"]);

    sendError("Failed to update week", 500);
}

function deleteWeek($db, $weekId) {
    if (!$weekId) return sendError("week_id is required", 400);

    $check = $db->prepare("SELECT * FROM weeks WHERE week_id = ?");
    $check->execute([$weekId]);

    if (!$check->fetch()) return sendError("Week not found", 404);

    $db->prepare("DELETE FROM comments WHERE week_id = ?")->execute([$weekId]);
    $ok = $db->prepare("DELETE FROM weeks WHERE week_id = ?")->execute([$weekId]);

    if ($ok) sendResponse(["success" => true, "message" => "Week and related comments deleted"]);

    sendError("Failed to delete week", 500);
}


// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================
function getCommentsByWeek($db, $weekId) {
    if (!$weekId) return sendError("week_id is required");

    $stmt = $db->prepare("SELECT * FROM comments WHERE week_id = ? ORDER BY created_at ASC");
    $stmt->execute([$weekId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(["success" => true, "data" => $comments]);
}

function createComment($db, $data) {
    if (!isset($data["week_id"], $data["author"], $data["text"])) {
        return sendError("Missing required fields", 400);
    }

    $week_id = sanitizeInput($data["week_id"]);
    $author = sanitizeInput($data["author"]);
    $text = sanitizeInput($data["text"]);

    if ($text === "") return sendError("Comment text cannot be empty", 400);

    $check = $db->prepare("SELECT id FROM weeks WHERE week_id = ?");
    $check->execute([$week_id]);
    if (!$check->fetch()) return sendError("Week does not exist", 404);

    $stmt = $db->prepare("INSERT INTO comments (week_id, author, text) VALUES (?, ?, ?)");
    $ok = $stmt->execute([$week_id, $author, $text]);

    if ($ok) {
        sendResponse([
            "success" => true,
            "message" => "Comment added",
            "data" => [
                "id" => $db->lastInsertId(),
                "week_id" => $week_id,
                "author" => $author,
                "text" => $text
            ]
        ], 201);
    }

    sendError("Failed to create comment", 500);
}

function deleteComment($db, $id) {
    if (!$id) return sendError("Comment ID is required", 400);

    $check = $db->prepare("SELECT id FROM comments WHERE id = ?");
    $check->execute([$id]);

    if (!$check->fetch()) return sendError("Comment not found", 404);

    $ok = $db->prepare("DELETE FROM comments WHERE id = ?")->execute([$id]);

    if ($ok) sendResponse(["success" => true, "message" => "Comment deleted"]);

    sendError("Failed to delete comment", 500);
}


// ============================================================================

// ============================================================================
try {

    // WEEKS RESOURCE
    if ($resource === "weeks") {

        if ($method === "GET") {
            if (isset($_GET["week_id"])) {
                getWeekById($db, $_GET["week_id"]);
            } else {
                getAllWeeks($db);
            }
        }

        if ($method === "POST") createWeek($db, $input);
        if ($method === "PUT") updateWeek($db, $input);

        if ($method === "DELETE") {
            $weekId = isset($_GET["week_id"]) ? $_GET["week_id"] : ($input["week_id"] ?? null);
            deleteWeek($db, $weekId);
        }

        sendError("Method not allowed", 405);
    }


    // COMMENTS RESOURCE
    elseif ($resource === "comments") {

        if ($method === "GET") {
            getCommentsByWeek($db, $_GET["week_id"] ?? null);
        }

        if ($method === "POST") createComment($db, $input);

        if ($method === "DELETE") {
            $id = isset($_GET["id"]) ? $_GET["id"] : ($input["id"] ?? null);
            deleteComment($db, $id);
        }

        sendError("Method not allowed", 405);
    }

    
    else {
        sendError("Invalid resource. Use 'weeks' or 'comments'", 400);
    }

} catch (Exception $e) {
    sendError("Server error occurred", 500);
}

?>
