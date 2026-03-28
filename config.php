<?php
// ================================================================
// CONFIGURAÇÕES COMPLETAS DO HIDROAPP
// ================================================================

// IMPORTANTE: Sempre definir constantes ANTES de usá-las
define('DEBUG_MODE', true);
define('LOG_ERRORS', true);
define('LOG_FILE', 'logs/hidroapp.log');

// Timezone e Locale PT-BR
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL, 'pt_BR.UTF-8', 'pt_BR', 'portuguese', 'ptb');
setlocale(LC_TIME, 'pt_BR.UTF-8', 'pt_BR', 'portuguese');
setlocale(LC_NUMERIC, 'pt_BR.UTF-8', 'pt_BR', 'portuguese');

// =====================================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// =====================================================
define('DB_HOST', 'localhost');           
define('DB_NAME', 'hidroapp');           
define('DB_USER', 'root');                
define('DB_PASS', '');                    
define('DB_CHARSET', 'utf8mb4');

// =====================================================
// CONFIGURAÇÕES DA APLICAÇÃO
// =====================================================
define('APP_NAME', 'HidroApp');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/hidroapp');

// =====================================================
// CONFIGURAÇÕES DE SESSÃO
// =====================================================
define('SESSION_TIMEOUT', 3600);
define('SESSION_NAME', 'hidroapp_session');

// =====================================================
// CONFIGURAÇÕES DE SEGURANÇA
// =====================================================
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

// =====================================================
// CONFIGURAÇÕES DE UPLOAD
// =====================================================
define('UPLOAD_MAX_SIZE', 5242880);
define('UPLOAD_PATH', 'uploads/');

// =====================================================
// CONFIGURAÇÕES DO SISTEMA
// =====================================================
define('MAINTENANCE_MODE', false);
define('ALLOW_REGISTRATION', false);

// Configurações de usuário
define('USER_SESSION_TIMEOUTS', json_encode([
    'admin' => 7200,    // 2 horas
    'tecnico' => 7200,  // 2 horas (igual admin)
    'usuario' => 1800   // 30 minutos
]));

define('DEFAULT_ADMIN_NAME', 'admin');
define('DEFAULT_ADMIN_EMAIL', 'admin@hidroapp.com');
define('DEFAULT_ADMIN_PASSWORD', 'admin123');

// =====================================================
// FUNÇÕES BÁSICAS
// =====================================================

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += intval($cpf[$i]) * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : (11 - $resto);
    
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += intval($cpf[$i]) * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : (11 - $resto);
    
    return ($cpf[9] == $digito1 && $cpf[10] == $digito2);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function formatPhone($phone) {
    if (empty($phone)) return '';
    
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) == 10) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
    } elseif (strlen($phone) == 11) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
    }
    
    return $phone;
}

function formatCPF($cpf) {
    if (empty($cpf)) return '';
    
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) == 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    
    return $cpf;
}

function getUserSessionTimeout($user_type) {
    $timeouts = json_decode(USER_SESSION_TIMEOUTS, true);
    return $timeouts[$user_type] ?? SESSION_TIMEOUT;
}

function logMessage($message, $level = 'INFO', $user_type = null) {
    if (!LOG_ERRORS) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $user_info = '';
    
    if (isset($_SESSION['user_id'])) {
        $user_info = " [User: {$_SESSION['user_name']} ({$_SESSION['user_type']})]";
    }
    
    $logEntry = "[{$timestamp}] [{$level}]{$user_info} {$message}" . PHP_EOL;
    
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    
    if (DEBUG_MODE && in_array($level, ['ERROR', 'CRITICAL'])) {
        error_log("HidroApp [{$level}]: {$message}");
    }
}

// =====================================================
// FUNÇÃO DE INICIALIZAÇÃO DA PÁGINA
// =====================================================

function initializePage() {
    // Configurar sessão se ainda não foi iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
        session_start();
    }
    
    // Verificar modo manutenção
    if (MAINTENANCE_MODE && basename($_SERVER['PHP_SELF']) !== 'maintenance.php') {
        header('Location: maintenance.php');
        exit;
    }
    
    // Verificar se o usuário está logado (exceto páginas de login)
    $public_pages = ['login.php', 'maintenance.php', 'register.php'];
    $current_page = basename($_SERVER['PHP_SELF']);
    
    if (!in_array($current_page, $public_pages)) {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            // Redirecionar para login se não estiver logado
            header('Location: login.php');
            exit;
        }
        
        // Verificar timeout da sessão
        if (isset($_SESSION['timeout']) && time() > $_SESSION['timeout']) {
            session_destroy();
            header('Location: login.php?msg=timeout');
            exit;
        }
        
        // Renovar timeout da sessão
        if (isset($_SESSION['user_type'])) {
            $_SESSION['timeout'] = time() + getUserSessionTimeout($_SESSION['user_type']);
        } else {
            $_SESSION['timeout'] = time() + SESSION_TIMEOUT;
        }
    }
    
    // Log de acesso
    if (isset($_SESSION['user_id'])) {
        logMessage("Página acessada: {$current_page}", 'INFO', $_SESSION['user_type'] ?? 'unknown');
    }
}

// =====================================================
// FUNÇÃO PARA OBTER INFORMAÇÕES DO USUÁRIO ATUAL
// =====================================================

