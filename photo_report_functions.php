<?php
/**
 * Funções para geração de relatórios fotográficos
 */

require_once 'photo_functions.php';
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


/**
 * Gera relatório fotográfico de equipamentos
 */
function generateEquipmentPhotoReport($download_pdf = false) {
    try {
        // Capturar filtros aplicados (mesmo código da função original)
        $search = $_GET['search'] ?? '';
        $tipo_filter = $_GET['tipo'] ?? '';
        $status_filter = $_GET['status'] ?? '';
        
        // Construir query com os mesmos filtros da listagem
        $where_conditions = [];
        $params = [];
        
        if ($search) {
            $where_conditions[] = "(e.codigo LIKE ? OR e.localizacao LIKE ? OR e.marca LIKE ? OR e.modelo LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }
        
        if ($tipo_filter) {
            $where_conditions[] = "e.tipo = ?";
            $params[] = $tipo_filter;
        }
        
        if ($status_filter) {
            $where_conditions[] = "e.status = ?";
            $params[] = $status_filter;
        }
        
        // Filtro adicional baseado em permissões do usuário
        if ($_SESSION['user_type'] === 'usuario') {
            $where_conditions[] = "e.status = 'ativo'";
        }
        
        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Buscar equipamentos para o relatório
        $equipamentos = Database::fetchAll(
            "SELECT e.*, COUNT(ef.id) as total_fotos 
             FROM equipamentos e 
             LEFT JOIN equipamento_fotos ef ON e.id = ef.equipamento_id AND ef.ativo = 1
             $where_clause 
             GROUP BY e.id 
             ORDER BY e.tipo, e.codigo",
            $params
        );
        
        // Filtrar dados conforme permissões
        $equipamentos = UserPermissions::filterData($_SESSION['user_type'], 'equipamentos', $equipamentos);
        
        // Buscar fotos para cada equipamento
        foreach ($equipamentos as &$equipamento) {
            $equipamento['fotos'] = getEquipmentPhotos($equipamento['id']);
        }
        
        // Gerar estatísticas
        $stats = generateEquipmentPhotoStats($equipamentos, $search, $tipo_filter, $status_filter);
        
        // Determinar título do relatório
        $titulo = 'RELATÓRIO FOTOGRÁFICO DE EQUIPAMENTOS';
        if ($search || $tipo_filter || $status_filter) {
            $titulo .= ' (Filtrado)';
        }
        
        // Gerar HTML do relatório
        $report_html = generateEquipmentPhotoReportHTML($equipamentos, $titulo, $stats, $download_pdf);
        
        if ($download_pdf && isMpdfAvailable()) {
            generatePDFPhotoReport($report_html, $titulo);
        } else {
            // Exibir HTML (modo visualização ou fallback se mPDF não disponível)
            echo $report_html;
        }
        
    } catch (Exception $e) {
        logMessage('Erro ao gerar relatório fotográfico: ' . $e->getMessage(), 'ERROR');
        
        if ($download_pdf) {
            header('Location: equipamentos.php?error=report_error');
        } else {
            echo '<div class="alert alert-danger">Erro ao gerar relatório. Tente novamente.</div>';
        }
    }
}

/**
 * Gera relatório fotográfico de manutenções
 */
function generateMaintenancePhotoReport($download_pdf = false) {
    try {
        // Incluir arquivo com HTML específico para manutenções
        require_once 'maintenance_photo_report_html.php';
        
        // Filtros para manutenções
        $equipamento_filter = $_GET['equipamento'] ?? '';
        $status_filter = $_GET['status'] ?? '';
        $tipo_filter = $_GET['tipo'] ?? '';
        $prioridade_filter = $_GET['prioridade'] ?? '';
        $data_inicio = $_GET['data_inicio'] ?? '';
        $data_fim = $_GET['data_fim'] ?? '';
        $atribuicao_filter = $_GET['atribuicao'] ?? '';
        
        $where_conditions = [];
        $params = [];
        
        // Filtros específicos por tipo de usuário
        if ($_SESSION['user_type'] === 'tecnico') {
            if ($atribuicao_filter === 'minhas') {
                $where_conditions[] = "m.tecnico_id = ?";
                $params[] = $_SESSION['user_id'];
            } elseif ($atribuicao_filter === 'disponiveis') {
                $where_conditions[] = "m.tecnico_id IS NULL";
            } else {
                $where_conditions[] = "(m.tecnico_id = ? OR m.tecnico_id IS NULL)";
                $params[] = $_SESSION['user_id'];
            }
        }
        
        // Usuários comuns só veem manutenções concluídas
        if ($_SESSION['user_type'] === 'usuario') {
            $where_conditions[] = "m.status IN ('concluida', 'cancelada')";
        }
        
        // Aplicar filtros
        if ($equipamento_filter) {
            $where_conditions[] = "m.equipamento_id = ?";
            $params[] = $equipamento_filter;
        }
        
        if ($status_filter) {
            $where_conditions[] = "m.status = ?";
            $params[] = $status_filter;
        }
        
        if ($tipo_filter) {
            $where_conditions[] = "m.tipo = ?";
            $params[] = $tipo_filter;
        }
        
        if ($prioridade_filter) {
            $where_conditions[] = "m.prioridade = ?";
            $params[] = $prioridade_filter;
        }
        
        if ($data_inicio) {
            $where_conditions[] = "m.data_agendada >= ?";
            $params[] = $data_inicio;
        }
        
        if ($data_fim) {
            $where_conditions[] = "m.data_agendada <= ?";
            $params[] = $data_fim;
        }
        
        // Só incluir manutenções que tenham fotos
        $where_conditions[] = "mf_count.total_fotos > 0";
        
        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Buscar manutenções com contagem de fotos
        $manutencoes = Database::fetchAll(
            "SELECT m.*, 
                    e.codigo as equipamento_codigo, 
                    e.localizacao as equipamento_localizacao,
                    e.endereco as equipamento_endereco,
                    e.tipo as equipamento_tipo,
                    u.nome as tecnico_nome,
                    mf_count.total_fotos
             FROM manutencoes m 
             LEFT JOIN equipamentos e ON m.equipamento_id = e.id 
             LEFT JOIN usuarios u ON m.tecnico_id = u.id 
             LEFT JOIN (
                 SELECT manutencao_id, COUNT(*) as total_fotos 
                 FROM manutencao_fotos 
                 WHERE ativo = 1 
                 GROUP BY manutencao_id
             ) mf_count ON m.id = mf_count.manutencao_id
             $where_clause 
             ORDER BY 
                CASE m.prioridade 
                    WHEN 'urgente' THEN 1 
                    WHEN 'alta' THEN 2 
                    WHEN 'media' THEN 3 
                    WHEN 'baixa' THEN 4 
                END,
                m.data_agendada DESC",
            $params
        );
        
        // Buscar fotos para cada manutenção
        foreach ($manutencoes as &$manutencao) {
            $manutencao['fotos_antes'] = getMaintenancePhotos($manutencao['id'], 'antes');
            $manutencao['fotos_durante'] = getMaintenancePhotos($manutencao['id'], 'durante');
            $manutencao['fotos_depois'] = getMaintenancePhotos($manutencao['id'], 'depois');
            $manutencao['fotos_problema'] = getMaintenancePhotos($manutencao['id'], 'problema');
            $manutencao['fotos_solucao'] = getMaintenancePhotos($manutencao['id'], 'solucao');
        }
        
        $stats = generateMaintenancePhotoStats($manutencoes);
        
        $titulo = 'RELATÓRIO FOTOGRÁFICO DE MANUTENÇÕES';
        
        // Usar a função HTML específica para manutenções
        $report_html = generateMaintenancePhotoReportHTML($manutencoes, $titulo, $stats, $download_pdf);
        
        if ($download_pdf && isMpdfAvailable()) {
            generatePDFPhotoReport($report_html, $titulo);
        } else {
            echo $report_html;
        }
        
    } catch (Exception $e) {
        logMessage('Erro ao gerar relatório fotográfico de manutenções: ' . $e->getMessage(), 'ERROR');
        
        if ($download_pdf) {
            header('Location: manutencoes.php?error=report_error');
        } else {
            echo '<div class="alert alert-danger">Erro ao gerar relatório. Tente novamente.</div>';
        }
    }
}

/**
 * Gera HTML do relatório fotográfico de equipamentos
 */
function generateEquipmentPhotoReportHTML($equipamentos, $title, $stats, $download_pdf = false) {
    $current_date = date('d/m/Y H:i:s');
    $user_name = $_SESSION['user_name'];
    $contract = getActiveContract();
    
    // Gerar URL para download se estiver em modo visualização
    $current_url = $_SERVER['REQUEST_URI'];
    $download_url = $current_url . '&download_pdf=true';
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $download_pdf ? 'Relatório Fotográfico de Equipamentos' : htmlspecialchars($title) ?></title>
        <style>
            <?php if ($download_pdf): ?>
            @page {
                margin: 15mm;
                margin-header: 10mm;
                margin-footer: 10mm;
            }
            body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; }
            <?php else: ?>
            body { font-family: 'Inter', sans-serif; background: #f5f5f5; padding: 20px; font-size: 11pt; }
            .report-container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.1); overflow: hidden; }
            .no-print { display: block; }
            <?php endif; ?>
            
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            .header {
                background: linear-gradient(135deg, #0066cc, #004499);
                color: white;
                padding: 20px;
                text-align: center;
                margin-bottom: 20px;
            }
            
            .company-logo {
                font-size: 24pt;
                font-weight: bold;
                margin-bottom: 8px;
            }
            
            .company-subtitle {
                font-size: 12pt;
                margin-bottom: 15px;
                opacity: 0.9;
            }
            
            .report-title {
                font-size: 18pt;
                font-weight: bold;
                margin: 10px 0;
            }
            
            .report-number {
                font-size: 14pt;
                font-weight: bold;
            }
            
            .contract-info {
                background: #f8f9fa;
                padding: 15px;
                margin: 20px 0;
                border-left: 4px solid #0066cc;
                border-radius: 4px;
            }
            
            .contract-title {
                font-weight: bold;
                color: #0066cc;
                margin-bottom: 8px;
            }
            
            .equipment-section {
                margin: 30px 0;
                page-break-inside: avoid;
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
            }
            
            .equipment-header {
                background: #0066cc;
                color: white;
                padding: 15px;
                font-weight: bold;
                font-size: 14pt;
            }
            
            .equipment-info {
                padding: 15px;
                background: #f8f9fa;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .equipment-photos {
                padding: 15px;
            }
            
            .photo-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-top: 15px;
            }
            
            .photo-item {
                text-align: center;
                border: 1px solid #ddd;
                border-radius: 4px;
                overflow: hidden;
            }
            
            .photo-item img {
                width: 100%;
                height: 200px;
                object-fit: cover;
                display: block;
            }
            
            .photo-caption {
                padding: 8px;
                background: #f8f9fa;
                font-size: 10pt;
                color: #666;
            }
            
            .no-photos {
                text-align: center;
                color: #999;
                font-style: italic;
                padding: 30px;
                background: #f8f9fa;
                border-radius: 4px;
            }
            
            .stats-section {
                background: #f8f9fa;
                padding: 20px;
                margin: 20px 0;
                border-radius: 8px;
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                text-align: center;
            }
            
            .stat-item {
                background: white;
                padding: 15px;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            
            .stat-number {
                font-size: 24pt;
                font-weight: bold;
                color: #0066cc;
                display: block;
            }
            
            .stat-label {
                font-size: 10pt;
                color: #666;
                text-transform: uppercase;
                margin-top: 5px;
            }
            
            .footer {
                margin-top: 40px;
                padding: 20px;
                text-align: center;
                background: #f8f9fa;
                border-top: 3px solid #0066cc;
            }
            
            <?php if (!$download_pdf): ?>
            .download-section {
                background: linear-gradient(135deg, #ff6b35, #ff8e53);
                color: white;
                padding: 20px;
                text-align: center;
                margin-bottom: 20px;
            }
            
            .btn-download {
                background: white;
                color: #ff6b35;
                border: none;
                padding: 12px 30px;
                border-radius: 25px;
                font-weight: bold;
                font-size: 14pt;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 10px;
            }
            
            .btn-back {
                background: #0066cc;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 20px;
                font-weight: bold;
                cursor: pointer;
                text-decoration: none;
                margin-left: 15px;
            }
            <?php endif; ?>
            
            @media print {
                .no-print { display: none !important; }
                body { background: white !important; padding: 0 !important; }
            }
        </style>
    </head>
    <body>
        <div class="report-container">
            <?php if (!$download_pdf): ?>
            <!-- Seção de Download -->
            <div class="download-section no-print">
                <h3 style="margin-bottom: 10px;">📸 Relatório Fotográfico Pronto!</h3>
                <p style="margin-bottom: 20px; opacity: 0.9;">
                    Visualize as fotos abaixo ou baixe o PDF completo
                </p>
                <a href="<?= htmlspecialchars($download_url) ?>" class="btn-download">
                    📥 Baixar PDF Fotográfico
                </a>
                <a href="equipamentos.php" class="btn-back">
                    ← Voltar aos Equipamentos
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Cabeçalho -->
            <div class="header">
                <div class="company-logo">🌊 HIDRO EVOLUTION</div>
                <div class="company-subtitle">Sistema de Gestão de Equipamentos Hídricos</div>
                <div class="report-title"><?= htmlspecialchars($title) ?></div>
                <div class="report-number">N° <?= str_pad(date('m'), 2, '0', STR_PAD_LEFT) ?>/<?= date('Y') ?></div>
            </div>
            
            <?php if ($contract): ?>
            <!-- Informações do Contrato -->
            <div class="contract-info">
                <div class="contract-title">CONTRATO N.º <?= htmlspecialchars($contract['numero_contrato']) ?></div>
                <div><strong><?= htmlspecialchars($contract['cliente']) ?></strong></div>
                <div style="margin-top: 10px;">
                    <strong>Serviço:</strong> <?= htmlspecialchars($contract['descricao_servicos']) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Estatísticas -->
            <div class="stats-section">
                <div class="stat-item">
                    <span class="stat-number"><?= count($equipamentos) ?></span>
                    <div class="stat-label">Equipamentos</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= array_sum(array_column($equipamentos, 'total_fotos')) ?></span>
                    <div class="stat-label">Total de Fotos</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= count(array_filter($equipamentos, fn($e) => $e['tipo'] === 'bebedouro')) ?></span>
                    <div class="stat-label">Bebedouros</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= count(array_filter($equipamentos, fn($e) => $e['tipo'] === 'ducha')) ?></span>
                    <div class="stat-label">Duchas</div>
                </div>
            </div>
            
            <!-- Seções dos Equipamentos -->
            <?php $counter = 1; ?>
            <?php foreach ($equipamentos as $equipamento): ?>
                <div class="equipment-section">
                    <div class="equipment-header">
                        <?= strtoupper($equipamento['tipo']) ?> <?= str_pad($counter, 2, '0', STR_PAD_LEFT) ?> 
                        (<?= strtoupper($equipamento['codigo']) ?>)
                    </div>
                    
                    <div class="equipment-info">
                        <div>
                            <strong>End:</strong> <?= htmlspecialchars($equipamento['localizacao']) ?>
                            <?php if ($equipamento['endereco']): ?>
                                <br><?= htmlspecialchars($equipamento['endereco']) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong>Data de Cadastro:</strong> <?= date('d/m/Y', strtotime($equipamento['created_at'])) ?><br>
                            <strong>Status:</strong> <?= ucfirst($equipamento['status']) ?><br>
                            <?php if ($equipamento['marca'] || $equipamento['modelo']): ?>
                                <strong>Marca/Modelo:</strong> <?= htmlspecialchars($equipamento['marca'] . ' ' . $equipamento['modelo']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="equipment-photos">
                        <?php if (!empty($equipamento['fotos'])): ?>
                            <div class="photo-grid">
                                <?php foreach ($equipamento['fotos'] as $foto): ?>
                                    <div class="photo-item">
                                        <img src="<?= htmlspecialchars(getPhotoUrl($foto['caminho_arquivo'])) ?>" 
                                             alt="<?= htmlspecialchars($foto['descricao'] ?: 'Foto do equipamento') ?>">
                                        <div class="photo-caption">
                                            <?= ucfirst($foto['tipo_foto']) ?>
                                            <?php if ($foto['descricao']): ?>
                                                <br><small><?= htmlspecialchars($foto['descricao']) ?></small>
                                            <?php endif; ?>
                                            <br><small>📅 <?= date('d/m/Y H:i', strtotime($foto['data_upload'])) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-photos">
                                📷 Nenhuma foto disponível para este equipamento
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php $counter++; ?>
            <?php endforeach; ?>
            
            <?php if (empty($equipamentos)): ?>
                <div class="no-photos" style="margin: 40px 0;">
                    <h3>📋 Nenhum equipamento encontrado</h3>
                    <p>Não há equipamentos que correspondam aos filtros aplicados.</p>
                </div>
            <?php endif; ?>
            
            <!-- Rodapé -->
            <div class="footer">
                <div>
                    <p><strong>Relatório gerado automaticamente pelo sistema HidroApp</strong></p>
                    <p>Data: <?= $current_date ?> | Gerado por: <?= htmlspecialchars($user_name) ?></p>
                    <div style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 15px;">
                        <p><strong>HIDRO EVOLUTION</strong><br>
                        CNPJ: 46.538.607/0001-20<br>
                        Sistema de Gestão de Equipamentos Hídricos</p>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Gera estatísticas para relatório fotográfico de equipamentos
 */
function generateEquipmentPhotoStats($equipamentos, $search = '', $tipo_filter = '', $status_filter = '') {
    return [
        'total' => count($equipamentos),
        'com_fotos' => count(array_filter($equipamentos, fn($eq) => $eq['total_fotos'] > 0)),
        'sem_fotos' => count(array_filter($equipamentos, fn($eq) => $eq['total_fotos'] == 0)),
        'total_fotos' => array_sum(array_column($equipamentos, 'total_fotos')),
        'bebedouros' => count(array_filter($equipamentos, fn($eq) => $eq['tipo'] === 'bebedouro')),
        'duchas' => count(array_filter($equipamentos, fn($eq) => $eq['tipo'] === 'ducha')),
        'ativos' => count(array_filter($equipamentos, fn($eq) => $eq['status'] === 'ativo')),
        'manutencao' => count(array_filter($equipamentos, fn($eq) => $eq['status'] === 'manutencao')),
        'inativos' => count(array_filter($equipamentos, fn($eq) => $eq['status'] === 'inativo'))
    ];
}

/**
 * Gera estatísticas para relatório fotográfico de manutenções
 */
function generateMaintenancePhotoStats($manutencoes) {
    $total_fotos = 0;
    foreach ($manutencoes as $manutencao) {
        $total_fotos += count($manutencao['fotos_antes']) + count($manutencao['fotos_durante']) + count($manutencao['fotos_depois']);
    }
    
    return [
        'total' => count($manutencoes),
        'com_fotos' => count(array_filter($manutencoes, fn($m) => $m['total_fotos'] > 0)),
        'sem_fotos' => count(array_filter($manutencoes, fn($m) => $m['total_fotos'] == 0)),
        'total_fotos' => $total_fotos,
        'preventivas' => count(array_filter($manutencoes, fn($m) => $m['tipo'] === 'preventiva')),
        'corretivas' => count(array_filter($manutencoes, fn($m) => $m['tipo'] === 'corretiva'))
    ];
}

/**
 * Gera PDF do relatório fotográfico
 */
function generatePDFPhotoReport($html_content, $title) {
    if (!isMpdfAvailable()) {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=mpdf_not_available');
        return;
    }
    
    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'default_font' => 'DejaVuSans'
        ]);
        
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor('HidroApp - Sistema de Gestão de Equipamentos');
        $mpdf->SetSubject('Relatório Fotográfico');
        
        $mpdf->WriteHTML($html_content);
        
        $filename = 'relatorio_fotografico_' . date('Y-m-d_H-i-s') . '.pdf';
        $mpdf->Output($filename, 'D');
        
        logMessage("Relatório fotográfico PDF gerado: {$filename} por {$_SESSION['user_name']}", 'INFO');
        
    } catch (Exception $e) {
        logMessage('Erro ao gerar PDF fotográfico: ' . $e->getMessage(), 'ERROR');
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=pdf_generation_failed');
    }
}

