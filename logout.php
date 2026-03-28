<?php
// ============ ARQUIVO logout.php COMPLETO ============

session_start();
require_once 'config.php';

// Verificar se o usuário estava logado
$was_logged_in = isset($_SESSION['user_id']);
$user_info = null;
$logout_reason = $_GET['reason'] ?? 'manual'; // manual, timeout, forced, security

if ($was_logged_in) {
    // Capturar informações do usuário antes de destruir a sessão
    $user_info = [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'Usuário',
        'email' => $_SESSION['user_email'] ?? '',
        'type' => $_SESSION['user_type'] ?? 'usuario',
        'login_time' => $_SESSION['login_time'] ?? time(),
        'login_ip' => $_SESSION['login_ip'] ?? $_SERVER['REMOTE_ADDR'],
        'session_timeout' => $_SESSION['user_session_timeout'] ?? SESSION_TIMEOUT,
        'theme' => $_SESSION['user_theme'] ?? null
    ];
    
    // Calcular tempo de sessão
    $session_duration = time() - $user_info['login_time'];
    $session_minutes = round($session_duration / 60, 1);
    $session_hours = round($session_duration / 3600, 2);
    
    // Determinar qualidade da sessão
    $session_quality = 'normal';
    if ($session_duration < 60) {
        $session_quality = 'muito_curta'; // Menos de 1 minuto
    } elseif ($session_duration < 300) {
        $session_quality = 'curta'; // Menos de 5 minutos
    } elseif ($session_duration > $user_info['session_timeout']) {
        $session_quality = 'expirada'; // Maior que o timeout configurado
    } elseif ($session_duration > 3600) {
        $session_quality = 'longa'; // Mais de 1 hora
    }
    
    try {
        // Conectar ao banco de dados
        require_once 'db.php';
        
        // Atualizar último logout no banco de dados
        Database::query(
            "UPDATE usuarios SET last_logout = NOW(), logout_reason = ? WHERE id = ?", 
            [$logout_reason, $user_info['id']]
        );
        
        // Registrar estatísticas de sessão (se tabela existir)
        try {
            Database::query(
                "INSERT INTO session_logs (user_id, login_time, logout_time, session_duration, ip_address, logout_reason, user_agent) 
                 VALUES (?, FROM_UNIXTIME(?), NOW(), ?, ?, ?, ?)",
                [
                    $user_info['id'],
                    $user_info['login_time'],
                    $session_duration,
                    $user_info['login_ip'],
                    $logout_reason,
                    $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]
            );
        } catch (Exception $e) {
            // Tabela session_logs não existe, apenas continuar
            if (DEBUG_MODE) {
                logMessage('Tabela session_logs não encontrada: ' . $e->getMessage(), 'DEBUG');
            }
        }
        
        // Determinar o nível do log baseado na razão do logout
        $log_level = 'INFO';
        switch ($logout_reason) {
            case 'timeout':
                $log_level = 'WARNING';
                break;
            case 'forced':
            case 'security':
                $log_level = 'CRITICAL';
                break;
            case 'manual':
            default:
                $log_level = 'INFO';
                break;
        }
        
        // Criar mensagem de log detalhada
        $log_message = "Logout realizado: {$user_info['name']} ({$user_info['type']}) - " .
                      "Razão: {$logout_reason} - " .
                      "Sessão: {$session_minutes} min ({$session_quality}) - " .
                      "IP: {$user_info['login_ip']} - " .
                      "User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
        
        // Log do logout com nível apropriado
        logMessage($log_message, $log_level, $user_info['type']);
        
        // Log adicional para sessões suspeitas
        if ($session_quality === 'muito_curta' && $logout_reason === 'manual') {
            logMessage(
                "SESSÃO SUSPEITA: Logout muito rápido ({$session_duration}s) para {$user_info['name']} - IP: {$user_info['login_ip']}", 
                'WARNING',
                $user_info['type']
            );
        }
        
        // Log para sessões longas
        if ($session_quality === 'longa') {
            logMessage(
                "Sessão longa detectada: {$user_info['name']} ficou logado por {$session_hours}h", 
                'INFO',
                $user_info['type']
            );
        }
        
    } catch (Exception $e) {
        // Log do erro mas continue com o logout
        logMessage('Erro ao registrar logout no banco: ' . $e->getMessage(), 'ERROR');
        
        // Mesmo com erro no banco, registrar o logout nos logs
        logMessage(
            "Logout (erro DB): {$user_info['name']} ({$user_info['type']}) - " .
            "Sessão: {$session_minutes} min - IP: {$user_info['login_ip']}", 
            'WARNING'
        );
    }
}

