<?php
/**
 * Estadísticas del bot de Telegram
 * admin/telegram_stats.php
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
    case 'dashboard':
        getDashboardStats($conn);
        break;
        
    case 'daily':
        getDailyStats($conn);
        break;
        
    case 'users':
        getUserStats($conn);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Obtener estadísticas para el dashboard
 */
function getDashboardStats($conn) {
    $stats = [];
    
    // Consultas de hoy
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM logs 
        WHERE source_channel = 'telegram' 
        AND DATE(fecha) = CURDATE()
    ");
    $stmt->execute();
    $stats['queries_today'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Usuarios de Telegram activos
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM users 
        WHERE telegram_id IS NOT NULL 
        AND telegram_id != '' 
        AND status = 1
    ");
    $stmt->execute();
    $stats['telegram_users'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Última actividad
    $stmt = $conn->prepare("
        SELECT fecha
        FROM logs 
        WHERE source_channel = 'telegram'
        ORDER BY fecha DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $last_activity = $result->fetch_assoc()['fecha'];
        $stats['last_activity'] = timeAgo($last_activity);
    } else {
        $stats['last_activity'] = 'Nunca';
    }
    
    // Consultas de la semana
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM logs 
        WHERE source_channel = 'telegram' 
        AND fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $stats['queries_week'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Usuarios activos en las últimas 24 horas
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT user_id) as count
        FROM logs 
        WHERE source_channel = 'telegram' 
        AND fecha >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND user_id IS NOT NULL
    ");
    $stmt->execute();
    $stats['active_users_24h'] = $stmt->get_result()->fetch_assoc()['count'];
    
    echo json_encode(['success' => true, 'stats' => $stats]);
}

/**
 * Obtener estadísticas diarias
 */
function getDailyStats($conn) {
    $days = intval($_GET['days'] ?? 7);
    if ($days < 1 || $days > 30) {
        $days = 7;
    }
    
    $stmt = $conn->prepare("
        SELECT 
            DATE(fecha) as date,
            COUNT(*) as queries,
            COUNT(DISTINCT user_id) as unique_users
        FROM logs 
        WHERE source_channel = 'telegram' 
        AND fecha >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(fecha)
        ORDER BY date DESC
    ");
    
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $daily_stats = [];
    while ($row = $result->fetch_assoc()) {
        $daily_stats[] = $row;
    }
    
    echo json_encode(['success' => true, 'daily_stats' => $daily_stats]);
}

/**
 * Obtener estadísticas de usuarios
 */
function getUserStats($conn) {
    // Top usuarios por consultas
    $stmt = $conn->prepare("
        SELECT 
            u.username,
            u.telegram_username,
            COUNT(l.id) as query_count,
            MAX(l.fecha) as last_query
        FROM users u
        INNER JOIN logs l ON u.id = l.user_id
        WHERE l.source_channel = 'telegram'
        AND u.telegram_id IS NOT NULL
        GROUP BY u.id
        ORDER BY query_count DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $top_users = [];
    while ($row = $result->fetch_assoc()) {
        $top_users[] = $row;
    }
    
    // Plataformas más consultadas
    $stmt = $conn->prepare("
        SELECT 
            plataforma,
            COUNT(*) as query_count
        FROM logs 
        WHERE source_channel = 'telegram'
        GROUP BY plataforma
        ORDER BY query_count DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $top_platforms = [];
    while ($row = $result->fetch_assoc()) {
        $top_platforms[] = $row;
    }
    
    echo json_encode([
        'success' => true, 
        'user_stats' => [
            'top_users' => $top_users,
            'top_platforms' => $top_platforms
        ]
    ]);
}

/**
 * Función para calcular tiempo transcurrido
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'Hace menos de 1 minuto';
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return "Hace {$minutes} minuto" . ($minutes != 1 ? 's' : '');
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return "Hace {$hours} hora" . ($hours != 1 ? 's' : '');
    } else {
        $days = floor($time / 86400);
        return "Hace {$days} día" . ($days != 1 ? 's' : '');
    }
}

$conn->close();
?>