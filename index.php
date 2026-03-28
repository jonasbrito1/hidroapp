<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

// Funções auxiliares para evitar undefined array key
function safeGet($array, $key, $default = 0) {
    return isset($array[$key]) ? $array[$key] : $default;
}

function createWelcomeInfo($user_type, $user_name) {
    $info = [
        'welcome_message' => "Olá, {$user_name}!",
        'description' => 'Sistema de gestão de manutenções',
        'user_type_display' => ucfirst($user_type),
        'badge_class' => 'badge bg-secondary'
    ];
    
    switch ($user_type) {
        case 'admin':
            $info['badge_class'] = 'badge bg-primary';
            $info['user_type_display'] = 'Administrador';
            $info['description'] = 'Gerencie todo o sistema de manutenções e equipamentos.';
            break;
        case 'tecnico':
            $info['badge_class'] = 'badge bg-info';
            $info['user_type_display'] = 'Técnico';
            $info['description'] = 'Acompanhe suas manutenções e tarefas pendentes.';
            break;
        default:
            $info['user_type_display'] = 'Usuário';
            $info['description'] = 'Acompanhe o status dos equipamentos e manutenções.';
            break;
    }
    return $info;
}

if (!function_exists('getUserPaginationLimit')) {
    function getUserPaginationLimit($user_type) {
        return $user_type === 'admin' ? 20 : ($user_type === 'tecnico' ? 15 : 10);
    }
}

if (!function_exists('includeUserThemeCSS')) {
    function includeUserThemeCSS() {
        // Fallback caso a função não exista
        echo "<!-- Tema padrão aplicado -->";
    }
}

// Inicializar página com verificações de segurança e configurações
initializePage();

// Obter informações do usuário atual
$current_user = getCurrentUserInfo();
$user_type = $_SESSION['user_type'];

// Log de acesso ao dashboard
logMessage("Acesso ao dashboard: {$_SESSION['user_name']} ({$user_type})", 'INFO', $user_type);

