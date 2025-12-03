<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ----------------------------
// Database Connection
// ----------------------------
class Database {
    private $host = "localhost";
    private $db_name = "discussion_board";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo json_encode(["success"=>false, "message"=>"Database Connection Error"]);
            exit;
        }
        return $this->conn;
    }
}

$database = new Database();
$db = $database->getConnection();

// ----------------------------
// Request Method & Body
// ----------------------------
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);

// ----------------------------
// Helper Functions
// ----------------------------
function sendResponse($data, $statusCode=200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sanitizeInput($data) {
    if (!is_string($data)) $data = strval($data);
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function isValidResource($resource) {
    $allowed = ['topics', 'replies'];
    return in_array($resource, $allowed);
}

// ----------------------------
// Get resource from query
// ----------------------------
$resource = $_GET['resource'] ?? '';
if (!isValidResource($resource)) sendResponse(["success"=>false,"message"=>"Invalid resource"],400);

// ----------------------------
// TOPICS FUNCTIONS
// ----------------------------
function getAllTopics($db) {
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'created_at';
    $order = $_GET['order'] ?? 'desc';
    $allowedSort = ['subject','author','created_at'];
    $allowedOrder = ['asc','desc'];
    $sort = in_array($sort,$allowedSort) ? $sort : 'created_at';
    $order = in_array($order,$allowedOrder) ? $order : 'desc';
    
    $sql = "SELECT topic_id, subject, message, author, DATE(created_at) as date FROM topics";
    $params = [];
    if ($search) {
        $sql .= " WHERE subject LIKE :s OR message LIKE :s OR author LIKE :s";
        $params[':s'] = "%$search%";
    }
    $sql .= " ORDER BY $sort $order";
    $stmt = $db->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(["success"=>true,"data"=>$rows]);
}

function getTopicById($db,$topicId) {
    if (!$topicId) sendResponse(["success"=>false,"message"=>"Topic ID required"],400);
    $stmt = $db->prepare("SELECT topic_id, subject, message, author, DATE(created_at) as date FROM topics WHERE topic_id=:id");
    $stmt->bindValue(':id',$topicId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) sendResponse(["success"=>true,"data"=>$row]);
    else sendResponse(["success"=>false,"message"=>"Topic not found"],404);
}

function createTopic($db,$data) {
    $topic_id = sanitizeInput($data['topic_id'] ?? '');
    $subject = sanitizeInput($data['subject'] ?? '');
    $message = sanitizeInput($data['message'] ?? '');
    $author = sanitizeInput($data['author'] ?? '');
    if (!$topic_id || !$subject || !$message || !$author)
        sendResponse(["success"=>false,"message"=>"Missing fields"],400);
    
    $check = $db->prepare("SELECT topic_id FROM topics WHERE topic_id=:id");
    $check->bindValue(':id',$topic_id);
    $check->execute();
    if ($check->fetch()) sendResponse(["success"=>false,"message"=>"Topic ID already exists"],409);

    $stmt = $db->prepare("INSERT INTO topics(topic_id,subject,message,author) VALUES(:id,:subject,:msg,:author)");
    $stmt->bindValue(':id',$topic_id);
    $stmt->bindValue(':subject',$subject);
    $stmt->bindValue(':msg',$message);
    $stmt->bindValue(':author',$author);
    if ($stmt->execute()) sendResponse(["success"=>true,"topic_id"=>$topic_id],201);
    else sendResponse(["success"=>false,"message"=>"Failed to create topic"],500);
}

function updateTopic($db,$data) {
    $topic_id = sanitizeInput($data['topic_id'] ?? '');
    if (!$topic_id) sendResponse(["success"=>false,"message"=>"Topic ID required"],400);
    $stmt = $db->prepare("SELECT topic_id FROM topics WHERE topic_id=:id");
    $stmt->bindValue(':id',$topic_id);
    $stmt->execute();
    if (!$stmt->fetch()) sendResponse(["success"=>false,"message"=>"Topic not found"],404);

    $updates = [];
    $params = [];
    if (!empty($data['subject'])) { $updates[]="subject=:subject"; $params[':subject']=sanitizeInput($data['subject']); }
    if (!empty($data['message'])) { $updates[]="message=:msg"; $params[':msg']=sanitizeInput($data['message']); }
    if (!$updates) sendResponse(["success"=>false,"message"=>"No fields to update"],400);

    $sql = "UPDATE topics SET ".implode(", ",$updates)." WHERE topic_id=:id";
    $params[':id']=$topic_id;
    $stmt = $db->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->execute();
    sendResponse(["success"=>true,"message"=>"Topic updated"]);
}

function deleteTopic($db,$topicId) {
    if (!$topicId) sendResponse(["success"=>false,"message"=>"Topic ID required"],400);
    $stmt = $db->prepare("SELECT topic_id FROM topics WHERE topic_id=:id");
    $stmt->bindValue(':id',$topicId);
    $stmt->execute();
    if (!$stmt->fetch()) sendResponse(["success"=>false,"message"=>"Topic not found"],404);

    // Delete replies first
    $stmt = $db->prepare("DELETE FROM replies WHERE topic_id=:id");
    $stmt->bindValue(':id',$topicId);
    $stmt->execute();

    // Delete topic
    $stmt = $db->prepare("DELETE FROM topics WHERE topic_id=:id");
    $stmt->bindValue(':id',$topicId);
    if ($stmt->execute()) sendResponse(["success"=>true,"message"=>"Topic deleted"]);
    else sendResponse(["success"=>false,"message"=>"Failed to delete topic"],500);
}

// ----------------------------
// REPLIES FUNCTIONS
// ----------------------------
function getRepliesByTopicId($db,$topicId) {
    if (!$topicId) sendResponse(["success"=>false,"message"=>"Topic ID required"],400);
    $stmt = $db->prepare("SELECT reply_id, topic_id, text, author, DATE(created_at) as date FROM replies WHERE topic_id=:id ORDER BY created_at ASC");
    $stmt->bindValue(':id',$topicId);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(["success"=>true,"data"=>$rows]);
}

function createReply($db,$data) {
    $reply_id = sanitizeInput($data['reply_id'] ?? '');
    $topic_id = sanitizeInput($data['topic_id'] ?? '');
    $text = sanitizeInput($data['text'] ?? '');
    $author = sanitizeInput($data['author'] ?? '');
    if (!$reply_id || !$topic_id || !$text || !$author) sendResponse(["success"=>false,"message"=>"Missing fields"],400);

    $checkTopic = $db->prepare("SELECT topic_id FROM topics WHERE topic_id=:id");
    $checkTopic->bindValue(':id',$topic_id);
    $checkTopic->execute();
    if (!$checkTopic->fetch()) sendResponse(["success"=>false,"message"=>"Topic not found"],404);

    $check = $db->prepare("SELECT reply_id FROM replies WHERE reply_id=:id");
    $check->bindValue(':id',$reply_id);
    $check->execute();
    if ($check->fetch()) sendResponse(["success"=>false,"message"=>"Reply ID already exists"],409);

    $stmt = $db->prepare("INSERT INTO replies(reply_id, topic_id, text, author) VALUES(:rid,:tid,:txt,:auth)");
    $stmt->bindValue(':rid',$reply_id);
    $stmt->bindValue(':tid',$topic_id);
    $stmt->bindValue(':txt',$text);
    $stmt->bindValue(':auth',$author);
    if ($stmt->execute()) sendResponse(["success"=>true,"reply_id"=>$reply_id],201);
    else sendResponse(["success"=>false,"message"=>"Failed to create reply"],500);
}

function deleteReply($db,$replyId) {
    if (!$replyId) sendResponse(["success"=>false,"message"=>"Reply ID required"],400);
    $stmt = $db->prepare("SELECT reply_id FROM replies WHERE reply_id=:id");
    $stmt->bindValue(':id',$replyId);
    $stmt->execute();
    if (!$stmt->fetch()) sendResponse(["success"=>false,"message"=>"Reply not found"],404);

    $stmt = $db->prepare("DELETE FROM replies WHERE reply_id=:id");
    $stmt->bindValue(':id',$replyId);
    if ($stmt->execute()) sendResponse(["success"=>true,"message"=>"Reply deleted"]);
    else sendResponse(["success"=>false,"message"=>"Failed to delete reply"],500);
}

// ----------------------------
// MAIN ROUTER
// ----------------------------
try {
    if ($resource === 'topics') {
        switch($method) {
            case 'GET':
                $id = $_GET['id'] ?? null;
                if ($id) getTopicById($db,$id);
                else getAllTopics($db);
            case 'POST':
                createTopic($db,$input);
            case 'PUT':
                updateTopic($db,$input);
            case 'DELETE':
                $id = $_GET['id'] ?? null;
                deleteTopic($db,$id);
            default:
                sendResponse(["success"=>false,"message"=>"Method Not Allowed"],405);
        }
    }
    if ($resource === 'replies') {
        switch($method) {
            case 'GET':
                $topic_id = $_GET['topic_id'] ?? null;
                getRepliesByTopicId($db,$topic_id);
            case 'POST':
                createReply($db,$input);
            case 'DELETE':
                $id = $_GET['id'] ?? null;
                deleteReply($db,$id);
            default:
                sendResponse(["success"=>false,"message"=>"Method Not Allowed"],405);
        }
    }
} catch(PDOException $e) {
    error_log($e->getMessage());
    sendResponse(["success"=>false,"message"=>"Database error"],500);
} catch(Exception $e) {
    error_log($e->getMessage());
    sendResponse(["success"=>false,"message"=>"Server error"],500);
}

?>
