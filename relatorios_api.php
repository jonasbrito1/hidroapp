<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Verificar permissão de relatórios
$user_type = $_SESSION['user_type'];
if (!UserPermissions::hasPermission($user_type, 'relatorios', 'view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para acessar relatórios']);
    exit;
}

// Obter ação
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Roteamento de ações
try {
    switch ($action) {
        case 'generate':
            generateReport();
            break;
        case 'export_pdf':
            exportPDF();
            break;
        case 'export_excel':
            exportExcel();
            break;
        case 'export_csv':
            exportCSV();
            break;
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    logMessage('Erro na API de relatórios: ' . $e->getMessage(), 'ERROR', $user_type);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ==========================================================================
// FUNÇÕES DE GERAÇÃO DE RELATÓRIOS
// ==========================================================================

function generateReport() {
    global $user_type;

    $reportType = $_POST['reportType'] ?? '';
    $dataInicio = $_POST['dataInicio'] ?? date('Y-m-d', strtotime('-30 days'));
    $dataFim = $_POST['dataFim'] ?? date('Y-m-d');
    $equipamentoId = $_POST['equipamentoId'] ?? '';
    $tecnicoId = $_POST['tecnicoId'] ?? '';
    $tipoManutencaoId = $_POST['tipoManutencaoId'] ?? '';
    $status = $_POST['status'] ?? '';

    // Log da geração
    logMessage("Gerando relatório: $reportType - Período: $dataInicio a $dataFim", 'INFO', $user_type);

    $data = [];

    switch ($reportType) {
        case 'manutencoes':
            $data = generateManutencoes Report($dataInicio, $dataFim, $equipamentoId, $tecnicoId, $tipoManutencaoId, $status);
            break;
        case 'equipamentos':
            $data = generateEquipamentosReport($dataInicio, $dataFim, $equipamentoId);
            break;
        case 'tecnicos':
            $data = generateTecnicosReport($dataInicio, $dataFim, $tecnicoId);
            break;
        case 'custos':
            $data = generateCustosReport($dataInicio, $dataFim, $equipamentoId, $tecnicoId);
            break;
        default:
            throw new Exception('Tipo de relatório inválido');
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => 'Relatório gerado com sucesso'
    ]);
}

// ==========================================================================
// RELATÓRIO DE MANUTENÇÕES
// ==========================================================================
function generateManutencoesReport($dataInicio, $dataFim, $equipamentoId, $tecnicoId, $tipoManutencaoId, $status) {
    global $user_type;

    // Construir query
    $query = "
        SELECT
            m.id,
            m.data_agendada,
            m.data_conclusao,
            m.status,
            m.observacoes,
            m.tempo_gasto,
            m.custo_total,
            e.codigo as equipamento_codigo,
            e.tipo as equipamento_tipo,
            e.localizacao,
            u.nome as tecnico_nome,
            tm.nome as tipo_manutencao,
            tm.categoria
        FROM manutencoes m
        LEFT JOIN equipamentos e ON m.equipamento_id = e.id
        LEFT JOIN usuarios u ON m.tecnico_id = u.id
        LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
        WHERE m.data_agendada BETWEEN ? AND ?
    ";

    $params = [$dataInicio, $dataFim];

    if ($equipamentoId) {
        $query .= " AND m.equipamento_id = ?";
        $params[] = $equipamentoId;
    }

    if ($tecnicoId && UserPermissions::hasPermission($user_type, 'usuarios', 'view')) {
        $query .= " AND m.tecnico_id = ?";
        $params[] = $tecnicoId;
    } elseif ($user_type === 'tecnico') {
        $query .= " AND m.tecnico_id = ?";
        $params[] = $_SESSION['user_id'];
    }

    if ($tipoManutencaoId) {
        $query .= " AND m.tipo_manutencao_id = ?";
        $params[] = $tipoManutencaoId;
    }

    if ($status) {
        $query .= " AND m.status = ?";
        $params[] = $status;
    }

    $query .= " ORDER BY m.data_agendada DESC";

    $manutencoes = Database::fetchAll($query, $params);

    // Calcular resumo
    $total = count($manutencoes);
    $concluidas = 0;
    $em_andamento = 0;
    $agendadas = 0;
    $canceladas = 0;
    $tempo_total = 0;
    $custo_total = 0;

    foreach ($manutencoes as $m) {
        switch ($m['status']) {
            case 'concluida':
                $concluidas++;
                break;
            case 'em_andamento':
                $em_andamento++;
                break;
            case 'agendada':
                $agendadas++;
                break;
            case 'cancelada':
                $canceladas++;
                break;
        }
        $tempo_total += $m['tempo_gasto'] ?? 0;
        $custo_total += $m['custo_total'] ?? 0;
    }

    // Resumo executivo
    $summary = [
        'total' => [
            'label' => 'Total de Manutenções',
            'value' => $total
        ],
        'concluidas' => [
            'label' => 'Concluídas',
            'value' => $concluidas
        ],
        'em_andamento' => [
            'label' => 'Em Andamento',
            'value' => $em_andamento
        ],
        'custo_total' => [
            'label' => 'Custo Total',
            'value' => 'R$ ' . number_format($custo_total, 2, ',', '.')
        ]
    ];

    // Dados para gráfico (manutenções por mês)
    $chartData = generateChartDataManutencoesMonth($dataInicio, $dataFim, $equipamentoId, $tecnicoId);

    // Dados para gráfico de pizza (status)
    $pieData = [
        'labels' => ['Concluídas', 'Em Andamento', 'Agendadas', 'Canceladas'],
        'datasets' => [[
            'data' => [$concluidas, $em_andamento, $agendadas, $canceladas],
            'backgroundColor' => [
                'rgba(82, 196, 26, 0.8)',
                'rgba(64, 169, 255, 0.8)',
                'rgba(250, 173, 20, 0.8)',
                'rgba(255, 77, 79, 0.8)'
            ],
            'borderColor' => [
                'rgba(82, 196, 26, 1)',
                'rgba(64, 169, 255, 1)',
                'rgba(250, 173, 20, 1)',
                'rgba(255, 77, 79, 1)'
            ],
            'borderWidth' => 2
        ]]
    ];

    // Dados para tabela
    $tableData = [
        'headers' => ['Equipamento', 'Tipo', 'Técnico', 'Data Agendada', 'Status', 'Tempo (min)', 'Custo'],
        'rows' => []
    ];

    foreach ($manutencoes as $m) {
        $tableData['rows'][] = [
            htmlspecialchars($m['equipamento_codigo']),
            htmlspecialchars($m['tipo_manutencao'] ?? 'N/A'),
            htmlspecialchars($m['tecnico_nome'] ?? 'Não atribuído'),
            date('d/m/Y', strtotime($m['data_agendada'])),
            '<span class="badge bg-' . getStatusBadgeClass($m['status']) . '">' . ucfirst(str_replace('_', ' ', $m['status'])) . '</span>',
            $m['tempo_gasto'] ?? '-',
            $m['custo_total'] ? 'R$ ' . number_format($m['custo_total'], 2, ',', '.') : '-'
        ];
    }

    return [
        'summary' => $summary,
        'chartData' => $chartData,
        'pieData' => $pieData,
        'tableData' => $tableData,
        'rawData' => $manutencoes
    ];
}

// ==========================================================================
// RELATÓRIO DE EQUIPAMENTOS
// ==========================================================================
function generateEquipamentosReport($dataInicio, $dataFim, $equipamentoId) {
    $query = "
        SELECT
            e.id,
            e.codigo,
            e.tipo,
            e.localizacao,
            e.status,
            e.data_instalacao,
            COUNT(DISTINCT m.id) as total_manutencoes,
            SUM(CASE WHEN m.status = 'concluida' THEN 1 ELSE 0 END) as manutencoes_concluidas,
            SUM(m.tempo_gasto) as tempo_total_manutencao,
            SUM(m.custo_total) as custo_total_manutencao
        FROM equipamentos e
        LEFT JOIN manutencoes m ON e.id = m.equipamento_id
            AND m.data_agendada BETWEEN ? AND ?
        WHERE 1=1
    ";

    $params = [$dataInicio, $dataFim];

    if ($equipamentoId) {
        $query .= " AND e.id = ?";
        $params[] = $equipamentoId;
    }

    $query .= " GROUP BY e.id ORDER BY e.codigo";

    $equipamentos = Database::fetchAll($query, $params);

    // Calcular resumo
    $total = count($equipamentos);
    $ativos = 0;
    $em_manutencao = 0;
    $total_manutencoes = 0;

    foreach ($equipamentos as $e) {
        if ($e['status'] === 'ativo') $ativos++;
        if ($e['status'] === 'manutencao') $em_manutencao++;
        $total_manutencoes += $e['total_manutencoes'];
    }

    $summary = [
        'total' => ['label' => 'Total de Equipamentos', 'value' => $total],
        'ativos' => ['label' => 'Ativos', 'value' => $ativos],
        'em_manutencao' => ['label' => 'Em Manutenção', 'value' => $em_manutencao],
        'total_manutencoes' => ['label' => 'Total de Manutenções', 'value' => $total_manutencoes]
    ];

    // Gráfico (equipamentos por tipo)
    $tipos = Database::fetchAll("SELECT tipo, COUNT(*) as total FROM equipamentos GROUP BY tipo");
    $chartData = [
        'labels' => array_column($tipos, 'tipo'),
        'datasets' => [[
            'label' => 'Quantidade de Equipamentos',
            'data' => array_column($tipos, 'total'),
            'backgroundColor' => 'rgba(0, 102, 204, 0.8)',
            'borderColor' => 'rgba(0, 102, 204, 1)',
            'borderWidth' => 2
        ]]
    ];

    // Pizza (status dos equipamentos)
    $statusData = Database::fetchAll("SELECT status, COUNT(*) as total FROM equipamentos GROUP BY status");
    $pieData = [
        'labels' => array_map('ucfirst', array_column($statusData, 'status')),
        'datasets' => [[
            'data' => array_column($statusData, 'total'),
            'backgroundColor' => [
                'rgba(82, 196, 26, 0.8)',
                'rgba(250, 173, 20, 0.8)',
                'rgba(255, 77, 79, 0.8)'
            ]
        ]]
    ];

    // Tabela
    $tableData = [
        'headers' => ['Código', 'Tipo', 'Localização', 'Status', 'Manutenções', 'Tempo Total', 'Custo Total'],
        'rows' => []
    ];

    foreach ($equipamentos as $e) {
        $tableData['rows'][] = [
            htmlspecialchars($e['codigo']),
            ucfirst($e['tipo']),
            htmlspecialchars($e['localizacao']),
            '<span class="badge bg-' . getStatusBadgeClass($e['status']) . '">' . ucfirst($e['status']) . '</span>',
            $e['total_manutencoes'],
            ($e['tempo_total_manutencao'] ?? 0) . ' min',
            $e['custo_total_manutencao'] ? 'R$ ' . number_format($e['custo_total_manutencao'], 2, ',', '.') : '-'
        ];
    }

    return [
        'summary' => $summary,
        'chartData' => $chartData,
        'pieData' => $pieData,
        'tableData' => $tableData,
        'rawData' => $equipamentos
    ];
}

// ==========================================================================
// RELATÓRIO DE TÉCNICOS
// ==========================================================================
function generateTecnicosReport($dataInicio, $dataFim, $tecnicoId) {
    global $user_type;

    // Apenas admin pode ver relatório de todos os técnicos
    if ($user_type !== 'admin' && !$tecnicoId) {
        $tecnicoId = $_SESSION['user_id'];
    }

    $query = "
        SELECT
            u.id,
            u.nome,
            u.email,
            COUNT(DISTINCT m.id) as total_manutencoes,
            SUM(CASE WHEN m.status = 'concluida' THEN 1 ELSE 0 END) as manutencoes_concluidas,
            SUM(CASE WHEN m.status = 'em_andamento' THEN 1 ELSE 0 END) as manutencoes_andamento,
            SUM(m.tempo_gasto) as tempo_total,
            AVG(m.tempo_gasto) as tempo_medio,
            SUM(m.custo_total) as custo_total
        FROM usuarios u
        LEFT JOIN manutencoes m ON u.id = m.tecnico_id
            AND m.data_agendada BETWEEN ? AND ?
        WHERE u.tipo = 'tecnico' AND u.ativo = 1
    ";

    $params = [$dataInicio, $dataFim];

    if ($tecnicoId) {
        $query .= " AND u.id = ?";
        $params[] = $tecnicoId;
    }

    $query .= " GROUP BY u.id ORDER BY manutencoes_concluidas DESC";

    $tecnicos = Database::fetchAll($query, $params);

    // Calcular resumo
    $total_tecnicos = count($tecnicos);
    $total_manutencoes_todos = array_sum(array_column($tecnicos, 'manutencoes_concluidas'));
    $tempo_total_todos = array_sum(array_column($tecnicos, 'tempo_total'));
    $custo_total_todos = array_sum(array_column($tecnicos, 'custo_total'));

    $summary = [
        'tecnicos' => ['label' => 'Técnicos Ativos', 'value' => $total_tecnicos],
        'manutencoes' => ['label' => 'Manutenções Concluídas', 'value' => $total_manutencoes_todos],
        'tempo_total' => ['label' => 'Tempo Total', 'value' => round($tempo_total_todos / 60, 1) . ' h'],
        'custo_total' => ['label' => 'Custo Total', 'value' => 'R$ ' . number_format($custo_total_todos, 2, ',', '.')]
    ];

    // Gráfico (manutenções por técnico)
    $chartData = [
        'labels' => array_column($tecnicos, 'nome'),
        'datasets' => [[
            'label' => 'Manutenções Concluídas',
            'data' => array_column($tecnicos, 'manutencoes_concluidas'),
            'backgroundColor' => 'rgba(64, 169, 255, 0.8)',
            'borderColor' => 'rgba(64, 169, 255, 1)',
            'borderWidth' => 2
        ]]
    ];

    // Pizza (distribuição de manutenções)
    $pieData = [
        'labels' => array_slice(array_column($tecnicos, 'nome'), 0, 5),
        'datasets' => [[
            'data' => array_slice(array_column($tecnicos, 'manutencoes_concluidas'), 0, 5),
            'backgroundColor' => [
                'rgba(0, 102, 204, 0.8)',
                'rgba(0, 180, 216, 0.8)',
                'rgba(64, 169, 255, 0.8)',
                'rgba(82, 196, 26, 0.8)',
                'rgba(250, 173, 20, 0.8)'
            ]
        ]]
    ];

    // Tabela
    $tableData = [
        'headers' => ['Técnico', 'Total', 'Concluídas', 'Em Andamento', 'Tempo Total', 'Tempo Médio', 'Custo Total'],
        'rows' => []
    ];

    foreach ($tecnicos as $t) {
        $tableData['rows'][] = [
            htmlspecialchars($t['nome']),
            $t['total_manutencoes'],
            $t['manutencoes_concluidas'],
            $t['manutencoes_andamento'],
            round($t['tempo_total'] / 60, 1) . ' h',
            round($t['tempo_medio'], 0) . ' min',
            $t['custo_total'] ? 'R$ ' . number_format($t['custo_total'], 2, ',', '.') : '-'
        ];
    }

    return [
        'summary' => $summary,
        'chartData' => $chartData,
        'pieData' => $pieData,
        'tableData' => $tableData,
        'rawData' => $tecnicos
    ];
}

// ==========================================================================
// RELATÓRIO DE CUSTOS
// ==========================================================================
function generateCustosReport($dataInicio, $dataFim, $equipamentoId, $tecnicoId) {
    $query = "
        SELECT
            DATE_FORMAT(m.data_agendada, '%Y-%m') as mes,
            COUNT(*) as total_manutencoes,
            SUM(m.custo_total) as custo_total,
            AVG(m.custo_total) as custo_medio,
            SUM(m.tempo_gasto) as tempo_total
        FROM manutencoes m
        WHERE m.data_agendada BETWEEN ? AND ?
    ";

    $params = [$dataInicio, $dataFim];

    if ($equipamentoId) {
        $query .= " AND m.equipamento_id = ?";
        $params[] = $equipamentoId;
    }

    if ($tecnicoId) {
        $query .= " AND m.tecnico_id = ?";
        $params[] = $tecnicoId;
    }

    $query .= " GROUP BY DATE_FORMAT(m.data_agendada, '%Y-%m') ORDER BY mes";

    $custos = Database::fetchAll($query, $params);

    // Resumo
    $custo_total = array_sum(array_column($custos, 'custo_total'));
    $total_manutencoes = array_sum(array_column($custos, 'total_manutencoes'));
    $custo_medio = $total_manutencoes > 0 ? $custo_total / $total_manutencoes : 0;

    $summary = [
        'custo_total' => ['label' => 'Custo Total', 'value' => 'R$ ' . number_format($custo_total, 2, ',', '.')],
        'manutencoes' => ['label' => 'Manutenções', 'value' => $total_manutencoes],
        'custo_medio' => ['label' => 'Custo Médio', 'value' => 'R$ ' . number_format($custo_medio, 2, ',', '.')],
        'economia' => ['label' => 'Economia Estimada', 'value' => 'R$ ' . number_format($custo_total * 0.2, 2, ',', '.')]
    ];

    // Gráfico
    $chartData = [
        'labels' => array_map(function($mes) {
            return date('M/Y', strtotime($mes . '-01'));
        }, array_column($custos, 'mes')),
        'datasets' => [[
            'label' => 'Custo Total (R$)',
            'data' => array_column($custos, 'custo_total'),
            'backgroundColor' => 'rgba(250, 173, 20, 0.8)',
            'borderColor' => 'rgba(250, 173, 20, 1)',
            'borderWidth' => 2
        ]]
    ];

    // Pizza (distribuição de custos por mês)
    $pieData = [
        'labels' => array_slice(array_map(function($mes) {
            return date('M/Y', strtotime($mes . '-01'));
        }, array_column($custos, 'mes')), -6),
        'datasets' => [[
            'data' => array_slice(array_column($custos, 'custo_total'), -6),
            'backgroundColor' => [
                'rgba(0, 102, 204, 0.8)',
                'rgba(0, 180, 216, 0.8)',
                'rgba(64, 169, 255, 0.8)',
                'rgba(82, 196, 26, 0.8)',
                'rgba(250, 173, 20, 0.8)',
                'rgba(255, 77, 79, 0.8)'
            ]
        ]]
    ];

    // Tabela
    $tableData = [
        'headers' => ['Mês', 'Manutenções', 'Custo Total', 'Custo Médio', 'Tempo Total'],
        'rows' => []
    ];

    foreach ($custos as $c) {
        $tableData['rows'][] = [
            date('M/Y', strtotime($c['mes'] . '-01')),
            $c['total_manutencoes'],
            'R$ ' . number_format($c['custo_total'], 2, ',', '.'),
            'R$ ' . number_format($c['custo_medio'], 2, ',', '.'),
            round($c['tempo_total'] / 60, 1) . ' h'
        ];
    }

    return [
        'summary' => $summary,
        'chartData' => $chartData,
        'pieData' => $pieData,
        'tableData' => $tableData,
        'rawData' => $custos
    ];
}

// ==========================================================================
// FUNÇÕES AUXILIARES
// ==========================================================================

function generateChartDataManutencoesMonth($dataInicio, $dataFim, $equipamentoId, $tecnicoId) {
    $query = "
        SELECT
            DATE_FORMAT(data_agendada, '%Y-%m') as mes,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas
        FROM manutencoes
        WHERE data_agendada BETWEEN ? AND ?
    ";

    $params = [$dataInicio, $dataFim];

    if ($equipamentoId) {
        $query .= " AND equipamento_id = ?";
        $params[] = $equipamentoId;
    }

    if ($tecnicoId) {
        $query .= " AND tecnico_id = ?";
        $params[] = $tecnicoId;
    }

    $query .= " GROUP BY DATE_FORMAT(data_agendada, '%Y-%m') ORDER BY mes";

    $data = Database::fetchAll($query, $params);

    return [
        'labels' => array_map(function($mes) {
            return date('M/Y', strtotime($mes . '-01'));
        }, array_column($data, 'mes')),
        'datasets' => [
            [
                'label' => 'Total',
                'data' => array_column($data, 'total'),
                'backgroundColor' => 'rgba(0, 102, 204, 0.2)',
                'borderColor' => 'rgba(0, 102, 204, 1)',
                'borderWidth' => 2,
                'fill' => true
            ],
            [
                'label' => 'Concluídas',
                'data' => array_column($data, 'concluidas'),
                'backgroundColor' => 'rgba(82, 196, 26, 0.2)',
                'borderColor' => 'rgba(82, 196, 26, 1)',
                'borderWidth' => 2,
                'fill' => true
            ]
        ]
    ];
}

function getStatusBadgeClass($status) {
    $classes = [
        'agendada' => 'warning',
        'em_andamento' => 'info',
        'concluida' => 'success',
        'cancelada' => 'danger',
        'ativo' => 'success',
        'inativo' => 'secondary',
        'manutencao' => 'warning'
    ];
    return $classes[$status] ?? 'secondary';
}

// ==========================================================================
// EXPORTAÇÕES
// ==========================================================================

function exportPDF() {
    require_once 'vendor/autoload.php';

    // Gerar relatório
    $_POST['action'] = 'generate';
    ob_start();
    generateReport();
    $jsonData = ob_get_clean();
    $reportData = json_decode($jsonData, true);

    if (!$reportData['success']) {
        throw new Exception('Erro ao gerar relatório');
    }

    $data = $reportData['data'];
    $reportType = $_GET['reportType'] ?? '';

    // Configurar mPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 20,
        'margin_bottom' => 20
    ]);

    // Título
    $titulo = getTituloRelatorio($reportType);

    // HTML do PDF
    $html = '
    <style>
        body { font-family: Arial, sans-serif; }
        h1 { color: #0066cc; text-align: center; }
        h2 { color: #004499; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #0066cc; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background: #f8f9fa; }
        .summary { background: #e6f2ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .summary-item { display: inline-block; margin: 10px 20px; }
    </style>

    <h1>' . $titulo . '</h1>
    <p style="text-align: center; color: #666;">Período: ' . date('d/m/Y', strtotime($_GET['dataInicio'])) . ' a ' . date('d/m/Y', strtotime($_GET['dataFim'])) . '</p>

    <h2>Resumo Executivo</h2>
    <div class="summary">';

    foreach ($data['summary'] as $item) {
        $html .= '<div class="summary-item"><strong>' . $item['label'] . ':</strong> ' . $item['value'] . '</div>';
    }

    $html .= '</div><h2>Dados Detalhados</h2><table><thead><tr>';

    foreach ($data['tableData']['headers'] as $header) {
        $html .= '<th>' . $header . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    foreach ($data['tableData']['rows'] as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . strip_tags($cell) . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    $mpdf->WriteHTML($html);

    // Rodapé
    $mpdf->SetFooter('HidroApp - Relatório gerado em ' . date('d/m/Y H:i') . ' | Página {PAGENO} de {nbpg}');

    // Output
    $filename = 'relatorio_' . $reportType . '_' . date('Y-m-d_H-i-s') . '.pdf';
    $mpdf->Output($filename, 'D');
}

function exportExcel() {
    // Implementar exportação Excel (usando PhpSpreadsheet se disponível)
    // Por enquanto, usar CSV como fallback
    exportCSV();
}

function exportCSV() {
    // Gerar relatório
    $_POST['action'] = 'generate';
    ob_start();
    generateReport();
    $jsonData = ob_get_clean();
    $reportData = json_decode($jsonData, true);

    if (!$reportData['success']) {
        throw new Exception('Erro ao gerar relatório');
    }

    $data = $reportData['data'];
    $reportType = $_GET['reportType'] ?? '';

    // Configurar headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_' . $reportType . '_' . date('Y-m-d') . '.csv"');

    // Criar output
    $output = fopen('php://output', 'w');

    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Cabeçalhos
    fputcsv($output, $data['tableData']['headers'], ';');

    // Dados
    foreach ($data['tableData']['rows'] as $row) {
        // Remover HTML
        $cleanRow = array_map('strip_tags', $row);
        fputcsv($output, $cleanRow, ';');
    }

    fclose($output);
}

function getTituloRelatorio($type) {
    $titulos = [
        'manutencoes' => 'Relatório de Manutenções',
        'equipamentos' => 'Relatório de Equipamentos',
        'tecnicos' => 'Relatório de Técnicos',
        'custos' => 'Relatório de Custos'
    ];
    return $titulos[$type] ?? 'Relatório';
}
