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

function writeData($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function logHWIDAction($action, $username, $admin = '', $message = '') {
    $log_file = __DIR__ . '/../debug_hwid.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "$timestamp - $action - User: $username - By: $admin - $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = $input['username'] ?? '';
    $admin_username = $input['admin_username'] ?? '';
    
    $users = readData($users_file);
    
    // Проверяем права администратора
    $is_admin = false;
    foreach ($users as $user) {
        if ($user['username'] === $admin_username && $user['role'] === 'admin') {
            $is_admin = true;
            break;
        }
    }
    
    if (!$is_admin) {
        echo json_encode([
            'success' => false,
            'error' => 'Admin rights required'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $user_updated = false;
    $old_hwid = null;
    foreach ($users as &$user) {
        if ($user['username'] === $username) {
            $old_hwid = $user['hwid'] ?? null;
            $user['hwid'] = null;
            $user['hwid_bound_date'] = null;
            $user_updated = true;
            break;
        }
    }
    
    if ($user_updated) {
        writeData($users_file, $users);
        logHWIDAction('RESET_HWID', $username, $admin_username, "Old HWID: $old_hwid");
        
        echo json_encode([
            'success' => true,
            'message' => 'HWID reset successfully',
            'old_hwid' => $old_hwid
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'User not found'
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
}
?>