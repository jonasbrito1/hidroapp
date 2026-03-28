<?php
/**
 * AJAX Handler para Gerenciamento de Fotos - HidroApp
 * Arquivo: ajax_photo_handler.php
 */

session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';
require_once 'photo_functions.php';

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é uma requisição AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Requisição inválida']);
    exit;
}

// Definir tipo de conteúdo
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'upload_equipment_photo':
            handleEquipmentPhotoUpload();
            break;
            
        case 'upload_maintenance_photo':
            handleMaintenancePhotoUpload();
            break;
            
        case 'get_equipment_photos':
            getEquipmentPhotosAjax();
            break;
            
        case 'get_maintenance_photos':
            getMaintenancePhotosAjax();
            break;
            
        case 'delete_equipment_photo':
            deleteEquipmentPhotoAjax();
            break;
            
        case 'delete_maintenance_photo':
            deleteMaintenancePhotoAjax();
            break;
            
        case 'update_photo_description':
            updatePhotoDescription();
            break;
            
        case 'reorder_photos':
            reorderPhotos();
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    logMessage('Erro no AJAX handler: ' . $e->getMessage(), 'ERROR');
}

/**
 * Upload de foto do equipamento via AJAX
 */
function handleEquipmentPhotoUpload() {
    if (!hasPermission('equipamentos', 'edit')) {
        throw new Exception('Sem permissão para fazer upload de fotos');
    }
    
    $equipamento_id = $_POST['equipamento_id'] ?? null;
    $tipo_foto = $_POST['tipo_foto'] ?? 'geral';
    $descricao = sanitize($_POST['descricao'] ?? '');
    
    if (!$equipamento_id) {
        throw new Exception('ID do equipamento é obrigatório');
    }
    
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Arquivo de foto é obrigatório');
    }
    
    // Verificar se equipamento existe
    $equipment = Database::fetch("SELECT codigo FROM equipamentos WHERE id = ?", [$equipamento_id]);
    if (!$equipment) {
        throw new Exception('Equipamento não encontrado');
    }
    
    $result = uploadEquipmentPhoto($equipamento_id, $_FILES['foto'], $tipo_foto, $descricao);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Foto enviada com sucesso!',
            'photo_id' => $result['photo_id'],
            'filename' => $result['filename']
        ]);
    } else {
        throw new Exception($result['error']);
    }
}

/**
 * Upload de foto da manutenção via AJAX
 */
function handleMaintenancePhotoUpload() {
    if (!hasPermission('manutencoes', 'edit')) {
        throw new Exception('Sem permissão para fazer upload de fotos');
    }
    
    $manutencao_id = $_POST['manutencao_id'] ?? null;
    $tipo_foto = $_POST['tipo_foto'] ?? 'durante';
    $descricao = sanitize($_POST['descricao'] ?? '');
    $ordem = (int)($_POST['ordem'] ?? 0);
    
    if (!$manutencao_id) {
        throw new Exception('ID da manutenção é obrigatório');
    }
    
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Arquivo de foto é obrigatório');
    }
    
    // Verificar se manutenção existe e se usuário pode editá-la
    $maintenance = Database::fetch("SELECT tecnico_id FROM manutencoes WHERE id = ?", [$manutencao_id]);
    if (!$maintenance) {
        throw new Exception('Manutenção não encontrada');
    }
    
    // Verificar permissões específicas para técnicos
    if ($_SESSION['user_type'] === 'tecnico') {
        if ($maintenance['tecnico_id'] && $maintenance['tecnico_id'] != $_SESSION['user_id']) {
            throw new Exception('Você só pode adicionar fotos em suas próprias manutenções');
        }
    }
    
    $result = uploadMaintenancePhoto($manutencao_id, $_FILES['foto'], $tipo_foto, $descricao, $ordem);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Foto da manutenção enviada com sucesso!',
            'photo_id' => $result['photo_id'],
            'filename' => $result['filename']
        ]);
    } else {
        throw new Exception($result['error']);
    }
}

/**
 * Buscar fotos do equipamento via AJAX
 */
