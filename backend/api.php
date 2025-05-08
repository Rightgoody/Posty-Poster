<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Error reporting (disable in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php/readit_api.log');

// Load configuration
require_once __DIR__ . '/config.php';

// Detect if running from the command line
if (php_sapi_name() === 'cli') {
    // Simulate HTTP variables for CLI
    $method = $argv[1] ?? 'GET'; // First argument is the HTTP method
    parse_str($argv[2] ?? '', $_GET); // Second argument is the query string
    $input = json_decode($argv[3] ?? '{}', true); // Third argument is the JSON input
} else {
    // Web server environment
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

// Database connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($db->connect_error) {
    error_log("Database connection failed: " . $db->connect_error);
    die(json_encode(['error' => 'Service unavailable']));
}

// Get action from query string
$action = $_GET['action'] ?? '';

// Rate limiting function
function rateLimit($key, $limit = 10, $timeout = 60) {
    $cache = new Memcached();
    $cache->addServer('localhost', 11211);
    $count = $cache->get($key) ?: 0;
    
    if ($count >= $limit) {
        http_response_code(429);
        die(json_encode(['error' => 'Too many requests']));
    }
    
    $cache->set($key, $count + 1, $timeout);
    return true;
}

// Main router
switch ($action) {
    case 'register':
        if ($method === 'POST') {
            rateLimit('register_'.$_SERVER['REMOTE_ADDR']);
            registerUser($db, $input);
        }
        break;
        
    case 'login':
        if ($method === 'POST') {
            rateLimit('login_'.$_SERVER['REMOTE_ADDR']);
            loginUser($db, $input);
        }
        break;
        
    case 'create_post':
        if ($method === 'POST') {
            verifySession();
            createPost($db, $input);
        }
        break;
        
    case 'get_posts':
        if ($method === 'GET') {
            getPosts($db, $_GET['page'] ?? 1);
        }
        break;
        
    case 'create_reply':
        if ($method === 'POST') {
            verifySession();
            createReply($db, $input);
        }
        break;
        
    case 'get_replies':
        if ($method === 'GET') {
            getReplies($db, $_GET['post_id'] ?? 0);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

// =====================
// CORE FUNCTIONS
// =====================

function registerUser($db, $data) {
    // Validate input
    $required = ['email', 'username', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            die(json_encode(['error' => "$field is required"]));
        }
    }

    // Sanitize
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', $data['username']);
    $password = password_hash($data['password'], PASSWORD_BCRYPT);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid email']));
    }

    if (strlen($username) < 3 || strlen($username) > 20) {
        http_response_code(400);
        die(json_encode(['error' => 'Username must be 3-20 characters']));
    }

    try {
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            http_response_code(409);
            die(json_encode(['error' => 'Username or email already exists']));
        }

        // Create user
        $stmt = $db->prepare("INSERT INTO users (email, username, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $username, $password);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception($db->error);
        }
    } catch (Exception $e) {
        error_log("Registration Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Registration failed']);
    }
}

function loginUser($db, $data) {
    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Username and password required']));
    }

    $username = $data['username'];
    $password = $data['password'];

    try {
        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            http_response_code(401);
            die(json_encode(['error' => 'Invalid credentials']));
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            http_response_code(401);
            die(json_encode(['error' => 'Invalid credentials']));
        }

        // Start secure session
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => true,
            'cookie_samesite' => 'Strict'
        ]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        session_regenerate_id(true);

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username']
            ]
        ]);
    } catch (Exception $e) {
        error_log("Login Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Login failed']);
    }
}

function createPost($db, $data) {
    if (empty($data['title']) || empty($data['content']) || empty($_SESSION['user_id'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing required fields']));
    }

    $title = htmlspecialchars($data['title'], ENT_QUOTES);
    $content = htmlspecialchars($data['content'], ENT_QUOTES);
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $db->prepare("INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $title, $content);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'post_id' => $stmt->insert_id]);
        } else {
            throw new Exception($db->error);
        }
    } catch (Exception $e) {
        error_log("Post Creation Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create post']);
    }
}

function getPosts($db, $page = 1) {
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    try {
        $stmt = $db->prepare("
            SELECT p.id, p.title, p.content, p.created_at, u.username 
            FROM posts p
            JOIN users u ON p.user_id = u.id
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("ii", $per_page, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $posts = [];
        while ($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }

        echo json_encode(['success' => true, 'posts' => $posts]);
    } catch (Exception $e) {
        error_log("Get Posts Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch posts']);
    }
}

function createReply($db, $data) {
    if (empty($data['post_id']) || empty($data['content']) || empty($_SESSION['user_id'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing required fields']));
    }

    $post_id = (int)$data['post_id'];
    $content = htmlspecialchars($data['content'], ENT_QUOTES);
    $user_id = $_SESSION['user_id'];

    try {
        // Verify post exists
        $check = $db->prepare("SELECT id FROM posts WHERE id = ?");
        $check->bind_param("i", $post_id);
        $check->execute();
        
        if ($check->get_result()->num_rows === 0) {
            http_response_code(404);
            die(json_encode(['error' => 'Post not found']));
        }

        $stmt = $db->prepare("INSERT INTO replies (post_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $post_id, $user_id, $content);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'reply_id' => $stmt->insert_id]);
        } else {
            throw new Exception($db->error);
        }
    } catch (Exception $e) {
        error_log("Reply Creation Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create reply']);
    }
}

function getReplies($db, $post_id) {
    $post_id = (int)$post_id;
    
    if ($post_id <= 0) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid post ID']));
    }

    try {
        $stmt = $db->prepare("
            SELECT r.id, r.content, r.created_at, u.username
            FROM replies r
            JOIN users u ON r.user_id = u.id
            WHERE r.post_id = ?
            ORDER BY r.created_at ASC
        ");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $replies = [];
        while ($row = $result->fetch_assoc()) {
            $replies[] = $row;
        }

        echo json_encode(['success' => true, 'replies' => $replies]);
    } catch (Exception $e) {
        error_log("Get Replies Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch replies']);
    }
}

function verifySession() {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => true,
        'cookie_samesite' => 'Strict'
    ]);
    
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }
}
?>