<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

$message = '';
$error = '';
$success = '';

// Verificar permissões de acesso à página
if (!UserPermissions::hasPermission($_SESSION['user_type'], 'configuracoes', 'view')) {
    logMessage("Tentativa de acesso não autorizado às configurações por: {$_SESSION['user_name']} (ID: {$_SESSION['user_id']}, Tipo: {$_SESSION['user_type']})", 'WARNING');
    header('Location: index.php?error=access_denied');
    exit;
}
// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar permissões específicas
UserPermissions::enforcePageAccess($_SESSION['user_type'], 'configuracoes.php');

// Buscar dados do usuário atual
try {
    $user = Database::fetch(
        "SELECT * FROM usuarios WHERE id = ?",
        [$_SESSION['user_id']]
    );
    
    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    $error = 'Erro ao carregar dados do usuário.';
    logMessage('Erro ao carregar usuário: ' . $e->getMessage(), 'ERROR');
}

// Processamento de ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Verificar se pode editar perfil
        if (!hasPermission('configuracoes', 'edit_profile')) {
            $error = 'Você não tem permissão para editar o perfil.';
        } else {
            $nome = sanitize($_POST['nome'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $telefone = sanitize($_POST['telefone'] ?? '');
            $cpf = sanitize($_POST['cpf'] ?? '');
            $endereco = sanitize($_POST['endereco'] ?? '');
            
            // Validações
            if (empty($nome) || empty($email)) {
                $error = 'Nome e email são obrigatórios.';
            } elseif (!validateEmail($email)) {
                $error = 'Email inválido.';
            } elseif (strlen($nome) < 2 || strlen($nome) > 100) {
                $error = 'Nome deve ter entre 2 e 100 caracteres.';
            } else {
                // Verificar se email ou nome já existem em outros usuários
                $existing = Database::fetch(
                    "SELECT id FROM usuarios WHERE (email = ? OR nome = ?) AND id != ?",
                    [$email, $nome, $_SESSION['user_id']]
                );
                
                if ($existing) {
                    $error = 'Email ou nome de usuário já estão em uso.';
                } else {
                    // Validar CPF se fornecido
                    if (!empty($cpf)) {
                        $cpf_clean = preg_replace('/[^0-9]/', '', $cpf);
                        if (strlen($cpf_clean) !== 11 || !validateCPF($cpf_clean)) {
                            $error = 'CPF inválido.';
                        } else {
                            // Verificar se CPF já existe em outros usuários
                            $existing_cpf = Database::fetch(
                                "SELECT id FROM usuarios WHERE cpf = ? AND id != ?",
                                [$cpf_clean, $_SESSION['user_id']]
                            );
                            if ($existing_cpf) {
                                $error = 'CPF já está em uso.';
                            }
                        }
                    }
                    
                    // Validar telefone se fornecido
                    if (!empty($telefone) && !$error) {
                        $telefone_clean = preg_replace('/[^0-9]/', '', $telefone);
                        if (strlen($telefone_clean) < 10 || strlen($telefone_clean) > 11) {
                            $error = 'Telefone deve ter 10 ou 11 dígitos.';
                        }
                    }
                    
                    if (!$error) {
                        try {
                            $cpf_final = !empty($cpf) ? preg_replace('/[^0-9]/', '', $cpf) : null;
                            $telefone_final = !empty($telefone) ? preg_replace('/[^0-9]/', '', $telefone) : null;
                            $endereco_final = !empty($endereco) ? $endereco : null;
                            
                            Database::query(
                                "UPDATE usuarios SET nome = ?, email = ?, telefone = ?, cpf = ?, endereco = ? WHERE id = ?",
                                [$nome, $email, $telefone_final, $cpf_final, $endereco_final, $_SESSION['user_id']]
                            );
                            
                            // Atualizar dados na sessão
                            $_SESSION['user_name'] = $nome;
                            $_SESSION['user_email'] = $email;
                            
                            // Recarregar dados do usuário
                            $user = Database::fetch("SELECT * FROM usuarios WHERE id = ?", [$_SESSION['user_id']]);
                            
                            $success = 'Perfil atualizado com sucesso!';
                            logMessage("Perfil atualizado: {$nome} (ID: {$_SESSION['user_id']})", 'INFO');
                            
                        } catch (Exception $e) {
                            $error = 'Erro ao atualizar perfil: ' . $e->getMessage();
                            logMessage('Erro ao atualizar perfil: ' . $e->getMessage(), 'ERROR');
                        }
                    }
                }
            }
        }
    } elseif ($action === 'change_password') {
        // Verificar se pode alterar senha
        if (!hasPermission('configuracoes', 'edit_profile')) {
            $error = 'Você não tem permissão para alterar a senha.';
        } else {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'Todos os campos de senha são obrigatórios.';
            } elseif (!verifyPassword($current_password, $user['senha'])) {
                $error = 'Senha atual incorreta.';
            } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
                $error = 'Nova senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Nova senha e confirmação não coincidem.';
            } else {
                try {
                    $new_hashed_password = hashPassword($new_password);
                    Database::query(
                        "UPDATE usuarios SET senha = ? WHERE id = ?",
                        [$new_hashed_password, $_SESSION['user_id']]
                    );
                    
                    $success = 'Senha alterada com sucesso!';
                    logMessage("Senha alterada: {$user['nome']} (ID: {$_SESSION['user_id']})", 'INFO');
                    
                } catch (Exception $e) {
                    $error = 'Erro ao alterar senha: ' . $e->getMessage();
                    logMessage('Erro ao alterar senha: ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    } elseif ($action === 'system_settings') {
        // VERIFICAÇÃO ESPECÍFICA PARA CONFIGURAÇÕES DO SISTEMA
        if (!hasPermission('configuracoes', 'system_settings')) {
            $error = 'Apenas administradores podem alterar configurações do sistema.';
        } else {
            $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
            $debug_mode = isset($_POST['debug_mode']) ? 1 : 0;
            
            // Aqui você implementaria a lógica para salvar configurações do sistema
            // Por enquanto, apenas log
            logMessage("Configurações do sistema alteradas por admin: {$user['nome']}", 'INFO');
            $success = 'Configurações do sistema atualizadas!';
        }
    }
}

// Buscar atividades recentes do usuário
try {
    $recent_activities = Database::fetchAll(
        "SELECT last_login, last_logout, created_at, updated_at FROM usuarios WHERE id = ?",
        [$_SESSION['user_id']]
    );
} catch (Exception $e) {
    $recent_activities = [];
}

// Buscar estatísticas do usuário baseadas em permissões
$user_stats = [];
if (hasPermission('dashboard', 'full_stats')) {
    try {
        $user_stats = [
            'total_users' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1")['total'],
            'users_created_by_me' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE created_by = ?", [$_SESSION['user_id']])['total'],
            'total_equipments' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos")['total'],
            'total_maintenances' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes")['total']
        ];
    } catch (Exception $e) {
        $user_stats = ['total_users' => 0, 'users_created_by_me' => 0, 'total_equipments' => 0, 'total_maintenances' => 0];
    }
} elseif (hasPermission('dashboard', 'basic_stats')) {
    try {
        $user_stats = [
            'my_maintenances' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE tecnico_id = ?", [$_SESSION['user_id']])['total'],
            'equipment_count' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'ativo'")['total'],
            'pending_maintenances' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE status IN ('agendada', 'em_andamento')")['total']
        ];
    } catch (Exception $e) {
        $user_stats = ['my_maintenances' => 0, 'equipment_count' => 0, 'pending_maintenances' => 0];
    }
}

// Obter informações de boas-vindas
$welcome_info = UserPermissions::getWelcomeMessage($_SESSION['user_type'], $_SESSION['user_name']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - HidroApp</title>
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
        
        .config-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
        }
        
        .config-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .config-card .card-header {
            background: linear-gradient(135deg, var(--bg-light), var(--bg-white));
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
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

        /* Tabs personalizadas */
        .nav-tabs {
            border-bottom: 2px solid var(--border-color);
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--text-gray);
            font-weight: 600;
            padding: 1rem 1.5rem;
            margin-right: 0.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            background: transparent;
            transition: var(--transition);
        }

        .nav-tabs .nav-link:hover {
            background: var(--bg-light);
            color: var(--primary-color);
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
            border-bottom: 2px solid var(--primary-color);
        }

        .tab-content {
            padding: 2rem 0;
        }

        /* User info display */
        .user-info {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .user-info::before {
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

        .user-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        /* Password strength indicator */
        .password-strength {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #198754; }

        /* Activity timeline */
        .activity-item {
            padding: 1rem;
            border-left: 3px solid var(--primary-color);
            margin-bottom: 1rem;
            background: var(--bg-light);
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }

        /* Switches modernos */
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
            background-color: var(--border-color);
            border: none;
            cursor: pointer;
        }

        .form-switch .form-check-input:checked {
            background-color: var(--primary-color);
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
            .config-card {
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

            .config-card {
                margin-bottom: 1rem;
            }

            .top-header {
                padding: 0 1rem;
                height: 60px;
            }

            .user-info {
                padding: 1rem;
                text-align: center;
            }

            .nav-tabs .nav-link {
                padding: 0.75rem 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .content-area {
                padding: 0.75rem;
            }

            .config-card .card-header {
                padding: 1rem;
            }

            .user-info {
                padding: 1rem;
            }

            .user-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .nav-tabs .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }
        }

        /* Animações suaves */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
                <?= UserPermissions::generateSidebar($_SESSION['user_type'], 'configuracoes.php') ?>
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
                <h4 class="mb-0">Configurações</h4>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($_SESSION['user_name']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#perfil"><i class="bi bi-person me-2"></i>Perfil</a></li>
                    <li><a class="dropdown-item" href="configuracoes.php"><i class="bi bi-gear me-2"></i>Configurações</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                </ul>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
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

            <!-- User Info Section -->
            <div class="user-info fade-in">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <div class="user-avatar mx-auto">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                    <div class="col-md-10">
                        <h2 class="mb-2"><?= htmlspecialchars($welcome_info['title']) ?></h2>
                        <p class="mb-1 opacity-90">
                            <i class="bi bi-envelope me-2"></i><?= htmlspecialchars($user['email']) ?>
                        </p>
                        <p class="mb-1 opacity-90">
                            <i class="bi bi-shield me-2"></i>
                            <span class="<?= $welcome_info['badge_class'] ?> px-2 py-1 rounded">
                                <?= $welcome_info['user_type_display'] ?>
                            </span>
                        </p>
                        <p class="mb-1 opacity-75">
                            <i class="bi bi-info-circle me-2"></i><?= $welcome_info['subtitle'] ?>
                        </p>
                        <p class="mb-0 opacity-75">
                            <i class="bi bi-calendar me-2"></i>Membro desde: <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Admin Stats -->
            <?php if (hasPermission('dashboard', 'full_stats') && !empty($user_stats)): ?>
            <!-- Stats para Admin -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                                <i class="bi bi-people"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $user_stats['total_users'] ?></h3>
                                <p class="text-muted mb-0">Total de Usuários</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                <i class="bi bi-person-plus"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $user_stats['users_created_by_me'] ?></h3>
                                <p class="text-muted mb-0">Usuários Criados</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--accent-color) 0%, #e6a800 100%);">
                                <i class="bi bi-hdd-stack"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $user_stats['total_equipments'] ?></h3>
                                <p class="text-muted mb-0">Equipamentos</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);">
                                <i class="bi bi-tools"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $user_stats['total_maintenances'] ?></h3>
                                <p class="text-muted mb-0">Manutenções</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif (hasPermission('dashboard', 'basic_stats') && !empty($user_stats)): ?>
            <!-- Stats para Técnico -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);">
                                <i class="bi bi-tools"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $user_stats['my_maintenances'] ?></h3>
                                <p class="text-muted mb-0">Minhas Manutenções</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                <i class="bi bi-hdd-stack"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $user_stats['equipment_count'] ?></h3>
                                <p class="text-muted mb-0">Equipamentos Ativos</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--accent-color) 0%, #e6a800 100%);">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $user_stats['pending_maintenances'] ?></h3>
                                <p class="text-muted mb-0">Manutenções Pendentes</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Configuration Tabs -->
            <div class="config-card fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-gear me-2"></i>Configurações da Conta
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Navigation Tabs - Filtradas por Permissão -->
                    <ul class="nav nav-tabs" id="configTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                <i class="bi bi-person me-2"></i>Perfil
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                <i class="bi bi-shield-lock me-2"></i>Segurança
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                                <i class="bi bi-clock-history me-2"></i>Atividade
                            </button>
                        </li>
                        <?php if (hasPermission('configuracoes', 'system_settings')): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                                <i class="bi bi-gear-wide-connected me-2"></i>Sistema
                            </button>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="configTabContent">
                        <!-- Profile Tab -->
                        <div class="tab-pane fade show active" id="profile" role="tabpanel">
                            <form method="POST" id="profileForm" novalidate>
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nome Completo *</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                                <input type="text" class="form-control" name="nome" id="nome" 
                                                       value="<?= htmlspecialchars($user['nome']) ?>" 
                                                       required maxlength="100">
                                            </div>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">E-mail *</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                                <input type="email" class="form-control" name="email" id="email" 
                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                            </div>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Telefone (opcional)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                                <input type="tel" class="form-control" name="telefone" id="telefone" 
                                                       value="<?= $user['telefone'] ? formatPhone($user['telefone']) : '' ?>" 
                                                       maxlength="15">
                                            </div>
                                            <small class="text-muted">Formato: (11) 99999-9999</small>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">CPF (opcional)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                                                <input type="text" class="form-control" name="cpf" id="cpf" 
                                                       value="<?= $user['cpf'] ? formatCPF($user['cpf']) : '' ?>" 
                                                       maxlength="14">
                                            </div>
                                            <small class="text-muted">Formato: 000.000.000-00</small>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Endereço (opcional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                        <input type="text" class="form-control" name="endereco" id="endereco" 
                                               value="<?= htmlspecialchars($user['endereco'] ?? '') ?>" 
                                               maxlength="300">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Tipo de Usuário</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-shield"></i></span>
                                        <input type="text" class="form-control" 
                                               value="<?= ucfirst($user['tipo']) ?>" disabled>
                                    </div>
                                    <small class="text-muted">O tipo de usuário não pode ser alterado</small>
                                </div>

                                <div class="d-flex gap-3 justify-content-end">
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetProfileForm()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Restaurar
                                    </button>
                                    <button type="submit" class="btn btn-primary-custom">
                                        <i class="bi bi-check me-1"></i>Salvar Alterações
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Security Tab -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <form method="POST" id="passwordForm" novalidate>
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Alteração de Senha:</strong> Por segurança, você deve fornecer sua senha atual para criar uma nova.
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Senha Atual *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" name="current_password" id="current_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nova Senha *</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                                <input type="password" class="form-control" name="new_password" id="new_password" required>
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <div id="passwordStrength" class="password-strength"></div>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Confirmar Nova Senha *</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Dicas de Segurança:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Use pelo menos <?= PASSWORD_MIN_LENGTH ?> caracteres</li>
                                        <li>Combine letras maiúsculas e minúsculas</li>
                                        <li>Inclua números e símbolos</li>
                                        <li>Evite informações pessoais óbvias</li>
                                    </ul>
                                </div>

                                <div class="d-flex gap-3 justify-content-end">
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetPasswordForm()">
                                        <i class="bi bi-x me-1"></i>Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-primary-custom">
                                        <i class="bi bi-shield-check me-1"></i>Alterar Senha
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Activity Tab -->
                        <div class="tab-pane fade" id="activity" role="tabpanel">
                            <h6 class="mb-3"><i class="bi bi-clock-history me-2"></i>Histórico de Atividades</h6>
                            
                            <?php if ($user['last_login']): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><i class="bi bi-box-arrow-in-right me-2 text-success"></i>Último Login</strong>
                                        <p class="mb-0 text-muted"><?= date('d/m/Y H:i:s', strtotime($user['last_login'])) ?></p>
                                    </div>
                                    <span class="badge bg-success">Login</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($user['last_logout']): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><i class="bi bi-box-arrow-right me-2 text-info"></i>Último Logout</strong>
                                        <p class="mb-0 text-muted"><?= date('d/m/Y H:i:s', strtotime($user['last_logout'])) ?></p>
                                    </div>
                                    <span class="badge bg-info">Logout</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><i class="bi bi-person-plus me-2 text-primary"></i>Conta Criada</strong>
                                        <p class="mb-0 text-muted"><?= date('d/m/Y H:i:s', strtotime($user['created_at'])) ?></p>
                                    </div>
                                    <span class="badge bg-primary">Registro</span>
                                </div>
                            </div>

                            <?php if ($user['updated_at'] && $user['updated_at'] !== $user['created_at']): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><i class="bi bi-pencil me-2 text-warning"></i>Perfil Atualizado</strong>
                                        <p class="mb-0 text-muted"><?= date('d/m/Y H:i:s', strtotime($user['updated_at'])) ?></p>
                                    </div>
                                    <span class="badge bg-warning">Edição</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="alert alert-info mt-4">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Informações de Sessão:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Sessão atual iniciada em: <?= date('d/m/Y H:i:s') ?></li>
                                    <li>Timeout da sessão: <?= SESSION_TIMEOUT / 60 ?> minutos</li>
                                    <li>IP atual: <?= $_SERVER['REMOTE_ADDR'] ?? 'Não disponível' ?></li>
                                    <li>Navegador: <?= substr($_SERVER['HTTP_USER_AGENT'] ?? 'Não disponível', 0, 100) ?>...</li>
                                </ul>
                            </div>
                        </div>

                        <!-- System Tab (Admin Only) -->
                        <?php if (hasPermission('configuracoes', 'system_settings')): ?>
                        <div class="tab-pane fade" id="system" role="tabpanel">
                            <form method="POST" id="systemForm">
                                <input type="hidden" name="action" value="system_settings">
                                
                                <h6 class="mb-3"><i class="bi bi-gear-wide-connected me-2"></i>Configurações do Sistema</h6>
                                
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Atenção:</strong> Estas configurações afetam todo o sistema. Use com cuidado.
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card border-0 bg-light p-3 mb-3">
                                            <h6><i class="bi bi-info-circle me-2"></i>Informações do Sistema</h6>
                                            <ul class="mb-0">
                                                <li><strong>Versão:</strong> <?= APP_VERSION ?></li>
                                                <li><strong>PHP:</strong> <?= PHP_VERSION ?></li>
                                                <li><strong>Debug:</strong> <?= DEBUG_MODE ? 'Ativado' : 'Desativado' ?></li>
                                                <li><strong>Timezone:</strong> <?= date_default_timezone_get() ?></li>
                                                <li><strong>Uptime:</strong> <?= date('H:i:s') ?></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-0 bg-light p-3 mb-3">
                                            <h6><i class="bi bi-database me-2"></i>Banco de Dados</h6>
                                            <ul class="mb-0">
                                                <li><strong>Host:</strong> <?= DB_HOST ?></li>
                                                <li><strong>Database:</strong> <?= DB_NAME ?></li>
                                                <li><strong>Charset:</strong> <?= DB_CHARSET ?></li>
                                                <li><strong>Status:</strong> <span class="text-success">Conectado</span></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6>Configurações Operacionais</h6>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                               <?= MAINTENANCE_MODE ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="maintenance_mode">
                                            <strong>Modo Manutenção</strong>
                                            <br><small class="text-muted">Ativar para bloquear acesso de usuários durante manutenção</small>
                                        </label>
                                    </div>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="debug_mode" name="debug_mode" 
                                               <?= DEBUG_MODE ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="debug_mode">
                                            <strong>Modo Debug</strong>
                                            <br><small class="text-muted">Ativar para mostrar erros detalhados (apenas desenvolvimento)</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="alert alert-danger">
                                    <i class="bi bi-shield-exclamation me-2"></i>
                                    <strong>Zona de Perigo:</strong> As ações abaixo são irreversíveis.
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-outline-warning me-2" onclick="clearLogs()">
                                            <i class="bi bi-trash me-1"></i>Limpar Logs
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" onclick="resetSystem()">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Reset Sistema
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex gap-3 justify-content-end">
                                    <button type="submit" class="btn btn-primary-custom">
                                        <i class="bi bi-check me-1"></i>Salvar Configurações
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
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
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        // Password strength checker
        document.getElementById('new_password')?.addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            // Length check
            if (password.length >= <?= PASSWORD_MIN_LENGTH ?>) strength++;
            else feedback.push('pelo menos <?= PASSWORD_MIN_LENGTH ?> caracteres');
            
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
        document.getElementById('cpf')?.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            this.value = value;
        });

        document.getElementById('telefone')?.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d{4})(\d)/, '($1) $2-$3');
            } else {
                value = value.replace(/(\d{2})(\d{5})(\d)/, '($1) $2-$3');
            }
            this.value = value;
        });

        // Form validation - Profile
        document.getElementById('profileForm')?.addEventListener('submit', function(e) {
            const nome = document.getElementById('nome');
            const email = document.getElementById('email');
            const cpf = document.getElementById('cpf');
            const telefone = document.getElementById('telefone');
            
            let isValid = true;

            // Reset validations
            [nome, email, cpf, telefone].forEach(field => {
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
            }
        });

        // Form validation - Password
        document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            let isValid = true;

            // Reset validations
            [currentPassword, newPassword, confirmPassword].forEach(field => {
                field.classList.remove('is-invalid');
            });

            // Validate current password
            if (!currentPassword.value.trim()) {
                showFieldError(currentPassword, 'Senha atual é obrigatória');
                isValid = false;
            }

            // Validate new password
            if (!newPassword.value) {
                showFieldError(newPassword, 'Nova senha é obrigatória');
                isValid = false;
            } else if (newPassword.value.length < <?= PASSWORD_MIN_LENGTH ?>) {
                showFieldError(newPassword, 'Nova senha deve ter pelo menos <?= PASSWORD_MIN_LENGTH ?> caracteres');
                isValid = false;
            }

            // Validate password confirmation
            if (!confirmPassword.value) {
                showFieldError(confirmPassword, 'Confirmação de senha é obrigatória');
                isValid = false;
            } else if (newPassword.value !== confirmPassword.value) {
                showFieldError(confirmPassword, 'Senhas não coincidem');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
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

        function resetProfileForm() {
            if (confirm('Tem certeza que deseja restaurar os dados originais?')) {
                document.getElementById('profileForm').reset();
                // Reload the page to restore original values
                window.location.reload();
            }
        }

        function resetPasswordForm() {
            document.getElementById('passwordForm').reset();
            document.getElementById('passwordStrength').textContent = '';
        }

        // System functions (Admin only)
        function clearLogs() {
            if (confirm('Tem certeza que deseja limpar todos os logs do sistema?\n\nEsta ação não pode ser desfeita.')) {
                // Implement log clearing logic
                alert('Funcionalidade em desenvolvimento');
            }
        }

        function resetSystem() {
            if (confirm('ATENÇÃO: Tem certeza que deseja resetar o sistema?\n\nEsta ação irá:\n- Limpar todos os logs\n- Resetar configurações\n- Esta ação é IRREVERSÍVEL!\n\nDigite "CONFIRMAR" para continuar:')) {
                const confirmation = prompt('Digite "CONFIRMAR" para prosseguir:');
                if (confirmation === 'CONFIRMAR') {
                    alert('Funcionalidade em desenvolvimento');
                } else {
                    alert('Reset cancelado');
                }
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
    </script>
</body>
</html>