function getEquipmentPhotosAjax() {
    $equipamento_id = $_GET['equipamento_id'] ?? null;
    
    if (!$equipamento_id) {
        throw new Exception('ID do equipamento é obrigatório');
    }
    
    $photos = getEquipmentPhotos($equipamento_id);
    
    $photos_html = '';
    if (empty($photos)) {
        $photos_html = '
            <div class="text-center text-muted py-4">
                <i class="bi bi-camera fs-1"></i>
                <p>Nenhuma foto disponível</p>
                <small>Use o formulário ao lado para enviar a primeira foto.</small>
            </div>';
    } else {
        foreach ($photos as $photo) {
            $photo_url = getPhotoUrl($photo['caminho_arquivo']);
            $thumb_url = getPhotoUrl($photo['caminho_arquivo'], true);
            
            $photos_html .= '
                <div class="photo-item mb-3" data-photo-id="' . $photo['id'] . '">
                    <div class="row">
                        <div class="col-4">
                            <img src="' . htmlspecialchars($thumb_url) . '" 
                                 class="img-fluid rounded" 
                                 style="height: 80px; width: 100%; object-fit: cover;"
                                 onclick="openPhotoModal(\'' . htmlspecialchars($photo_url) . '\')">
                        </div>
                        <div class="col-6">
                            <div class="small">
                                <strong>' . ucfirst($photo['tipo_foto']) . '</strong><br>
                                <span class="text-muted">' . date('d/m/Y H:i', strtotime($photo['data_upload'])) . '</span><br>';
            
            if ($photo['descricao']) {
                $photos_html .= '<em>' . htmlspecialchars($photo['descricao']) . '</em>';
            }
            
            $photos_html .= '
                            </div>
                        </div>
                        <div class="col-2">';
            
            // Mostrar botão de deletar apenas se tiver permissão
            if (hasPermission('equipamentos', 'edit') && 
                ($_SESSION['user_type'] === 'admin' || $photo['uploaded_by'] == $_SESSION['user_id'])) {
                $photos_html .= '
                            <button class="btn btn-outline-danger btn-sm" 
                                    onclick="deletePhoto(\'equipment\', ' . $photo['id'] . ')"
                                    title="Excluir foto">
                                <i class="bi bi-trash"></i>
                            </button>';
            }
            
            $photos_html .= '
                        </div>
                    </div>
                </div>';
        }
    }
    
    echo json_encode([
        'success' => true,
        'photos' => $photos,
        'photos_html' => $photos_html,
        'total' => count($photos)
    ]);
}

/**
 * Buscar fotos da manutenção via AJAX
 */
function getMaintenancePhotosAjax() {
    $manutencao_id = $_GET['manutencao_id'] ?? null;
    
    if (!$manutencao_id) {
        throw new Exception('ID da manutenção é obrigatório');
    }
    
    // Buscar fotos organizadas por tipo
    $tipos = ['antes', 'durante', 'depois', 'problema', 'solucao'];
    $photos_by_type = [];
    
    foreach ($tipos as $tipo) {
        $photos_by_type[$tipo] = getMaintenancePhotos($manutencao_id, $tipo);
    }
    
    $photos_html = '';
    $total_photos = 0;
    
    $tipo_labels = [
        'antes' => ['📸 ANTES da Manutenção', 'primary'],
        'durante' => ['🔧 DURANTE a Manutenção', 'warning'],
        'depois' => ['✅ DEPOIS da Manutenção', 'success'],
        'problema' => ['⚠️ Problema Encontrado', 'danger'],
        'solucao' => ['🔧 Solução Aplicada', 'info']
    ];
    
    foreach ($tipo_labels as $tipo => $info) {
        $label = $info[0];
        $color = $info[1];
        $photos = $photos_by_type[$tipo] ?? [];
        $count = count($photos);
        $total_photos += $count;
        
        $collapsed = $count === 0 ? 'collapsed' : '';
        $show = $count > 0 ? 'show' : '';
        
        $photos_html .= '
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button ' . $collapsed . '" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#collapse-' . $tipo . '">
                        ' . $label . ' (' . $count . ' fotos)
                    </button>
                </h2>
                <div id="collapse-' . $tipo . '" class="accordion-collapse collapse ' . $show . '" 
                     data-bs-parent="#photosAccordion">
                    <div class="accordion-body">';
        
        if (empty($photos)) {
            $photos_html .= '
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-camera"></i>
                            <p>Nenhuma foto "' . strtolower(explode(' ', $label)[1]) . '" ainda.</p>
                        </div>';
        } else {
            $photos_html .= '<div class="row">';
            foreach ($photos as $photo) {
                $photo_url = getPhotoUrl($photo['caminho_arquivo']);
                $thumb_url = getPhotoUrl($photo['caminho_arquivo'], true);
                
                $photos_html .= '
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <img src="' . htmlspecialchars($thumb_url) . '" 
                                 class="card-img-top" 
                                 style="height: 150px; object-fit: cover; cursor: pointer;"
                                 onclick="openPhotoModal(\'' . htmlspecialchars($photo_url) . '\')">
                            <div class="card-body p-2">
                                <small class="text-muted">
                                    ' . date('d/m/Y H:i', strtotime($photo['data_upload'])) . '<br>';
                
                if ($photo['descricao']) {
                    $photos_html .= htmlspecialchars($photo['descricao']);
                }
                
                $photos_html .= '</small>';
                
                // Botão de deletar
                if (hasPermission('manutencoes', 'edit') && 
                    ($_SESSION['user_type'] === 'admin' || $photo['uploaded_by'] == $_SESSION['user_id'])) {
                    $photos_html .= '
                                <br><button class="btn btn-outline-danger btn-sm mt-1" 
                                           onclick="deletePhoto(\'maintenance\', ' . $photo['id'] . ')">
                                    <i class="bi bi-trash"></i>
                                </button>';
                }
                
                $photos_html .= '
                            </div>
                        </div>
                    </div>';
            }
            $photos_html .= '</div>';
        }
        
        $photos_html .= '
                    </div>
                </div>
            </div>';
    }
    
    if ($total_photos === 0) {
        $photos_html = '
            <div class="text-center text-muted py-4">
                <i class="bi bi-camera fs-1"></i>
                <p>Nenhuma foto disponível</p>
                <small>Use o formulário ao lado para documentar a manutenção.</small>
            </div>';
    } else {
        $photos_html = '<div class="accordion" id="photosAccordion">' . $photos_html . '</div>';
    }
    
    echo json_encode([
        'success' => true,
        'photos_by_type' => $photos_by_type,
        'photos_html' => $photos_html,
        'total' => $total_photos
    ]);
}

