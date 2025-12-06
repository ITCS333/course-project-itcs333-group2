
<?php

/**
 * Weekly Course Breakdown API 
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

// Paths to JSON files
$weeksFile = __DIR__ . "/weeks.json";
$commentsFile = __DIR__ . "/comments.json";

// Load JSON data
$weeks = file_exists($weeksFile) ? json_decode(file_get_contents($weeksFile), true) : [];
$comments = file_exists($commentsFile) ? json_decode(file_get_contents($commentsFile), true) : [];

// Get request info
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);
$resource = isset($_GET["resource"]) ? $_GET["resource"] : "weeks";

// Helper functions
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function sendError($message, $statusCode = 400) {
    sendResponse(["success" => false, "error" => $message], $statusCode);
}

// Save JSON helper
function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Sanitize input
function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)));
}

// ==================== WEEKS FUNCTIONS ====================
function getAllWeeks(&$weeks) {
    sendResponse(["success" => true, "data" => $weeks]);
}

function getWeekById(&$weeks, $weekId) {
    foreach ($weeks as $w) {
        if ($w['week_id'] === $weekId) {
            sendResponse(["success" => true, "data" => $w]);
        }
    }
    sendError("Week not found", 404);
}

function createWeek(&$weeks, $input, $weeksFile) {
    if (!isset($input['week_id'], $input['title'], $input['start_date'], $input['description'])) {
        sendError("Missing required fields", 400);
    }

    foreach ($weeks as $w) {
        if ($w['week_id'] === $input['week_id']) sendError("Week ID already exists", 409);
    }

    $input['links'] = isset($input['links']) ? $input['links'] : [];
    $weeks[] = $input;
    saveJson($weeksFile, $weeks);
    sendResponse(["success" => true, "message" => "Week created successfully"], 201);
}

function updateWeek(&$weeks, $input, $weeksFile) {
    if (!isset($input['week_id'])) sendError("week_id is required", 400);

    foreach ($weeks as &$w) {
        if ($w['week_id'] === $input['week_id']) {
            if (isset($input['title'])) $w['title'] = sanitize($input['title']);
            if (isset($input['start_date'])) $w['start_date'] = sanitize($input['start_date']);
            if (isset($input['description'])) $w['description'] = sanitize($input['description']);
            if (isset($input['links'])) $w['links'] = $input['links'];
            saveJson($weeksFile, $weeks);
            sendResponse(["success" => true, "message" => "Week updated successfully"]);
        }
    }

    sendError("Week not found", 404);
}

function deleteWeek(&$weeks, &$comments, $weekId, $weeksFile, $commentsFile) {
    $found = false;
    foreach ($weeks as $i => $w) {
        if ($w['week_id'] === $weekId) {
            unset($weeks[$i]);
            $weeks = array_values($weeks);
            $found = true;
            break;
        }
    }
    if (!$found) sendError("Week not found", 404);

    // Delete related comments
    foreach ($comments as $i => $c) {
        if ($c['week_id'] === $weekId) {
            unset($comments[$i]);
        }
    }
    $comments = array_values($comments);

    saveJson($weeksFile, $weeks);
    saveJson($commentsFile, $comments);

    sendResponse(["success" => true, "message" => "Week and related comments deleted"]);
}

// ==================== COMMENTS FUNCTIONS ====================
function getCommentsByWeek(&$comments, $weekId) {
    $res = [];
    foreach ($comments as $c) {
        if ($c['week_id'] === $weekId) $res[] = $c;
    }
    sendResponse(["success" => true, "data" => $res]);
}

function createComment(&$comments, $input, $commentsFile) {
    if (!isset($input['week_id'], $input['author'], $input['text'])) sendError("Missing required fields", 400);

    if ($input['text'] === "") sendError("Comment text cannot be empty", 400);

    $input['id'] = count($comments) ? max(array_column($comments, 'id')) + 1 : 1;
    $comments[] = $input;
    saveJson($commentsFile, $comments);
    sendResponse(["success" => true, "message" => "Comment added", "data" => $input], 201);
}

function deleteComment(&$comments, $id, $commentsFile) {
    foreach ($comments as $i => $c) {
        if ($c['id'] == $id) {
            unset($comments[$i]);
            $comments = array_values($comments);
            saveJson($commentsFile, $comments);
            sendResponse(["success" => true, "message" => "Comment deleted"]);
        }
    }
    sendError("Comment not found", 404);
}

// ==================== ROUTING ====================
try {

    if ($resource === "weeks") {
        if ($method === "GET") {
            if (isset($_GET['week_id'])) getWeekById($weeks, $_GET['week_id']);
            else getAllWeeks($weeks);
        } elseif ($method === "POST") createWeek($weeks, $input, $weeksFile);
        elseif ($method === "PUT") updateWeek($weeks, $input, $weeksFile);
        elseif ($method === "DELETE") {
            $weekId = $_GET['week_id'] ?? ($input['week_id'] ?? null);
            deleteWeek($weeks, $comments, $weekId, $weeksFile, $commentsFile);
        } else sendError("Method not allowed", 405);

    } elseif ($resource === "comments") {
        if ($method === "GET") {
            $weekId = $_GET['week_id'] ?? null;
            getCommentsByWeek($comments, $weekId);
        } elseif ($method === "POST") createComment($comments, $input, $commentsFile);
        elseif ($method === "DELETE") {
            $id = $_GET['id'] ?? ($input['id'] ?? null);
            deleteComment($comments, $id, $commentsFile);
        } else sendError("Method not allowed", 405);

    } else sendError("Invalid resource. Use 'weeks' or 'comments'", 400);

} catch (Exception $e) {
    sendError("Server error occurred", 500);
}

?>