// ============ LIMPEZA COMPLETA DA SESSÃO ============

// Armazenar informações importantes antes da limpeza para logs
$original_session_id = session_id();
$cleanup_info = [
    'session_id' => $original_session_id,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    'timestamp' => time()
];

// Limpar cookies de sessão se existirem
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
    
    // Limpar outros cookies relacionados ao sistema (se houver)
    $system_cookies = ['hidroapp_remember', 'hidroapp_theme', 'hidroapp_lang'];
    foreach ($system_cookies as $cookie) {
        if (isset($_COOKIE[$cookie])) {
            setcookie($cookie, '', time() - 42000, '/');
        }
    }
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Destruir a sessão
session_destroy();

// ============ INICIAR NOVA SESSÃO LIMPA ============

// Regenerar ID da sessão para nova sessão (segurança)
session_start();
session_regenerate_id(true);

// Definir variáveis de controle para a nova sessão
$_SESSION['logout_performed'] = true;
$_SESSION['logout_time'] = time();
$_SESSION['logout_reason'] = $logout_reason;

// ============ LOGS DE SEGURANÇA ============

// Log de segurança se não estava logado (possível tentativa de ataque)
if (!$was_logged_in) {
    logMessage(
        "SEGURANÇA: Tentativa de logout sem estar logado - " .
        "IP: {$_SERVER['REMOTE_ADDR']} - " .
        "User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . " - " .
        "Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'Direct'),
        'WARNING'
    );
}

// Log da limpeza da sessão
logMessage(
    "Sessão limpa: ID {$cleanup_info['session_id']} - " .
    "IP: {$cleanup_info['ip']} - " .
    "Nova sessão iniciada: " . session_id(),
    'DEBUG'
);

// ============ REDIRECIONAMENTO INTELIGENTE ============

// Determinar página de destino baseada na razão do logout
$redirect_params = ['logout' => 'success'];

switch ($logout_reason) {
    case 'timeout':
        $redirect_params['timeout'] = '1';
        unset($redirect_params['logout']);
        break;
        
    case 'forced':
        $redirect_params['forced'] = '1';
        $redirect_params['message'] = 'Sua sessão foi encerrada por um administrador.';
        unset($redirect_params['logout']);
        break;
        
    case 'security':
        $redirect_params['security'] = '1';
        $redirect_params['message'] = 'Logout realizado por motivos de segurança.';
        unset($redirect_params['logout']);
        break;
        
    case 'maintenance':
        $redirect_params['maintenance'] = '1';
        $redirect_params['message'] = 'Sistema em manutenção. Tente novamente em alguns minutos.';
        unset($redirect_params['logout']);
        break;
        
    case 'manual':
    default:
        // Manter o padrão 'logout=success'
        break;
}

// Construir URL de redirecionamento
$redirect_url = 'login.php?' . http_build_query($redirect_params);

// ============ HEADERS DE SEGURANÇA ============

// Limpar cache para evitar problemas de volta do navegador
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Header de segurança
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Log final do redirecionamento
logMessage(
    "Redirecionando após logout - Razão: {$logout_reason} - " .
    "Destino: {$redirect_url} - " .
    "IP: {$_SERVER['REMOTE_ADDR']}",
    'DEBUG'
);

// Redirecionar para login com parâmetros apropriados
header("Location: {$redirect_url}");
exit;

// ============ FIM DO ARQUIVO ============
?>