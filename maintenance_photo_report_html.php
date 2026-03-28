<?php
/**
 * Função específica para gerar HTML do relatório fotográfico de manutenções
 * Baseado no modelo fornecido pelo usuário
 */

function generateMaintenancePhotoReportHTML($manutencoes, $title, $stats, $download_pdf = false) {
    $current_date = date('d/m/Y H:i:s');
    $user_name = $_SESSION['user_name'];
    $contract = getActiveContract();
    
    // Determinar número do relatório baseado no mês/ano
    $report_number = str_pad(date('m'), 2, '0', STR_PAD_LEFT) . '/' . date('Y');
    
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
        <title><?= $download_pdf ? 'Relatório Fotográfico de Manutenções' : htmlspecialchars($title) ?></title>
        <style>
            <?php if ($download_pdf): ?>
            @page {
                margin: 15mm;
                margin-header: 10mm;
                margin-footer: 10mm;
            }
            body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.3; }
            <?php else: ?>
            body { font-family: 'Inter', sans-serif; background: #f5f5f5; padding: 20px; font-size: 11pt; }
            .report-container { max-width: 1400px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.1); overflow: hidden; }
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
                font-size: 12pt;
            }
            
            .maintenance-section {
                margin: 30px 0;
                page-break-inside: avoid;
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
                background: white;
            }
            
            .maintenance-header {
                background: linear-gradient(135deg, #0066cc, #004499);
                color: white;
                padding: 15px;
                font-weight: bold;
                font-size: 14pt;
                text-transform: uppercase;
            }
            
            .maintenance-info {
                padding: 15px;
                background: #f8f9fa;
                font-size: 10pt;
            }
            
            .maintenance-info .info-row {
                margin-bottom: 5px;
            }
            
            .maintenance-photos {
                padding: 15px;
            }
            
            .photos-section {
                margin: 15px 0;
            }
            
            .photos-section-title {
                background: #0066cc;
                color: white;
                padding: 8px 12px;
                font-weight: bold;
                font-size: 11pt;
                margin-bottom: 10px;
                text-align: center;
                text-transform: uppercase;
            }
            
            .photo-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
                margin: 15px 0;
            }
            
            .photo-grid.two-columns {
                grid-template-columns: 1fr 1fr;
            }
            
            .photo-item {
                text-align: center;
                border: 1px solid #ddd;
                border-radius: 4px;
                overflow: hidden;
                background: white;
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
                font-size: 9pt;
                color: #666;
                text-align: center;
            }
            
            .no-photos {
                text-align: center;
                color: #999;
                font-style: italic;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 4px;
                border: 2px dashed #ddd;
            }
            
            .obs-section {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                padding: 10px;
                margin-top: 15px;
            }
            
            .obs-title {
                font-weight: bold;
                color: #856404;
                margin-bottom: 5px;
            }
            
            .tech-signature {
                text-align: center;
                margin-top: 15px;
                padding: 10px;
                background: #e3f2fd;
                border-radius: 4px;
            }
            
            .stats-section {
                background: #f8f9fa;
                padding: 20px;
                margin: 20px 0;
                border-radius: 8px;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
                font-size: 20pt;
                font-weight: bold;
                color: #0066cc;
                display: block;
            }
            
            .stat-label {
                font-size: 9pt;
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
            
            .signature-section {
                margin-top: 30px;
                text-align: center;
                border-top: 1px solid #ccc;
                padding-top: 20px;
            }
            
            .signature-line {
                border-top: 1px solid #333;
                width: 300px;
                margin: 30px auto 10px auto;
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
            
            @media (max-width: 768px) {
                .photo-grid { grid-template-columns: 1fr; }
                .photo-grid.two-columns { grid-template-columns: 1fr; }
                .stats-section { grid-template-columns: repeat(2, 1fr); }
            }
        </style>
    </head>
    <body>
        <div class="report-container">
            <?php if (!$download_pdf): ?>
            <!-- Seção de Download -->
            <div class="download-section no-print">
                <h3 style="margin-bottom: 10px;">📸 Relatório Fotográfico de Manutenções Pronto!</h3>
                <p style="margin-bottom: 20px; opacity: 0.9;">
                    Visualize as fotos das manutenções abaixo ou baixe o PDF completo
                </p>
                <a href="<?= htmlspecialchars($download_url) ?>" class="btn-download">
                    📥 Baixar PDF Fotográfico
                </a>
                <a href="manutencoes.php" class="btn-back">
                    ← Voltar às Manutenções
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Cabeçalho -->
            <div class="header">
                <div class="company-logo">🌊 HIDRO EVOLUTION</div>
                <div class="company-subtitle">Sistema de Gestão de Equipamentos Hídricos</div>
                <div class="report-title"><?= htmlspecialchars($title) ?></div>
                <div class="report-number">N° <?= $report_number ?></div>
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
                    <span class="stat-number"><?= count($manutencoes) ?></span>
                    <div class="stat-label">Manutenções</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $stats['total_fotos'] ?? 0 ?></span>
                    <div class="stat-label">Total de Fotos</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $stats['com_fotos'] ?? 0 ?></span>
                    <div class="stat-label">Com Fotos</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $stats['preventivas'] ?? 0 ?></span>
                    <div class="stat-label">Preventivas</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $stats['corretivas'] ?? 0 ?></span>
                    <div class="stat-label">Corretivas</div>
                </div>
            </div>
            
            <!-- Seções das Manutenções -->
            <?php $counter = 1; ?>
            <?php foreach ($manutencoes as $manutencao): ?>
                <?php if ($manutencao['total_fotos'] > 0): // Só mostrar manutenções com fotos ?>
                <div class="maintenance-section">
                    <div class="maintenance-header">
                        <?= strtoupper($manutencao['equipamento_tipo']) ?> <?= str_pad($counter, 2, '0', STR_PAD_LEFT) ?> 
                        (<?= strtoupper($manutencao['equipamento_codigo']) ?>)
                    </div>
                    
                    <div class="maintenance-info">
                        <div class="info-row">
                            <strong>End:</strong> <?= htmlspecialchars($manutencao['equipamento_localizacao']) ?>
                            <?php if ($manutencao['equipamento_endereco']): ?>
                                <br><?= htmlspecialchars($manutencao['equipamento_endereco']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <strong>Data da Manutenção:</strong> <?= $manutencao['data_realizada'] ? date('d/m/Y', strtotime($manutencao['data_realizada'])) : ($manutencao['data_agendada'] ? date('d/m/Y', strtotime($manutencao['data_agendada'])) : 'Não definida') ?>
                        </div>
                        <div class="info-row">
                            <strong>Responsável Técnico:</strong> <?= strtoupper($manutencao['tecnico_nome'] ?? 'NÃO ATRIBUÍDO') ?>
                        </div>
                        <div class="info-row">
                            <strong>Tipo:</strong> <?= ucfirst($manutencao['tipo']) ?> | 
                            <strong>Prioridade:</strong> <?= ucfirst($manutencao['prioridade']) ?> | 
                            <strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $manutencao['status'])) ?>
                        </div>
                        <?php if ($manutencao['descricao']): ?>
                        <div class="info-row">
                            <strong>Descrição:</strong> <?= htmlspecialchars($manutencao['descricao']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="maintenance-photos">
                        <?php
                        // Organizar fotos por tipo
                        $fotos_por_tipo = [
                            'antes' => $manutencao['fotos_antes'] ?? [],
                            'durante' => $manutencao['fotos_durante'] ?? [],
                            'depois' => $manutencao['fotos_depois'] ?? []
                        ];
                        
                        $tipos_labels = [
                            'antes' => 'ANTES DA MANUTENÇÃO',
                            'durante' => 'DURANTE A MANUTENÇÃO', 
                            'depois' => 'DEPOIS DA MANUTENÇÃO'
                        ];
                        ?>
                        
                        <div class="photo-grid two-columns">
                            <?php foreach ($tipos_labels as $tipo => $label): ?>
                                <?php if (!empty($fotos_por_tipo[$tipo])): ?>
                                    <div class="photos-section">
                                        <div class="photos-section-title"><?= $label ?>:</div>
                                        <?php foreach ($fotos_por_tipo[$tipo] as $foto): ?>
                                            <div class="photo-item" style="margin-bottom: 15px;">
                                                <img src="<?= htmlspecialchars(getPhotoUrl($foto['caminho_arquivo'])) ?>" 
                                                     alt="<?= htmlspecialchars($foto['descricao'] ?: $label) ?>">
                                                <?php if ($foto['descricao']): ?>
                                                <div class="photo-caption">
                                                    <?= htmlspecialchars($foto['descricao']) ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($manutencao['observacoes']): ?>
                        <div class="obs-section">
                            <div class="obs-title">OBS FINAIS:</div>
                            <div><?= htmlspecialchars($manutencao['observacoes']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($manutencao['fotos_antes']) && empty($manutencao['fotos_durante']) && empty($manutencao['fotos_depois'])): ?>
                        <div class="no-photos">
                            📷 Manutenção registrada mas sem documentação fotográfica
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php $counter++; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <?php if (empty($manutencoes) || array_sum(array_column($manutencoes, 'total_fotos')) == 0): ?>
                <div class="no-photos" style="margin: 40px 0; padding: 40px;">
                    <h3>📋 Nenhuma manutenção com fotos encontrada</h3>
                    <p>Não há manutenções com documentação fotográfica que correspondam aos filtros aplicados.</p>
                    <small class="text-muted">💡 Para relatórios completos, incentive os técnicos a documentar fotograficamente as manutenções.</small>
                </div>
            <?php endif; ?>
            
            <!-- Rodapé -->
            <div class="footer">
                <div class="signature-section">
                    <p><strong>Relatório gerado automaticamente pelo sistema HidroApp</strong></p>
                    <p>Data: <?= $current_date ?> | Gerado por: <?= htmlspecialchars($user_name) ?></p>
                    
                    <div class="signature-line"></div>
                    <p><strong>HIDRO EVOLUTION</strong><br>
                    CNPJ: 46.538.607/0001-20<br>
                    Sistema de Gestão de Equipamentos Hídricos</p>
                    
                    <div style="margin-top: 20px;">
                        <small style="color: #666;">Este relatório contém informações confidenciais e deve ser tratado com a devida segurança.</small>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>