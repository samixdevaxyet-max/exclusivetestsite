<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
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
    $auth_token = $input['auth_token'] ?? '';
    $action = $input['action'] ?? '';
    
    if (empty($username)) {
        echo json_encode([
            'success' => false,
            'error' => 'Username is required'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $users = readData($users_file);
    
    $user = null;
    foreach ($users as $u) {
        if ($u['username'] === $username) {
            $user = $u;
            break;
        }
    }
    
    if ($user) {
        // Проверяем подписку
        $hasValidSubscription = $user['subscription'] !== 'None';
        
        if ($hasValidSubscription) {
            echo json_encode([
                'success' => true,
                'message' => 'User verified successfully',
                'username' => $user['username'],
                'subscription' => $user['subscription'],
                'expiry' => $user['subscriptionExpiry'] ?? null
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No active subscription'
            ], JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'User not found'
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ], JSON_UNESCAPED_UNICODE);
}
?>