/**
 * Função de teste para verificar se tudo está funcionando
 */
function testPhotoReportGeneration() {
    if (DEBUG_MODE && isset($_GET['test_reports'])) {
        echo "<h3>Teste de Relatórios Fotográficos</h3>";
        
        // Teste 1: Verificar mPDF
        echo "<p><strong>mPDF Status:</strong> " . (isMpdfAvailable() ? "✅ Funcionando" : "❌ Não disponível") . "</p>";
        
        // Teste 2: Verificar permissões
        echo "<p><strong>Permissões:</strong> " . (hasPermission('relatorios', 'view') ? "✅ OK" : "❌ Sem permissão") . "</p>";
        
        // Teste 3: Verificar arquivos necessários
        $required_files = ['photo_functions.php', 'maintenance_photo_report_html.php', 'config.php'];
        foreach ($required_files as $file) {
            echo "<p><strong>$file:</strong> " . (file_exists($file) ? "✅ Existe" : "❌ Não encontrado") . "</p>";
        }
        
        // Teste 4: Verificar banco
        try {
            $test_query = Database::fetch("SELECT COUNT(*) as total FROM equipamentos");
            echo "<p><strong>Banco de dados:</strong> ✅ Conectado ({$test_query['total']} equipamentos)</p>";
        } catch (Exception $e) {
            echo "<p><strong>Banco de dados:</strong> ❌ Erro: " . $e->getMessage() . "</p>";
        }
        
        exit;
    }
}

// Executar teste se solicitado
testPhotoReportGeneration();
?>