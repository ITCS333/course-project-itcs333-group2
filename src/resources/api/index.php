<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database connection (using PDO) ---
$host = 'localhost';
$dbname = 'course_resources';
$user = 'root';
$pass = ''; // adjust as needed

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    sendResponse(['success'=>false, 'message'=>'Database connection failed.'], 500);
}

// --- Parse request ---
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;

// --- Helper functions ---
function sendResponse($data, $status=200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES);
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// --- Resource functions ---
function getAllResources($db) {
    $search = $_GET['search'] ?? '';
    $sort = in_array($_GET['sort'] ?? '', ['title','created_at']) ? $_GET['sort'] : 'created_at';
    $order = in_array(strtolower($_GET['order'] ?? ''), ['asc','desc']) ? $_GET['order'] : 'desc';

    $sql = "SELECT id, title, description, link, created_at FROM resources";
    if ($search) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
    }
    $sql .= " ORDER BY $sort $order";
    $stmt = $db->prepare($sql);
    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(['success'=>true,'data'=>$results]);
}

function getResourceById($db, $id) {
    if (!$id || !is_numeric($id)) {
        sendResponse(['success'=>false,'message'=>'Invalid resource ID.'], 400);
    }
    $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id=?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        sendResponse(['success'=>true,'data'=>$res]);
    } else {
        sendResponse(['success'=>false,'message'=>'Resource not found.'],404);
    }
}

function createResource($db, $data) {
    if (empty($data['title']) || empty($data['link'])) {
        sendResponse(['success'=>false,'message'=>'Title and link required.'],400);
    }
    $title = sanitize($data['title']);
    $desc = sanitize($data['description'] ?? '');
    $link = sanitize($data['link']);
    if (!validateUrl($link)) sendResponse(['success'=>false,'message'=>'Invalid URL.'],400);

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?,?,?)");
    if ($stmt->execute([$title,$desc,$link])) {
        sendResponse(['success'=>true,'message'=>'Resource created.','id'=>$db->lastInsertId()],201);
    } else {
        sendResponse(['success'=>false,'message'=>'Insert failed.'],500);
    }
}

function updateResource($db, $data) {
    if (empty($data['id'])) sendResponse(['success'=>false,'message'=>'Resource ID required.'],400);
    $id = $data['id'];
    $stmt = $db->prepare("SELECT * FROM resources WHERE id=?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) sendResponse(['success'=>false,'message'=>'Resource not found.'],404);

    $fields = [];
    $values = [];
    if (!empty($data['title'])) { $fields[]="title=?"; $values[]=sanitize($data['title']); }
    if (!empty($data['description'])) { $fields[]="description=?"; $values[]=sanitize($data['description']); }
    if (!empty($data['link'])) { 
        if (!validateUrl($data['link'])) sendResponse(['success'=>false,'message'=>'Invalid URL.'],400);
        $fields[]="link=?"; $values[]=sanitize($data['link']); 
    }
    if (!$fields) sendResponse(['success'=>false,'message'=>'Nothing to update.'],400);

    $sql = "UPDATE resources SET ".implode(',',$fields)." WHERE id=?";
    $values[]=$id;
    $stmt=$db->prepare($sql);
    if($stmt->execute($values)){
        sendResponse(['success'=>true,'message'=>'Resource updated.']);
    } else {
        sendResponse(['success'=>false,'message'=>'Update failed.'],500);
    }
}

function deleteResource($db, $id) {
    if (!$id || !is_numeric($id)) sendResponse(['success'=>false,'message'=>'Invalid resource ID.'],400);
    $stmt = $db->prepare("SELECT * FROM resources WHERE id=?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) sendResponse(['success'=>false,'message'=>'Resource not found.'],404);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("DELETE FROM comments WHERE resource_id=?");
        $stmt->execute([$id]);
        $stmt = $db->prepare("DELETE FROM resources WHERE id=?");
        $stmt->execute([$id]);
        $db->commit();
        sendResponse(['success'=>true,'message'=>'Resource deleted.']);
    } catch(Exception $e){
        $db->rollBack();
        sendResponse(['success'=>false,'message'=>'Delete failed.'],500);
    }
}

// --- Comment functions ---
function getCommentsByResourceId($db,$rid){
    if (!$rid || !is_numeric($rid)) sendResponse(['success'=>false,'message'=>'Invalid resource ID.'],400);
    $stmt=$db->prepare("SELECT id, resource_id, author, text, created_at FROM comments WHERE resource_id=? ORDER BY created_at ASC");
    $stmt->execute([$rid]);
    $res=$stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(['success'=>true,'data'=>$res]);
}

function createComment($db,$data){
    if(empty($data['resource_id']) || empty($data['author']) || empty($data['text'])){
        sendResponse(['success'=>false,'message'=>'resource_id, author, and text are required.'],400);
    }
    $rid=$data['resource_id'];
    if(!is_numeric($rid)) sendResponse(['success'=>false,'message'=>'Invalid resource_id.'],400);
    $stmt=$db->prepare("SELECT * FROM resources WHERE id=?");
    $stmt->execute([$rid]);
    if(!$stmt->fetch()) sendResponse(['success'=>false,'message'=>'Resource not found.'],404);

    $author=sanitize($data['author']);
    $text=sanitize($data['text']);
    $stmt=$db->prepare("INSERT INTO comments (resource_id, author, text) VALUES (?,?,?)");
    if($stmt->execute([$rid,$author,$text])){
        sendResponse(['success'=>true,'message'=>'Comment created.','id'=>$db->lastInsertId()],201);
    } else {
        sendResponse(['success'=>false,'message'=>'Insert failed.'],500);
    }
}

function deleteComment($db,$cid){
    if (!$cid || !is_numeric($cid)) sendResponse(['success'=>false,'message'=>'Invalid comment ID.'],400);
    $stmt=$db->prepare("SELECT * FROM comments WHERE id=?");
    $stmt->execute([$cid]);
    if(!$stmt->fetch()) sendResponse(['success'=>false,'message'=>'Comment not found.'],404);
    $stmt=$db->prepare("DELETE FROM comments WHERE id=?");
    if($stmt->execute([$cid])){
        sendResponse(['success'=>true,'message'=>'Comment deleted.']);
    } else {
        sendResponse(['success'=>false,'message'=>'Delete failed.'],500);
    }
}

// --- Main router ---
try {
    if($method==='GET'){
        if($action==='comments'){
            getCommentsByResourceId($db,$resource_id);
        } elseif ($id){
            getResourceById($db,$id);
        } else {
            getAllResources($db);
        }
    } elseif ($method==='POST'){
        if($action==='comment'){
            createComment($db,$input);
        } else {
            createResource($db,$input);
        }
    } elseif ($method==='PUT'){
        updateResource($db,$input);
    } elseif ($method==='DELETE'){
        if($action==='delete_comment'){
            deleteComment($db,$comment_id ?? $input['comment_id'] ?? null);
        } else {
            deleteResource($db,$id ?? $input['id'] ?? null);
        }
    } else {
        sendResponse(['success'=>false,'message'=>'Method not allowed.'],405);
    }
} catch(PDOException $e){
    error_log($e->getMessage());
    sendResponse(['success'=>false,'message'=>'Database error.'],500);
} catch(Exception $e){
    error_log($e->getMessage());
    sendResponse(['success'=>false,'message'=>'Server error.'],500);
}
?>
