<?php
header("Content-Type: application/json");

// Database connection
$db = new mysqli('localhost', 'meow', 'plumeria', 'ReadIt_data');

if ($db->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

// Get the HTTP method and request data
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Simple router
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        if ($method === 'POST') {
            registerUser($db, $input);
        }
        break;
    case 'login':
        if ($method === 'POST') {
            loginUser($db, $input);
        }
        break;
    case 'create_post':
        if ($method === 'POST') {
            createPost($db, $input);
        }
        break;
    case 'get_posts':
        if ($method === 'GET') {
            getPosts($db);
        }
        break;
    case 'create_reply':
        if ($method === 'POST') {
            createReply($db, $input);
        }
        break;
    case 'get_replies':
        if ($method === 'GET') {
            getReplies($db, $_GET['post_id']);
        }
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

// User registration (INSECURE - for demo only)
function registerUser($db, $data) {
    if (empty($data['email']) || empty($data['username']) || empty($data['password'])) {
        echo json_encode(['error' => 'Email, username, and password are required']);
        return;
    }

    $email = $data['email'];
    $username = $data['username'];
    $password = $data['password']; // Insecure: store hashed password in real apps

    $stmt = $db->prepare("INSERT INTO users (email, username, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $username, $password);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'User registered']);
    } else {
        echo json_encode(['error' => 'Registration failed — maybe username/email already exists']);
    }
}


// User login (INSECURE - for demo only)
function loginUser($db, $data) {
    if (empty($data['username']) || empty($data['password'])) {
        echo json_encode(['error' => 'Username and password required']);
        return;
    }
    
    $stmt = $db->prepare("SELECT id, username FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $data['username'], $data['password']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(['message' => 'Login successful', 'user' => $user]);
    } else {
        echo json_encode(['error' => 'Invalid credentials']);
    }
}

// Create a new post
function createPost($db, $data) {
    if (empty($data['user_id']) || empty($data['title']) || empty($data['content'])) {
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $data['user_id'], $data['title'], $data['content']);
    
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Post created']);
    } else {
        echo json_encode(['error' => 'Failed to create post']);
    }
}

// Get all posts
function getPosts($db) {
    $result = $db->query("
        SELECT p.*, u.username 
        FROM posts p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
    ");
    
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    
    echo json_encode(['posts' => $posts]);
}

// Create a reply to a post
function createReply($db, $data) {
    if (empty($data['post_id']) || empty($data['user_id']) || empty($data['content'])) {
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO replies (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $data['post_id'], $data['user_id'], $data['content']);
    
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Reply created']);
    } else {
        echo json_encode(['error' => 'Failed to create reply']);
    }
}

// Get replies for a post
function getReplies($db, $postId) {
    $stmt = $db->prepare("
        SELECT r.*, u.username 
        FROM replies r
        JOIN users u ON r.user_id = u.id
        WHERE r.post_id = ?
        ORDER BY r.created_at ASC
    ");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $replies = [];
    while ($row = $result->fetch_assoc()) {
        $replies[] = $row;
    }
    
    echo json_encode(['replies' => $replies]);
}
?>