function getCurrentUserInfo() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'Usuário',
        'email' => $_SESSION['user_email'] ?? '',
        'type' => $_SESSION['user_type'] ?? 'usuario',
        'last_login' => $_SESSION['last_login'] ?? null,
        'timeout' => $_SESSION['timeout'] ?? time() + SESSION_TIMEOUT
    ];
}

// =====================================================
// CONFIGURAÇÕES GLOBAIS
// =====================================================

// Configurar exibição de erros
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configurar sessão globalmente
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    session_start();
}

// =====================================================
// FUNÇÕES AUXILIARES DO SISTEMA
// =====================================================

/**
 * Aplicar configurações específicas do tipo de usuário
 */
function applyUserSpecificSettings($user_type) {
    // Definir timeout específico para o tipo de usuário
    $timeout = getUserSessionTimeout($user_type);
    $_SESSION['timeout'] = time() + $timeout;
    $_SESSION['max_timeout'] = $timeout;
    
    // Configurações específicas por tipo de usuário
    switch ($user_type) {
        case 'admin':
            $_SESSION['dashboard_refresh'] = 30; // 30 segundos
            $_SESSION['max_items_page'] = 50;
            $_SESSION['can_access_logs'] = true;
            $_SESSION['can_manage_system'] = true;
            break;
            
        case 'tecnico':
            $_SESSION['dashboard_refresh'] = 60; // 1 minuto
            $_SESSION['max_items_page'] = 25;
            $_SESSION['can_access_logs'] = false;
            $_SESSION['can_manage_system'] = false;
            break;
            
        case 'usuario':
            $_SESSION['dashboard_refresh'] = 120; // 2 minutos
            $_SESSION['max_items_page'] = 15;
            $_SESSION['can_access_logs'] = false;
            $_SESSION['can_manage_system'] = false;
            break;
    }
    
    // Log da aplicação das configurações
    if (DEBUG_MODE) {
        logMessage("Configurações aplicadas para tipo de usuário: {$user_type} (timeout: {$timeout}s)", 'DEBUG');
    }
}

/**
 * Verificar e renovar sessão
 */
function renewSession() {
    if (isset($_SESSION['user_type'])) {
        $newTimeout = time() + getUserSessionTimeout($_SESSION['user_type']);
        $_SESSION['timeout'] = $newTimeout;
        return true;
    }
    return false;
}

/**
 * Obter informações de segurança da sessão
 */
function getSessionSecurityInfo() {
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'user_type' => $_SESSION['user_type'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
        'login_ip' => $_SESSION['login_ip'] ?? null,
        'timeout' => $_SESSION['timeout'] ?? null,
        'expires_in' => isset($_SESSION['timeout']) ? ($_SESSION['timeout'] - time()) : 0
    ];
}

/**
 * Verificar se a sessão é válida
 */
function isValidSession() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['timeout'])) {
        return false;
    }
    
    if (time() > $_SESSION['timeout']) {
        return false;
    }
    
    // Verificar se o IP mudou (opcional, para segurança adicional)
    if (isset($_SESSION['login_ip']) && $_SESSION['login_ip'] !== $_SERVER['REMOTE_ADDR']) {
        logMessage("IP alterado durante sessão: de {$_SESSION['login_ip']} para {$_SERVER['REMOTE_ADDR']}", 'WARNING');
        // Dependendo da política de segurança, pode invalidar a sessão
        // return false;
    }
    
    return true;
}

/**
 * Limpar sessão de forma segura
 */
function clearSession($reason = 'logout') {
    if (isset($_SESSION['user_name'])) {
        logMessage("Sessão encerrada: {$_SESSION['user_name']} - Motivo: {$reason}", 'INFO');
    }
    
    session_unset();
    session_destroy();
    
    // Iniciar nova sessão limpa
    session_start();
}

/**
 * Função para debugar dados (apenas em modo debug)
 */
function debugLog($data, $label = 'DEBUG') {
    if (DEBUG_MODE) {
        $message = $label . ': ';
        if (is_array($data) || is_object($data)) {
            $message .= json_encode($data, JSON_PRETTY_PRINT);
        } else {
            $message .= (string)$data;
        }
        logMessage($message, 'DEBUG');
    }
}

/**
 * Obter limite de paginação baseado no tipo de usuário
 */
function getUserPaginationLimit($user_type) {
    $limits = [
        'admin' => 20,
        'tecnico' => 15,
        'usuario' => 10
    ];
    
    return $limits[$user_type] ?? 10;
}

/**
 * Verificar se um usuário pode criar outros usuários
 */
function canCreateUsers($user_type) {
    return in_array($user_type, ['admin', 'tecnico']);
}

/**
 * Verificar se um usuário pode editar outros usuários
 */
function canEditUsers($user_type) {
    return in_array($user_type, ['admin', 'tecnico']);
}

/**
 * Verificar se um usuário pode deletar outros usuários
 */
function canDeleteUsers($user_type) {
    return $user_type === 'admin';
}

// =====================================================
// INCLUIR TRADUÇÕES PT-BR
// =====================================================
require_once __DIR__ . '/traducoes_ptbr.php';

// Log de inicialização
if (DEBUG_MODE) {
    logMessage('Sistema inicializado com sucesso - Versão: ' . APP_VERSION);
}
?>