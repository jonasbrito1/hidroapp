<?php
session_start();
require_once 'config.php';
require_once 'db.php';

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

header('Content-Type: application/json');

try {
    $type = $_GET['type'] ?? '';
    
    if ($type === 'materiais') {
        $materiais = Database::fetchAll(
            "SELECT id, codigo, nome, categoria, unidade_medida, preco_unitario, estoque_atual 
             FROM pecas_materiais 
             WHERE ativo = 1 
             ORDER BY categoria, nome"
        );
        echo json_encode($materiais ?: []);
        
    } elseif ($type === 'servicos') {
        $servicos = Database::fetchAll(
            "SELECT id, codigo, nome, categoria, tipo_equipamento, tempo_estimado, prioridade_default 
             FROM tipos_manutencao 
             WHERE ativo = 1 
             ORDER BY categoria, nome"
        );
        echo json_encode($servicos ?: []);
        
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo inválido']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
?>