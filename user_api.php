<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

// Definir cabeçalhos para API JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado',
        'error_code' => 'NOT_AUTHENTICATED'
    ]);
    exit;
}

// Verificar se tem permissão básica de visualização de usuários
if (!UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'view')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Permissão negada',
        'error_code' => 'PERMISSION_DENIED'
    ]);
    exit;
}

// Obter ação solicitada
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_user':
            handleGetUser();
            break;
            
        case 'search_users':
            handleSearchUsers();
            break;
            
        case 'get_user_stats':
            handleGetUserStats();
            break;
            
        case 'check_email':
            handleCheckEmail();
            break;
            
        case 'check_cpf':
            handleCheckCPF();
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Ação inválida',
                'error_code' => 'INVALID_ACTION'
            ]);
            break;
    }
} catch (Exception $e) {
    // Log do erro
    logMessage('Erro na API de usuários: ' . $e->getMessage(), 'ERROR');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => DEBUG_MODE ? $e->getMessage() : 'Erro interno do servidor',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}

/**
 * Obter dados de um usuário específico
 */
function handleGetUser() {
    $user_id = (int)($_GET['id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID do usuário inválido',
            'error_code' => 'INVALID_USER_ID'
        ]);
        return;
    }
    
    // Buscar usuário
    $user = Database::fetch("SELECT * FROM usuarios WHERE id = ? AND deleted_at IS NULL", [$user_id]);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Usuário não encontrado',
            'error_code' => 'USER_NOT_FOUND'
        ]);
        return;
    }
    
    // Verificar se pode visualizar este usuário
    if (!canViewUser($_SESSION['user_type'], $user)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Permissão negada para visualizar este usuário',
            'error_code' => 'VIEW_PERMISSION_DENIED'
        ]);
        return;
    }
    
    // Remover dados sensíveis se necessário
    if (!UserPermissions::canViewSensitiveFields($_SESSION['user_type'])) {
        unset($user['senha'], $user['login_ip']);
    }
    
    // Formatar campos
    if ($user['telefone']) {
        $user['telefone_formatted'] = formatPhone($user['telefone']);
    }
    
    if ($user['cpf']) {
        $user['cpf_formatted'] = formatCPF($user['cpf']);
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
}

/**
 * Buscar usuários com filtros
 */
function handleSearchUsers() {
    $search = $_GET['search'] ?? '';
    $tipo = $_GET['tipo'] ?? '';
    $status = $_GET['status'] ?? '';
    $limit = min(50, (int)($_GET['limit'] ?? 20));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    
    // Construir query base
    $where_conditions = ['deleted_at IS NULL'];
    $params = [];
    
    // Aplicar filtros
    if (!empty($search)) {
        $where_conditions[] = "(nome LIKE ? OR email LIKE ? OR cpf LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($tipo) && in_array($tipo, ['admin', 'tecnico', 'usuario'])) {
        $where_conditions[] = "tipo = ?";
        $params[] = $tipo;
    }
    
    if (!empty($status)) {
        if ($status === 'ativo') {
            $where_conditions[] = "ativo = 1";
        } elseif ($status === 'inativo') {
            $where_conditions[] = "ativo = 0";
        }
    }
    
    // Filtros específicos por permissão
    if ($_SESSION['user_type'] === 'tecnico') {
        $where_conditions[] = "(created_by = ? OR tipo = 'usuario')";
        $params[] = $_SESSION['user_id'];
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Buscar usuários
    $users = Database::fetchAll("
        SELECT id, nome, email, tipo, ativo, created_at, last_login
        FROM usuarios 
        WHERE {$where_clause}
        ORDER BY nome ASC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));
    
    // Contar total
    $total = Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE {$where_clause}", $params)['total'];
    
    echo json_encode([
        'success' => true,
        'users' => $users ?? [],
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Obter estatísticas de usuários
 */
function handleGetUserStats() {
    if (!UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'view')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Permissão negada',
            'error_code' => 'PERMISSION_DENIED'
        ]);
        return;
    }
    
    $stats = [];
    
    if ($_SESSION['user_type'] === 'admin') {
        $stats = [
            'total_usuarios' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE deleted_at IS NULL")['total'],
            'usuarios_ativos' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1 AND deleted_at IS NULL")['total'],
            'usuarios_inativos' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 0 AND deleted_at IS NULL")['total'],
            'total_admins' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'admin' AND deleted_at IS NULL")['total'],
            'total_tecnicos' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'tecnico' AND deleted_at IS NULL")['total'],
            'total_usuarios_comuns' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'usuario' AND deleted_at IS NULL")['total'],
            'novos_este_mes' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND deleted_at IS NULL")['total'],
            'logins_hoje' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE DATE(last_login) = CURDATE() AND deleted_at IS NULL")['total']
        ];
    } elseif ($_SESSION['user_type'] === 'tecnico') {
        $stats = [
            'usuarios_criados_por_mim' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE created_by = ? AND deleted_at IS NULL", [$_SESSION['user_id']])['total'],
            'total_usuarios_comuns' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'usuario' AND deleted_at IS NULL")['total']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

/**
 * Verificar se email já existe
 */
function handleCheckEmail() {
    $email = $_GET['email'] ?? '';
    $exclude_id = (int)($_GET['exclude_id'] ?? 0);
    
    if (empty($email)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email não informado',
            'error_code' => 'EMAIL_REQUIRED'
        ]);
        return;
    }
    
    if (!validateEmail($email)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email inválido',
            'error_code' => 'INVALID_EMAIL'
        ]);
        return;
    }
    
    $query = "SELECT id FROM usuarios WHERE email = ? AND deleted_at IS NULL";
    $params = [$email];
    
    if ($exclude_id > 0) {
        $query .= " AND id != ?";
        $params[] = $exclude_id;
    }
    
    $existing = Database::fetch($query, $params);
    
    echo json_encode([
        'success' => true,
        'exists' => (bool)$existing,
        'email' => $email
    ]);
}

