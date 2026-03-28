<?php
/**
 * Middleware de Verificação de Sessão
 * HidroApp - Sistema de Gestão de Manutenção
 * 
 * Este arquivo deve ser incluído no início de todas as páginas protegidas
 */

class SessionMiddleware {
    
    /**
     * Verifica e valida a sessão do usuário
     */
    public static function validateSession() {
        // Garantir que a sessão está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
            self::redirectToLogin('not_logged_in');
            return false;
        }
        
        // Verificar timeout da sessão
        if (isset($_SESSION['timeout']) && time() > $_SESSION['timeout']) {
            self::destroySession();
            self::redirectToLogin('timeout');
            return false;
        }
        
        // Verificar se o IP mudou (para tipos de usuário que requerem)
        $security_settings = getUserSecuritySettings($_SESSION['user_type']);
        if ($security_settings['session_ip_check'] && 
            isset($_SESSION['login_ip']) && 
            $_SESSION['login_ip'] !== $_SERVER['REMOTE_ADDR']) {
            
            logMessage(
                "IP alterado durante sessão: {$_SESSION['user_name']} - " .
                "Original: {$_SESSION['login_ip']} - Atual: {$_SERVER['REMOTE_ADDR']}", 
                'SECURITY'
            );
            
            self::destroySession();
            self::redirectToLogin('ip_changed');
            return false;
        }
        
        // Verificar se o usuário ainda está ativo no banco
        if (!self::isUserStillActive($_SESSION['user_id'])) {
            logMessage(
                "Usuário inativo tentou acessar: {$_SESSION['user_name']} (ID: {$_SESSION['user_id']})", 
                'WARNING'
            );
            
            self::destroySession();
            self::redirectToLogin('user_inactive');
            return false;
        }
        
        // Atualizar timeout da sessão
        self::refreshSessionTimeout();
        
        // Aplicar configurações se necessário
        self::ensureUserSettings();
        