// Buscar dados do dashboard baseado em permissões
try {
    $stats = [];
    
if (UserPermissions::hasPermission($user_type, 'dashboard', 'full_stats')) {
            // Estatísticas completas para admin
        $stats = [
            'total_equipamentos' => (Database::fetch("SELECT COUNT(*) as total FROM equipamentos")['total'] ?? 0),
            'equipamentos_ativos' => (Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'ativo'")['total'] ?? 0),
            'equipamentos_manutencao' => (Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'manutencao'")['total'] ?? 0),
            'equipamentos_inativos' => (Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'inativo'")['total'] ?? 0),
            'manutencoes_pendentes' => (Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE status = 'agendada'")['total'] ?? 0),
            'manutencoes_andamento' => (Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE status = 'em_andamento'")['total'] ?? 0),
            'manutencoes_hoje' => (Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE DATE(data_agendada) = CURDATE()")['total'] ?? 0),
            'manutencoes_atrasadas' => (Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE DATE(data_agendada) < CURDATE() AND status IN ('agendada', 'em_andamento')")['total'] ?? 0),
            'total_usuarios' => (Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1")['total'] ?? 0),
            'total_tecnicos' => (Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'tecnico' AND ativo = 1")['total'] ?? 0)
        ];
} elseif (UserPermissions::hasPermission($user_type, 'manutencoes', 'view') && $user_type === 'tecnico') {
        // Estatísticas limitadas para técnico
        $stats = [
            'total_equipamentos' => (Database::fetch("SELECT COUNT(*) as total FROM equipamentos")['total'] ?? 0),
            'equipamentos_ativos' => (Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'ativo'")['total'] ?? 0),
            'minhas_manutencoes_pendentes' => (Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE tecnico_id = ? AND status = 'agendada'", [$_SESSION['user_id']])['total'] ?? 0),
            'minhas_manutencoes_andamento' => (Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE tecnico_id = ? AND status = 'em_andamento'", [$_SESSION['user_id']])['total'] ?? 0),
            'manutencoes_hoje' => (Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE DATE(data_agendada) = CURDATE() AND tecnico_id = ?", [$_SESSION['user_id']])['total'] ?? 0),
            'manutencoes_concluidas_mes' => (Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE tecnico_id = ? AND status = 'concluida' AND MONTH(data_conclusao) = MONTH(CURDATE())", [$_SESSION['user_id']])['total'] ?? 0)
        ];
    } else {
        // Estatísticas básicas para usuário comum
        $total_eq = (Database::fetch("SELECT COUNT(*) as total FROM equipamentos")['total'] ?? 0);
        $ativos = (Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'ativo'")['total'] ?? 0);
        
        $stats = [
            'total_equipamentos' => $total_eq,
            'equipamentos_ativos' => $ativos,
            'manutencoes_mes' => (Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE MONTH(data_agendada) = MONTH(CURDATE())")['total'] ?? 0),
            'disponibilidade_sistema' => $total_eq > 0 ? round(($ativos / $total_eq) * 100, 1) : 0
        ];
    }
    
    // Buscar manutenções recentes baseado em permissões
if (UserPermissions::hasPermission($user_type, 'manutencoes', 'manage') || $user_type === 'admin') {
        $recent_manutencoes = Database::fetchAll("
            SELECT m.*, e.codigo, e.localizacao, u.nome as tecnico_nome
            FROM manutencoes m
            LEFT JOIN equipamentos e ON m.equipamento_id = e.id
            LEFT JOIN usuarios u ON m.tecnico_id = u.id
            ORDER BY m.created_at DESC
            LIMIT ?
        ", [getUserPaginationLimit($user_type)]) ?? [];
} elseif (UserPermissions::hasPermission($user_type, 'manutencoes', 'view') && $user_type === 'tecnico') {
        $recent_manutencoes = Database::fetchAll("
            SELECT m.*, e.codigo, e.localizacao, u.nome as tecnico_nome
            FROM manutencoes m
            LEFT JOIN equipamentos e ON m.equipamento_id = e.id
            LEFT JOIN usuarios u ON m.tecnico_id = u.id
            WHERE m.tecnico_id = ?
            ORDER BY m.created_at DESC
            LIMIT ?
        ", [$_SESSION['user_id'], getUserPaginationLimit($user_type)]) ?? [];
    } else {
        $recent_manutencoes = Database::fetchAll("
            SELECT m.*, e.codigo, e.localizacao, u.nome as tecnico_nome
            FROM manutencoes m
            LEFT JOIN equipamentos e ON m.equipamento_id = e.id
            LEFT JOIN usuarios u ON m.tecnico_id = u.id
            WHERE m.status = 'concluida'
            ORDER BY m.created_at DESC
            LIMIT ?
        ", [getUserPaginationLimit($user_type)]) ?? [];
    }
    
    // Buscar equipamentos em manutenção
if (UserPermissions::hasPermission($user_type, 'equipamentos', 'view')) {
        $equipamentos_manutencao = Database::fetchAll("
            SELECT * FROM equipamentos 
            WHERE status = 'manutencao'
            ORDER BY codigo
            LIMIT 5
        ") ?? [];
    } else {
        $equipamentos_manutencao = [];
    }
    
    // Gráfico de manutenções (apenas para admin e técnico)
    $manutencoes_por_mes = [];
if (UserPermissions::hasPermission($user_type, 'relatorios', 'view')) {
        if ($user_type === 'admin') {
            $manutencoes_por_mes = Database::fetchAll("
                SELECT 
                    DATE_FORMAT(data_agendada, '%Y-%m') as mes,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas
                FROM manutencoes 
                WHERE data_agendada >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(data_agendada, '%Y-%m')
                ORDER BY mes
            ") ?? [];
        } else {
            $manutencoes_por_mes = Database::fetchAll("
                SELECT 
                    DATE_FORMAT(data_agendada, '%Y-%m') as mes,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas
                FROM manutencoes 
                WHERE tecnico_id = ? AND data_agendada >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(data_agendada, '%Y-%m')
                ORDER BY mes
            ", [$_SESSION['user_id']]) ?? [];
        }
    }
    
} catch (Exception $e) {
    logMessage('Erro ao buscar dados do dashboard: ' . $e->getMessage(), 'ERROR', $user_type);
    
    // Valores padrão em caso de erro
    $stats = [];
    $recent_manutencoes = [];
    $equipamentos_manutencao = [];
    $manutencoes_por_mes = [];
}

// Garantir que $stats tenha todas as chaves necessárias
$default_stats = [
    'total_equipamentos' => 0, 'equipamentos_ativos' => 0, 'equipamentos_manutencao' => 0,
    'equipamentos_inativos' => 0, 'manutencoes_pendentes' => 0, 'manutencoes_andamento' => 0,
    'manutencoes_hoje' => 0, 'manutencoes_atrasadas' => 0, 'total_usuarios' => 0,
    'total_tecnicos' => 0, 'minhas_manutencoes_pendentes' => 0, 'minhas_manutencoes_andamento' => 0,
    'manutencoes_concluidas_mes' => 0, 'manutencoes_mes' => 0, 'disponibilidade_sistema' => 0
];
$stats = array_merge($default_stats, $stats ?? []);

// Função para obter badge de status
function getStatusBadge($status) {
    $badges = [
        'agendada' => 'bg-warning text-dark',
        'em_andamento' => 'bg-info text-white',
        'concluida' => 'bg-success text-white',
        'cancelada' => 'bg-primary text-white',
        'ativo' => 'bg-success text-white',
        'inativo' => 'bg-secondary text-white',
        'manutencao' => 'bg-warning text-dark'
    ];
    return $badges[$status] ?? 'bg-secondary text-white';
}

// Função para obter ícone do equipamento
function getEquipmentIcon($tipo) {
    $icons = [
        'bebedouro' => 'cup-straw',
        'filtro' => 'funnel',
        'bomba' => 'gear-wide-connected',
        'torneira' => 'water',
        'default' => 'hdd-stack'
    ];
    return $icons[$tipo] ?? $icons['default'];
}

// Obter mensagem de boas-vindas personalizada
$welcome_info = createWelcomeInfo($user_type, $_SESSION['user_name'] ?? 'Usuário');

// Tentar usar função personalizada se existir
if (method_exists('UserPermissions', 'getWelcomeMessage')) {
    try {
        $custom_welcome = UserPermissions::getWelcomeMessage($user_type, $_SESSION['user_name']);
        if (is_array($custom_welcome)) {
            $welcome_info = array_merge($welcome_info, $custom_welcome);
        }
    } catch (Exception $e) {
        // Continuar com fallback
        logMessage('Erro ao obter mensagem de boas-vindas: ' . $e->getMessage(), 'WARNING', $user_type);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HidroApp - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Dinâmico do Tema do Usuário -->
    <?php if (function_exists('includeUserThemeCSS')) includeUserThemeCSS(); ?>
    
    <style>
        /* Reset e configurações globais */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-color: #0066cc;
            --primary-dark: #004499;
            --secondary-color: #00b4d8;
            --accent-color: #4a90e2;
            --success-color: #52c41a;
            --warning-color: #1890ff;
            --info-color: #40a9ff;
            --danger-color: #1677ff;
            --text-dark: #1a1a1a;
            --text-gray: #666;
            --text-light: #999;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --border-color: #e2e8f0;
            --shadow-light: 0 2px 10px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 20px rgba(0, 0, 0, 0.15);
            --shadow-heavy: 0 8px 30px rgba(0, 0, 0, 0.2);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 280px;
            --header-height: 70px;
        }

        html {
            scroll-behavior: smooth;
            font-size: 16px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--bg-light);
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* Sidebar moderna */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-heavy);
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 1rem 1.5rem;
            margin: 0.25rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            display: flex;
            align-items: center;
            font-weight: 500;
            text-decoration: none;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .nav-link i {
            font-size: 1.1rem;
            margin-right: 0.75rem;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .top-header {
            background: var(--bg-white);
            height: var(--header-height);
            box-shadow: var(--shadow-light);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
        }
        
        .content-area {
            padding: 2rem;
            flex: 1;
        }
        
        .stat-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-heavy);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .table-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .table-card .card-header {
            background: linear-gradient(135deg, var(--bg-light), var(--bg-white));
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow-light);
            color: white;
            text-decoration: none;
        }
        
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            color: white;
        }
        
        .footer-area {
            background: var(--bg-white);
            border-top: 1px solid var(--border-color);
            margin-top: auto;
            padding: 1.5rem 0;
        }
        
        .footer-link {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer-link:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        /* Cards de atividades recentes */
        .activity-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .activity-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Welcome section */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }

        /* Quick actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action-btn {
            background: var(--bg-white);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-decoration: none;
            color: var(--text-dark);
            transition: var(--transition);
            text-align: center;
        }

        .quick-action-btn:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            color: var(--primary-color);
        }

        .quick-action-btn i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        /* Charts container */
        .chart-container {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        /* Badges modernos */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Sistema de permissões visual */
        .permission-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.75rem;
            opacity: 0.7;
        }

        .restricted-content {
            opacity: 0.6;
            position: relative;
        }

        .restricted-content::after {
            content: '🔒 Acesso Restrito';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-size: 0.8rem;
        }

        /* Alerta de notificações */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Performance indicators */
        .performance-indicator {
            position: relative;
            overflow: hidden;
        }

        .performance-indicator::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: var(--primary-color);
            transition: width 1s ease-in-out;
        }

        .performance-good::after { width: 100%; background: #52c41a; }
        .performance-medium::after { width: 70%; background: var(--info-color); }
        .performance-poor::after { width: 30%; background: var(--primary-color); }

        /* Responsividade completa */
        @media (max-width: 1400px) {
            :root {
                --sidebar-width: 250px;
            }
        }

        @media (max-width: 1200px) {
            .content-area {
                padding: 1.5rem;
            }
            .stat-card {
                padding: 1.5rem;
            }
            .welcome-section {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 992px) {
            .content-area {
                padding: 1rem;
            }
            .welcome-section {
                padding: 1rem;
            }
            .top-header {
                padding: 0 1rem;
            }
            .stat-card {
                margin-bottom: 1rem;
            }
            .quick-actions {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 100%;
                --header-height: 60px;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 300px;
            }

            .sidebar.show {
                transform: translateX(0);
                z-index: 1050;
            }

            .main-content {
                margin-left: 0;
            }

            .content-area {
                padding: 1rem;
            }

            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .top-header {
                padding: 0 1rem;
                height: 60px;
            }

            .top-header h4 {
                font-size: 1.1rem;
            }

            .welcome-section {
                padding: 1rem;
                text-align: center;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .quick-action-btn {
                padding: 1rem;
            }

            .quick-action-btn i {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .content-area {
                padding: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .table-card .card-header {
                padding: 1rem;
            }

            .top-header {
                padding: 0 0.75rem;
                height: 56px;
            }

            .sidebar-header {
                padding: 1rem;
            }

            .nav-link {
                padding: 0.75rem 1rem;
            }

            .welcome-section {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .quick-actions {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .quick-action-btn {
                padding: 0.75rem;
            }
        }

        /* Melhorias de acessibilidade */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            
            html {
                scroll-behavior: auto;
            }
        }

        /* Estados de foco para acessibilidade */
        .nav-link:focus,
        .btn:focus,
        .quick-action-btn:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Scroll suave customizado */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-light);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Loading states */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Animações suaves */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Hover effects */
        .hover-lift {
            transition: var(--transition);
        }

        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Custom dropdown styles */
        .dropdown-menu {
            border: none;
            box-shadow: var(--shadow-medium);
            border-radius: var(--border-radius);
        }

        .dropdown-item:hover {
            background-color: var(--bg-light);
            color: var(--primary-color);
        }

        /* User type indicator */
        .user-type-indicator {
            font-size: 0.7rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Session info */
        .session-info {
            background: rgba(255,255,255,0.1);
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            margin-top: 1rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="bi bi-droplet-fill fs-3"></i>
                </div>
                <div>
                    <h5 class="mb-0">HidroApp</h5>
                    <small class="opacity-75">v1.0</small>
                </div>
            </div>
            
            <!-- Informações da sessão -->
            <div class="session-info">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="user-type-indicator"><?= safeGet($welcome_info, 'user_type_display', 'Usuário') ?></span>
                    <small><?= isset($_SESSION['timeout']) ? round(($_SESSION['timeout'] - time()) / 60) : 0 ?> min</small>
                </div>
            </div>
        </div>
        
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <?php if (method_exists('UserPermissions', 'generateSidebar')): ?>
                    <?= UserPermissions::generateSidebar($user_type, 'index.php') ?>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="bi bi-house-door"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="equipamentos.php">
                            <i class="bi bi-hdd-stack"></i>Equipamentos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manutencoes.php">
                            <i class="bi bi-tools"></i>Manutenções
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center">
                <button class="btn d-md-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h4 class="mb-0">Dashboard</h4>
                <div class="ms-3">
                    <span class="<?= safeGet($welcome_info, 'badge_class', 'badge bg-secondary') ?>">
                        <?= safeGet($welcome_info, 'user_type_display', 'Usuário') ?>
                    </span>
                </div>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle position-relative" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?>
                    <?php if (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change']): ?>
                        <span class="notification-badge">!</span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#perfil"><i class="bi bi-person me-2"></i>Perfil</a></li>
                    <li><a class="dropdown-item" href="#configuracoes"><i class="bi bi-gear me-2"></i>Configurações</a></li>
<?php if (UserPermissions::hasPermission($user_type, 'usuarios', 'manage')): ?>
                            <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="register.php"><i class="bi bi-person-plus me-2"></i>Cadastrar Usuário</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                </ul>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Welcome Section -->
            <div class="welcome-section fade-in">
                <div class="position-relative">
                    <h2 class="mb-2">
                        <?= htmlspecialchars(safeGet($welcome_info, 'welcome_message', 'Bem-vindo ao HidroApp')) ?>
                    </h2>
                    <p class="mb-0 opacity-90">
                        <?= htmlspecialchars(safeGet($welcome_info, 'description', 'Sistema de gestão de manutenções')) ?>
                    </p>
                    <small class="d-block mt-2 opacity-75">
                        Último acesso: <?= isset($_SESSION['last_login']) && $_SESSION['last_login'] ? date('d/m/Y H:i', strtotime($_SESSION['last_login'])) : 'Primeiro acesso' ?>
                    </small>
                </div>
            </div>

            <!-- Quick Actions - Baseadas em permissões -->
            <div class="quick-actions fade-in">
<?php if (UserPermissions::hasPermission($user_type, 'manutencoes', 'create')): ?>
                <a href="manutencoes.php" class="quick-action-btn">
                    <i class="bi bi-plus-circle text-primary"></i>
                    <strong>Nova Manutenção</strong>
                    <small class="d-block text-muted">Cadastrar manutenção</small>
                </a>
                <?php endif; ?>
                
<?php if (UserPermissions::hasPermission($user_type, 'equipamentos', 'view')): ?>
                <a href="equipamentos.php" class="quick-action-btn">
                    <i class="bi bi-hdd-stack text-success"></i>
                    <strong>Equipamentos</strong>
                    <small class="d-block text-muted">Gerenciar equipamentos</small>
                </a>
                <?php endif; ?>
                
<?php if (UserPermissions::hasPermission($user_type, 'relatorios', 'view')): ?>
                <a href="#relatorios" class="quick-action-btn">
                    <i class="bi bi-graph-up text-info"></i>
                    <strong>Relatórios</strong>
                    <small class="d-block text-muted">Análises e métricas</small>
                </a>
                <?php endif; ?>
                
<?php if (UserPermissions::hasPermission($user_type, 'usuarios', 'manage')): ?>
                    <a href="register.php" class="quick-action-btn">
                    <i class="bi bi-person-plus text-info"></i>
                    <strong>Cadastrar Usuário</strong>
                    <small class="d-block text-muted">Adicionar ao sistema</small>
                </a>
                <?php endif; ?>
            </div>

            <!-- Stats Cards - Baseadas em permissões -->
            <div class="row mb-4">
                <?php if ($user_type === 'admin'): ?>
                    <!-- Stats completas para Admin -->
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in performance-<?= safeGet($stats, 'equipamentos_ativos') > safeGet($stats, 'total_equipamentos') * 0.8 ? 'good' : (safeGet($stats, 'equipamentos_ativos') > safeGet($stats, 'total_equipamentos') * 0.6 ? 'medium' : 'poor') ?>">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-hdd-stack"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= safeGet($stats, 'total_equipamentos') ?></h3>
                                    <p class="text-muted mb-0">Total Equipamentos</p>
                                    <small class="text-success"><?= safeGet($stats, 'equipamentos_ativos') ?> ativos</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--info-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= safeGet($stats, 'manutencoes_pendentes') ?></h3>
                                    <p class="text-muted mb-0">Manutenções Pendentes</p>
                                    <small class="text-info"><?= safeGet($stats, 'manutencoes_andamento') ?> em andamento</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--info-color) 0%, var(--primary-color) 100%);">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= safeGet($stats, 'manutencoes_hoje') ?></h3>
                                    <p class="text-muted mb-0">Agendadas Hoje</p>
                                    <?php if (safeGet($stats, 'manutencoes_atrasadas') > 0): ?>
                                        <small class="text-primary"><?= safeGet($stats, 'manutencoes_atrasadas') ?> atrasadas</small>
                                    <?php else: ?>
                                        <small class="text-success">Em dia</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= safeGet($stats, 'total_usuarios') ?></h3>
                                    <p class="text-muted mb-0">Usuários Ativos</p>
                                    <small class="text-info"><?= safeGet($stats, 'total_tecnicos') ?> técnicos</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($user_type === 'tecnico'): ?>
                    <!-- Stats para Técnico -->
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= safeGet($stats, 'minhas_manutencoes_pendentes') ?></h3>
                                    <p class="text-muted mb-0">Minhas Pendentes</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--info-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-gear-wide-connected"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= safeGet($stats, 'minhas_manutencoes_andamento') ?></h3>
                                    <p class="text-muted mb-0">Em Andamento</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--info-color) 0%, var(--primary-color) 100%);">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= safeGet($stats, 'manutencoes_hoje') ?></h3>
                                    <p class="text-muted mb-0">Hoje</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= safeGet($stats, 'manutencoes_concluidas_mes') ?></h3>
                                    <p class="text-muted mb-0">Concluídas (Mês)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Stats básicas para Usuário -->
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-hdd-stack"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= safeGet($stats, 'total_equipamentos') ?></h3>
                                    <p class="text-muted mb-0">Total Equipamentos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= safeGet($stats, 'equipamentos_ativos') ?></h3>
                                    <p class="text-muted mb-0">Equipamentos Ativos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--info-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-speedometer2"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= safeGet($stats, 'disponibilidade_sistema') ?>%</h3>
                                    <p class="text-muted mb-0">Disponibilidade</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activities -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card table-card fade-in">
                        <div class="card-header">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history me-2"></i>
                                    <?php if ($user_type === 'tecnico'): ?>
                                        Minhas Manutenções Recentes
                                    <?php elseif ($user_type === 'usuario'): ?>
                                        Manutenções Concluídas
                                    <?php else: ?>
                                        Manutenções Recentes
                                    <?php endif; ?>
                                </h5>
<?php if (UserPermissions::hasPermission($user_type, 'manutencoes', 'create')): ?>                                <a href="manutencoes.php" class="btn btn-primary-custom btn-sm">
                                    <i class="bi bi-plus me-1"></i>Nova Manutenção
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_manutencoes)): ?>
                                <div class="text-center p-5 text-muted">
                                    <i class="bi bi-inbox fs-1 mb-3 opacity-50"></i>
                                    <h5>Nenhuma manutenção encontrada</h5>
                                    <p>
<?php if (UserPermissions::hasPermission($user_type, 'manutencoes', 'create')): ?>                                            Cadastre sua primeira manutenção para começar.
                                        <?php else: ?>
                                            Aguarde as próximas manutenções.
                                        <?php endif; ?>
                                    </p>
<?php if (UserPermissions::hasPermission($user_type, 'manutencoes', 'create')): ?>                                    <a href="manutencoes.php" class="btn btn-primary-custom mt-2">
                                        <i class="bi bi-plus me-1"></i>Cadastrar Manutenção
                                    </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th><i class="bi bi-hdd-stack me-1"></i>Equipamento</th>
                                                <th class="d-none d-md-table-cell"><i class="bi bi-geo-alt me-1"></i>Localização</th>
<?php if (UserPermissions::hasPermission($user_type, 'manutencoes', 'manage') || $user_type === 'admin'): ?>                                                <th class="d-none d-lg-table-cell"><i class="bi bi-person me-1"></i>Técnico</th>
                                                <?php endif; ?>
                                                <th><i class="bi bi-calendar me-1"></i>Data</th>
                                                <th><i class="bi bi-flag me-1"></i>Status</th>
                                                <th class="text-center">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_manutencoes as $manutencao): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($manutencao['codigo'] ?? 'N/A') ?></strong>
                                                        <div class="d-md-none">
                                                            <small class="text-muted"><?= htmlspecialchars($manutencao['localizacao'] ?? 'N/A') ?></small>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell">
                                                        <small><?= htmlspecialchars($manutencao['localizacao'] ?? 'N/A') ?></small>
                                                    </td>
<?php if (UserPermissions::hasPermission($user_type, 'manutencoes', 'manage') || $user_type === 'admin'): ?>
                                                        <td class="d-none d-lg-table-cell">
                                                        <small><?= htmlspecialchars($manutencao['tecnico_nome'] ?? 'Não atribuído') ?></small>
                                                    </td>
                                                    <?php endif; ?>
                                                    <td>
                                                        <small><?= date('d/m/Y', strtotime($manutencao['data_agendada'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= getStatusBadge($manutencao['status']) ?> rounded-pill">
                                                            <?= ucfirst(str_replace('_', ' ', $manutencao['status'])) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="manutencoes.php" class="btn btn-sm btn-outline-primary" title="Ver detalhes">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="card-footer bg-light text-center">
                                    <a href="manutencoes.php" class="btn btn-link text-primary">
                                        <i class="bi bi-arrow-right me-1"></i>Ver todas as manutenções
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
<?php if (UserPermissions::hasPermission($user_type, 'equipamentos', 'view')): ?>
                        <div class="card table-card fade-in">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-tools me-2"></i>Equipamentos em Manutenção</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($equipamentos_manutencao)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-check-circle fs-1 mb-3 text-success opacity-50"></i>
                                    <h6>Todos os equipamentos estão funcionando!</h6>
                                    <p class="small">Nenhum equipamento em manutenção no momento.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($equipamentos_manutencao as $equipamento): ?>
                                    <div class="d-flex align-items-center p-2 border-bottom hover-lift rounded">
                                        <div class="me-3">
                                            <i class="bi bi-<?= getEquipmentIcon($equipamento['tipo'] ?? 'default') ?> fs-4 text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($equipamento['codigo']) ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($equipamento['localizacao']) ?></small>
                                        </div>
                                        <span class="badge <?= getStatusBadge($equipamento['status']) ?> rounded-pill">
                                            🔧
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="text-center mt-3">
                                    <a href="equipamentos.php" class="btn btn-link text-primary">
                                        <i class="bi bi-arrow-right me-1"></i>Ver todos os equipamentos
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Performance Summary -->
                    <div class="card table-card mt-3 fade-in">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Resumo de Performance</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($user_type === 'admin'): ?>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h4 class="text-success"><?= number_format((safeGet($stats, 'equipamentos_ativos') / max(safeGet($stats, 'total_equipamentos'), 1)) * 100, 1) ?>%</h4>
                                        <small class="text-muted">Disponibilidade</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-info"><?= safeGet($stats, 'manutencoes_pendentes') ?></h4>
                                    <small class="text-muted">Pendentes</small>
                                </div>
                            </div>
                            <?php elseif ($user_type === 'tecnico'): ?>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h4 class="text-primary"><?= safeGet($stats, 'minhas_manutencoes_pendentes') + safeGet($stats, 'minhas_manutencoes_andamento') ?></h4>
                                        <small class="text-muted">Minhas Ativas</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success"><?= safeGet($stats, 'manutencoes_concluidas_mes') ?></h4>
                                    <small class="text-muted">Concluídas</small>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="text-center">
                                <h4 class="text-success"><?= safeGet($stats, 'disponibilidade_sistema') ?>%</h4>
                                <small class="text-muted">Disponibilidade do Sistema</small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <div class="progress" style="height: 8px;">
                                    <?php 
                                    if ($user_type === 'admin') {
                                        $performance = (safeGet($stats, 'equipamentos_ativos') / max(safeGet($stats, 'total_equipamentos'), 1)) * 100;
                                    } elseif ($user_type === 'tecnico') {
                                        $performance = safeGet($stats, 'manutencoes_concluidas_mes') > 0 ? min(100, safeGet($stats, 'manutencoes_concluidas_mes') * 10) : 0;
                                    } else {
                                        $performance = safeGet($stats, 'disponibilidade_sistema');
                                    }
                                    $cor_progress = $performance >= 80 ? 'bg-success' : ($performance >= 60 ? 'bg-info' : 'bg-primary');
                                    ?>
                                    <div class="progress-bar <?= $cor_progress ?>" role="progressbar" style="width: <?= $performance ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?php if ($user_type === 'admin'): ?>
                                        Status geral dos equipamentos
                                    <?php elseif ($user_type === 'tecnico'): ?>
                                        Performance mensal
                                    <?php else: ?>
                                        Saúde do sistema
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
<?php if (!empty($manutencoes_por_mes) && UserPermissions::hasPermission($user_type, 'relatorios', 'view')): ?>            <div class="row mt-4">
                <div class="col-12">
                    <div class="chart-container fade-in">
                        <h5 class="mb-3">
                            <i class="bi bi-bar-chart me-2"></i>
                            <?= $user_type === 'admin' ? 'Manutenções nos Últimos 6 Meses' : 'Minhas Manutenções - Últimos 6 Meses' ?>
                        </h5>
                        <canvas id="maintenanceChart" width="400" height="100"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <footer class="footer-area">
            <div class="container-fluid">
                <div class="text-center py-3">
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <p class="mb-1 text-muted">
                                <small>
                                    Desenvolvido por 
                                    <a href="https://i9script.com" target="_blank" class="footer-link">
                                        <strong>i9Script Technology</strong>
                                    </a>
                                </small>
                            </p>
                        </div>
                        <div class="col-12 col-md-6">
                            <p class="mb-1 text-muted">
                                <small>© Hidro Evolution 2025 - Todos os direitos reservados</small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');
            
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle?.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });

        // Auto-refresh stats every 30 seconds (apenas para admin e técnico)
<?php if (UserPermissions::hasPermission($user_type, 'relatorios', 'view')): ?>
            let refreshInterval = setInterval(function() {
            // Aqui você pode adicionar AJAX para atualizar stats sem recarregar a página
            console.log('Auto-refresh stats...');
        }, 30000);
        <?php endif; ?>

        // Chart configuration
        <?php if (!empty($manutencoes_por_mes)): ?>
        const ctx = document.getElementById('maintenanceChart').getContext('2d');
        const maintenanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($manutencoes_por_mes as $mes): ?>
                        '<?= date('M/Y', strtotime($mes['mes'] . '-01')) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Total',
                    data: [
                        <?php foreach ($manutencoes_por_mes as $mes): ?>
                            <?= $mes['total'] ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: 'var(--primary-color)',
                    backgroundColor: 'rgba(0, 102, 204, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Concluídas',
                    data: [
                        <?php foreach ($manutencoes_por_mes as $mes): ?>
                            <?= $mes['concluidas'] ?? 0 ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: 'var(--secondary-color)',
                    backgroundColor: 'rgba(0, 180, 216, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });

        // Set chart height
        document.getElementById('maintenanceChart').style.height = '300px';
        <?php endif; ?>

        // Session timeout warning
        let sessionTimeout = <?= isset($_SESSION['timeout']) ? ($_SESSION['timeout'] - time()) : 3600 ?> * 1000;
        let warningTime = sessionTimeout - (5 * 60 * 1000); // 5 minutos antes

        if (warningTime > 0) {
            setTimeout(function() {
                if (confirm('Sua sessão expirará em 5 minutos. Deseja renovar?')) {
                    // Fazer uma requisição AJAX para renovar a sessão
                    fetch('refresh_session.php').then(() => {
                        location.reload();
                    });
                }
            }, warningTime);
        }

        // Real-time session timer
        function updateSessionTimer() {
            const timeLeft = Math.max(0, <?= isset($_SESSION['timeout']) ? $_SESSION['timeout'] : time() + 3600 ?> - Math.floor(Date.now() / 1000));
            const minutes = Math.floor(timeLeft / 60);
            
            // Atualizar no header se existir elemento
            const sessionInfo = document.querySelector('.session-info small:last-child');
            if (sessionInfo) {
                sessionInfo.textContent = minutes + ' min';
            }
            
            if (timeLeft <= 0) {
                alert('Sua sessão expirou. Você será redirecionado para o login.');
                window.location.href = 'logout.php?reason=timeout';
            }
        }

        // Atualizar timer a cada minuto
        setInterval(updateSessionTimer, 60000);

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Notification system
        function showNotification(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart;
            console.log(`Dashboard loaded in ${loadTime}ms`);
            
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            console.log('User permissions:', <?= json_encode(UserPermissions::getUserPermissions($user_type) ?? []) ?>);
            console.log('User theme:', <?= json_encode($_SESSION['user_theme'] ?? []) ?>);
            <?php endif; ?>
        });

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>