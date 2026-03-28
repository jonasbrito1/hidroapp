<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';
require_once 'photo_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$equipamento_id = (int)($_GET['id'] ?? 0);
if (!$equipamento_id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Verificar permissões
    if (!hasPermission('equipamentos', 'view')) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        exit;
    }
   
    // Buscar dados do equipamento
    $equipamento = Database::fetch(
        "SELECT * FROM equipamentos WHERE id = ?",
        [$equipamento_id]
    );
   
    if (!$equipamento) {
        echo json_encode(['success' => false, 'message' => 'Equipamento não encontrado']);
        exit;
    }
   
    // Buscar fotos do equipamento usando a função correta
    $fotos = getEquipmentPhotosWithDetails($equipamento_id);
   
    // Buscar manutenções recentes
    $manutencoes = Database::fetchAll(
        "SELECT m.*, u.nome as tecnico_nome
         FROM manutencoes m
         LEFT JOIN usuarios u ON m.tecnico_id = u.id
         WHERE m.equipamento_id = ?
         ORDER BY m.data_agendada DESC
         LIMIT 5",
        [$equipamento_id]
    );
    
    // Buscar materiais associados ao equipamento
    $materiais = Database::fetchAll(
        "SELECT em.*, pm.nome, pm.codigo, pm.unidade_medida
         FROM equipamento_materiais em
         LEFT JOIN pecas_materiais pm ON em.material_id = pm.id
         WHERE em.equipamento_id = ?
         ORDER BY em.id DESC",
        [$equipamento_id]
    );
    
    // Buscar serviços associados ao equipamento
    $servicos = Database::fetchAll(
        "SELECT es.*, tm.nome, tm.codigo, tm.descricao
         FROM equipamento_servicos es
         LEFT JOIN tipos_manutencao tm ON es.servico_id = tm.id
         WHERE es.equipamento_id = ?
         ORDER BY es.id DESC",
        [$equipamento_id]
    );
   
    echo json_encode([
        'success' => true,
        'equipamento' => $equipamento,
        'fotos' => $fotos,
        'manutencoes_recentes' => $manutencoes,
        'materiais' => $materiais,
        'servicos' => $servicos,
        'total_fotos' => count($fotos)
    ]);
    
} catch (Exception $e) {
    logMessage('Erro ao buscar detalhes do equipamento: ' . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>