<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ПРАВИЛЬНЫЕ ПУТИ - данные в папке data на одном уровне с api
$licenses_file = __DIR__ . '/../data/licenses.json';
$users_file = __DIR__ . '/../data/users.json';

// Функция для логирования
function logMessage($message) {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
}

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

function generateLicenseKey() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $key = '';
    
    for ($i = 0; $i < 4; $i++) {
        for ($j = 0; $j < 5; $j++) {
            $key .= $chars[rand(0, strlen($chars) - 1)];
        }
        if ($i < 3) $key .= '-';
    }
    
    return $key;
}

function calculateExpiryDate($type) {
    switch($type) {
        case '1 Month':
            return date('d.m.Y', strtotime('+1 month'));
        case '3 Months':
            return date('d.m.Y', strtotime('+3 months'));
        case '6 Months':
            return date('d.m.Y', strtotime('+6 months'));
        case '1 Year':
            return date('d.m.Y', strtotime('+1 year'));
        case 'Lifetime':
            return '01.01.2038';
        default:
            return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    logMessage("Action: $action, Input: " . json_encode($input));
    
    $licenses = readData($licenses_file);
    $users = readData($users_file);
    
    logMessage("Total users: " . count($users) . ", Total licenses: " . count($licenses));
    
    switch ($action) {
        case 'generate':
            $type = $input['type'] ?? '';
            $amount = intval($input['amount'] ?? 1);
            $created_by = $input['createdBy'] ?? 'system';
            
            if ($amount < 1 || $amount > 20) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Количество ключей должно быть от 1 до 20'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $new_keys = [];
            for ($i = 0; $i < $amount; $i++) {
                $key = generateLicenseKey();
                $new_keys[] = $key;
                
                $licenses[] = [
                    'key' => $key,
                    'type' => $type,
                    'expiryDate' => calculateExpiryDate($type),
                    'used' => false,
                    'createdAt' => date('d.m.Y H:i:s'),
                    'createdBy' => $created_by
                ];
            }
            
            writeData($licenses_file, $licenses);
            logMessage("Generated $amount keys of type: $type");
            
            echo json_encode([
                'success' => true,
                'keys' => $new_keys,
                'type' => $type
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'get_active':
            $active_licenses = array_filter($licenses, function($license) {
                return !$license['used'];
            });
            $active_licenses = array_slice($active_licenses, 0, 5);
            
            echo json_encode([
                'success' => true,
                'licenses' => $active_licenses
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'activate':
            $key = $input['key'] ?? '';
            $username = $input['username'] ?? '';
            
            logMessage("Activation attempt - Key: $key, Username: $username");
            
            if (empty($key) || empty($username)) {
                logMessage("Activation failed: Key or username empty");
                echo json_encode([
                    'success' => false,
                    'error' => 'Ключ и имя пользователя обязательны'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Находим ключ
            $license_index = -1;
            foreach ($licenses as $index => $license) {
                if ($license['key'] === $key && !$license['used']) {
                    $license_index = $index;
                    break;
                }
            }
            
            if ($license_index === -1) {
                logMessage("Activation failed: Invalid key - $key");
                echo json_encode([
                    'success' => false,
                    'error' => 'Неверный или уже использованный ключ'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Находим пользователя
            $user_index = -1;
            $found_users = [];
            foreach ($users as $index => $user) {
                $found_users[] = $user['username'];
                if ($user['username'] === $username) {
                    $user_index = $index;
                    break;
                }
            }
            
            logMessage("Available users: " . implode(', ', $found_users));
            
            if ($user_index === -1) {
                logMessage("Activation failed: User not found - $username");
                echo json_encode([
                    'success' => false,
                    'error' => 'Пользователь не найден. Доступные пользователи: ' . implode(', ', $found_users)
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Активируем ключ
            $licenses[$license_index]['used'] = true;
            $licenses[$license_index]['usedBy'] = $username;
            $licenses[$license_index]['activatedAt'] = date('d.m.Y H:i:s');
            
            // Обновляем подписку пользователя
            $users[$user_index]['subscription'] = 'Lifetime';
            $users[$user_index]['subscriptionExpiry'] = $licenses[$license_index]['expiryDate'];
            
            writeData($users_file, $users);
            writeData($licenses_file, $licenses);
            
            logMessage("Activation successful - User: $username, Key: $key, Expiry: " . $licenses[$license_index]['expiryDate']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Подписка успешно активирована',
                'subscription' => 'Lifetime',
                'expiryDate' => $licenses[$license_index]['expiryDate']
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