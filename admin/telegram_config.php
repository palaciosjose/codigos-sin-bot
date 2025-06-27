<?php
/**
 * Manejador de configuración de Telegram
 * admin/telegram_config.php
 */

session_start();
require_once '../instalacion/basededatos.php';
require_once '../security/auth.php';

// Verificar que es admin
check_session(true, '../index.php');

header('Content-Type: application/json; charset=utf-8');

// Conexión a la base de datos
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit();
}

/**
 * Obtener configuración actual de Telegram
 */
function getTelegramConfig($conn) {
    $config = [];
    
    $telegram_settings = [
        'TELEGRAM_BOT_TOKEN',
        'TELEGRAM_WEBHOOK_SECRET', 
        'TELEGRAM_RATE_LIMIT',
        'TELEGRAM_MAX_MESSAGE_LENGTH',
        'TELEGRAM_ENABLED'
    ];
    
    $placeholders = str_repeat('?,', count($telegram_settings) - 1) . '?';
    $stmt = $conn->prepare("SELECT name, value FROM settings WHERE name IN ($placeholders)");
    $stmt->bind_param(str_repeat('s', count($telegram_settings)), ...$telegram_settings);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $key = strtolower(str_replace('TELEGRAM_', '', $row['name']));
        $config[$key] = $row['value'];
    }
    
    // Valores por defecto
    $defaults = [
        'bot_token' => '',
        'webhook_secret' => '',
        'rate_limit' => '30',
        'max_message_length' => '4096',
        'enabled' => '0'
    ];
    
    return array_merge($defaults, $config);
}

/**
 * Guardar configuración de Telegram
 */
function saveTelegramConfig($conn, $config) {
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("INSERT INTO settings (name, value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        
        $settings_to_save = [
            ['TELEGRAM_BOT_TOKEN', $config['bot_token'], 'Token del bot de Telegram'],
            ['TELEGRAM_WEBHOOK_SECRET', $config['webhook_secret'], 'Secret del webhook de Telegram'],
            ['TELEGRAM_RATE_LIMIT', $config['rate_limit'], 'Límite de requests por minuto'],
            ['TELEGRAM_MAX_MESSAGE_LENGTH', $config['max_message_length'], 'Longitud máxima de mensajes'],
            ['TELEGRAM_ENABLED', $config['enabled'], 'Bot de Telegram activado/desactivado']
        ];
        
        foreach ($settings_to_save as $setting) {
            $stmt->bind_param('sss', $setting[0], $setting[1], $setting[2]);
            $stmt->execute();
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error guardando configuración de Telegram: " . $e->getMessage());
        return false;
    }
}

/**
 * Validar configuración de Telegram
 */
function validateTelegramConfig($config) {
    $errors = [];
    
    // Validar token del bot
    if (empty($config['bot_token'])) {
        $errors[] = 'El token del bot es obligatorio';
    } elseif (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $config['bot_token'])) {
        $errors[] = 'El formato del token del bot no es válido';
    }
    
    // Validar rate limit
    $rate_limit = intval($config['rate_limit']);
    if ($rate_limit < 1 || $rate_limit > 100) {
        $errors[] = 'El límite de requests debe estar entre 1 y 100';
    }
    
    // Validar longitud de mensaje
    $msg_length = intval($config['max_message_length']);
    if ($msg_length < 100 || $msg_length > 4096) {
        $errors[] = 'La longitud máxima debe estar entre 100 y 4096 caracteres';
    }
    
    return $errors;
}

