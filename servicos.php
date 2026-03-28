<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

$message = '';
$error = '';

// Verificar permissões de acesso
UserPermissions::enforcePageAccess($_SESSION['user_type'], 'servicos.php');

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Processamento de ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        // Verificar permissão para criar/editar
        $required_permission = ($action === 'create') ? 'create' : 'edit';
        
        if (!hasPermission('servicos', $required_permission)) {
            $error = 'Você não tem permissão para ' . ($action === 'create' ? 'criar' : 'editar') . ' serviços.';
        } else {
            $codigo = sanitize($_POST['codigo'] ?? '');
            $nome = sanitize($_POST['nome'] ?? '');
            $descricao = sanitize($_POST['descricao'] ?? '');
            $categoria = sanitize($_POST['categoria'] ?? 'manutencao');
            $tipo_equipamento = sanitize($_POST['tipo_equipamento'] ?? 'ambos');
            $unidade_medida = sanitize($_POST['unidade_medida'] ?? 'UN');
            $tempo_estimado = (int)($_POST['tempo_estimado'] ?? 30);
            $prioridade_default = sanitize($_POST['prioridade_default'] ?? 'media');
            $periodicidade_dias = !empty($_POST['periodicidade_dias']) ? (int)$_POST['periodicidade_dias'] : null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $id = $_POST['id'] ?? null;
            
            if (empty($codigo) || empty($nome)) {
                $error = 'Campos obrigatórios: Código e Nome.';
            } elseif (!in_array($categoria, ['limpeza', 'manutencao', 'instalacao', 'inspecao', 'troca'])) {
                $error = 'Categoria inválida.';
            } elseif (!in_array($tipo_equipamento, ['bebedouro', 'ducha', 'ambos'])) {
                $error = 'Tipo de equipamento inválido.';
            } elseif (!in_array($prioridade_default, ['baixa', 'media', 'alta', 'urgente'])) {
                $error = 'Prioridade inválida.';
            } else {
                try {
                    if ($action === 'create') {
                        $existing = Database::fetch("SELECT id FROM tipos_manutencao WHERE codigo = ?", [$codigo]);
                        if ($existing) {
                            $error = 'Código já existe.';
                        } else {
                            Database::query(
                                "INSERT INTO tipos_manutencao (codigo, nome, descricao, categoria, tipo_equipamento, unidade_medida, tempo_estimado, prioridade_default, periodicidade_dias, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                                [$codigo, $nome, $descricao, $categoria, $tipo_equipamento, $unidade_medida, $tempo_estimado, $prioridade_default, $periodicidade_dias, $ativo]
                            );
                            $message = 'Serviço cadastrado com sucesso!';
                            logMessage("Serviço criado: {$codigo} por {$_SESSION['user_name']}", 'INFO');
                        }
                    } else {
                        $existing = Database::fetch("SELECT id FROM tipos_manutencao WHERE codigo = ? AND id != ?", [$codigo, $id]);
                        if ($existing) {
                            $error = 'Código já existe em outro serviço.';
                        } else {
                            Database::query(
                                "UPDATE tipos_manutencao SET codigo = ?, nome = ?, descricao = ?, categoria = ?, tipo_equipamento = ?, unidade_medida = ?, tempo_estimado = ?, prioridade_default = ?, periodicidade_dias = ?, ativo = ? WHERE id = ?",
                                [$codigo, $nome, $descricao, $categoria, $tipo_equipamento, $unidade_medida, $tempo_estimado, $prioridade_default, $periodicidade_dias, $ativo, $id]
                            );
                            $message = 'Serviço atualizado com sucesso!';
                            logMessage("Serviço atualizado: {$codigo} por {$_SESSION['user_name']}", 'INFO');
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Erro interno. Tente novamente.';
                    logMessage('Erro ao salvar serviço: ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    } elseif ($action === 'delete') {
        // Verificar permissão para excluir
        if (!hasPermission('servicos', 'delete')) {
            $error = 'Você não tem permissão para excluir serviços.';
        } else {
            $id = $_POST['id'] ?? null;
            if ($id) {
                try {
                    // Verificar se há manutenções usando este serviço
                    $usage = Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE tipo_manutencao_id = ?", [$id]);
                    if ($usage['total'] > 0) {
                        $error = 'Não é possível excluir este serviço pois há manutenções vinculadas a ele.';
                    } else {
                        // Buscar nome do serviço para log
                        $service = Database::fetch("SELECT nome FROM tipos_manutencao WHERE id = ?", [$id]);
                        
                        Database::query("DELETE FROM tipos_manutencao WHERE id = ?", [$id]);
                        $message = 'Serviço excluído com sucesso!';
                        
                        if ($service) {
                            logMessage("Serviço excluído: {$service['nome']} por {$_SESSION['user_name']}", 'INFO');
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Erro ao excluir. Verifique se não há dependências.';
                    logMessage('Erro ao excluir serviço: ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    }
}

// Filtros e busca
$search = $_GET['search'] ?? '';
$categoria_filter = $_GET['categoria'] ?? '';
$tipo_equipamento_filter = $_GET['tipo_equipamento'] ?? '';
$ativo_filter = $_GET['ativo'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Query base
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(codigo LIKE ? OR nome LIKE ? OR descricao LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($categoria_filter) {
    $where_conditions[] = "categoria = ?";
    $params[] = $categoria_filter;
}

if ($tipo_equipamento_filter) {
    $where_conditions[] = "tipo_equipamento = ? OR tipo_equipamento = 'ambos'";
    $params[] = $tipo_equipamento_filter;
}

if ($ativo_filter !== '') {
    $where_conditions[] = "ativo = ?";
    $params[] = $ativo_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Buscar serviços
$servicos = Database::fetchAll(
    "SELECT * FROM tipos_manutencao $where_clause ORDER BY categoria, nome LIMIT $per_page OFFSET $offset",
    $params
);

// Contar total para paginação
$total = Database::fetch(
    "SELECT COUNT(*) as total FROM tipos_manutencao $where_clause",
    $params
)['total'];

$total_pages = ceil($total / $per_page);

// Estatísticas
$stats = [
    'total' => Database::fetch("SELECT COUNT(*) as total FROM tipos_manutencao")['total'],
    'ativos' => Database::fetch("SELECT COUNT(*) as total FROM tipos_manutencao WHERE ativo = 1")['total'],
    'limpeza' => Database::fetch("SELECT COUNT(*) as total FROM tipos_manutencao WHERE categoria = 'limpeza'")['total'],
    'manutencao' => Database::fetch("SELECT COUNT(*) as total FROM tipos_manutencao WHERE categoria = 'manutencao'")['total'],
    'instalacao' => Database::fetch("SELECT COUNT(*) as total FROM tipos_manutencao WHERE categoria = 'instalacao'")['total'],
    'inspecao' => Database::fetch("SELECT COUNT(*) as total FROM tipos_manutencao WHERE categoria = 'inspecao'")['total']
];

function getCategoriaIcon($categoria) {
    $icons = [
        'limpeza' => 'stars',
        'manutencao' => 'tools',
        'instalacao' => 'plus-circle',
        'inspecao' => 'search',
        'troca' => 'arrow-repeat'
    ];
    return $icons[$categoria] ?? 'gear';
}

function getCategoriaColor($categoria) {
    $colors = [
        'limpeza' => '#52c41a',
        'manutencao' => '#1890ff',
        'instalacao' => '#722ed1',
        'inspecao' => '#fa8c16',
        'troca' => '#eb2f96'
    ];
    return $colors[$categoria] ?? '#666';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serviços - HidroApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Usar os mesmos estilos do sistema existente */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-color: #0066cc;
            --primary-dark: #004499;
            --secondary-color: #00b4d8;
            --text-dark: #1a1a1a;
            --text-gray: #666;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --border-color: #e2e8f0;
            --shadow-light: 0 2px 10px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 20px rgba(0, 0, 0, 0.15);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 280px;
            --header-height: 70px;
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
            box-shadow: var(--shadow-medium);
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
            box-shadow: var(--shadow-medium);
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
        
        .service-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            overflow: hidden;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }
        
        .service-card .card-header {
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--bg-light), var(--bg-white));
            position: relative;
            padding: 1rem;
        }
        
        .service-card .card-body {
            padding: 1rem;
            flex: 1;
        }
        
        .service-card .card-footer {
            padding: 1rem;
            background: transparent;
            border-top: 1px solid var(--border-color);
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
        
        .search-filters {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-medium);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border-bottom: none;
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

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

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

        .categoria-indicator {
            width: 4px;
            height: 100%;
            position: absolute;
            left: 0;
            top: 0;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsividade */
        @media (max-width: 768px) {
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
                <?= UserPermissions::generateSidebar($_SESSION['user_type'], 'servicos.php') ?>
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
                <h4 class="mb-0">Gestão de Serviços</h4>
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
                <div class="alert alert-success alert-dismissible fade show fade-in" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                                <i class="bi bi-gear"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $stats['total'] ?></h3>
                                <p class="text-muted mb-0">Total</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $stats['ativos'] ?></h3>
                                <p class="text-muted mb-0">Ativos</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                <i class="bi bi-stars"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $stats['limpeza'] ?></h3>
                                <p class="text-muted mb-0">Limpeza</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);">
                                <i class="bi bi-tools"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $stats['manutencao'] ?></h3>
                                <p class="text-muted mb-0">Manutenção</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, #722ed1 0%, #531dab 100%);">
                                <i class="bi bi-plus-circle"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $stats['instalacao'] ?></h3>
                                <p class="text-muted mb-0">Instalação</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, #fa8c16 0%, #d46b08 100%);">
                                <i class="bi bi-search"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $stats['inspecao'] ?></h3>
                                <p class="text-muted mb-0">Inspeção</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters fade-in">
                <form method="GET" class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Buscar Serviço</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Código, nome, descrição..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Categoria</label>
                        <select class="form-select" name="categoria">
                            <option value="">Todas as categorias</option>
                            <option value="limpeza" <?= $categoria_filter === 'limpeza' ? 'selected' : '' ?>>Limpeza</option>
                            <option value="manutencao" <?= $categoria_filter === 'manutencao' ? 'selected' : '' ?>>Manutenção</option>
                            <option value="instalacao" <?= $categoria_filter === 'instalacao' ? 'selected' : '' ?>>Instalação</option>
                            <option value="inspecao" <?= $categoria_filter === 'inspecao' ? 'selected' : '' ?>>Inspeção</option>
                            <option value="troca" <?= $categoria_filter === 'troca' ? 'selected' : '' ?>>Troca</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Equipamento</label>
                        <select class="form-select" name="tipo_equipamento">
                            <option value="">Todos os tipos</option>
                            <option value="bebedouro" <?= $tipo_equipamento_filter === 'bebedouro' ? 'selected' : '' ?>>Bebedouro</option>
                            <option value="ducha" <?= $tipo_equipamento_filter === 'ducha' ? 'selected' : '' ?>>Ducha</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="ativo">
                            <option value="">Todos</option>
                            <option value="1" <?= $ativo_filter === '1' ? 'selected' : '' ?>>Ativo</option>
                            <option value="0" <?= $ativo_filter === '0' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-6">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary-custom w-100">
                            <i class="bi bi-funnel"></i>
                        </button>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <a href="servicos.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                            <?php if (hasPermission('servicos', 'create')): ?>
                            <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#serviceModal" onclick="openModal('create')">
                                <i class="bi bi-plus me-1"></i>Novo
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Services Cards -->
            <div class="row">
                <?php if (empty($servicos)): ?>
                    <div class="col-12">
                        <div class="text-center p-5 text-muted">
                            <i class="bi bi-inbox fs-1 mb-3 opacity-50"></i>
                            <h5>Nenhum serviço encontrado</h5>
                            <p>Não há serviços que correspondam aos filtros aplicados.</p>
                            <?php if (hasPermission('servicos', 'create')): ?>
                            <button class="btn btn-primary-custom mt-2" data-bs-toggle="modal" data-bs-target="#serviceModal" onclick="openModal('create')">
                                <i class="bi bi-plus me-1"></i>Cadastrar Primeiro Serviço
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($servicos as $servico): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="service-card fade-in">
                                <div class="categoria-indicator" style="background-color: <?= getCategoriaColor($servico['categoria']) ?>;"></div>
                                
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="bi bi-<?= getCategoriaIcon($servico['categoria']) ?> me-2" style="color: <?= getCategoriaColor($servico['categoria']) ?>;"></i>
                                                <strong><?= htmlspecialchars($servico['codigo']) ?></strong>
                                            </h6>
                                            <h5 class="mb-1"><?= htmlspecialchars($servico['nome']) ?></h5>
                                        </div>
                                        <span class="badge <?= $servico['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $servico['ativo'] ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <p class="card-text text-muted mb-3">
                                        <?= htmlspecialchars(strlen($servico['descricao']) > 80 ? substr($servico['descricao'], 0, 80) . '...' : $servico['descricao']) ?>
                                    </p>
                                    
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <small class="text-muted d-block">Categoria</small>
                                            <small><strong><?= ucfirst($servico['categoria']) ?></strong></small>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">Tempo</small>
                                            <small><strong><?= $servico['tempo_estimado'] ?>min</strong></small>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">Prioridade</small>
                                            <small><strong><?= ucfirst($servico['prioridade_default']) ?></strong></small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($servico['periodicidade_dias']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Periodicidade: </small>
                                        <small><?= $servico['periodicidade_dias'] ?> dias</small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer">
                                    <div class="btn-group w-100">
                                        <button class="btn btn-outline-info btn-sm" 
                                                onclick="viewService(<?= htmlspecialchars(json_encode($servico)) ?>)"
                                                title="Visualizar">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        
                                        <?php if (hasPermission('servicos', 'edit')): ?>
                                        <button class="btn btn-outline-primary btn-sm" 
                                                onclick="editService(<?= htmlspecialchars(json_encode($servico)) ?>)"
                                                title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('servicos', 'delete')): ?>
                                        <button class="btn btn-outline-danger btn-sm" 
                                                onclick="deleteService(<?= $servico['id'] ?>, '<?= htmlspecialchars($servico['nome']) ?>')"
                                                title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center p-3 gap-3">
                    <small class="text-muted text-center">
                        Mostrando <?= ($page - 1) * $per_page + 1 ?> a <?= min($page * $per_page, $total) ?> de <?= $total ?> registros
                    </small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&categoria=<?= urlencode($categoria_filter) ?>&tipo_equipamento=<?= urlencode($tipo_equipamento_filter) ?>&ativo=<?= urlencode($ativo_filter) ?>">Anterior</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&categoria=<?= urlencode($categoria_filter) ?>&tipo_equipamento=<?= urlencode($tipo_equipamento_filter) ?>&ativo=<?= urlencode($ativo_filter) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&categoria=<?= urlencode($categoria_filter) ?>&tipo_equipamento=<?= urlencode($tipo_equipamento_filter) ?>&ativo=<?= urlencode($ativo_filter) ?>">Próximo</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
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

    <!-- Service Modal -->
    <?php if (hasPermission('servicos', 'create') || hasPermission('servicos', 'edit')): ?>
    <div class="modal fade" id="serviceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Novo Serviço</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="serviceForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="modalAction" value="create">
                        <input type="hidden" name="id" id="modalId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Código *</label>
                                    <input type="text" class="form-control" name="codigo" id="modalCodigo" required maxlength="50">
                                    <small class="text-muted">Código único identificador do serviço</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Categoria *</label>
                                    <select class="form-select" name="categoria" id="modalCategoria" required>
                                        <option value="">Selecione</option>
                                        <option value="limpeza">Limpeza</option>
                                        <option value="manutencao">Manutenção</option>
                                        <option value="instalacao">Instalação</option>
                                        <option value="inspecao">Inspeção</option>
                                        <option value="troca">Troca</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" class="form-control" name="nome" id="modalNome" required maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" name="descricao" id="modalDescricao" rows="3" placeholder="Descreva detalhadamente o serviço..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tipo Equipamento</label>
                                    <select class="form-select" name="tipo_equipamento" id="modalTipoEquipamento">
                                        <option value="ambos">Ambos</option>
                                        <option value="bebedouro">Bebedouro</option>
                                        <option value="ducha">Ducha</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Unidade</label>
                                    <select class="form-select" name="unidade_medida" id="modalUnidadeMedida">
                                        <option value="UN">Unidade</option>
                                        <option value="HR">Hora</option>
                                        <option value="MT">Metro</option>
                                        <option value="LT">Litro</option>
                                        <option value="KG">Quilograma</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tempo Estimado (min)</label>
                                    <input type="number" class="form-control" name="tempo_estimado" id="modalTempoEstimado" min="1" max="600" value="30">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prioridade Padrão</label>
                                    <select class="form-select" name="prioridade_default" id="modalPrioridadeDefault">
                                        <option value="baixa">Baixa</option>
                                        <option value="media" selected>Média</option>
                                        <option value="alta">Alta</option>
                                        <option value="urgente">Urgente</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Periodicidade (dias)</label>
                                    <input type="number" class="form-control" name="periodicidade_dias" id="modalPeriodicidadeDias" min="1" max="365" placeholder="Opcional">
                                    <small class="text-muted">Para manutenções preventivas</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="ativo" id="modalAtivo" checked>
                                <label class="form-check-label" for="modalAtivo">
                                    Serviço ativo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom" id="modalSubmit">
                            <i class="bi bi-check me-1"></i>Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Detalhes do Serviço</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewModalBody">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Form -->
    <?php if (hasPermission('servicos', 'delete')): ?>
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>
    <?php endif; ?>

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

        <?php if (hasPermission('servicos', 'create') || hasPermission('servicos', 'edit')): ?>
        function openModal(action, data = null) {
            const modal = document.getElementById('serviceModal');
            const form = document.getElementById('serviceForm');
            
            // Reset form
            form.reset();
            
            // Clear previous validation states
            document.querySelectorAll('.is-invalid').forEach(field => {
                field.classList.remove('is-invalid');
            });
            document.querySelectorAll('.invalid-feedback').forEach(feedback => {
                feedback.remove();
            });
            
            if (action === 'create') {
                document.getElementById('modalTitle').textContent = 'Novo Serviço';
                document.getElementById('modalAction').value = 'create';
                document.getElementById('modalSubmit').innerHTML = '<i class="bi bi-check me-1"></i>Salvar';
                document.getElementById('modalId').value = '';
                document.getElementById('modalAtivo').checked = true;
            } else if (action === 'edit' && data) {
                document.getElementById('modalTitle').textContent = 'Editar Serviço';
                document.getElementById('modalAction').value = 'update';
                document.getElementById('modalSubmit').innerHTML = '<i class="bi bi-check me-1"></i>Atualizar';
                
                // Fill form with data
                document.getElementById('modalCodigo').value = data.codigo || '';
                document.getElementById('modalNome').value = data.nome || '';
                document.getElementById('modalDescricao').value = data.descricao || '';
                document.getElementById('modalCategoria').value = data.categoria || '';
                document.getElementById('modalTipoEquipamento').value = data.tipo_equipamento || 'ambos';
                document.getElementById('modalUnidadeMedida').value = data.unidade_medida || 'UN';
                document.getElementById('modalTempoEstimado').value = data.tempo_estimado || 30;
                document.getElementById('modalPrioridadeDefault').value = data.prioridade_default || 'media';
                document.getElementById('modalPeriodicidadeDias').value = data.periodicidade_dias || '';
                document.getElementById('modalAtivo').checked = data.ativo == 1;
                document.getElementById('modalId').value = data.id;
            }
            
            new bootstrap.Modal(modal).show();
        }

        function editService(data) {
            <?php if (hasPermission('servicos', 'edit')): ?>
                openModal('edit', data);
            <?php else: ?>
                alert('Você não tem permissão para editar serviços.');
            <?php endif; ?>
        }
        <?php endif; ?>

        function viewService(data) {
            const categoriaLabels = {
                'limpeza': 'Limpeza',
                'manutencao': 'Manutenção', 
                'instalacao': 'Instalação',
                'inspecao': 'Inspeção',
                'troca': 'Troca'
            };
            
            const tipoLabels = {
                'bebedouro': 'Bebedouro',
                'ducha': 'Ducha',
                'ambos': 'Ambos'
            };
            
            const prioridadeLabels = {
                'baixa': 'Baixa',
                'media': 'Média',
                'alta': 'Alta',
                'urgente': 'Urgente'
            };
            
            const content = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações Básicas</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr><td><strong>Código:</strong></td><td>${data.codigo}</td></tr>
                                    <tr><td><strong>Nome:</strong></td><td>${data.nome}</td></tr>
                                    <tr><td><strong>Categoria:</strong></td><td><span class="badge" style="background-color: ${getCategoriaColorJS(data.categoria)};">${categoriaLabels[data.categoria] || data.categoria}</span></td></tr>
                                    <tr><td><strong>Status:</strong></td><td><span class="badge ${data.ativo == 1 ? 'bg-success' : 'bg-secondary'}">${data.ativo == 1 ? 'Ativo' : 'Inativo'}</span></td></tr>
                                    <tr><td><strong>Aplicável a:</strong></td><td>${tipoLabels[data.tipo_equipamento] || data.tipo_equipamento}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Parâmetros</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr><td><strong>Unidade:</strong></td><td>${data.unidade_medida}</td></tr>
                                    <tr><td><strong>Tempo Est.:</strong></td><td>${data.tempo_estimado} minutos</td></tr>
                                    <tr><td><strong>Prioridade:</strong></td><td>${prioridadeLabels[data.prioridade_default] || data.prioridade_default}</td></tr>
                                    <tr><td><strong>Periodicidade:</strong></td><td>${data.periodicidade_dias ? data.periodicidade_dias + ' dias' : 'Não definida'}</td></tr>
                                    <tr><td><strong>Criado em:</strong></td><td>${data.created_at ? new Date(data.created_at).toLocaleDateString('pt-BR') : '-'}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                ${data.descricao ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="bi bi-chat-text me-2"></i>Descrição</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0">${data.descricao}</p>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('viewModalBody').innerHTML = content;
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        }
        
        function getCategoriaColorJS(categoria) {
            const colors = {
                'limpeza': '#52c41a',
                'manutencao': '#1890ff',
                'instalacao': '#722ed1',
                'inspecao': '#fa8c16',
                'troca': '#eb2f96'
            };
            return colors[categoria] || '#666';
        }

        <?php if (hasPermission('servicos', 'delete')): ?>
        function deleteService(id, nome) {
            if (confirm(`Tem certeza que deseja excluir o serviço "${nome}"?\n\nEsta ação não pode ser desfeita.`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        <?php else: ?>
        function deleteService(id, nome) {
            alert('Você não tem permissão para excluir serviços.');
        }
        <?php endif; ?>

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            });
        }, 5000);

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

        <?php if (hasPermission('servicos', 'create') || hasPermission('servicos', 'edit')): ?>
        // Form validation
        document.getElementById('serviceForm').addEventListener('submit', function(e) {
            const codigo = document.getElementById('modalCodigo').value.trim();
            const nome = document.getElementById('modalNome').value.trim();
            const categoria = document.getElementById('modalCategoria').value;
            
            // Reset previous validations
            document.querySelectorAll('.is-invalid').forEach(field => {
                field.classList.remove('is-invalid');
            });
            document.querySelectorAll('.invalid-feedback').forEach(feedback => {
                feedback.remove();
            });
            
            let isValid = true;
            
            if (!codigo) {
                showFieldError('modalCodigo', 'Código é obrigatório');
                isValid = false;
            } else if (codigo.length > 50) {
                showFieldError('modalCodigo', 'Código deve ter no máximo 50 caracteres');
                isValid = false;
            }
            
            if (!nome) {
                showFieldError('modalNome', 'Nome é obrigatório');
                isValid = false;
            } else if (nome.length > 100) {
                showFieldError('modalNome', 'Nome deve ter no máximo 100 caracteres');
                isValid = false;
            }
            
            if (!categoria) {
                showFieldError('modalCategoria', 'Categoria é obrigatória');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            } else {
                // Add loading state to submit button
                const submitBtn = document.getElementById('modalSubmit');
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Salvando...';
            }
        });
        
        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            field.classList.add('is-invalid');
            
            let feedback = field.parentNode.querySelector('.invalid-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                field.parentNode.appendChild(feedback);
            }
            feedback.textContent = message;
        }
        <?php endif; ?>
    </script>
</body>
</html>