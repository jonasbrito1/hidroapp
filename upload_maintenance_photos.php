<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';
require_once 'photo_functions.php';

header('Content-Type: application/json');

// Verificar login e permissões
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

if (!hasPermission('manutencoes', 'edit')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para fazer upload de fotos']);
    exit;
}

try {
    $manutencao_id = (int)($_POST['manutencao_id'] ?? 0);
    $tipo_foto = sanitize($_POST['tipo_foto'] ?? '');
    $descricao = sanitize($_POST['descricao'] ?? '');
    
    if (!$manutencao_id || !$tipo_foto) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não informados']);
        exit;
    }
    
    // Verificar se a manutenção existe e se o usuário pode editá-la
    $manutencao = Database::fetch("SELECT * FROM manutencoes WHERE id = ?", [$manutencao_id]);
    if (!$manutencao) {
        echo json_encode(['success' => false, 'message' => 'Manutenção não encontrada']);
        exit;
    }
    
    // Técnicos só podem editar suas próprias manutenções
    if ($_SESSION['user_type'] === 'tecnico' && $manutencao['tecnico_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Você só pode adicionar fotos em suas próprias manutenções']);
        exit;
    }
    
    // Verificar se há fotos
    if (!isset($_FILES['fotos']) || empty($_FILES['fotos']['name'][0])) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma foto foi selecionada']);
        exit;
    }
    
    // Verificar limite de fotos por tipo
    $fotos_existentes = Database::fetch(
        "SELECT COUNT(*) as total FROM manutencao_fotos WHERE manutencao_id = ? AND tipo_foto = ? AND ativo = 1",
        [$manutencao_id, $tipo_foto]
    )['total'];
    
    if ($fotos_existentes >= 5) {
        echo json_encode(['success' => false, 'message' => 'Máximo de 5 fotos por tipo já atingido']);
        exit;
    }
    
    $fotos_enviadas = count($_FILES['fotos']['name']);
    if ($fotos_existentes + $fotos_enviadas > 5) {
        echo json_encode(['success' => false, 'message' => 'O upload excederia o limite de 5 fotos por tipo']);
        exit;
    }
    
    // Processar upload das fotos
    $fotos_salvas = [];
    $upload_dir = UPLOAD_PATH . '/manutencoes/' . $manutencao_id . '/';
    
    // Criar diretório se não existir
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Erro ao criar diretório de upload');
        }
    }
    
    for ($i = 0; $i < $fotos_enviadas; $i++) {
        $arquivo = [
            'name' => $_FILES['fotos']['name'][$i],
            'type' => $_FILES['fotos']['type'][$i],
            'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
            'error' => $_FILES['fotos']['error'][$i],
            'size' => $_FILES['fotos']['size'][$i]
        ];
        
        // Validar arquivo
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        if ($arquivo['size'] > 5 * 1024 * 1024) { // 5MB
            continue;
        }
        
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'])) {
            continue;
        }
        
        // Gerar nome único
        $nome_arquivo = uniqid('manutencao_' . $manutencao_id . '_') . '.' . $extensao;
        $caminho_completo = $upload_dir . $nome_arquivo;
        $caminho_relativo = 'uploads/manutencoes/' . $manutencao_id . '/' . $nome_arquivo;
        
        // Mover arquivo
        if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
            // Salvar no banco
            Database::query(
                "INSERT INTO manutencao_fotos (manutencao_id, tipo_foto, caminho_arquivo, nome_original, descricao, uploaded_by, data_upload, ativo) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)",
                [$manutencao_id, $tipo_foto, $caminho_relativo, $arquivo['name'], $descricao, $_SESSION['user_id']]
            );
            
            $fotos_salvas[] = $nome_arquivo;
        }
    }
    
    if (empty($fotos_salvas)) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma foto foi salva. Verifique os formatos e tamanhos.']);
        exit;
    }
    
    // Log da ação
    logMessage("Upload de " . count($fotos_salvas) . " fotos na manutenção {$manutencao_id} por {$_SESSION['user_name']}", 'INFO');
    
    echo json_encode([
        'success' => true, 
        'message' => count($fotos_salvas) . ' foto(s) enviada(s) com sucesso!',
        'fotos_salvas' => count($fotos_salvas)
    ]);
    
} catch (Exception $e) {
    logMessage('Erro no upload de fotos de manutenção: ' . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>