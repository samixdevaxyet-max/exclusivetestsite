<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ПРАВИЛЬНЫЕ ПУТИ
$users_file = __DIR__ . '/../data/users.json';
$licenses_file = __DIR__ . '/../data/licenses.json';

function readData($file) {
    if (!file_exists($file)) {
        writeData($file, []);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    $users = readData($users_file);
    
    switch ($action) {
        case 'get_all':
            // Убираем пароли из ответа
            $users_safe = array_map(function($user) {
                unset($user['password']);
                return $user;
            }, $users);
            
            echo json_encode([
                'success' => true,
                'users' => $users_safe
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'update_role':
            $user_id = $input['userId'] ?? '';
            $new_role = $input['role'] ?? '';
            
            $user_updated = false;
            foreach ($users as &$user) {
                if ($user['id'] == $user_id) {
                    $user['role'] = $new_role;
                    $user_updated = true;
                    break;
                }
            }
            
            if ($user_updated) {
                writeData($users_file, $users);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Роль пользователя обновлена'
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Пользователь не найден'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'get_stats':
            $licenses = readData($licenses_file);
            
            $stats = [
                'totalUsers' => count($users),
                'adminUsers' => count(array_filter($users, function($user) {
                    return $user['role'] === 'admin';
                })),
                'activeSubs' => count(array_filter($users, function($user) {
                    return $user['subscription'] !== 'None';
                })),
                'totalLicenses' => count($licenses),
                'usedLicenses' => count(array_filter($licenses, function($license) {
                    return $license['used'];
                }))
            ];
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Неизвестное действие'
            ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Метод не разрешен'
    ], JSON_UNESCAPED_UNICODE);
}
?>