// Manejar diferentes acciones
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_config':
        $config = getTelegramConfig($conn);
        echo json_encode(['success' => true, 'config' => $config]);
        break;
        
    case 'save_config':
        $config = [
            'bot_token' => $_POST['telegram_bot_token'] ?? '',
            'webhook_secret' => $_POST['telegram_webhook_secret'] ?? '',
            'rate_limit' => $_POST['telegram_rate_limit'] ?? '30',
            'max_message_length' => $_POST['telegram_max_message_length'] ?? '4096',
            'enabled' => $_POST['telegram_enabled'] ?? '0'
        ];
        
        // Validar configuración
        $errors = validateTelegramConfig($config);
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
            break;
        }
        
        // Guardar configuración
        if (saveTelegramConfig($conn, $config)) {
            // También actualizar el archivo .env si existe
            updateEnvFile($config);
            
            echo json_encode(['success' => true, 'message' => 'Configuración guardada exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar la configuración']);
        }
        break;
        
    case 'test_connection':
        $config = getTelegramConfig($conn);
        $result = testBotConnection($config['bot_token']);
        echo json_encode($result);
        break;
        
    case 'setup_webhook':
        $config = getTelegramConfig($conn);
        $webhook_url = "https://" . $_SERVER['HTTP_HOST'] . "/telegram_bot/webhook.php";
        $result = setupWebhook($config['bot_token'], $webhook_url, $config['webhook_secret']);
        echo json_encode($result);
        break;
        
    case 'get_bot_info':
        $config = getTelegramConfig($conn);
        $result = getBotInfo($config['bot_token']);
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Actualizar archivo .env con la configuración
 */
function updateEnvFile($config) {
    $env_file = dirname(__DIR__) . '/.env';
    $env_content = "";
    
    // Leer contenido existente
    if (file_exists($env_file)) {
        $env_content = file_get_contents($env_file);
    }
    
    // Variables de Telegram a actualizar
    $telegram_vars = [
        'TELEGRAM_BOT_TOKEN' => $config['bot_token'],
        'TELEGRAM_WEBHOOK_SECRET' => $config['webhook_secret'],
        'TELEGRAM_WEBHOOK_URL' => "https://" . $_SERVER['HTTP_HOST'] . "/telegram_bot/webhook.php"
    ];
    
    foreach ($telegram_vars as $key => $value) {
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";
        
        if (preg_match($pattern, $env_content)) {
            $env_content = preg_replace($pattern, $replacement, $env_content);
        } else {
            $env_content .= "\n{$replacement}";
        }
    }
    
    file_put_contents($env_file, $env_content);
}

/**
 * Probar conexión con el bot de Telegram
 */
function testBotConnection($token) {
    if (empty($token)) {
        return ['success' => false, 'message' => 'Token no configurado'];
    }
    
    $url = "https://api.telegram.org/bot{$token}/getMe";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'Error de conexión: ' . $error];
    }
    
    if ($http_code !== 200) {
        return ['success' => false, 'message' => 'Error HTTP: ' . $http_code];
    }
    
    $data = json_decode($response, true);
    
    if ($data['ok']) {
        return [
            'success' => true, 
            'message' => 'Conexión exitosa',
            'bot_info' => $data['result']
        ];
    } else {
        return ['success' => false, 'message' => $data['description'] ?? 'Error desconocido'];
    }
}

/**
 * Configurar webhook de Telegram
 */
function setupWebhook($token, $webhook_url, $secret) {
    if (empty($token)) {
        return ['success' => false, 'message' => 'Token no configurado'];
    }
    
    $url = "https://api.telegram.org/bot{$token}/setWebhook";
    $data = [
        'url' => $webhook_url,
        'secret_token' => $secret
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return ['success' => false, 'message' => 'Error HTTP: ' . $http_code];
    }
    
    $data = json_decode($response, true);
    
    if ($data['ok']) {
        return ['success' => true, 'message' => 'Webhook configurado exitosamente'];
    } else {
        return ['success' => false, 'message' => $data['description'] ?? 'Error configurando webhook'];
    }
}

/**
 * Obtener información del bot
 */
function getBotInfo($token) {
    if (empty($token)) {
        return ['success' => false, 'message' => 'Token no configurado'];
    }
    
    $url = "https://api.telegram.org/bot{$token}/getMe";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($data['ok']) {
        return ['success' => true, 'bot_info' => $data['result']];
    } else {
        return ['success' => false, 'message' => $data['description'] ?? 'Error obteniendo info del bot'];
    }
}

$conn->close();
?>