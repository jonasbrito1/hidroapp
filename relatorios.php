<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

// Inicializar página com verificações de segurança
initializePage();

// Verificar permissão de acesso a relatórios
$user_type = $_SESSION['user_type'];
if (!UserPermissions::hasPermission($user_type, 'relatorios', 'view')) {
    header('Location: index.php?error=sem_permissao');
    exit;
}

// Obter informações do usuário
$current_user = getCurrentUserInfo();

// Log de acesso
logMessage("Acesso à página de relatórios: {$_SESSION['user_name']} ({$user_type})", 'INFO', $user_type);

// Buscar dados para os filtros
try {
    // Buscar equipamentos para filtro
    $equipamentos = Database::fetchAll("SELECT id, codigo, tipo, localizacao FROM equipamentos ORDER BY codigo");

    // Buscar técnicos para filtro (apenas admin)
    $tecnicos = [];
    if (UserPermissions::hasPermission($user_type, 'usuarios', 'view')) {
        $tecnicos = Database::fetchAll("SELECT id, nome FROM usuarios WHERE tipo = 'tecnico' AND ativo = 1 ORDER BY nome");
    }

    // Buscar tipos de manutenção
    $tipos_manutencao = Database::fetchAll("SELECT id, codigo, nome, categoria FROM tipos_manutencao ORDER BY categoria, nome");

} catch (Exception $e) {
    logMessage('Erro ao buscar dados para filtros: ' . $e->getMessage(), 'ERROR', $user_type);
    $equipamentos = [];
    $tecnicos = [];
    $tipos_manutencao = [];
}

