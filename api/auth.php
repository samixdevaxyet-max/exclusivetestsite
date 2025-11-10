<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Включаем вывод ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

$data_file = '../data/users.json';

// Функция для чтения данных
function readData($file) {
    if (!file_exists($file)) {
        return [];
    }
    $data = file_get_contents($file);
    return json_decode($data, true) ?: [];
}

// Функция для записи данных
function writeData($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Инициализация базы данных при первом запуске
function initializeDatabase() {
    global $data_file;
    
    $users = readData($data_file);
    
    if (empty($users)) {
        $users = [
            [
                'id' => 1,
                'uid' => 1001,
                'username' => 'admin',
                'email' => 'admin@exclusive.ru',
                'password' => 'admin123',
                'role' => 'admin',
                'subscription' => 'Forever',
                'registrationDate' => '28.02.2023'
            ],
            [
                'id' => 2,
                'uid' => 1002,
                'username' => 'samixzxc',
                'email' => 'samixzxc@mail.ru',
                'password' => '12345',
                'role' => 'user',
                'subscription' => 'Reborn',
                'registrationDate' => '28.02.2023'
            ]
        ];
        writeData($data_file, $users);
    }
    
    return $users;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $action = $_GET['action'] ?? '';
    $users = initializeDatabase();
    
    switch ($action) {
        case 'login':
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            
            $user = null;
            foreach ($users as $u) {
                if ($u['username'] === $username && $u['password'] === $password) {
                    $user = $u;
                    break;
                }
            }
            
            if ($user) {
                // Убираем пароль из ответа для безопасности
                unset($user['password']);
                echo json_encode([
                    'success' => true,
                    'user' => $user
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Неверный логин или пароль'
                ], JSON_UNESCAPED_UNICODE);
            }
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