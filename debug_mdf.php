<?php
require_once 'config.php';

$status = getMpdfStatus();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Status mPDF - HidroApp</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .status { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    </style>
</head>
<body>
    <h1>🔍 Status mPDF - HidroApp</h1>
    
    <div class="status <?= $status['working'] ? 'success' : ($status['installed'] ? 'warning' : 'error') ?>">
        <h3><?= $status['working'] ? '✅' : ($status['installed'] ? '⚠️' : '❌') ?> Status: <?= $status['message'] ?></h3>
        
        <?php if ($status['installed']): ?>
            <p><strong>Versão:</strong> <?= $status['version'] ?></p>
        <?php endif; ?>
        
        <p><strong>Autoloader:</strong> <?= file_exists('vendor/autoload.php') ? '✅ Encontrado' : '❌ Não encontrado' ?></p>
        <p><strong>Classe mPDF:</strong> <?= class_exists('\\Mpdf\\Mpdf') ? '✅ Disponível' : '❌ Não disponível' ?></p>
    </div>
    
    <?php if ($status['working']): ?>
    <div class="status success">
        <h3>🧪 Teste Rápido</h3>
        <a href="test_mpdf.php" target="_blank">Testar Geração de PDF</a>
    </div>
    <?php endif; ?>
    
    <div class="status">
        <h3>📋 Informações do Sistema</h3>
        <p><strong>PHP:</strong> <?= PHP_VERSION ?></p>
        <p><strong>Composer:</strong> <?= file_exists('composer.json') ? '✅ Configurado' : '❌ Não configurado' ?></p>
        <p><strong>Pasta vendor:</strong> <?= is_dir('vendor') ? '✅ Existe' : '❌ Não existe' ?></p>
    </div>
    
    <p><a href="equipamentos.php">← Voltar para Equipamentos</a></p>
</body>
</html>