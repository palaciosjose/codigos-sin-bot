<?php
/**
 * Gestión de logs de Telegram
 * admin/telegram_logs.php
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'recent':
        getRecentTelegramLogs($conn);
        break;
        
    case 'clear':
        clearTelegramLogs($conn);
        break;
        
    case 'stats':
        getTelegramLogStats($conn);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Obtener logs recientes de Telegram
 */
function getRecentTelegramLogs($conn) {
    $limit = intval($_GET['limit'] ?? 10);
    if ($limit < 1 || $limit > 100) {
        $limit = 10;
    }
    
    $stmt = $conn->prepare("
        SELECT 
            l.*,
            u.username
        FROM logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.source_channel = 'telegram'
        ORDER BY l.fecha DESC
        LIMIT ?
    ");
    
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    echo json_encode(['success' => true, 'logs' => $logs]);
}

/**
 * Limpiar logs de Telegram
 */
function clearTelegramLogs($conn) {
    $stmt = $conn->prepare("DELETE FROM logs WHERE source_channel = 'telegram'");
    
    if ($stmt->execute()) {
        $deleted_count = $conn->affected_rows;
        echo json_encode([
            'success' => true, 
            'message' => "Se eliminaron {$deleted_count} logs de Telegram",
            'deleted_count' => $deleted_count
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al limpiar logs']);
    }
}

/**
 * Obtener estadísticas de logs de Telegram
 */
function getTelegramLogStats($conn) {
    // Logs del día actual
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM logs 
        WHERE source_channel = 'telegram' 
        AND DATE(fecha) = CURDATE()
    ");
    $stmt->execute();
    $today_logs = $stmt->get_result()->fetch_assoc()['count'];
    
    // Logs de la semana
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM logs 
        WHERE source_channel = 'telegram' 
        AND fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $week_logs = $stmt->get_result()->fetch_assoc()['count'];
    
    // Total de logs
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM logs 
        WHERE source_channel = 'telegram'
    ");
    $stmt->execute();
    $total_logs = $stmt->get_result()->fetch_assoc()['count'];
    
    // Último log
    $stmt = $conn->prepare("
        SELECT fecha
        FROM logs 
        WHERE source_channel = 'telegram'
        ORDER BY fecha DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $last_activity = $result->num_rows > 0 ? $result->fetch_assoc()['fecha'] : null;
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'today_logs' => $today_logs,
            'week_logs' => $week_logs,
            'total_logs' => $total_logs,
            'last_activity' => $last_activity
        ]
    ]);
}

$conn->close();
?>