// Definir período padrão (últimos 30 dias)
$data_inicio_default = date('Y-m-d', strtotime('-30 days'));
$data_fim_default = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HidroApp - Relatórios</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS Responsivo -->
    <link href="assets/css/responsive.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .report-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .report-card.selected {
            border-color: var(--primary-color);
            background: rgba(0, 102, 204, 0.05);
        }

        .filter-panel {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-lg);
        }

        .chart-container-wrapper {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-lg);
        }

        .chart-canvas {
            max-height: 400px;
        }

        .stats-summary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .export-buttons {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
        }

        @media (max-width: 767px) {
            .export-buttons {
                flex-direction: column;
            }

            .export-buttons .btn {
                width: 100%;
            }

            .filter-panel {
                padding: var(--spacing-sm);
            }

            .chart-canvas {
                max-height: 300px;
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
                    <small class="opacity-75">Relatórios</small>
                </div>
            </div>
        </div>

        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <?php if (method_exists('UserPermissions', 'generateSidebar')): ?>
                    <?= UserPermissions::generateSidebar($user_type, 'relatorios.php') ?>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
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
                    <li class="nav-item">
                        <a class="nav-link active" href="relatorios.php">
                            <i class="bi bi-graph-up"></i>Relatórios
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center">
                <button class="btn d-md-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h4 class="mb-0">Relatórios</h4>
            </div>

            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i>
                    <span class="d-none d-sm-inline"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="index.php"><i class="bi bi-house me-2"></i>Dashboard</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                </ul>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Título da página -->
            <div class="stats-summary fade-in">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h2 class="mb-2"><i class="bi bi-graph-up-arrow me-2"></i>Relatórios do Sistema</h2>
                        <p class="mb-0 opacity-90">Análises e métricas detalhadas do sistema de manutenção</p>
                    </div>
                    <div class="mt-3 mt-md-0">
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-calendar-event me-1"></i>
                            <?= date('d/m/Y') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tipos de Relatórios -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3"><i class="bi bi-file-earmark-text me-2"></i>Selecione o Tipo de Relatório</h5>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card report-card hover-lift" data-report-type="manutencoes" style="border-left-color: var(--primary-color);">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
                                    <i class="bi bi-tools text-white fs-4"></i>
                                </div>
                                <h6 class="mb-0">Manutenções</h6>
                            </div>
                            <p class="text-muted small mb-0">Relatório completo de manutenções realizadas, agendadas e pendentes</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card report-card hover-lift" data-report-type="equipamentos" style="border-left-color: var(--success-color);">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--success-color), var(--success-light)); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
                                    <i class="bi bi-hdd-stack text-white fs-4"></i>
                                </div>
                                <h6 class="mb-0">Equipamentos</h6>
                            </div>
                            <p class="text-muted small mb-0">Status, localização e histórico de manutenção dos equipamentos</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card report-card hover-lift" data-report-type="tecnicos" style="border-left-color: var(--info-color);">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--info-color), var(--info-light)); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
                                    <i class="bi bi-person-badge text-white fs-4"></i>
                                </div>
                                <h6 class="mb-0">Técnicos</h6>
                            </div>
                            <p class="text-muted small mb-0">Desempenho e produtividade dos técnicos do sistema</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card report-card hover-lift" data-report-type="custos" style="border-left-color: var(--warning-color);">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--warning-color), var(--warning-light)); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
                                    <i class="bi bi-currency-dollar text-white fs-4"></i>
                                </div>
                                <h6 class="mb-0">Custos</h6>
                            </div>
                            <p class="text-muted small mb-0">Análise de custos com manutenções, peças e materiais</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filter-panel fade-in" id="filterPanel" style="display: none;">
                <h5 class="mb-4"><i class="bi bi-funnel me-2"></i>Filtros do Relatório</h5>

                <form id="reportFilterForm">
                    <input type="hidden" id="reportType" name="reportType" value="">

                    <div class="row">
                        <!-- Período -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label-responsive">
                                <i class="bi bi-calendar-range me-1"></i>Data Início
                            </label>
                            <input type="date" class="form-control-responsive" id="dataInicio" name="dataInicio" value="<?= $data_inicio_default ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label-responsive">
                                <i class="bi bi-calendar-check me-1"></i>Data Fim
                            </label>
                            <input type="date" class="form-control-responsive" id="dataFim" name="dataFim" value="<?= $data_fim_default ?>" required>
                        </div>

                        <!-- Equipamento -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label-responsive">
                                <i class="bi bi-hdd-stack me-1"></i>Equipamento
                            </label>
                            <select class="form-control-responsive" id="equipamentoId" name="equipamentoId">
                                <option value="">Todos os equipamentos</option>
                                <?php foreach ($equipamentos as $equip): ?>
                                    <option value="<?= $equip['id'] ?>">
                                        <?= htmlspecialchars($equip['codigo']) ?> - <?= htmlspecialchars($equip['localizacao']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Técnico (apenas para admin) -->
                        <?php if (UserPermissions::hasPermission($user_type, 'usuarios', 'view')): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-responsive">
                                <i class="bi bi-person me-1"></i>Técnico
                            </label>
                            <select class="form-control-responsive" id="tecnicoId" name="tecnicoId">
                                <option value="">Todos os técnicos</option>
                                <?php foreach ($tecnicos as $tec): ?>
                                    <option value="<?= $tec['id'] ?>"><?= htmlspecialchars($tec['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Tipo de Manutenção -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label-responsive">
                                <i class="bi bi-wrench me-1"></i>Tipo de Manutenção
                            </label>
                            <select class="form-control-responsive" id="tipoManutencaoId" name="tipoManutencaoId">
                                <option value="">Todos os tipos</option>
                                <?php
                                $categoria_atual = '';
                                foreach ($tipos_manutencao as $tipo):
                                    if ($categoria_atual != $tipo['categoria']) {
                                        if ($categoria_atual != '') echo '</optgroup>';
                                        echo '<optgroup label="' . ucfirst($tipo['categoria']) . '">';
                                        $categoria_atual = $tipo['categoria'];
                                    }
                                ?>
                                    <option value="<?= $tipo['id'] ?>">
                                        <?= htmlspecialchars($tipo['codigo']) ?> - <?= htmlspecialchars($tipo['nome']) ?>
                                    </option>
                                <?php
                                endforeach;
                                if ($categoria_atual != '') echo '</optgroup>';
                                ?>
                            </select>
                        </div>

                        <!-- Status (para relatório de manutenções) -->
                        <div class="col-md-6 mb-3" id="statusFilter" style="display: none;">
                            <label class="form-label-responsive">
                                <i class="bi bi-flag me-1"></i>Status
                            </label>
                            <select class="form-control-responsive" id="status" name="status">
                                <option value="">Todos os status</option>
                                <option value="agendada">Agendada</option>
                                <option value="em_andamento">Em Andamento</option>
                                <option value="concluida">Concluída</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </div>
                    </div>

                    <!-- Botões de ação -->
                    <div class="export-buttons mt-4">
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-eye me-2"></i>Visualizar Relatório
                        </button>
                        <button type="button" class="btn btn-outline-danger" id="btnExportPDF">
                            <i class="bi bi-file-pdf me-2"></i>Exportar PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" id="btnExportExcel">
                            <i class="bi bi-file-excel me-2"></i>Exportar Excel
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnExportCSV">
                            <i class="bi bi-filetype-csv me-2"></i>Exportar CSV
                        </button>
                        <button type="button" class="btn btn-outline-dark" id="btnPrint">
                            <i class="bi bi-printer me-2"></i>Imprimir
                        </button>
                    </div>
                </form>
            </div>

            <!-- Área de Resultado -->
            <div id="reportResult" style="display: none;">
                <!-- Resumo Executivo -->
                <div class="card-responsive mb-4">
                    <div class="card-header-responsive">
                        <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Resumo Executivo</h5>
                    </div>
                    <div class="card-body-responsive">
                        <div class="row" id="summaryStats">
                            <!-- Preenchido via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="chart-container-wrapper">
                            <h5 class="mb-3"><i class="bi bi-bar-chart me-2"></i>Gráfico Principal</h5>
                            <canvas id="mainChart" class="chart-canvas"></canvas>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-4">
                        <div class="chart-container-wrapper">
                            <h5 class="mb-3"><i class="bi bi-pie-chart me-2"></i>Distribuição</h5>
                            <canvas id="pieChart" class="chart-canvas"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Dados -->
                <div class="card-responsive">
                    <div class="card-header-responsive">
                        <h5 class="mb-0"><i class="bi bi-table me-2"></i>Dados Detalhados</h5>
                    </div>
                    <div class="card-body-responsive">
                        <div class="table-responsive-custom">
                            <table class="table table-hover" id="reportDataTable">
                                <thead class="table-light">
                                    <!-- Preenchido via JavaScript -->
                                </thead>
                                <tbody>
                                    <!-- Preenchido via JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Cards para mobile -->
                        <div class="table-mobile-cards" id="reportDataCards">
                            <!-- Preenchido via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div id="loadingIndicator" style="display: none;" class="text-center py-5">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <p class="mt-3 text-muted">Gerando relatório...</p>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer-area">
            <div class="container-fluid">
                <div class="text-center py-3">
                    <p class="mb-0 text-muted">
                        <small>© Hidro Evolution 2025 - Todos os direitos reservados</small>
                    </p>
                </div>
            </div>
        </footer>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript Responsivo -->
    <script src="assets/js/responsive.js"></script>

    <!-- Script da Página -->
    <script>
        let currentReportType = '';
        let mainChartInstance = null;
        let pieChartInstance = null;

        // Selecionar tipo de relatório
        document.querySelectorAll('.report-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remover seleção anterior
                document.querySelectorAll('.report-card').forEach(c => c.classList.remove('selected'));

                // Adicionar seleção
                this.classList.add('selected');

                // Obter tipo
                currentReportType = this.dataset.reportType;
                document.getElementById('reportType').value = currentReportType;

                // Mostrar filtros
                document.getElementById('filterPanel').style.display = 'block';
                document.getElementById('reportResult').style.display = 'none';

                // Mostrar/ocultar campo de status
                if (currentReportType === 'manutencoes') {
                    document.getElementById('statusFilter').style.display = 'block';
                } else {
                    document.getElementById('statusFilter').style.display = 'none';
                }

                // Scroll para filtros
                document.getElementById('filterPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        // Submeter formulário
        document.getElementById('reportFilterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            generateReport();
        });

        // Função para gerar relatório
        function generateReport() {
            const formData = new FormData(document.getElementById('reportFilterForm'));

            // Mostrar loading
            document.getElementById('loadingIndicator').style.display = 'block';
            document.getElementById('reportResult').style.display = 'none';

            // Fazer requisição
            fetch('relatorios_api.php?action=generate', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReport(data.data);
                } else {
                    showNotification(data.message || 'Erro ao gerar relatório', 'danger');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showNotification('Erro ao gerar relatório', 'danger');
            })
            .finally(() => {
                document.getElementById('loadingIndicator').style.display = 'none';
            });
        }

        // Exibir relatório
        function displayReport(data) {
            document.getElementById('reportResult').style.display = 'block';

            // Preencher resumo
            displaySummary(data.summary);

            // Criar gráficos
            if (data.chartData) {
                createMainChart(data.chartData);
                createPieChart(data.pieData);
            }

            // Preencher tabela
            displayTable(data.tableData);

            // Scroll para resultado
            document.getElementById('reportResult').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Exibir resumo
        function displaySummary(summary) {
            const container = document.getElementById('summaryStats');
            container.innerHTML = '';

            if (!summary) return;

            Object.keys(summary).forEach(key => {
                const col = document.createElement('div');
                col.className = 'col-lg-3 col-md-6 mb-3';
                col.innerHTML = `
                    <div class="text-center p-3 bg-light rounded">
                        <h3 class="text-primary mb-1">${summary[key].value}</h3>
                        <p class="text-muted mb-0 small">${summary[key].label}</p>
                    </div>
                `;
                container.appendChild(col);
            });
        }

        // Criar gráfico principal
        function createMainChart(chartData) {
            const ctx = document.getElementById('mainChart').getContext('2d');

            // Destruir gráfico anterior
            if (mainChartInstance) {
                mainChartInstance.destroy();
            }

            mainChartInstance = new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Criar gráfico de pizza
        function createPieChart(pieData) {
            const ctx = document.getElementById('pieChart').getContext('2d');

            // Destruir gráfico anterior
            if (pieChartInstance) {
                pieChartInstance.destroy();
            }

            pieChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: pieData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Exibir tabela
        function displayTable(tableData) {
            if (!tableData || !tableData.headers || !tableData.rows) return;

            const table = document.getElementById('reportDataTable');
            const thead = table.querySelector('thead');
            const tbody = table.querySelector('tbody');

            // Limpar
            thead.innerHTML = '';
            tbody.innerHTML = '';

            // Criar cabeçalho
            const headerRow = document.createElement('tr');
            tableData.headers.forEach(header => {
                const th = document.createElement('th');
                th.textContent = header;
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);

            // Criar linhas
            tableData.rows.forEach(row => {
                const tr = document.createElement('tr');
                row.forEach(cell => {
                    const td = document.createElement('td');
                    td.innerHTML = cell;
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });

            // Criar cards para mobile
            createMobileCards(tableData);
        }

        // Criar cards para mobile
        function createMobileCards(tableData) {
            const container = document.getElementById('reportDataCards');
            container.innerHTML = '';

            tableData.rows.forEach(row => {
                const card = document.createElement('div');
                card.className = 'table-mobile-card';

                row.forEach((cell, index) => {
                    if (index === 0) {
                        const header = document.createElement('div');
                        header.className = 'table-mobile-card-header';
                        header.textContent = cell;
                        card.appendChild(header);
                    } else {
                        const cardRow = document.createElement('div');
                        cardRow.className = 'table-mobile-card-row';
                        cardRow.innerHTML = `
                            <div class="table-mobile-card-label">${tableData.headers[index]}</div>
                            <div class="table-mobile-card-value">${cell}</div>
                        `;
                        card.appendChild(cardRow);
                    }
                });

                container.appendChild(card);
            });
        }

        // Exportar PDF
        document.getElementById('btnExportPDF').addEventListener('click', function() {
            if (!currentReportType) {
                showNotification('Selecione um tipo de relatório primeiro', 'warning');
                return;
            }

            const formData = new FormData(document.getElementById('reportFilterForm'));
            const params = new URLSearchParams(formData);
            window.open(`relatorios_api.php?action=export_pdf&${params.toString()}`, '_blank');
        });

        // Exportar Excel
        document.getElementById('btnExportExcel').addEventListener('click', function() {
            if (!currentReportType) {
                showNotification('Selecione um tipo de relatório primeiro', 'warning');
                return;
            }

            const formData = new FormData(document.getElementById('reportFilterForm'));
            const params = new URLSearchParams(formData);
            window.location.href = `relatorios_api.php?action=export_excel&${params.toString()}`;
        });

        // Exportar CSV
        document.getElementById('btnExportCSV').addEventListener('click', function() {
            if (!currentReportType) {
                showNotification('Selecione um tipo de relatório primeiro', 'warning');
                return;
            }

            const formData = new FormData(document.getElementById('reportFilterForm'));
            const params = new URLSearchParams(formData);
            window.location.href = `relatorios_api.php?action=export_csv&${params.toString()}`;
        });

        // Imprimir
        document.getElementById('btnPrint').addEventListener('click', function() {
            window.print();
        });
    </script>
</body>
</html>
