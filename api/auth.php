<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';

// Start session to store logged in user if not using JWT
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($action === 'signup') {
        $firstName = sanitize_html($data['first_name'] ?? '');
        $lastName = sanitize_html($data['last_name'] ?? '');
        $email = sanitize_html($data['email'] ?? '');
        $phone = sanitize_html($data['phone'] ?? '');
        $password = $data['password'] ?? '';
        
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already registered.']);
            exit;
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$firstName, $lastName, $email, $phone, $hash])) {
            $userId = $pdo->lastInsertId();
            $_SESSION['customer_id'] = $userId;
            send_welcome_email($pdo, $email, trim("$firstName $lastName"));
            
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful.',
                'user' => [
                    'id' => $userId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed.']);
        }
    } 
    elseif ($action === 'login') {
        $email = sanitize_html($data['email'] ?? '');
        $password = $data['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password_hash, phone, gender FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            $_SESSION['customer_id'] = $user['id'];
            
            // Generate a simple token (in a real app, use JWT)
            $token = base64_encode(json_encode(['id' => $user['id'], 'time' => time()]));
            
            echo json_encode([
                'success' => true, 
                'message' => 'Login successful.',
                'user' => $user,
                'token' => $token
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'me') {
        // Read token from Authorization header
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $decoded = json_decode(base64_decode($token), true);
            
            if ($decoded && isset($decoded['id'])) {
                $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, gender FROM customers WHERE id = ?");
                $stmt->execute([$decoded['id']]);
                $user = $stmt->fetch();
                
                if ($user) {
                    echo json_encode(['success' => true, 'user' => $user]);
                    exit;
                }
            }
        }
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    }
}