/**
 * Excluir foto do equipamento via AJAX
 */
function deleteEquipmentPhotoAjax() {
    $photo_id = $_POST['photo_id'] ?? null;
    
    if (!$photo_id) {
        throw new Exception('ID da foto é obrigatório');
    }
    
    $result = deleteEquipmentPhoto($photo_id, $_SESSION['user_type']);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Foto excluída com sucesso!'
        ]);
    } else {
        throw new Exception($result['error']);
    }
}

/**
 * Excluir foto da manutenção via AJAX
 */
function deleteMaintenancePhotoAjax() {
    $photo_id = $_POST['photo_id'] ?? null;
    
    if (!$photo_id) {
        throw new Exception('ID da foto é obrigatório');
    }
    
    $result = deleteMaintenancePhoto($photo_id, $_SESSION['user_type']);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Foto excluída com sucesso!'
        ]);
    } else {
        throw new Exception($result['error']);
    }
}

/**
 * Atualizar descrição da foto
 */
function updatePhotoDescription() {
    $photo_id = $_POST['photo_id'] ?? null;
    $type = $_POST['type'] ?? null; // 'equipment' ou 'maintenance'
    $description = sanitize($_POST['description'] ?? '');
    
    if (!$photo_id || !$type) {
        throw new Exception('Dados obrigatórios não fornecidos');
    }
    
    if ($type === 'equipment') {
        $table = 'equipamento_fotos';
        $permission = hasPermission('equipamentos', 'edit');
    } else {
        $table = 'manutencao_fotos';
        $permission = hasPermission('manutencoes', 'edit');
    }
    
    if (!$permission) {
        throw new Exception('Sem permissão para editar fotos');
    }
    
    // Verificar se é o dono da foto ou admin
    $photo = Database::fetch("SELECT uploaded_by FROM {$table} WHERE id = ?", [$photo_id]);
    if (!$photo) {
        throw new Exception('Foto não encontrada');
    }
    
    if ($_SESSION['user_type'] !== 'admin' && $photo['uploaded_by'] != $_SESSION['user_id']) {
        throw new Exception('Você só pode editar suas próprias fotos');
    }
    
    Database::query("UPDATE {$table} SET descricao = ? WHERE id = ?", [$description, $photo_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Descrição atualizada com sucesso!'
    ]);
}

/**
 * Reordenar fotos (apenas para manutenções)
 */
function reorderPhotos() {
    if (!hasPermission('manutencoes', 'edit')) {
        throw new Exception('Sem permissão para reordenar fotos');
    }
    
    $photo_orders = $_POST['photo_orders'] ?? [];
    
    if (empty($photo_orders)) {
        throw new Exception('Dados de ordenação não fornecidos');
    }
    
    Database::beginTransaction();
    
    try {
        foreach ($photo_orders as $photo_id => $ordem) {
            // Verificar se é o dono da foto ou admin
            $photo = Database::fetch("SELECT uploaded_by FROM manutencao_fotos WHERE id = ?", [$photo_id]);
            if ($photo && ($_SESSION['user_type'] === 'admin' || $photo['uploaded_by'] == $_SESSION['user_id'])) {
                Database::query("UPDATE manutencao_fotos SET ordem = ? WHERE id = ?", [(int)$ordem, $photo_id]);
            }
        }
        
        Database::commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Fotos reordenadas com sucesso!'
        ]);
        
    } catch (Exception $e) {
        Database::rollback();
        throw $e;
    }
}
?>