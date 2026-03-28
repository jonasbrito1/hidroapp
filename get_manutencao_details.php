<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

header('Content-Type: application/json');

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$manutencao_id = (int)($_GET['id'] ?? 0);

if (!$manutencao_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID da manutenção é obrigatório']);
    exit;
}

try {
    // Verificar permissão para visualizar manutenções
    if (!hasPermission('manutencoes', 'view')) {
        http_response_code(403);
        echo json_encode(['error' => 'Sem permissão para visualizar manutenções']);
        exit;
    }
    
    // Buscar dados da manutenção
    $manutencao = Database::fetch(
        "SELECT m.*, e.codigo as equipamento_codigo, e.tipo as equipamento_tipo
         FROM manutencoes m
         LEFT JOIN equipamentos e ON m.equipamento_id = e.id
         WHERE m.id = ?",
        [$manutencao_id]
    );
    
    if (!$manutencao) {
        http_response_code(404);
        echo json_encode(['error' => 'Manutenção não encontrada']);
        exit;
    }
    
    // Verificar se o técnico pode acessar esta manutenção
    if ($_SESSION['user_type'] === 'tecnico') {
        if ($manutencao['tecnico_id'] && $manutencao['tecnico_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado a esta manutenção']);
            exit;
        }
    }
    
    // Buscar serviços da manutenção
    $servicos = Database::fetchAll(
        "SELECT ms.*, tm.codigo, tm.nome as servico_nome, tm.categoria, tm.tempo_estimado,
                u.nome as executado_por_nome
         FROM manutencao_servicos ms
         LEFT JOIN tipos_manutencao tm ON ms.tipo_manutencao_id = tm.id
         LEFT JOIN usuarios u ON ms.executado_por = u.id
         WHERE ms.manutencao_id = ?
         ORDER BY ms.created_at DESC",
        [$manutencao_id]
    );
    
    // Buscar materiais da manutenção
    $materiais = Database::fetchAll(
        "SELECT mm.*, pm.codigo, pm.nome as material_nome, pm.categoria, pm.unidade_medida
         FROM manutencao_materiais mm
         LEFT JOIN pecas_materiais pm ON mm.material_id = pm.id
         WHERE mm.manutencao_id = ?
         ORDER BY mm.created_at DESC",
        [$manutencao_id]
    );
    
    // Buscar tratativas da manutenção
    $tratativas = Database::fetchAll(
        "SELECT mt.*, u.nome as usuario_nome
         FROM manutencao_tratativas mt
         LEFT JOIN usuarios u ON mt.usuario_id = u.id
         WHERE mt.manutencao_id = ?
         ORDER BY mt.data_tratativa DESC",
        [$manutencao_id]
    );
    
    // Calcular custos
    $custo_materiais = 0;
    $tempo_total = 0;
    
    foreach ($materiais as $material) {
        $custo_materiais += ($material['quantidade_utilizada'] ?: 0) * ($material['preco_unitario'] ?: 0);
    }
    
    foreach ($servicos as $servico) {
        $tempo_total += ($servico['tempo_gasto'] ?: 0) * ($servico['quantidade'] ?: 1);
    }
    
// Buscar fotos da manutenção
    $fotos = Database::fetchAll(
        "SELECT mf.*, u.nome as uploaded_by_name
         FROM manutencao_fotos mf
         LEFT JOIN usuarios u ON mf.uploaded_by = u.id
         WHERE mf.manutencao_id = ? AND mf.ativo = 1
         ORDER BY mf.tipo_foto, mf.data_upload",
        [$manutencao_id]
    );
    
    // Organizar fotos por tipo
    $fotos_organizadas = [
        'antes' => [],
        'durante' => [],
        'depois' => [],
        'problema' => [],
        'solucao' => []
    ];
    
    foreach ($fotos as $foto) {
        $tipo = $foto['tipo_foto'] ?? 'durante';
        if (isset($fotos_organizadas[$tipo])) {
            $fotos_organizadas[$tipo][] = $foto;
        }
    }
    
    $response = [
        'success' => true,
        'manutencao' => $manutencao,
        'servicos' => $servicos,
        'materiais' => $materiais,
        'tratativas' => $tratativas,
        'fotos' => $fotos,
        'fotos_por_tipo' => $fotos_organizadas,
        'resumo' => [
            'custo_materiais' => $custo_materiais,
            'tempo_total' => $tempo_total,
            'total_servicos' => count($servicos),
            'total_materiais' => count($materiais),
            'total_fotos' => count($fotos)
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Erro ao buscar detalhes da manutenção: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}
?>