/**
 * Verificar se CPF já existe
 */
function handleCheckCPF() {
    $cpf = $_GET['cpf'] ?? '';
    $exclude_id = (int)($_GET['exclude_id'] ?? 0);
    
    if (empty($cpf)) {
        echo json_encode([
            'success' => false,
            'message' => 'CPF não informado',
            'error_code' => 'CPF_REQUIRED'
        ]);
        return;
    }
    
    $cpf_clean = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf_clean) !== 11) {
        echo json_encode([
            'success' => false,
            'message' => 'CPF deve ter 11 dígitos',
            'error_code' => 'INVALID_CPF_LENGTH'
        ]);
        return;
    }
    
    if (!validateCPF($cpf_clean)) {
        echo json_encode([
            'success' => false,
            'message' => 'CPF inválido',
            'error_code' => 'INVALID_CPF'
        ]);
        return;
    }
    
    $query = "SELECT id FROM usuarios WHERE cpf = ? AND deleted_at IS NULL";
    $params = [$cpf_clean];
    
    if ($exclude_id > 0) {
        $query .= " AND id != ?";
        $params[] = $exclude_id;
    }
    
    $existing = Database::fetch($query, $params);
    
    echo json_encode([
        'success' => true,
        'exists' => (bool)$existing,
        'cpf' => $cpf_clean
    ]);
}

/**
 * Verificar se pode visualizar um usuário específico
 */
function canViewUser($current_user_type, $target_user) {
    // Admin pode ver qualquer usuário
    if ($current_user_type === 'admin') {
        return true;
    }
    
    // Técnico pode ver usuários que criou ou usuários comuns
    if ($current_user_type === 'tecnico') {
        return $target_user['created_by'] == $_SESSION['user_id'] || 
               $target_user['tipo'] === 'usuario';
    }
    
    // Usuário comum só pode ver a si mesmo
    if ($current_user_type === 'usuario') {
        return $target_user['id'] == $_SESSION['user_id'];
    }
    
    return false;
}

/**
 * Log da ação da API
 */
function logApiAction($action, $details = '') {
    $user_info = $_SESSION['user_name'] ?? 'Unknown';
    $user_type = $_SESSION['user_type'] ?? 'unknown';
    
    logMessage("API Action: {$action} by {$user_info} ({$user_type}) - {$details}", 'INFO');
}
?>