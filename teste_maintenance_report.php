<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

// Simular um usuário admin para teste
$_SESSION['user_id'] = 1;
$_SESSION['user_type'] = 'admin';
$_SESSION['user_name'] = 'Admin Teste';

echo "<h2>Teste de Relatório de Manutenções</h2>";

// Teste 1: Verificar se arquivos existem
$files_to_check = [
    'photo_report_functions.php',
    'maintenance_photo_report_html.php', 
    'photo_functions.php'
];

foreach ($files_to_check as $file) {
    echo "<p><strong>$file:</strong> " . (file_exists($file) ? "✅ Existe" : "❌ Não encontrado") . "</p>";
}

// Teste 2: Verificar mPDF
echo "<p><strong>mPDF:</strong> " . (isMpdfAvailable() ? "✅ Disponível" : "❌ Não disponível") . "</p>";

// Teste 3: Verificar banco
try {
    $total_manutencoes = Database::fetch("SELECT COUNT(*) as total FROM manutencoes")['total'];
    echo "<p><strong>Banco de dados:</strong> ✅ Conectado ($total_manutencoes manutenções)</p>";
} catch (Exception $e) {
    echo "<p><strong>Banco de dados:</strong> ❌ Erro: " . $e->getMessage() . "</p>";
}

// Teste 4: Simular geração de relatório
echo "<h3>Teste de Geração de Relatório</h3>";
try {
    if (file_exists('photo_report_functions.php')) {
        require_once 'photo_report_functions.php';
        echo "<p>✅ Arquivo de funções carregado com sucesso</p>";
        
        // Verificar se a função existe
        if (function_exists('generateMaintenancePhotoReport')) {
            echo "<p>✅ Função generateMaintenancePhotoReport encontrada</p>";
            echo "<p>🎯 <a href='manutencoes.php?generate_photo_report=true' target='_blank'>Clique aqui para testar o relatório</a></p>";
        } else {
            echo "<p>❌ Função generateMaintenancePhotoReport não encontrada</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>❌ Erro ao carregar: " . $e->getMessage() . "</p>";
}
?>