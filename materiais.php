<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

$message = '';
$error = '';

// Verificar permissões de acesso
UserPermissions::enforcePageAccess($_SESSION['user_type'], 'materiais.php');

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
        
        if (!hasPermission('materiais', $required_permission)) {
            $error = 'Você não tem permissão para ' . ($action === 'create' ? 'criar' : 'editar') . ' materiais.';
        } else {
            $codigo = sanitize($_POST['codigo'] ?? '');
            $nome = sanitize($_POST['nome'] ?? '');
            $descricao = sanitize($_POST['descricao'] ?? '');
            $categoria = sanitize($_POST['categoria'] ?? 'consumivel');
            $unidade_medida = sanitize($_POST['unidade_medida'] ?? 'UN');
            $preco_unitario = !empty($_POST['preco_unitario']) ? (float)$_POST['preco_unitario'] : 0.00;
            $estoque_minimo = !empty($_POST['estoque_minimo']) ? (int)$_POST['estoque_minimo'] : 0;
            $estoque_atual = !empty($_POST['estoque_atual']) ? (int)$_POST['estoque_atual'] : 0;
            $fornecedor = sanitize($_POST['fornecedor'] ?? '');
            $observacoes = sanitize($_POST['observacoes'] ?? '');
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $id = $_POST['id'] ?? null;
            
            if (empty($codigo) || empty($nome)) {
                $error = 'Campos obrigatórios: Código e Nome.';
            } elseif (!in_array($categoria, ['filtro', 'peca', 'consumivel', 'ferramenta', 'quimico'])) {
                $error = 'Categoria inválida.';
            } elseif ($preco_unitario < 0) {
                $error = 'Preço não pode ser negativo.';
            } elseif ($estoque_minimo < 0 || $estoque_atual < 0) {
                $error = 'Estoque não pode ser negativo.';
            } else {
                try {
                    if ($action === 'create') {
                        $existing = Database::fetch("SELECT id FROM pecas_materiais WHERE codigo = ?", [$codigo]);
                        if ($existing) {
                            $error = 'Código já existe.';
                        } else {
                            Database::query(
                                "INSERT INTO pecas_materiais (codigo, nome, descricao, categoria, unidade_medida, preco_unitario, estoque_minimo, estoque_atual, fornecedor, observacoes, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                                [$codigo, $nome, $descricao, $categoria, $unidade_medida, $preco_unitario, $estoque_minimo, $estoque_atual, $fornecedor, $observacoes, $ativo]
                            );
                            $message = 'Material cadastrado com sucesso!';
                            logMessage("Material criado: {$codigo} por {$_SESSION['user_name']}", 'INFO');
                        }
                    } else {
                        $existing = Database::fetch("SELECT id FROM pecas_materiais WHERE codigo = ? AND id != ?", [$codigo, $id]);
                        if ($existing) {
                            $error = 'Código já existe em outro material.';
                        } else {
                            Database::query(
                                "UPDATE pecas_materiais SET codigo = ?, nome = ?, descricao = ?, categoria = ?, unidade_medida = ?, preco_unitario = ?, estoque_minimo = ?, estoque_atual = ?, fornecedor = ?, observacoes = ?, ativo = ? WHERE id = ?",
                                [$codigo, $nome, $descricao, $categoria, $unidade_medida, $preco_unitario, $estoque_minimo, $estoque_atual, $fornecedor, $observacoes, $ativo, $id]
                            );
                            $message = 'Material atualizado com sucesso!';
                            logMessage("Material atualizado: {$codigo} por {$_SESSION['user_name']}", 'INFO');
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Erro interno. Tente novamente.';
                    logMessage('Erro ao salvar material: ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    } elseif ($action === 'delete') {
        // Verificar permissão para excluir
        if (!hasPermission('materiais', 'delete')) {
            $error = 'Você não tem permissão para excluir materiais.';
        } else {
            $id = $_POST['id'] ?? null;
            if ($id) {
                try {
                    // Verificar se há manutenções usando este material
                    $usage = Database::fetch("SELECT COUNT(*) as total FROM manutencao_materiais WHERE material_id = ?", [$id]);
                    if ($usage['total'] > 0) {
                        $error = 'Não é possível excluir este material pois há manutenções que o utilizam.';
                    } else {
                        // Buscar nome do material para log
                        $material = Database::fetch("SELECT nome FROM pecas_materiais WHERE id = ?", [$id]);
                        
                        Database::query("DELETE FROM pecas_materiais WHERE id = ?", [$id]);
                        $message = 'Material excluído com sucesso!';
                        
                        if ($material) {
                            logMessage("Material excluído: {$material['nome']} por {$_SESSION['user_name']}", 'INFO');
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Erro ao excluir. Verifique se não há dependências.';
                    logMessage('Erro ao excluir material: ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    } elseif ($action === 'update_stock') {
        // Atualizar estoque
        if (!hasPermission('materiais', 'edit')) {
            $error = 'Você não tem permissão para atualizar o estoque.';
        } else {
            $id = $_POST['id'] ?? null;
            $tipo_movimentacao = $_POST['tipo_movimentacao'] ?? '';
            $quantidade = (float)($_POST['quantidade'] ?? 0);
            $observacao = sanitize($_POST['observacao'] ?? '');
            
            if ($id && $quantidade > 0 && in_array($tipo_movimentacao, ['entrada', 'saida'])) {
                try {
                    $material = Database::fetch("SELECT * FROM pecas_materiais WHERE id = ?", [$id]);
                    if ($material) {
                        $novo_estoque = $material['estoque_atual'];
                        
                        if ($tipo_movimentacao === 'entrada') {
                            $novo_estoque += $quantidade;
                        } else {
                            $novo_estoque = max(0, $novo_estoque - $quantidade);
                        }
                        
                        Database::query("UPDATE pecas_materiais SET estoque_atual = ? WHERE id = ?", [$novo_estoque, $id]);
                        
                        // Log da movimentação
                        logMessage("Estoque {$tipo_movimentacao}: {$material['nome']} - Qtd: {$quantidade} por {$_SESSION['user_name']}", 'INFO');
                        
                        $message = 'Estoque atualizado com sucesso!';
                    } else {
                        $error = 'Material não encontrado.';
                    }
                } catch (Exception $e) {
                    $error = 'Erro ao atualizar estoque.';
                    logMessage('Erro ao atualizar estoque: ' . $e->getMessage(), 'ERROR');
                }
            } else {
                $error = 'Dados inválidos para movimentação de estoque.';
            }
        }
    }
}

// Filtros e busca
$search = $_GET['search'] ?? '';
$categoria_filter = $_GET['categoria'] ?? '';
$estoque_filter = $_GET['estoque'] ?? '';
$ativo_filter = $_GET['ativo'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Query base
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(codigo LIKE ? OR nome LIKE ? OR descricao LIKE ? OR fornecedor LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($categoria_filter) {
    $where_conditions[] = "categoria = ?";
    $params[] = $categoria_filter;
}

if ($estoque_filter === 'baixo') {
    $where_conditions[] = "estoque_atual <= estoque_minimo";
} elseif ($estoque_filter === 'zerado') {
    $where_conditions[] = "estoque_atual = 0";
}

if ($ativo_filter !== '') {
    $where_conditions[] = "ativo = ?";
    $params[] = $ativo_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Buscar materiais
$materiais = Database::fetchAll(
    "SELECT * FROM pecas_materiais $where_clause ORDER BY categoria, nome LIMIT $per_page OFFSET $offset",
    $params
);

// Contar total para paginação
$total = Database::fetch(
    "SELECT COUNT(*) as total FROM pecas_materiais $where_clause",
    $params
)['total'];

$total_pages = ceil($total / $per_page);

// Estatísticas
$stats = [
    'total' => Database::fetch("SELECT COUNT(*) as total FROM pecas_materiais")['total'],
    'ativos' => Database::fetch("SELECT COUNT(*) as total FROM pecas_materiais WHERE ativo = 1")['total'],
    'estoque_baixo' => Database::fetch("SELECT COUNT(*) as total FROM pecas_materiais WHERE estoque_atual <= estoque_minimo")['total'],
    'estoque_zerado' => Database::fetch("SELECT COUNT(*) as total FROM pecas_materiais WHERE estoque_atual = 0")['total'],
    'valor_total' => Database::fetch("SELECT SUM(preco_unitario * estoque_atual) as total FROM pecas_materiais WHERE ativo = 1")['total'] ?? 0
];

function getCategoriaIcon($categoria) {
    $icons = [
        'filtro' => 'funnel',
        'peca' => 'gear',
        'consumivel' => 'box-seam',
        'ferramenta' => 'tools',
        'quimico' => 'droplet'
    ];
    return $icons[$categoria] ?? 'box';
}

function getCategoriaColor($categoria) {
    $colors = [
        'filtro' => '#1890ff',
        'peca' => '#52c41a',
        'consumivel' => '#fa8c16',
        'ferramenta' => '#722ed1',
        'quimico' => '#eb2f96'
    ];
    return $colors[$categoria] ?? '#666';
}

function getEstoqueStatus($atual, $minimo) {
    if ($atual == 0) return ['class' => 'bg-danger', 'text' => 'Zerado'];
    if ($atual <= $minimo) return ['class' => 'bg-warning text-dark', 'text' => 'Baixo'];
    return ['class' => 'bg-success', 'text' => 'Normal'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materiais - HidroApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Usar os mesmos estilos do sistema existente - copiado do servicos.php */
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
        
        .material-card {
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
        
        .material-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }
        
        .material-card .card-header {
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--bg-light), var(--bg-white));
            position: relative;
            padding: 1rem;
        }
        
        .material-card .card-body {
            padding: 1rem;
            flex: 1;
        }
        
        .material-card .card-footer {
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

        .estoque-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.8rem;
        }

        .price-tag {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .stock-progress {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .stock-progress-bar {
            height: 100%;
            transition: width 0.3s ease;
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
                <?= UserPermissions::generateSidebar($_SESSION['user_type'], 'materiais.php') ?>
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
                <h4 class="mb-0">Gestão de Materiais</h4>
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
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $stats['total'] ?></h3>
                                <p class="text-muted mb-0">Total Itens</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
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
                
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, #fa8c16 0%, #d46b08 100%);">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $stats['estoque_baixo'] ?></h3>
                                <p class="text-muted mb-0">Estoque Baixo</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                <i class="bi bi-cash"></i>
                            </div>
                            <div>
                                <h3 class="mb-1">R$ <?= number_format($stats['valor_total'], 2, ',', '.') ?></h3>
                                <p class="text-muted mb-0">Valor Total</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters fade-in">
                <form method="GET" class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Buscar Material</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Código, nome, fornecedor..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Categoria</label>
                        <select class="form-select" name="categoria">
                            <option value="">Todas as categorias</option>
                            <option value="filtro" <?= $categoria_filter === 'filtro' ? 'selected' : '' ?>>Filtro</option>
                            <option value="peca" <?= $categoria_filter === 'peca' ? 'selected' : '' ?>>Peça</option>
                            <option value="consumivel" <?= $categoria_filter === 'consumivel' ? 'selected' : '' ?>>Consumível</option>
                            <option value="ferramenta" <?= $categoria_filter === 'ferramenta' ? 'selected' : '' ?>>Ferramenta</option>
                            <option value="quimico" <?= $categoria_filter === 'quimico' ? 'selected' : '' ?>>Químico</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Estoque</label>
                        <select class="form-select" name="estoque">
                            <option value="">Todos</option>
                            <option value="baixo" <?= $estoque_filter === 'baixo' ? 'selected' : '' ?>>Estoque Baixo</option>
                            <option value="zerado" <?= $estoque_filter === 'zerado' ? 'selected' : '' ?>>Zerado</option>
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
                            <a href="materiais.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                            <?php if (hasPermission('materiais', 'create')): ?>
                            <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#materialModal" onclick="openModal('create')">
                                <i class="bi bi-plus me-1"></i>Novo
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Materials Cards -->
            <div class="row">
                <?php if (empty($materiais)): ?>
                    <div class="col-12">
                        <div class="text-center p-5 text-muted">
                            <i class="bi bi-inbox fs-1 mb-3 opacity-50"></i>
                            <h5>Nenhum material encontrado</h5>
                            <p>Não há materiais que correspondam aos filtros aplicados.</p>
                            <?php if (hasPermission('materiais', 'create')): ?>
                            <button class="btn btn-primary-custom mt-2" data-bs-toggle="modal" data-bs-target="#materialModal" onclick="openModal('create')">
                                <i class="bi bi-plus me-1"></i>Cadastrar Primeiro Material
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($materiais as $material): ?>
                        <?php 
                        $estoque_status = getEstoqueStatus($material['estoque_atual'], $material['estoque_minimo']);
                        $percentual_estoque = $material['estoque_minimo'] > 0 ? min(100, ($material['estoque_atual'] / $material['estoque_minimo']) * 100) : 100;
                        ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="material-card fade-in">
                                <div class="categoria-indicator" style="background-color: <?= getCategoriaColor($material['categoria']) ?>;"></div>
                                
                                <div class="estoque-indicator">
                                    <span class="badge <?= $estoque_status['class'] ?> rounded-pill">
                                        <?= $estoque_status['text'] ?>
                                    </span>
                                </div>
                                
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <i class="bi bi-<?= getCategoriaIcon($material['categoria']) ?> me-2" style="color: <?= getCategoriaColor($material['categoria']) ?>;"></i>
                                                <strong><?= htmlspecialchars($material['codigo']) ?></strong>
                                            </h6>
                                            <h5 class="mb-1"><?= htmlspecialchars($material['nome']) ?></h5>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <?php if ($material['preco_unitario'] > 0): ?>
                                            <span class="price-tag">
                                                R$ <?= number_format($material['preco_unitario'], 2, ',', '.') ?>/<?= $material['unidade_medida'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <?php if ($material['descricao']): ?>
                                    <p class="card-text text-muted mb-3">
                                        <?= htmlspecialchars(strlen($material['descricao']) > 80 ? substr($material['descricao'], 0, 80) . '...' : $material['descricao']) ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <div class="row text-center mb-3">
                                        <div class="col-4">
                                            <small class="text-muted d-block">Estoque</small>
                                            <small><strong><?= $material['estoque_atual'] ?></strong></small>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">Mínimo</small>
                                            <small><strong><?= $material['estoque_minimo'] ?></strong></small>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">Unidade</small>
                                            <small><strong><?= $material['unidade_medida'] ?></strong></small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($material['estoque_minimo'] > 0): ?>
                                    <div class="stock-progress">
                                        <div class="stock-progress-bar <?= $material['estoque_atual'] <= $material['estoque_minimo'] ? 'bg-warning' : 'bg-success' ?>" 
                                             style="width: <?= min(100, $percentual_estoque) ?>%;"></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($material['fornecedor']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Fornecedor: </small>
                                        <small><?= htmlspecialchars($material['fornecedor']) ?></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer">
                                    <div class="row g-1">
                                        <div class="col-12 mb-2">
                                            <div class="btn-group w-100">
                                                <button class="btn btn-outline-info btn-sm" 
                                                        onclick="viewMaterial(<?= htmlspecialchars(json_encode($material)) ?>)"
                                                        title="Visualizar">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                
                                                <?php if (hasPermission('materiais', 'edit')): ?>
                                                <button class="btn btn-outline-success btn-sm" 
                                                        onclick="openStockModal(<?= $material['id'] ?>, '<?= htmlspecialchars($material['nome']) ?>', <?= $material['estoque_atual'] ?>)"
                                                        title="Movimentar Estoque">
                                                    <i class="bi bi-arrow-up-down"></i>
                                                </button>
                                                
                                                <button class="btn btn-outline-primary btn-sm" 
                                                        onclick="editMaterial(<?= htmlspecialchars(json_encode($material)) ?>)"
                                                        title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if (hasPermission('materiais', 'delete')): ?>
                                                <button class="btn btn-outline-danger btn-sm" 
                                                        onclick="deleteMaterial(<?= $material['id'] ?>, '<?= htmlspecialchars($material['nome']) ?>')"
                                                        title="Excluir">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <small class="text-muted d-block text-center">
                                                <?= ucfirst($material['categoria']) ?>
                                                <?php if (!$material['ativo']): ?>
                                                    <span class="badge bg-secondary ms-1">Inativo</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
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
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&categoria=<?= urlencode($categoria_filter) ?>&estoque=<?= urlencode($estoque_filter) ?>&ativo=<?= urlencode($ativo_filter) ?>">Anterior</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&categoria=<?= urlencode($categoria_filter) ?>&estoque=<?= urlencode($estoque_filter) ?>&ativo=<?= urlencode($ativo_filter) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&categoria=<?= urlencode($categoria_filter) ?>&estoque=<?= urlencode($estoque_filter) ?>&ativo=<?= urlencode($ativo_filter) ?>">Próximo</a>
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

    <!-- Material Modal -->
    <?php if (hasPermission('materiais', 'create') || hasPermission('materiais', 'edit')): ?>
    <div class="modal fade" id="materialModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Novo Material</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="materialForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="modalAction" value="create">
                        <input type="hidden" name="id" id="modalId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Código *</label>
                                    <input type="text" class="form-control" name="codigo" id="modalCodigo" required maxlength="50">
                                    <small class="text-muted">Código único identificador do material</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Categoria *</label>
                                    <select class="form-select" name="categoria" id="modalCategoria" required>
                                        <option value="">Selecione</option>
                                        <option value="filtro">Filtro</option>
                                        <option value="peca">Peça</option>
                                        <option value="consumivel">Consumível</option>
                                        <option value="ferramenta">Ferramenta</option>
                                        <option value="quimico">Químico</option>
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
                            <textarea class="form-control" name="descricao" id="modalDescricao" rows="3" placeholder="Descreva detalhadamente o material..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Unidade</label>
                                    <select class="form-select" name="unidade_medida" id="modalUnidadeMedida">
                                        <option value="UN">Unidade</option>
                                        <option value="MT">Metro</option>
                                        <option value="LT">Litro</option>
                                        <option value="KG">Quilograma</option>
                                        <option value="CX">Caixa</option>
                                        <option value="PC">Peça</option>
                                        <option value="M2">Metro²</option>
                                        <option value="M3">Metro³</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Preço Unitário (R$)</label>
                                    <input type="number" class="form-control" name="preco_unitario" id="modalPrecoUnitario" 
                                           min="0" step="0.01" value="0.00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Fornecedor</label>
                                    <input type="text" class="form-control" name="fornecedor" id="modalFornecedor" maxlength="200">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Estoque Mínimo</label>
                                    <input type="number" class="form-control" name="estoque_minimo" id="modalEstoqueMinimo" 
                                           min="0" value="0">
                                    <small class="text-muted">Quantidade para alerta de reposição</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Estoque Atual</label>
                                    <input type="number" class="form-control" name="estoque_atual" id="modalEstoqueAtual" 
                                           min="0" value="0">
                                    <small class="text-muted">Quantidade disponível</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" name="observacoes" id="modalObservacoes" rows="2" 
                                      placeholder="Informações adicionais sobre o material..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="ativo" id="modalAtivo" checked>
                                <label class="form-check-label" for="modalAtivo">
                                    Material ativo
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

    <!-- Stock Movement Modal -->
    <?php if (hasPermission('materiais', 'edit')): ?>
    <div class="modal fade" id="stockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-up-down me-2"></i>Movimentação de Estoque</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="stockForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="id" id="stockMaterialId">
                        
                        <div class="mb-3">
                            <label class="form-label">Material</label>
                            <input type="text" class="form-control" id="stockMaterialNome" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Estoque Atual</label>
                            <input type="text" class="form-control" id="stockAtual" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tipo de Movimentação *</label>
                                    <select class="form-select" name="tipo_movimentacao" id="stockTipo" required>
                                        <option value="">Selecione</option>
                                        <option value="entrada">Entrada (+)</option>
                                        <option value="saida">Saída (-)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Quantidade *</label>
                                    <input type="number" class="form-control" name="quantidade" id="stockQuantidade" 
                                           min="0.01" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observação</label>
                            <textarea class="form-control" name="observacao" id="stockObservacao" rows="2" 
                                      placeholder="Motivo da movimentação..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-check me-1"></i>Movimentar
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
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Detalhes do Material</h5>
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
    <?php if (hasPermission('materiais', 'delete')): ?>
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

        <?php if (hasPermission('materiais', 'create') || hasPermission('materiais', 'edit')): ?>
        function openModal(action, data = null) {
            const modal = document.getElementById('materialModal');
            const form = document.getElementById('materialForm');
            
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
                document.getElementById('modalTitle').textContent = 'Novo Material';
                document.getElementById('modalAction').value = 'create';
                document.getElementById('modalSubmit').innerHTML = '<i class="bi bi-check me-1"></i>Salvar';
                document.getElementById('modalId').value = '';
                document.getElementById('modalAtivo').checked = true;
                document.getElementById('modalEstoqueAtual').value = '0';
                document.getElementById('modalEstoqueMinimo').value = '0';
                document.getElementById('modalPrecoUnitario').value = '0.00';
            } else if (action === 'edit' && data) {
                document.getElementById('modalTitle').textContent = 'Editar Material';
                document.getElementById('modalAction').value = 'update';
                document.getElementById('modalSubmit').innerHTML = '<i class="bi bi-check me-1"></i>Atualizar';
                
                // Fill form with data
                document.getElementById('modalCodigo').value = data.codigo || '';
                document.getElementById('modalNome').value = data.nome || '';
                document.getElementById('modalDescricao').value = data.descricao || '';
                document.getElementById('modalCategoria').value = data.categoria || '';
                document.getElementById('modalUnidadeMedida').value = data.unidade_medida || 'UN';
                document.getElementById('modalPrecoUnitario').value = data.preco_unitario || '0.00';
                document.getElementById('modalEstoqueMinimo').value = data.estoque_minimo || '0';
                document.getElementById('modalEstoqueAtual').value = data.estoque_atual || '0';
                document.getElementById('modalFornecedor').value = data.fornecedor || '';
                document.getElementById('modalObservacoes').value = data.observacoes || '';
                document.getElementById('modalAtivo').checked = data.ativo == 1;
                document.getElementById('modalId').value = data.id;
            }
            
            new bootstrap.Modal(modal).show();
        }

        function editMaterial(data) {
            <?php if (hasPermission('materiais', 'edit')): ?>
                openModal('edit', data);
            <?php else: ?>
                alert('Você não tem permissão para editar materiais.');
            <?php endif; ?>
        }
        <?php endif; ?>

        <?php if (hasPermission('materiais', 'edit')): ?>
        function openStockModal(id, nome, estoqueAtual) {
            document.getElementById('stockMaterialId').value = id;
            document.getElementById('stockMaterialNome').value = nome;
            document.getElementById('stockAtual').value = estoqueAtual;
            document.getElementById('stockForm').reset();
            document.getElementById('stockMaterialId').value = id; // Reset limpa, então redefine
            document.getElementById('stockMaterialNome').value = nome;
            document.getElementById('stockAtual').value = estoqueAtual;
            
            new bootstrap.Modal(document.getElementById('stockModal')).show();
        }
        <?php endif; ?>

        function viewMaterial(data) {
            const categoriaLabels = {
                'filtro': 'Filtro',
                'peca': 'Peça',
                'consumivel': 'Consumível',
                'ferramenta': 'Ferramenta',
                'quimico': 'Químico'
            };
            
            const estoqueStatus = data.estoque_atual == 0 ? 'Zerado' : 
                                 data.estoque_atual <= data.estoque_minimo ? 'Baixo' : 'Normal';
            const estoqueClass = data.estoque_atual == 0 ? 'bg-danger' : 
                                data.estoque_atual <= data.estoque_minimo ? 'bg-warning text-dark' : 'bg-success';
            
            const valorTotalEstoque = (parseFloat(data.preco_unitario) * parseFloat(data.estoque_atual)).toFixed(2);
            
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
                                    <tr><td><strong>Fornecedor:</strong></td><td>${data.fornecedor || '-'}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Estoque e Preços</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr><td><strong>Estoque Atual:</strong></td><td><span class="badge ${estoqueClass}">${data.estoque_atual} ${data.unidade_medida}</span></td></tr>
                                    <tr><td><strong>Estoque Mínimo:</strong></td><td>${data.estoque_minimo} ${data.unidade_medida}</td></tr>
                                    <tr><td><strong>Status Estoque:</strong></td><td><span class="badge ${estoqueClass}">${estoqueStatus}</span></td></tr>
                                    <tr><td><strong>Preço Unitário:</strong></td><td>R$ ${parseFloat(data.preco_unitario).toFixed(2).replace('.', ',')}</td></tr>
                                    <tr><td><strong>Valor Total:</strong></td><td><strong>R$ ${valorTotalEstoque.replace('.', ',')}</strong></td></tr>
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
                ${data.observacoes ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Observações</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0">${data.observacoes}</p>
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
                'filtro': '#1890ff',
                'peca': '#52c41a',
                'consumivel': '#fa8c16',
                'ferramenta': '#722ed1',
                'quimico': '#eb2f96'
            };
            return colors[categoria] || '#666';
        }

        <?php if (hasPermission('materiais', 'delete')): ?>
        function deleteMaterial(id, nome) {
            if (confirm(`Tem certeza que deseja excluir o material "${nome}"?\n\nEsta ação não pode ser desfeita.`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        <?php else: ?>
        function deleteMaterial(id, nome) {
            alert('Você não tem permissão para excluir materiais.');
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

        <?php if (hasPermission('materiais', 'create') || hasPermission('materiais', 'edit')): ?>
        // Form validation
        document.getElementById('materialForm').addEventListener('submit', function(e) {
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