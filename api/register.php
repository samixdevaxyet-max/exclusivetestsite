<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ПРАВИЛЬНЫЙ ПУТЬ
$data_file = __DIR__ . '/../data/users.json';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = $input['username'] ?? '';
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode([
            'success' => false,
            'error' => 'Все поля обязательны для заполнения'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $users = readData($data_file);
    
    // Проверяем существующего пользователя
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            echo json_encode([
                'success' => false,
                'error' => 'Пользователь с таким логином уже существует'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($user['email'] === $email) {
            echo json_encode([
                'success' => false,
                'error' => 'Пользователь с таким email уже существует'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // Генерируем новый UID (просто порядковый номер)
    $new_uid = count($users) + 1;
    
    // Создаем нового пользователя
    $new_user = [
        'id' => $new_uid,
        'uid' => $new_uid,
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'role' => 'user',
        'subscription' => 'None',
        'subscriptionExpiry' => null,
        'registrationDate' => date('d.m.Y')
    ];
    
    $users[] = $new_user;
    writeData($data_file, $users);
    
    // Убираем пароль из ответа
    unset($new_user['password']);
    
    echo json_encode([
        'success' => true,
        'user' => $new_user,
        'message' => 'Регистрация успешна'
    ], JSON_UNESCAPED_UNICODE);
    
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Метод не разрешен'
    ], JSON_UNESCAPED_UNICODE);
}
?>