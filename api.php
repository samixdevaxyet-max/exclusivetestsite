<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Безопасность - отключаем вывод ошибок
error_reporting(0);
ini_set('display_errors', 0);

// Файловая база данных
$db_file = __DIR__ . '/database.json';

function getDatabase() {
    global $db_file;
    
    if (!file_exists($db_file)) {
        $initial_data = [
            'users' => [
                [
                    'id' => 1,
                    'uid' => 1001,
                    'username' => 'admin',
                    'email' => 'admin@exclusive.ru',
                    'password' => 'admin123',
                    'role' => 'admin',
                    'subscription' => 'Forever',
                    'registrationDate' => '28.02.2023',
                    'hwid' => null
                ],
                [
                    'id' => 2,
                    'uid' => 1002,
                    'username' => 'samixzxc',
                    'email' => 'samixzxc@mail.ru',
                    'password' => '12345',
                    'role' => 'user',
                    'subscription' => 'Reborn',
                    'registrationDate' => '28.02.2023',
                    'hwid' => null
                ]
            ],
            'licenses' => [],
            'last_uid' => 1002
        ];
        file_put_contents($db_file, json_encode($initial_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    $data = file_get_contents($db_file);
    return json_decode($data, true);
}

function saveDatabase($data) {
    global $db_file;
    file_put_contents($db_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    $db = getDatabase();
    
    switch ($action) {
        case 'web_auth':
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            
            $user = null;
            foreach ($db['users'] as $u) {
                if ($u['username'] === $username && $u['password'] === $password) {
                    $user = $u;
                    break;
                }
            }
            
            if ($user) {
                echo json_encode([
                    'success' => true,
                    'user' => $user
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Invalid credentials'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'web_register':
            $username = $input['username'] ?? '';
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            foreach ($db['users'] as $u) {
                if ($u['username'] === $username) {
                    echo json_encode([
                        'success' => false, 
                        'error' => 'Username already exists'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }
                if ($u['email'] === $email) {
                    echo json_encode([
                        'success' => false, 
                        'error' => 'Email already exists'
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }
            }
            
            // Генерируем новый UID
            $db['last_uid'] = $db['last_uid'] + 1;
            $newUid = $db['last_uid'];
            
            $newUser = [
                'id' => count($db['users']) + 1,
                'uid' => $newUid,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => 'user',
                'subscription' => 'None',
                'registrationDate' => date('d.m.Y'),
                'hwid' => null
            ];
            
            $db['users'][] = $newUser;
            saveDatabase($db);
            
            echo json_encode([
                'success' => true,
                'user' => $newUser,
                'message' => 'Registration successful'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'update_user_role':
            $userId = $input['userId'] ?? '';
            $newRole = $input['role'] ?? '';
            
            foreach ($db['users'] as &$user) {
                if ($user['id'] == $userId) {
                    $user['role'] = $newRole;
                    break;
                }
            }
            
            saveDatabase($db);
            
            echo json_encode([
                'success' => true,
                'message' => 'Role updated successfully'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode([
                'success' => false, 
                'error' => 'Unknown action'
            ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
}
?>