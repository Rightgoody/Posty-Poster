<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = new mysqli('localhost', 'meow', 'plumeria', 'ReadIt_data');

if ($db->connect_error) {
    die(json_encode([
        'error' => 'Database connection failed: ' . $db->connect_error,
        'details' => 'Check your MySQL credentials and ensure database exists'
    ]));
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        if ($method === 'POST') registerUser($db, $input);
        break;
    case 'login':
        if ($method === 'POST') loginUser($db, $input);
        break;
    case 'create_post':
        if ($method === 'POST') createPost($db, $input);
        break;
    case 'get_posts':
        if ($method === 'GET') getPosts($db);
        break;
    case 'create_reply':
        if ($method === 'POST') createReply($db, $input);
        break;
    case 'get_replies':
        if ($method === 'GET') getReplies($db, $_GET['post_id']);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function registerUser($db, $data) {
    try {
        $required = ['email', 'username', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required");
            }
        }

        $email = $data['email'];
        $username = $data['username'];
        $password = $data['password'];

        // Check for existing user
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            throw new Exception('Username or email already exists');
        }

        // Insert new user
        $stmt = $db->prepare("INSERT INTO users (email, username, password) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception('Database error: ' . $db->error);
        }

        $stmt->bind_param("sss", $email, $username, $password);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User registered']);
        } else {
            throw new Exception('Registration failed: ' . $stmt->error);
        }
    } catch (Exception $e) {
        echo json_encode([
            'error' => $e->getMessage(),
            'mysql_error' => $db->error ?? null
        ]);
    }
}

function loginUser($db, $data) {
    try {
        if (empty($data['username']) || empty($data['password'])) {
            throw new Exception('Username and password required');
        }

        $stmt = $db->prepare("SELECT id, username FROM users WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $data['username'], $data['password']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'user' => $user,
                'message' => 'Login successful'
            ]);
        } else {
            throw new Exception('Invalid credentials');
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// [Keep other functions unchanged as they're already MySQL compatible]
?>