        return true;
    }
    
    /**
     * Verifica permissões para uma página específica
     */
    public static function checkPagePermission($page) {
        if (!self::validateSession()) {
            return false;
        }
        
        // Salvar página atual para redirecionamento após login se necessário
        $_SESSION['current_page'] = $page;
        
        // Verificar se o usuário pode acessar a página
        if (!UserPermissions::canAccessPage($_SESSION['user_type'], $page)) {
            logMessage(
                "Acesso negado: {$_SESSION['user_name']} ({$_SESSION['user_type']}) " .
                "tentou acessar {$page}", 
                'WARNING'
            );
            
            // Redirecionar para página apropriada baseada no tipo de usuário
            self::redirectToAppropriatePage();
            return false;
        }
        
        return true;
    }
    
    /**
     * Middleware específico para páginas administrativas
     */
    public static function requireAdmin($page = null) {
        if (!self::validateSession()) {
            return false;
        }
        
        if ($_SESSION['user_type'] !== 'admin') {
            logMessage(
                "Tentativa de acesso admin: {$_SESSION['user_name']} ({$_SESSION['user_type']}) " .
                "tentou acessar área administrativa" . ($page ? " - {$page}" : ""), 
                'WARNING'
            );
            
            self::redirectToAppropriatePage();
            return false;
        }
        
        return true;
    }
    
    /**
     * Middleware para técnicos e administradores
     */
    public static function requireTechnicianOrAdmin($page = null) {
        if (!self::validateSession()) {
            return false;
        }
        
        if (!in_array($_SESSION['user_type'], ['admin', 'tecnico'])) {
            logMessage(
                "Tentativa de acesso técnico: {$_SESSION['user_name']} ({$_SESSION['user_type']}) " .
                "tentou acessar área técnica" . ($page ? " - {$page}" : ""), 
                'WARNING'
            );
            
            self::redirectToAppropriatePage();
            return false;
        }
        
        return true;
    }
    
    /**
     * Verifica se o usuário ainda está ativo no banco
     */
    private static function isUserStillActive($user_id) {
        try {
            require_once 'db.php';
            $user = Database::fetch(
                "SELECT ativo FROM usuarios WHERE id = ?", 
                [$user_id]
            );
            
            return $user && $user['ativo'] == 1;
        } catch (Exception $e) {
            logMessage('Erro ao verificar status do usuário: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Atualiza o timeout da sessão
     */
    private static function refreshSessionTimeout() {
        $timeout = getUserSessionTimeout($_SESSION['user_type']);
        $_SESSION['timeout'] = time() + $timeout;
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Garante que as configurações do usuário estão aplicadas
     */
    private static function ensureUserSettings() {
        if (!isset($_SESSION['user_theme']) || !isset($_SESSION['pagination_limit'])) {
            applyUserSpecificSettings($_SESSION['user_type']);
        }
    }
    
    /**
     * Destrói a sessão de forma segura
     */
    private static function destroySession() {
        // Limpar cookies de sessão
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Limpar variáveis de sessão
        $_SESSION = array();
        
        // Destruir sessão
        session_destroy();
    }
    
    /**
     * Redireciona para login com mensagem apropriada
     */
    private static function redirectToLogin($reason = '') {
        $query = '';
        
        switch ($reason) {
            case 'timeout':
                $query = '?timeout=1';
                break;
            case 'ip_changed':
                $query = '?security=1';
                break;
            case 'user_inactive':
                $query = '?inactive=1';
                break;
            case 'not_logged_in':
            default:
                $query = '';
                break;
        }
        
        header("Location: login.php{$query}");
        exit;
    }
    
    /**
     * Redireciona para página apropriada baseada no tipo de usuário
     */
    private static function redirectToAppropriatePage() {
        $redirect_pages = [
            'admin' => 'index.php',
            'tecnico' => 'index.php',
            'usuario' => 'index.php'
        ];
        
        $page = $redirect_pages[$_SESSION['user_type']] ?? 'index.php';
        header("Location: {$page}");
        exit;
    }
    
    /**
     * Gera cabeçalhos de segurança
     */
    public static function setSecurityHeaders() {
        // Prevenir clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevenir sniffing de MIME type
        header('X-Content-Type-Options: nosniff');
        
        // Habilitar filtro XSS do browser
        header('X-XSS-Protection: 1; mode=block');
        
        // Política de referrer
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Política de conteúdo (CSP básica)
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
               "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; " .
               "img-src 'self' data:; " .
               "connect-src 'self';";
        
        header("Content-Security-Policy: {$csp}");
    }
    
    /**
     * Verifica rate limiting por IP
     */
    public static function checkRateLimit($action = 'general', $limit = 60, $window = 60) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = "rate_limit_{$action}_{$ip}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + $window];
        }
        
        $rate_data = $_SESSION[$key];
        
        // Reset if window expired
        if (time() >= $rate_data['reset_time']) {
            $_SESSION[$key] = ['count' => 1, 'reset_time' => time() + $window];
            return true;
        }
        
        // Check if limit exceeded
        if ($rate_data['count'] >= $limit) {
            logMessage("Rate limit exceeded: {$action} - IP: {$ip}", 'WARNING');
            return false;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        return true;
    }
    
    /**
     * Função helper para usar no início das páginas
     */
    public static function initializePage($page_name, $required_permission = null) {
        // Definir cabeçalhos de segurança
        self::setSecurityHeaders();
        
        // Verificar rate limiting
        if (!self::checkRateLimit('page_access', 100, 60)) {
            http_response_code(429);
            die('Too Many Requests');
        }
        
        // Verificar sessão
        if (!self::validateSession()) {
            return false;
        }
        
        // Verificar permissão da página
        if (!self::checkPagePermission($page_name)) {
            return false;
        }
        
        // Verificar permissão específica se fornecida
        if ($required_permission && !hasPermission($required_permission['module'], $required_permission['action'])) {
            logMessage(
                "Permissão negada: {$_SESSION['user_name']} tentou {$required_permission['module']}:{$required_permission['action']}", 
                'WARNING'
            );
            self::redirectToAppropriatePage();
            return false;
        }
        
        return true;
    }
}

// ============ FUNÇÕES HELPER GLOBAIS ============

/**
 * Função de conveniência para inicializar página
 */
function initPage($page_name, $required_permission = null) {
    return SessionMiddleware::initializePage($page_name, $required_permission);
}

/**
 * Função de conveniência para verificar se é admin
 */
function requireAdminAccess($page = null) {
    return SessionMiddleware::requireAdmin($page);
}

/**
 * Função de conveniência para verificar se é técnico ou admin
 */
function requireTechnicianAccess($page = null) {
    return SessionMiddleware::requireTechnicianOrAdmin($page);
}

/**
 * Função para obter informações da sessão do usuário
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'type' => $_SESSION['user_type'],
        'login_time' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'theme' => $_SESSION['user_theme'] ?? null,
        'pagination_limit' => $_SESSION['pagination_limit'] ?? PAGINATION_DEFAULT
    ];
}

/**
 * Função para verificar se a sessão expira em breve
 */
function getSessionTimeRemaining() {
    if (!isset($_SESSION['timeout'])) {
        return 0;
    }
    
    return max(0, $_SESSION['timeout'] - time());
}

/**
 * Função para extender sessão via AJAX
 */
function extendSession() {
    if (SessionMiddleware::validateSession()) {
        return ['success' => true, 'new_timeout' => $_SESSION['timeout']];
    }
    
    return ['success' => false, 'message' => 'Sessão inválida'];
}

// ============ EXEMPLO DE USO ============
/*
// No início de cada página protegida:

require_once 'session_middleware.php';

// Método 1: Verificação simples
if (!initPage('equipamentos.php')) {
    exit; // Middleware já redirecionou
}

// Método 2: Com permissão específica
if (!initPage('equipamentos.php', ['module' => 'equipamentos', 'action' => 'view'])) {
    exit;
}

// Método 3: Para páginas administrativas
if (!requireAdminAccess('configuracoes.php')) {
    exit;
}

// Método 4: Para páginas técnicas
if (!requireTechnicianAccess('manutencoes.php')) {
    exit;
}
*/
?>