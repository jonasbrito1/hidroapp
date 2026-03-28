<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

$message = '';
$error = '';
$success = '';

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar se o usuário pode cadastrar usuários
if (!UserPermissions::hasPermission($_SESSION['user_type'], 'cadastro_usuarios')) {
    logMessage("Tentativa de acesso não autorizado à página de cadastro por: {$_SESSION['user_name']} (ID: {$_SESSION['user_id']}, Tipo: {$_SESSION['user_type']})", 'WARNING');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nome = sanitize($_POST['nome'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $tipo = sanitize($_POST['tipo'] ?? 'usuario');
        $telefone = sanitize($_POST['telefone'] ?? '');
        $cpf = sanitize($_POST['cpf'] ?? '');
        $endereco = sanitize($_POST['endereco'] ?? '');
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        
        // VALIDAÇÃO ESPECÍFICA POR TIPO DE USUÁRIO
        $allowed_types = [];
        
        if ($_SESSION['user_type'] === 'admin') {
            // Admin pode cadastrar qualquer tipo
            $allowed_types = ['admin', 'tecnico', 'usuario'];
        } elseif ($_SESSION['user_type'] === 'tecnico') {
            // Técnico só pode cadastrar usuários comuns
            $allowed_types = ['usuario'];
            
            // Forçar tipo para usuário se técnico tentar cadastrar outro tipo
            if (!in_array($tipo, $allowed_types)) {
                $tipo = 'usuario';
                $message = 'Técnicos só podem cadastrar usuários comuns. Tipo ajustado automaticamente.';
            }
        }
        
        // Validação do tipo de usuário
        if (!in_array($tipo, $allowed_types)) {
            $error = 'Você não tem permissão para cadastrar este tipo de usuário.';
        }
        
        // Validações básicas
        if (!$error) {
            if (empty($nome) || empty($email) || empty($password) || empty($confirm_password)) {
                $error = 'Por favor, preencha todos os campos obrigatórios.';
            } elseif (!validateEmail($email)) {
                $error = 'Email inválido.';
            } elseif (strlen($password) < 6) {
                $error = 'A senha deve ter pelo menos 6 caracteres.';
            } elseif ($password !== $confirm_password) {
                $error = 'As senhas não coincidem.';
            } elseif (strlen($nome) < 2) {
                $error = 'Nome deve ter pelo menos 2 caracteres.';
            } elseif (strlen($nome) > 100) {
                $error = 'Nome deve ter no máximo 100 caracteres.';
            }
            
            // Validar CPF se fornecido (opcional)
            if (!$error && !empty($cpf)) {
                $cpf_numbers = preg_replace('/[^0-9]/', '', $cpf);
                if (strlen($cpf_numbers) !== 11) {
                    $error = 'CPF deve ter 11 dígitos.';
                } elseif (!validateCPF($cpf_numbers)) {
                    $error = 'CPF inválido.';
                }
            }
            
            // Validar telefone se fornecido (opcional)
            if (!$error && !empty($telefone)) {
                $telefone_numbers = preg_replace('/[^0-9]/', '', $telefone);
                if (strlen($telefone_numbers) < 10 || strlen($telefone_numbers) > 11) {
                    $error = 'Telefone deve ter 10 ou 11 dígitos.';
                }
            }
        }
        
        if (!$error) {
            try {
                // Verificar se email já existe
                $existing = Database::fetch("SELECT id FROM usuarios WHERE email = ?", [$email]);
                
                if ($existing) {
                    $error = 'Este email já está cadastrado.';
                } else {
                    // Verificar se nome já existe
                    $existing_name = Database::fetch("SELECT id FROM usuarios WHERE nome = ?", [$nome]);
                    if ($existing_name) {
                        $error = 'Este nome de usuário já está cadastrado.';
                    } else {
                        // Verificar se CPF já existe (se fornecido)
                        if (!empty($cpf)) {
                            $cpf_clean = preg_replace('/[^0-9]/', '', $cpf);
                            $existing_cpf = Database::fetch("SELECT id FROM usuarios WHERE cpf = ? AND cpf IS NOT NULL", [$cpf_clean]);
                            if ($existing_cpf) {
                                $error = 'Este CPF já está cadastrado.';
                            }
                        }
                        
                        if (!$error) {
                            // Preparar dados para inserção
                            $hashedPassword = hashPassword($password);
                            $cpf_clean = !empty($cpf) ? preg_replace('/[^0-9]/', '', $cpf) : null;
                            $telefone_clean = !empty($telefone) ? preg_replace('/[^0-9]/', '', $telefone) : null;
                            $endereco_final = !empty($endereco) ? $endereco : null;
                            $observacoes_final = !empty($observacoes) ? $observacoes : null;
                            
                            // Inserir novo usuário
                            $sql = "INSERT INTO usuarios (nome, email, senha, tipo, telefone, cpf, endereco, observacoes, ativo";
                            $params = [$nome, $email, $hashedPassword, $tipo, $telefone_clean, $cpf_clean, $endereco_final, $observacoes_final, 1];
                            
                            // Adicionar created_by se a coluna existir
                            try {
                                $columns = Database::fetchAll("SHOW COLUMNS FROM usuarios LIKE 'created_by'");
                                if (!empty($columns)) {
                                    $sql .= ", created_by";
                                    $params[] = $_SESSION['user_id'];
                                }
                            } catch (Exception $e) {
                                // Coluna não existe, continuar sem ela
                            }
                            
                            $sql .= ") VALUES (" . str_repeat('?,', count($params) - 1) . "?)";
                            
                            Database::query($sql, $params);
                            $user_id = Database::lastInsertId();
                            
                            // Se for técnico, inserir também na tabela de técnicos
                            if ($tipo === 'tecnico') {
                                try {
                                    Database::query(
                                        "INSERT INTO tecnicos (nome, email, telefone, usuario_id, ativo) VALUES (?, ?, ?, ?, 1)",
                                        [$nome, $email, $telefone_clean, $user_id]
                                    );
                                } catch (Exception $e) {
                                    // Se a tabela tecnicos não existir, apenas log o erro
                                    error_log('Tabela tecnicos não encontrada: ' . $e->getMessage());
                                }
                            }
                            
                            $success = "Usuário <strong>{$nome}</strong> cadastrado com sucesso como <strong>{$tipo}</strong>!";
                            
                            // Log específico por tipo de usuário
                            if ($_SESSION['user_type'] === 'admin') {
                                $log_message = "Usuário cadastrado pelo Admin: {$nome} (ID: {$user_id}, Tipo: {$tipo}) por {$_SESSION['user_name']} (ID: {$_SESSION['user_id']})";
                            } else {
                                $log_message = "Usuário cadastrado pelo Técnico: {$nome} (ID: {$user_id}, Tipo: {$tipo}) por {$_SESSION['user_name']} (ID: {$_SESSION['user_id']})";
                            }
                            
                            logMessage($log_message, 'INFO');
                            
                            // Limpar campos após sucesso
                            $nome = $email = $telefone = $cpf = $endereco = $observacoes = $tipo = '';
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Erro ao cadastrar usuário: ' . $e->getMessage();
                logMessage('Erro detalhado ao cadastrar usuário: ' . $e->getMessage() . ' | Linha: ' . $e->getLine() . ' | Arquivo: ' . $e->getFile() . ' | Admin: ' . $_SESSION['user_name'], 'ERROR');
            }
        }
    }
}

// Buscar estatísticas baseadas em permissões
try {
    $stats = [];
    
    if ($_SESSION['user_type'] === 'admin') {
        // Estatísticas completas para Admin
        $stats = [
            'total_usuarios' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1")['total'],
            'total_admins' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'admin' AND ativo = 1")['total'],
            'total_tecnicos' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'tecnico' AND ativo = 1")['total'],
            'total_usuarios_comuns' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'usuario' AND ativo = 1")['total']
        ];
    } elseif ($_SESSION['user_type'] === 'tecnico') {
        // Estatísticas limitadas para Técnico
        $stats = [
            'usuarios_criados_por_mim' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE created_by = ? AND ativo = 1", [$_SESSION['user_id']])['total'],
            'total_usuarios_comuns' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'usuario' AND ativo = 1")['total'],
            'manutencoes_ativas' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE tecnico_id = ? AND status IN ('agendada', 'em_andamento')", [$_SESSION['user_id']])['total']
        ];
    }
} catch (Exception $e) {
    $stats = [];
}

// Buscar usuários recentes
try {
    if ($_SESSION['user_type'] === 'admin') {
        $recent_users = Database::fetchAll("
            SELECT u.*, uc.nome as created_by_name
            FROM usuarios u
            LEFT JOIN usuarios uc ON u.created_by = uc.id
            WHERE u.ativo = 1
            ORDER BY u.created_at DESC
            LIMIT 5
        ");
    } else {
        $recent_users = Database::fetchAll("
            SELECT u.*, uc.nome as created_by_name
            FROM usuarios u
            LEFT JOIN usuarios uc ON u.created_by = uc.id
            WHERE u.ativo = 1 AND u.created_by = ?
            ORDER BY u.created_at DESC
            LIMIT 5
        ", [$_SESSION['user_id']]);
    }
} catch (Exception $e) {
    $recent_users = [];
}

// Obter informações de boas-vindas
$welcome_info = UserPermissions::getWelcomeMessage($_SESSION['user_type'], $_SESSION['user_name']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Usuários - HidroApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --accent-color: #ffb800;
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
        
        .register-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            overflow: hidden;
            position: relative;
        }
        
        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .register-card .card-header {
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

        /* Formulários modernos */
        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            transition: var(--transition);
            background: var(--bg-white);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
            background: var(--bg-white);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        /* Alertas modernos */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-light);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
        }

        /* Indicador de força da senha */
        .password-strength {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #198754; }

        /* Badges de permissão */
        .permission-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .permission-admin {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
        }

        .permission-tecnico {
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            color: white;
        }

        .permission-usuario {
            background: linear-gradient(135deg, #55a3ff, #2d3436);
            color: white;
        }

        /* Campos desabilitados */
        .form-control:disabled,
        .form-select:disabled {
            background-color: #f8f9fa;
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Cards de usuários recentes */
        .user-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Input group styles */
        .input-group-text {
            background: linear-gradient(135deg, var(--bg-light), var(--bg-white));
            border: 2px solid var(--border-color);
            border-right: none;
            color: var(--text-gray);
        }

        .input-group .form-control {
            border-left: none;
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
        }
        
        @media (max-width: 992px) {
            .content-area {
                padding: 1rem;
            }
            .top-header {
                padding: 0 1rem;
            }
            .stat-card {
                margin-bottom: 1rem;
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

            .register-card .card-header {
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
        .form-control:focus,
        .form-select:focus {
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

        /* Form validation styles */
        .is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .invalid-feedback {
            display: block;
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
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
        </div>
        
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <?= UserPermissions::generateSidebar($_SESSION['user_type'], 'register.php') ?>
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
                <h4 class="mb-0">Cadastro de Usuários</h4>
                <div class="ms-3">
                    <span class="<?= $welcome_info['badge_class'] ?>">
                        <?= $welcome_info['user_type_display'] ?>
                    </span>
                </div>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($_SESSION['user_name']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#perfil"><i class="bi bi-person me-2"></i>Perfil</a></li>
                    <li><a class="dropdown-item" href="#configuracoes"><i class="bi bi-gear me-2"></i>Configurações</a></li>
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
                        <i class="bi bi-shield-check me-3"></i>
                        <?php if ($_SESSION['user_type'] === 'admin'): ?>
                            Painel Administrativo - Cadastro de Usuários
                        <?php else: ?>
                            Painel Técnico - Cadastro de Usuários
                        <?php endif; ?>
                    </h2>
                    <p class="mb-0 opacity-90">
                        <?php if ($_SESSION['user_type'] === 'admin'): ?>
                            Como administrador, você tem acesso total para criar e gerenciar contas de usuários no sistema.
                        <?php else: ?>
                            Como técnico, você pode cadastrar usuários comuns para o sistema.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-info alert-dismissible fade show fade-in" role="alert">
                    <i class="bi bi-info-circle me-2"></i><?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show fade-in" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Permission Info -->
            <div class="alert alert-info fade-in">
                <div class="d-flex align-items-start">
                    <i class="bi bi-shield-check fs-5 me-3 mt-1"></i>
                    <div>
                        <h6 class="mb-2">
                            <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                Acesso Administrativo
                            <?php else: ?>
                                Acesso de Técnico
                            <?php endif; ?>
                        </h6>
                        <p class="mb-0">
                            <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                <strong>Você tem acesso total ao sistema</strong> e pode cadastrar todos os tipos de usuários:
                                <strong>Administradores</strong>, <strong>Técnicos</strong> e <strong>Usuários</strong> comuns.
                            <?php else: ?>
                                <strong>Como técnico</strong>, você pode cadastrar apenas <strong>Usuários comuns</strong> 
                                para ajudar na gestão do sistema.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <?php if ($_SESSION['user_type'] === 'admin'): ?>
                    <!-- Stats para Admin -->
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total_usuarios'] ?></h3>
                                    <p class="text-muted mb-0">Total de Usuários</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total_admins'] ?></h3>
                                    <p class="text-muted mb-0">Administradores</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total_tecnicos'] ?></h3>
                                    <p class="text-muted mb-0">Técnicos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #55a3ff 0%, #2d3436 100%);">
                                    <i class="bi bi-person"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total_usuarios_comuns'] ?></h3>
                                    <p class="text-muted mb-0">Usuários Comuns</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($_SESSION['user_type'] === 'tecnico'): ?>
                    <!-- Stats para Técnico -->
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);">
                                    <i class="bi bi-person-plus"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['usuarios_criados_por_mim'] ?></h3>
                                    <p class="text-muted mb-0">Usuários que Cadastrei</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #55a3ff 0%, #2d3436 100%);">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total_usuarios_comuns'] ?></h3>
                                    <p class="text-muted mb-0">Total de Usuários</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--accent-color) 0%, #e6a800 100%);">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['manutencoes_ativas'] ?? 0 ?></h3>
                                    <p class="text-muted mb-0">Manutenções Ativas</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <!-- Registration Form -->
                <div class="col-lg-8 mb-4">
                    <div class="card register-card fade-in">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-person-plus me-2"></i>Cadastrar Novo Usuário
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" id="registerForm" novalidate>
                                <input type="hidden" name="action" value="create">
                                
                                <!-- Informações Básicas -->
                                <div class="mb-4">
                                    <h6 class="text-primary mb-3">
                                        <i class="bi bi-person-badge me-2"></i>Informações Básicas
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label class="form-label">Nome Completo *</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                                    <input type="text" class="form-control" name="nome" id="nome" 
                                                           placeholder="Nome completo do usuário" 
                                                           value="<?= htmlspecialchars($nome ?? '') ?>" 
                                                           required maxlength="100">
                                                </div>
                                                <div class="invalid-feedback"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Tipo de Usuário *</label>
                                                <select class="form-select" name="tipo" id="tipo" required>
                                                    <option value="">Selecione o tipo</option>
                                                    
                                                    <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                                        <option value="usuario" <?= ($tipo ?? '') === 'usuario' ? 'selected' : '' ?>>
                                                            👤 Usuário
                                                        </option>
                                                        <option value="tecnico" <?= ($tipo ?? '') === 'tecnico' ? 'selected' : '' ?>>
                                                            🔧 Técnico
                                                        </option>
                                                        <option value="admin" <?= ($tipo ?? '') === 'admin' ? 'selected' : '' ?>>
                                                            🛡️ Administrador
                                                        </option>
                                                    <?php elseif ($_SESSION['user_type'] === 'tecnico'): ?>
                                                        <option value="usuario" selected>
                                                            👤 Usuário Comum
                                                        </option>
                                                    <?php endif; ?>
                                                </select>
                                                <?php if ($_SESSION['user_type'] === 'tecnico'): ?>
                                                    <small class="text-info">
                                                        <i class="bi bi-info-circle me-1"></i>
                                                        Técnicos só podem cadastrar usuários comuns
                                                    </small>
                                                <?php endif; ?>
                                                <div class="invalid-feedback"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">E-mail *</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                                    <input type="email" class="form-control" name="email" id="email" 
                                                           placeholder="usuario@exemplo.com" 
                                                           value="<?= htmlspecialchars($email ?? '') ?>" required>
                                                </div>
                                                <div class="invalid-feedback"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Telefone (opcional)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                                    <input type="tel" class="form-control" name="telefone" id="telefone" 
                                                           placeholder="(11) 99999-9999" 
                                                           value="<?= htmlspecialchars($telefone ?? '') ?>" 
                                                           maxlength="15">
                                                </div>
                                                <small class="text-muted">Formato: (11) 99999-9999 (opcional)</small>
                                                <div class="invalid-feedback"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">CPF (opcional)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                                            <input type="text" class="form-control" name="cpf" id="cpf" 
                                                   placeholder="000.000.000-00" 
                                                   value="<?= htmlspecialchars($cpf ?? '') ?>" 
                                                   maxlength="14">
                                        </div>
                                        <small class="text-muted">Formato: 000.000.000-00 (opcional)</small>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>

                                <!-- Informações de Acesso -->
                                <div class="mb-4">
                                    <h6 class="text-primary mb-3">
                                        <i class="bi bi-key me-2"></i>Informações de Acesso
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Senha *</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                                    <input type="password" class="form-control" name="password" id="password" 
                                                           placeholder="Mínimo 6 caracteres" required>
                                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                                <div id="passwordStrength" class="password-strength"></div>
                                                <div class="invalid-feedback"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Confirmar Senha *</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" 
                                                           placeholder="Confirme a senha" required>
                                                </div>
                                                <div class="invalid-feedback"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Informações Adicionais -->
                                <div class="mb-4">
                                    <h6 class="text-primary mb-3">
                                        <i class="bi bi-info-circle me-2"></i>Informações Adicionais
                                    </h6>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Endereço (opcional)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                            <input type="text" class="form-control" name="endereco" id="endereco" 
                                                   placeholder="Endereço completo (opcional)" 
                                                   value="<?= htmlspecialchars($endereco ?? '') ?>" 
                                                   maxlength="300">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Observações</label>
                                        <textarea class="form-control" name="observacoes" id="observacoes" rows="3" 
                                                  placeholder="Informações adicionais sobre o usuário (opcional)" 
                                                  maxlength="500"><?= htmlspecialchars($observacoes ?? '') ?></textarea>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">Informações extras, função, departamento, etc.</small>
                                            <small class="text-muted" id="observacoesCount">0/500</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botões de Ação -->
                                <div class="d-flex gap-3 justify-content-end">
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Limpar
                                    </button>
                                    <button type="submit" class="btn btn-primary-custom" id="submitBtn">
                                        <i class="bi bi-person-plus me-1"></i>Cadastrar Usuário
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Recent Users & Info -->
                <div class="col-lg-4">
                    <!-- User Type Info -->
                    <div class="card user-card mb-4 fade-in">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-info-circle me-2"></i>Tipos de Usuário
                                <?php if ($_SESSION['user_type'] === 'tecnico'): ?>
                                    (Seu Acesso)
                                <?php endif; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                <!-- Informações completas para Admin -->
                                <div class="mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="permission-badge permission-admin me-2">Admin</span>
                                        <strong>Administrador</strong>
                                    </div>
                                    <small class="text-muted">
                                        Acesso total ao sistema, pode gerenciar usuários, equipamentos, 
                                        manutenções e relatórios.
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="permission-badge permission-tecnico me-2">Técnico</span>
                                        <strong>Técnico</strong>
                                    </div>
                                    <small class="text-muted">
                                        Pode gerenciar manutenções, visualizar equipamentos e criar 
                                        usuários básicos.
                                    </small>
                                </div>
                                
                                <div class="mb-0">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="permission-badge permission-usuario me-2">User</span>
                                        <strong>Usuário</strong>
                                    </div>
                                    <small class="text-muted">
                                        Acesso básico para visualizar equipamentos e manutenções.
                                    </small>
                                </div>
                                
                            <?php elseif ($_SESSION['user_type'] === 'tecnico'): ?>
                                <!-- Informações limitadas para Técnico -->
                                <div class="mb-0">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="permission-badge permission-usuario me-2">User</span>
                                        <strong>Usuário Comum</strong>
                                    </div>
                                    <small class="text-muted">
                                        Acesso básico para visualizar equipamentos e manutenções concluídas.
                                        Pode consultar o status dos equipamentos e histórico de manutenções.
                                    </small>
                                </div>
                                
                                <div class="mt-3 p-2 bg-light rounded">
                                    <small class="text-info">
                                        <i class="bi bi-info-circle me-1"></i>
                                        <strong>Nota:</strong> Como técnico, você só pode cadastrar usuários comuns. 
                                        Para cadastrar técnicos ou administradores, contacte um administrador.
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Users -->
                    <?php if (!empty($recent_users)): ?>
                    <div class="card user-card fade-in">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-clock-history me-2"></i>
                                <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                    Usuários Recentes
                                <?php else: ?>
                                    Usuários que Cadastrei
                                <?php endif; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($recent_users as $user): ?>
                                <div class="d-flex align-items-center p-2 border-bottom hover-lift rounded">
                                    <div class="me-3">
                                        <i class="bi bi-person-circle fs-4 text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($user['nome']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                        <div class="mt-1">
                                            <span class="permission-badge permission-<?= strtolower($user['tipo']) ?>">
                                                <?= ucfirst($user['tipo']) ?>
                                            </span>
                                        </div>
                                        <?php if ($user['created_by_name']): ?>
                                            <small class="text-muted d-block">
                                                Por: <?= htmlspecialchars($user['created_by_name']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?= date('d/m', strtotime($user['created_at'])) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
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
    <script>
        // Sidebar toggle
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

        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            // Length check
            if (password.length >= 6) strength++;
            else feedback.push('pelo menos 6 caracteres');
            
            // Uppercase and lowercase
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) {
                strength++;
            } else {
                feedback.push('letras maiúsculas e minúsculas');
            }
            
            // Numbers
            if (password.match(/\d/)) {
                strength++;
            } else {
                feedback.push('números');
            }
            
            // Special characters
            if (password.match(/[^a-zA-Z\d]/)) {
                strength++;
            } else {
                feedback.push('caracteres especiais');
            }
            
            const messages = [
                { text: 'Muito fraca', class: 'strength-weak' },
                { text: 'Fraca', class: 'strength-weak' },
                { text: 'Média', class: 'strength-medium' },
                { text: 'Forte', class: 'strength-strong' },
                { text: 'Muito forte', class: 'strength-strong' }
            ];
            
            const strengthInfo = messages[strength];
            strengthDiv.innerHTML = `
                Força: <span class="${strengthInfo.class}">${strengthInfo.text}</span>
                ${feedback.length > 0 ? '<br><small>Adicione: ' + feedback.join(', ') + '</small>' : ''}
            `;
        });

        // CPF and phone formatting
        document.getElementById('cpf').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            this.value = value;
        });

        document.getElementById('telefone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d{4})(\d)/, '($1) $2-$3');
            } else {
                value = value.replace(/(\d{2})(\d{5})(\d)/, '($1) $2-$3');
            }
            this.value = value;
        });

        // Character count for observations
        document.getElementById('observacoes').addEventListener('input', function() {
            const count = this.value.length;
            document.getElementById('observacoesCount').textContent = `${count}/500`;
            
            if (count > 450) {
                document.getElementById('observacoesCount').className = 'text-warning';
            } else if (count === 500) {
                document.getElementById('observacoesCount').className = 'text-danger';
            } else {
                document.getElementById('observacoesCount').className = 'text-muted';
            }
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const nome = document.getElementById('nome');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const tipo = document.getElementById('tipo');
            const cpf = document.getElementById('cpf');
            const telefone = document.getElementById('telefone');
            
            let isValid = true;

            // Reset validations
            [nome, email, password, confirmPassword, tipo, cpf, telefone].forEach(field => {
                field.classList.remove('is-invalid');
            });

            // Validate name
            if (!nome.value.trim()) {
                showFieldError(nome, 'Nome é obrigatório');
                isValid = false;
            } else if (nome.value.trim().length < 2) {
                showFieldError(nome, 'Nome deve ter pelo menos 2 caracteres');
                isValid = false;
            } else if (nome.value.trim().length > 100) {
                showFieldError(nome, 'Nome deve ter no máximo 100 caracteres');
                isValid = false;
            }

            // Validate email
            if (!email.value.trim()) {
                showFieldError(email, 'Email é obrigatório');
                isValid = false;
            } else if (!isValidEmail(email.value)) {
                showFieldError(email, 'Email inválido');
                isValid = false;
            }

            // Validate user type
            if (!tipo.value) {
                showFieldError(tipo, 'Tipo de usuário é obrigatório');
                isValid = false;
            }

            // Validate password
            if (!password.value) {
                showFieldError(password, 'Senha é obrigatória');
                isValid = false;
            } else if (password.value.length < 6) {
                showFieldError(password, 'Senha deve ter pelo menos 6 caracteres');
                isValid = false;
            }

            // Validate password confirmation
            if (!confirmPassword.value) {
                showFieldError(confirmPassword, 'Confirmação de senha é obrigatória');
                isValid = false;
            } else if (password.value !== confirmPassword.value) {
                showFieldError(confirmPassword, 'Senhas não coincidem');
                isValid = false;
            }

            // Validate CPF (if provided)
            if (cpf.value.trim()) {
                const cpfNumbers = cpf.value.replace(/\D/g, '');
                if (cpfNumbers.length !== 11) {
                    showFieldError(cpf, 'CPF deve ter 11 dígitos');
                    isValid = false;
                } else if (!isValidCPF(cpfNumbers)) {
                    showFieldError(cpf, 'CPF inválido');
                    isValid = false;
                }
            }

            // Validate phone (if provided)
            if (telefone.value.trim()) {
                const phoneNumbers = telefone.value.replace(/\D/g, '');
                if (phoneNumbers.length < 10 || phoneNumbers.length > 11) {
                    showFieldError(telefone, 'Telefone deve ter 10 ou 11 dígitos');
                    isValid = false;
                }
            }

            if (!isValid) {
                e.preventDefault();
            } else {
                // Add loading state
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Cadastrando...';
            }
        });

        function showFieldError(field, message) {
            field.classList.add('is-invalid');
            let feedback = field.closest('.mb-3').querySelector('.invalid-feedback');
            if (feedback) {
                feedback.textContent = message;
            }
        }

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function isValidCPF(cpf) {
            // Remove any non-digit characters
            cpf = cpf.replace(/\D/g, '');
            
            // Check if CPF has 11 digits
            if (cpf.length !== 11) return false;
            
            // Check if all digits are the same
            if (/^(\d)\1{10}$/.test(cpf)) return false;
            
            // Validate first check digit
            let sum = 0;
            for (let i = 0; i < 9; i++) {
                sum += parseInt(cpf.charAt(i)) * (10 - i);
            }
            let digit1 = (sum * 10) % 11;
            if (digit1 === 10) digit1 = 0;
            if (digit1 !== parseInt(cpf.charAt(9))) return false;
            
            // Validate second check digit
            sum = 0;
            for (let i = 0; i < 10; i++) {
                sum += parseInt(cpf.charAt(i)) * (11 - i);
            }
            let digit2 = (sum * 10) % 11;
            if (digit2 === 10) digit2 = 0;
            if (digit2 !== parseInt(cpf.charAt(10))) return false;
            
            return true;
        }

        function resetForm() {
            if (confirm('Tem certeza que deseja limpar todos os campos?')) {
                document.getElementById('registerForm').reset();
                document.getElementById('passwordStrength').textContent = '';
                document.getElementById('observacoesCount').textContent = '0/500';
                document.getElementById('observacoesCount').className = 'text-muted';
                
                // Remove validation states
                document.querySelectorAll('.is-invalid').forEach(field => {
                    field.classList.remove('is-invalid');
                });
            }
        }

        // Clear errors on input
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            });
        }, 8000);

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

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart;
            console.log(`Register page loaded in ${loadTime}ms`);
        });

        // Initialize character count
        document.addEventListener('DOMContentLoaded', function() {
            const observacoes = document.getElementById('observacoes');
            if (observacoes.value) {
                const count = observacoes.value.length;
                document.getElementById('observacoesCount').textContent = `${count}/500`;
            }
        });
    </script>
</body>
</html>