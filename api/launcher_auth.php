<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$users_file = __DIR__ . '/../data/users.json';

function readData($file) {
    if (!file_exists($file)) {
        return [];
    }
    $data = file_get_contents($file);
    return json_decode($data, true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode([
            'success' => false,
            'error' => 'Username and password are required'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $users = readData($users_file);
    
    $user = null;
    foreach ($users as $u) {
        if ($u['username'] === $username && $u['password'] === $password) {
            $user = $u;
            break;
        }
    }
    
    if ($user) {
        // Проверяем подписку
        $hasSubscription = $user['subscription'] !== 'None';
        
        echo json_encode([
            'success' => true,
            'user' => [
                'username' => $user['username'],
                'role' => $user['role'],
                'subscription' => $user['subscription'],
                'hasSubscription' => $hasSubscription,
                'uid' => $user['uid']
            ],
            'message' => 'Authentication successful'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid credentials'
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
}
?>