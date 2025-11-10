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

// Функция для логирования
function logHWIDAction($action, $username, $hwid = null, $message = '') {
    $log_file = __DIR__ . '/../debug_hwid.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "$timestamp - $action - User: $username";
    if ($hwid) {
        $log_message .= " - HWID: $hwid";
    }
    if ($message) {
        $log_message .= " - $message";
    }
    $log_message .= "\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    $users = readData($users_file);
    
    switch ($action) {
        case 'bind_hwid':
            $username = $input['username'] ?? '';
            $hwid = $input['hwid'] ?? '';
            
            if (empty($username) || empty($hwid)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Username and HWID are required'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $user_updated = false;
            foreach ($users as &$user) {
                if ($user['username'] === $username) {
                    // Если HWID уже привязан и отличается
                    if (!empty($user['hwid']) && $user['hwid'] !== $hwid) {
                        logHWIDAction('BIND_FAILED', $username, $hwid, 'HWID already bound to another device');
                        echo json_encode([
                            'success' => false,
                            'error' => 'HWID already bound to another device. Current HWID: ' . $user['hwid']
                        ], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    
                    // Привязываем HWID
                    $user['hwid'] = $hwid;
                    $user['hwid_bound_date'] = date('d.m.Y H:i:s');
                    $user_updated = true;
                    logHWIDAction('BIND_SUCCESS', $username, $hwid, 'HWID bound successfully');
                    break;
                }
            }
            
            if ($user_updated) {
                writeData($users_file, $users);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'HWID successfully bound',
                    'hwid' => $hwid
                ], JSON_UNESCAPED_UNICODE);
            } else {
                logHWIDAction('BIND_FAILED', $username, $hwid, 'User not found');
                echo json_encode([
                    'success' => false,
                    'error' => 'User not found'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'check_hwid':
            $username = $input['username'] ?? '';
            $hwid = $input['hwid'] ?? '';
            
            $user_found = null;
            foreach ($users as $user) {
                if ($user['username'] === $username) {
                    $user_found = $user;
                    break;
                }
            }
            
            if ($user_found) {
                if (empty($user_found['hwid'])) {
                    // HWID не привязан - разрешаем доступ
                    logHWIDAction('CHECK_SUCCESS', $username, $hwid, 'HWID not bound - access granted');
                    echo json_encode([
                        'success' => true,
                        'bound' => false,
                        'message' => 'HWID not bound'
                    ], JSON_UNESCAPED_UNICODE);
                } elseif ($user_found['hwid'] === $hwid) {
                    // HWID совпадает
                    logHWIDAction('CHECK_SUCCESS', $username, $hwid, 'HWID matches - access granted');
                    echo json_encode([
                        'success' => true,
                        'bound' => true,
                        'message' => 'HWID matches'
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    // HWID не совпадает
                    logHWIDAction('CHECK_FAILED', $username, $hwid, 'HWID mismatch - access denied');
                    echo json_encode([
                        'success' => false,
                        'error' => 'HWID mismatch. Your HWID: ' . $hwid . ', Bound HWID: ' . $user_found['hwid']
                    ], JSON_UNESCAPED_UNICODE);
                }
            } else {
                logHWIDAction('CHECK_FAILED', $username, $hwid, 'User not found');
                echo json_encode([
                    'success' => false,
                    'error' => 'User not found'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Unknown action'
            ], JSON_UNESCAPED_UNICODE);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $users = readData($users_file);
    
    switch ($action) {
        case 'get_hwid':
            $username = $_GET['username'] ?? '';
            
            $user_found = null;
            foreach ($users as $user) {
                if ($user['username'] === $username) {
                    $user_found = $user;
                    break;
                }
            }
            
            if ($user_found) {
                echo json_encode([
                    'success' => true,
                    'hwid' => $user_found['hwid'] ?? null,
                    'hwid_bound_date' => $user_found['hwid_bound_date'] ?? null
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'User not found'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'get_all_hwids':
            // Только для администраторов
            $admin_username = $_GET['admin'] ?? '';
            
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
                    'error' => 'Admin access required'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $hwid_list = [];
            foreach ($users as $user) {
                if (!empty($user['hwid'])) {
                    $hwid_list[] = [
                        'username' => $user['username'],
                        'hwid' => $user['hwid'],
                        'bound_date' => $user['hwid_bound_date'] ?? 'Unknown',
                        'subscription' => $user['subscription']
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'hwids' => $hwid_list,
                'total' => count($hwid_list)
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