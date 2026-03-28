<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';
require_once 'photo_functions.php';

// ========== HANDLER PARA MÚLTIPLAS FOTOS DE EQUIPAMENTOS (INTEGRADO) ==========

/**
 * Processa múltiplos uploads de fotos para equipamentos
 */
function processMultipleEquipmentPhotos($equipamento_id, $files, $tipo_foto = 'adicional', $descricao = '') {
    $results = [];
    $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'usuario';
    $max_photos = 10;
    
    // Verificar quantas fotos já existem
    $existing_count = countEquipmentPhotos($equipamento_id);
    $available_slots = $max_photos - $existing_count;
    
    if ($available_slots <= 0) {
        return [
            'success' => false,
            'error' => 'Limite máximo de fotos atingido (10 fotos por equipamento)',
            'results' => []
        ];
    }
    
    // Processar cada arquivo
    $successful_uploads = 0;
    $errors = [];
    
    // Se $files não é um array multidimensional, converter
    if (isset($files['name']) && !is_array($files['name'])) {
        $files = [
            'name' => [$files['name']],
            'type' => [$files['type']], 
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']]
        ];
    }
    
    $total_files = count($files['name']);
    $files_to_process = min($total_files, $available_slots);
    
    for ($i = 0; $i < $files_to_process; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK && !empty($files['tmp_name'][$i])) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            $result = uploadEquipmentPhoto($equipamento_id, $file, $tipo_foto, $descricao);
            
            if ($result['success']) {
                $successful_uploads++;
                $results[] = $result;
            } else {
                $errors[] = "Arquivo {$files['name'][$i]}: {$result['error']}";
            }
        }
    }
    
    return [
        'success' => $successful_uploads > 0,
        'uploaded_count' => $successful_uploads,
        'total_files' => $total_files,
        'available_slots' => $available_slots,
        'errors' => $errors,
        'results' => $results
    ];
}

/**
 * Obter todas as fotos de um equipamento com informações extras
 */
function getEquipmentPhotosWithDetails($equipamento_id) {
    try {
        $photos = Database::fetchAll(
            "SELECT ef.*, u.nome as uploaded_by_name 
             FROM equipamento_fotos ef 
             LEFT JOIN usuarios u ON ef.uploaded_by = u.id 
             WHERE ef.equipamento_id = ? AND ef.ativo = 1 
             ORDER BY ef.tipo_foto, ef.data_upload DESC",
            [$equipamento_id]
        );
        
        // Adicionar URLs das fotos e informações extras
        foreach ($photos as &$photo) {
            $photo['photo_url'] = getPhotoUrl($photo['caminho_arquivo'], false);
            $photo['thumb_url'] = getPhotoUrl($photo['caminho_arquivo'], true);
            
            if (file_exists($photo['caminho_arquivo'])) {
                $photo['file_size_formatted'] = formatFileSize(filesize($photo['caminho_arquivo']));
            } else {
                $photo['file_size_formatted'] = 'N/A';
            }
        }
        
        return $photos;
        
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro ao buscar fotos do equipamento: ' . $e->getMessage(), 'ERROR');
        }
        return [];
    }
}

/**
 * Deletar foto específica via AJAX
 */
function deleteEquipmentPhotoAjax($photo_id, $user_type) {
    try {
        // Verificar se a foto existe
        $photo = Database::fetch("SELECT * FROM equipamento_fotos WHERE id = ?", [$photo_id]);
        if (!$photo) {
            throw new Exception('Foto não encontrada.');
        }
        
        // Verificar permissões
        if ($user_type !== 'admin' && isset($_SESSION['user_id']) && $photo['uploaded_by'] != $_SESSION['user_id']) {
            throw new Exception('Você só pode excluir suas próprias fotos.');
        }
        
        // Excluir arquivos físicos
        if (file_exists($photo['caminho_arquivo'])) {
            unlink($photo['caminho_arquivo']);
        }
        
        $thumb_path = str_replace(['/equipamentos/', '/manutencoes/'], ['/thumbs/equipamentos/', '/thumbs/manutencoes/'], $photo['caminho_arquivo']);
        if (file_exists($thumb_path)) {
            unlink($thumb_path);
        }
        
        // Excluir do banco
        Database::query("DELETE FROM equipamento_fotos WHERE id = ?", [$photo_id]);
        
        if (function_exists('logMessage')) {
            logMessage("Foto do equipamento excluída: {$photo['nome_arquivo']}", 'INFO');
        }
        
        return [
            'success' => true,
            'message' => 'Foto excluída com sucesso!'
        ];
        
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro ao excluir foto: ' . $e->getMessage(), 'ERROR');
        }
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ========== PROCESSAMENTO DE REQUISIÇÕES AJAX PARA FOTOS ==========

// Processar exclusão de foto via AJAX
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'delete_photo') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Usuário não logado']);
        exit;
    }
    
    $photo_id = intval($_POST['photo_id'] ?? 0);
    $user_type = $_SESSION['user_type'] ?? 'usuario';
    
    if ($photo_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID da foto inválido']);
        exit;
    }
    
    $result = deleteEquipmentPhotoAjax($photo_id, $user_type);
    echo json_encode($result);
    exit;
}

// Carregar fotos via AJAX
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_photos') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Usuário não logado']);
        exit;
    }
    
    $equipamento_id = intval($_GET['equipamento_id'] ?? 0);
    
    if ($equipamento_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID do equipamento inválido']);
        exit;
    }
    
    $photos = getEquipmentPhotosWithDetails($equipamento_id);
    
    echo json_encode([
        'success' => true,
        'photos' => $photos,
        'total_photos' => count($photos),
        'max_photos' => 10
    ]);
    exit;
}

// Carregar materiais de um equipamento específico via AJAX
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_equipment_materials') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Usuário não logado']);
        exit;
    }
    
    $equipamento_id = intval($_GET['equipamento_id'] ?? 0);
    
    if ($equipamento_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID do equipamento inválido']);
        exit;
    }
    
    try {
        $materials = Database::fetchAll(
            "SELECT em.*, pm.nome, pm.descricao, pm.unidade_medida 
             FROM equipamento_materiais em 
             LEFT JOIN pecas_materiais pm ON em.material_id = pm.id 
             WHERE em.equipamento_id = ? 
             ORDER BY pm.nome",
            [$equipamento_id]
        );
        
        echo json_encode([
            'success' => true,
            'materials' => $materials
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Carregar serviços de um equipamento específico via AJAX
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_equipment_services') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Usuário não logado']);
        exit;
    }
    
    $equipamento_id = intval($_GET['equipamento_id'] ?? 0);
    
    if ($equipamento_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID do equipamento inválido']);
        exit;
    }
    
    try {
        $services = Database::fetchAll(
            "SELECT es.*, tm.nome, tm.descricao 
             FROM equipamento_servicos es 
             LEFT JOIN tipos_manutencao tm ON es.servico_id = tm.id 
             WHERE es.equipamento_id = ? 
             ORDER BY tm.nome",
            [$equipamento_id]
        );
        
        echo json_encode([
            'success' => true,
            'services' => $services
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Carregar materiais e serviços via AJAX
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_materials_services') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Usuário não logado']);
        exit;
    }
    
    $type = $_GET['type'] ?? '';
    
    try {
        if ($type === 'materiais') {
            // Tentar primeiro a tabela pecas_materiais, depois materiais como fallback
            $data = Database::fetchAll(
                "SELECT id, codigo, nome, descricao, unidade_medida, preco_unitario 
                 FROM pecas_materiais 
                 WHERE ativo = 1 
                 ORDER BY categoria, nome"
            );
            
            // Se não encontrar dados, tentar tabela alternativa
            if (empty($data)) {
                $data = Database::fetchAll(
                    "SELECT id, nome, descricao, unidade_medida 
                     FROM materiais 
                     WHERE ativo = 1 
                     ORDER BY nome ASC"
                );
            }
        } elseif ($type === 'servicos') {
            // Tentar primeiro tipos_manutencao, depois servicos como fallback
            $data = Database::fetchAll(
                "SELECT id, codigo, nome, descricao, tempo_estimado 
                 FROM tipos_manutencao 
                 WHERE ativo = 1 
                 ORDER BY categoria, nome"
            );
            
            // Se não encontrar dados, tentar tabela alternativa
            if (empty($data)) {
                $data = Database::fetchAll(
                    "SELECT id, nome, descricao, tempo_estimado 
                     FROM servicos 
                     WHERE ativo = 1 
                     ORDER BY nome ASC"
                );
            }
        } else {
            throw new Exception('Tipo inválido');
        }
        
        echo json_encode($data ?: []);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ========== VERIFICAÇÕES E CONFIGURAÇÕES ==========

// Verificar status do mPDF para exibir alertas se necessário
if (function_exists('getMpdfStatus')) {
    $mpdf_status = getMpdfStatus();
    if (!$mpdf_status['working']) {
        if (function_exists('logMessage')) {
            logMessage('mPDF não está funcionando: ' . $mpdf_status['message'], 'WARNING', $_SESSION['user_type'] ?? null);
        }
    }
}

$message = '';
$error = '';

// Verificar permissões de acesso
UserPermissions::enforcePageAccess($_SESSION['user_type'], 'equipamentos.php');

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Processamento de relatórios
if (isset($_GET['generate_report']) && $_GET['generate_report'] === 'true') {
    // Verificar permissão para gerar relatórios
    if (!hasPermission('relatorios', 'view')) {
        header('Location: equipamentos.php?error=no_permission');
        exit;
    }
    
    $download_pdf = isset($_GET['download_pdf']) && $_GET['download_pdf'] === 'true';
    
    // Incluir as funções de relatório
    if (file_exists('photo_report_functions.php')) {
        require_once 'photo_report_functions.php';
        generateEquipmentPhotoReport($download_pdf);
    }
    exit;
}

// ========== PROCESSAMENTO DE AÇÕES ==========

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        // Verificar permissão para criar/editar
        $required_permission = ($action === 'create') ? 'create' : 'edit';
        
        if (!hasPermission('equipamentos', $required_permission)) {
            $error = 'Você não tem permissão para ' . ($action === 'create' ? 'criar' : 'editar') . ' equipamentos.';
        } else {
            $codigo = sanitize($_POST['codigo'] ?? '');
            $tipo = sanitize($_POST['tipo'] ?? '');
            $localizacao = sanitize($_POST['localizacao'] ?? '');
            $endereco = sanitize($_POST['endereco'] ?? '');
            $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
            $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
            $marca = sanitize($_POST['marca'] ?? '');
            $modelo = sanitize($_POST['modelo'] ?? '');
            $data_instalacao = !empty($_POST['data_instalacao']) ? $_POST['data_instalacao'] : null;
            $status = sanitize($_POST['status'] ?? 'ativo');
            $observacoes = sanitize($_POST['observacoes'] ?? '');
            $google_maps_url = sanitize($_POST['google_maps_url'] ?? '');
            $id = $_POST['id'] ?? null;
            
            // Validação adicional para ação de UPDATE
            if ($action === 'update' && empty($id)) {
                $error = 'ID do equipamento é obrigatório para atualização.';
            } elseif (empty($codigo) || empty($tipo) || empty($localizacao)) {
                $error = 'Campos obrigatórios: Código, Tipo e Localização.';
            } elseif (!in_array($tipo, ['bebedouro', 'ducha'])) {
                $error = 'Tipo deve ser bebedouro ou ducha.';
            } elseif (!in_array($status, ['ativo', 'inativo', 'manutencao'])) {
                $error = 'Status inválido.';
            } else {
                try {
                    if ($action === 'create') {
                        // ========== CRIAR NOVO EQUIPAMENTO ==========
                        $existing = Database::fetch("SELECT id FROM equipamentos WHERE codigo = ?", [$codigo]);
                        if ($existing) {
                            $error = 'Código já existe.';
                        } else {
                            try {
                                // Iniciar transação
                                Database::beginTransaction();
                                
                                // Inserir equipamento
                                $sql = "INSERT INTO equipamentos (codigo, tipo, localizacao, endereco, latitude, longitude, marca, modelo, data_instalacao, status, observacoes, google_maps_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                                Database::query($sql, [
                                    $codigo, 
                                    $tipo, 
                                    $localizacao, 
                                    $endereco, 
                                    $latitude, 
                                    $longitude, 
                                    $marca, 
                                    $modelo, 
                                    $data_instalacao, 
                                    $status, 
                                    $observacoes, 
                                    $google_maps_url
                                ]);
                                
                                // Obter ID do equipamento inserido
                                $equipamento_id = Database::lastInsertId();
                                
                                // Processar múltiplas fotos para criação
                                if (isset($_FILES['equipment_photos']) && !empty($_FILES['equipment_photos']['name'][0])) {
                                    $photo_result = processMultipleEquipmentPhotos($equipamento_id, $_FILES['equipment_photos'], 'principal', 'Fotos do equipamento');
                                    
                                    if (!$photo_result['success'] && !empty($photo_result['errors'])) {
                                        // Log dos erros mas não falha a transação
                                        if (function_exists('logMessage')) {
                                            logMessage('Erros no upload de fotos: ' . implode(', ', $photo_result['errors']), 'WARNING');
                                        }
                                    }
                                    
                                    if ($photo_result['success'] && $photo_result['uploaded_count'] > 0) {
                                        // Definir a primeira foto como principal no equipamento
                                        if (!empty($photo_result['results'])) {
                                            $main_photo_path = $photo_result['results'][0]['path'];
                                            Database::query("UPDATE equipamentos SET photo_path = ? WHERE id = ?", [$main_photo_path, $equipamento_id]);
                                        }
                                    }
                                }
                                
                                // Processar materiais selecionados
                                if (isset($_POST['materiais']) && is_array($_POST['materiais'])) {
                                    foreach ($_POST['materiais'] as $materialData) {
                                        if (isset($materialData['id']) && !empty($materialData['id'])) {
                                            $materialId = intval($materialData['id']);
                                            $quantidade = floatval($materialData['quantidade'] ?? 1);
                                            $observacoes_material = sanitize($materialData['observacoes'] ?? '');
                                            
                                            if ($materialId > 0 && $quantidade > 0) {
                                                Database::query(
                                                    "INSERT INTO equipamento_materiais (equipamento_id, material_id, quantidade, observacoes) VALUES (?, ?, ?, ?)",
                                                    [$equipamento_id, $materialId, $quantidade, $observacoes_material]
                                                );
                                            }
                                        }
                                    }
                                }
                                
                                // Processar serviços selecionados
                                if (isset($_POST['servicos']) && is_array($_POST['servicos'])) {
                                    foreach ($_POST['servicos'] as $servicoData) {
                                        if (isset($servicoData['id']) && !empty($servicoData['id'])) {
                                            $servicoId = intval($servicoData['id']);
                                            $observacoes_servico = sanitize($servicoData['observacoes'] ?? '');
                                            
                                            if ($servicoId > 0) {
                                                Database::query(
                                                    "INSERT INTO equipamento_servicos (equipamento_id, servico_id, observacoes) VALUES (?, ?, ?)",
                                                    [$equipamento_id, $servicoId, $observacoes_servico]
                                                );
                                            }
                                        }
                                    }
                                }
                                
                                // Commit da transação
                                Database::commit();
                                
                                $message = 'Equipamento cadastrado com sucesso!';
                                if (isset($photo_result) && $photo_result['success']) {
                                    $message .= " {$photo_result['uploaded_count']} foto(s) adicionada(s).";
                                }
                                
                                if (function_exists('logMessage')) {
                                    logMessage("Equipamento criado: {$codigo} por {$_SESSION['user_name']}", 'INFO');
                                }
                                
                            } catch (Exception $e) {
                                // Rollback em caso de erro
                                Database::rollback();
                                throw $e;
                            }
                        }
                    } elseif ($action === 'update') {
                        // ========== ATUALIZAR EQUIPAMENTO EXISTENTE ==========
                        
                        // Primeiro, verificar se o equipamento existe
                        $current_equipment = Database::fetch("SELECT id, codigo FROM equipamentos WHERE id = ?", [$id]);
                        if (!$current_equipment) {
                            $error = 'Equipamento não encontrado para atualização.';
                        } else {
                            // Verificar se o código já existe em outro equipamento
                            $existing_code = Database::fetch("SELECT id FROM equipamentos WHERE codigo = ? AND id != ?", [$codigo, $id]);
                            if ($existing_code) {
                                $error = 'Código já existe em outro equipamento.';
                            } else {
                                try {
                                    // Atualizar dados básicos do equipamento
                                    $sql = "UPDATE equipamentos SET 
                                            codigo = ?, 
                                            tipo = ?, 
                                            localizacao = ?, 
                                            endereco = ?, 
                                            latitude = ?, 
                                            longitude = ?, 
                                            marca = ?, 
                                            modelo = ?, 
                                            data_instalacao = ?, 
                                            status = ?, 
                                            observacoes = ?, 
                                            google_maps_url = ? 
                                            WHERE id = ?";

                                    $result = Database::query($sql, [
                                        $codigo, 
                                        $tipo, 
                                        $localizacao, 
                                        $endereco, 
                                        $latitude, 
                                        $longitude, 
                                        $marca, 
                                        $modelo, 
                                        $data_instalacao, 
                                        $status, 
                                        $observacoes, 
                                        $google_maps_url, 
                                        $id
                                    ]);
                                    
                                    $message = 'Equipamento atualizado com sucesso!';
                                    
                                    // Processar novas fotos se houver
                                    if (isset($_FILES['equipment_photos']) && !empty($_FILES['equipment_photos']['name'][0])) {
                                        $photo_result = processMultipleEquipmentPhotos($id, $_FILES['equipment_photos'], 'adicional', 'Fotos adicionais');
                                        
                                        if ($photo_result['success']) {
                                            $message .= " {$photo_result['uploaded_count']} nova(s) foto(s) adicionada(s).";
                                            
                                            // Se não há foto principal definida, usar a primeira foto adicionada
                                            $current_equipment_photos = Database::fetch("SELECT photo_path FROM equipamentos WHERE id = ?", [$id]);
                                            if (empty($current_equipment_photos['photo_path']) && !empty($photo_result['results'])) {
                                                $main_photo_path = $photo_result['results'][0]['path'];
                                                Database::query("UPDATE equipamentos SET photo_path = ? WHERE id = ?", [$main_photo_path, $id]);
                                            }
                                        } else if (!empty($photo_result['errors'])) {
                                            $message .= " Erros no upload: " . implode(', ', array_slice($photo_result['errors'], 0, 2));
                                            if (count($photo_result['errors']) > 2) {
                                                $message .= " e mais " . (count($photo_result['errors']) - 2) . " erro(s).";
                                            }
                                        }
                                    }
                                    
                                    if (function_exists('logMessage')) {
                                        logMessage("Equipamento atualizado ID {$id}: {$codigo} por {$_SESSION['user_name']}", 'INFO');
                                    }
                                    
                                } catch (Exception $e) {
                                    $error = 'Erro interno ao atualizar. Tente novamente.';
                                    if (function_exists('logMessage')) {
                                        logMessage('Erro ao atualizar equipamento ID ' . $id . ': ' . $e->getMessage(), 'ERROR');
                                    }
                                }
                            }
                        }
                    } else {
                        $error = 'Ação inválida. Use "create" ou "update".';
                    }
                } catch (Exception $e) {
                    $error = 'Erro interno. Tente novamente.';
                    if (function_exists('logMessage')) {
                        logMessage('Erro geral ao processar equipamento: ' . $e->getMessage(), 'ERROR');
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        // Verificar permissão para excluir
        if (!hasPermission('equipamentos', 'delete')) {
            $error = 'Você não tem permissão para excluir equipamentos.';
        } else {
            $id = $_POST['id'] ?? null;
            if ($id) {
                try {
                    // Buscar código do equipamento para log
                    $equipment = Database::fetch("SELECT codigo FROM equipamentos WHERE id = ?", [$id]);
                    
                    Database::query("DELETE FROM equipamentos WHERE id = ?", [$id]);
                    $message = 'Equipamento excluído com sucesso!';
                    
                    if ($equipment && function_exists('logMessage')) {
                        logMessage("Equipamento excluído: {$equipment['codigo']} por {$_SESSION['user_name']}", 'INFO');
                    }
                } catch (Exception $e) {
                    $error = 'Erro ao excluir. Verifique se não há manutenções vinculadas.';
                    if (function_exists('logMessage')) {
                        logMessage('Erro ao excluir equipamento: ' . $e->getMessage(), 'ERROR');
                    }
                }
            }
        }
    }
}

// ========== FILTROS E BUSCA ==========

$search = $_GET['search'] ?? '';
$tipo_filter = $_GET['tipo'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Query base
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(codigo LIKE ? OR localizacao LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}
if ($tipo_filter) {
    $where_conditions[] = "tipo = ?";
    $params[] = $tipo_filter;
}

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

// Filtro adicional baseado em permissões do usuário
if ($_SESSION['user_type'] === 'usuario') {
    // Usuários comuns veem apenas equipamentos ativos
    $where_conditions[] = "status = 'ativo'";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Buscar equipamentos
$equipamentos = Database::fetchAll(
    "SELECT * FROM equipamentos $where_clause ORDER BY codigo LIMIT $per_page OFFSET $offset",
    $params
);

// Filtrar dados conforme permissões do usuário
$equipamentos = UserPermissions::filterData($_SESSION['user_type'], 'equipamentos', $equipamentos);

// Contar total para paginação
$total = Database::fetch(
    "SELECT COUNT(*) as total FROM equipamentos $where_clause",
    $params
)['total'];

$total_pages = ceil($total / $per_page);

// ========== ESTATÍSTICAS ==========

$stats = [];

if (hasPermission('dashboard', 'full_stats')) {
    // Estatísticas completas para Admin
    $stats = [
        'total' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos")['total'],
        'ativos' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'ativo'")['total'],
        'manutencao' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'manutencao'")['total'],
        'inativos' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'inativo'")['total']
    ];
} elseif (hasPermission('dashboard', 'basic_stats')) {
    // Estatísticas básicas para Técnico
    $stats = [
        'total' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status != 'inativo'")['total'],
        'ativos' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'ativo'")['total'],
        'manutencao' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'manutencao'")['total'],
        'meus_equipamentos' => Database::fetch("
            SELECT COUNT(DISTINCT m.equipamento_id) as total 
            FROM manutencoes m 
            WHERE m.tecnico_id = ? AND m.status IN ('agendada', 'em_andamento')", 
            [$_SESSION['user_id']])['total']
    ];
} else {
    // Estatísticas mínimas para Usuário comum
    $stats = [
        'total' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'ativo'")['total'],
        'bebedouros' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE tipo = 'bebedouro' AND status = 'ativo'")['total'],
        'duchas' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE tipo = 'ducha' AND status = 'ativo'")['total'],
    ];
}

function getStatusBadge($status) {
    $badges = [
        'ativo' => 'bg-success',
        'inativo' => 'bg-secondary',
        'manutencao' => 'bg-warning'
    ];
    return $badges[$status] ?? 'bg-secondary';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Equipamentos - HidroApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/forms-mobile.css" rel="stylesheet">
    <style>
        /* Reset e configurações globais */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-color: #0066cc;
            --primary-dark: #004499;
            --secondary-color: #00b4d8;
            --accent-color: #4a90e2;
            --success-color: #52c41a;
            --warning-color: #1890ff;
            --info-color: #40a9ff;
            --danger-color: #1677ff;
            --text-dark: #1a1a1a;
            --text-gray: #666;
            --text-light: #999;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --border-color: #e2e8f0;
            --shadow-light: 0 2px 10px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 20px rgba(0, 0, 0, 0.15);
            --shadow-heavy: 0 8px 30px rgba(0, 0, 0, 0.2);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 280px;
            --header-height: 70px;
        }

        html {
            scroll-behavior: smooth;
            font-size: 16px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--bg-light);
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* Sidebar moderna */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-heavy);
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 1rem 1.5rem;
            margin: 0.25rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            display: flex;
            align-items: center;
            font-weight: 500;
            text-decoration: none;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .nav-link i {
            font-size: 1.1rem;
            margin-right: 0.75rem;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .top-header {
            background: var(--bg-white);
            height: var(--header-height);
            box-shadow: var(--shadow-light);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
        }
        
        .content-area {
            padding: 2rem;
            flex: 1;
        }
        
        .stat-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-heavy);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .table-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .table-card .card-header {
            background: linear-gradient(135deg, var(--bg-light), var(--bg-white));
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow-light);
            color: white;
            text-decoration: none;
        }
        
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            color: white;
        }
        
        .btn-report {
            background: linear-gradient(135deg, #ff6b35 0%, #ff8e53 100%);
            border: none;
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow-light);
            color: white;
            text-decoration: none;
        }
        
        .btn-report:hover {
            background: linear-gradient(135deg, #e55a2e 0%, #ff6b35 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            color: white;
        }
        
        .search-filters {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-heavy);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border-bottom: none;
        }
        
        .footer-area {
            background: var(--bg-white);
            border-top: 1px solid var(--border-color);
            margin-top: auto;
            padding: 1.5rem 0;
        }
        
        .footer-link {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer-link:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        /* Badges modernos */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Formulários modernos */
        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            transition: var(--transition);
            background: var(--bg-white);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
            background: var(--bg-white);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        /* Alertas modernos */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-light);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
        }

        /* Input group styles */
        .input-group-text {
            background: linear-gradient(135deg, var(--bg-light), var(--bg-white));
            border: 2px solid var(--border-color);
            border-right: none;
            color: var(--text-gray);
        }

        .input-group .form-control {
            border-left: none;
        }

        /* Table modern styles */
        .table th {
            border-bottom: 2px solid var(--border-color);
            background: var(--bg-light);
            font-weight: 600;
            color: var(--text-dark);
            padding: 1rem 0.75rem;
        }

        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 102, 204, 0.05);
        }

        /* Pagination styles */
        .pagination .page-link {
            border: none;
            color: var(--primary-color);
            font-weight: 500;
            border-radius: var(--border-radius);
            margin: 0 0.25rem;
        }

        .pagination .page-link:hover {
            background-color: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-light);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* ========== ESTILOS PARA GALERIA DE FOTOS ========== */
        .photo-gallery-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 1rem;
        }

        .photo-gallery-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .photo-gallery-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }

        .photo-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.2), rgba(0,0,0,0.7));
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding: 10px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .photo-gallery-item:hover .photo-overlay {
            opacity: 1;
        }

        .photo-info {
            color: white;
            font-size: 0.75rem;
        }

        .photo-actions {
            display: flex;
            gap: 5px;
        }

        .btn-photo-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
        }

        .new-photo-preview {
            position: relative;
            border: 2px dashed #007bff;
            border-radius: 8px;
            padding: 5px;
            margin-bottom: 1rem;
        }

        .new-photo-preview img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 6px;
        }

        .remove-new-photo {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .photos-section {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .photos-section h6 {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }

        /* Responsividade melhorada */
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 100%;
                --header-height: 60px;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 300px;
            }

            .sidebar.show {
                transform: translateX(0);
                z-index: 1050;
            }

            .main-content {
                margin-left: 0;
            }

            .content-area {
                padding: 1rem;
            }

            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }

            .modal-xl {
                max-width: calc(100% - 1rem);
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .btn-group {
                flex-direction: column;
                gap: 0.25rem;
            }

            .btn-group .btn {
                border-radius: 0.375rem !important;
            }

            .photo-gallery-item img {
                height: 120px;
            }

            .search-filters {
                padding: 1rem;
            }

            .search-filters .row > div {
                margin-bottom: 1rem;
            }

            .top-header {
                padding: 0 1rem;
            }

            .top-header h4 {
                font-size: 1.1rem;
            }

            /* Melhor experiência em cards de estatísticas */
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }

            .stat-card h3 {
                font-size: 1.5rem;
            }

            /* Ajustes para formulário modal */
            .modal-body .row > div {
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 576px) {
            .content-area {
                padding: 0.75rem;
            }

            .search-filters {
                padding: 0.75rem;
            }

            .stat-card {
                padding: 0.75rem;
                text-align: center;
            }

            .table-responsive {
                font-size: 0.8rem;
            }

            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }

            .modal-header h5 {
                font-size: 1rem;
            }

            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }

            .pagination .page-link {
                padding: 0.375rem 0.5rem;
                font-size: 0.875rem;
            }
        }

        /* Overlay para sidebar mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Animações */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hover-lift {
            transition: var(--transition);
        }

        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .invalid-feedback {
            display: block;
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Loading states */
        .loading {
            pointer-events: none;
            opacity: 0.6;
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        /* Touch improvements for mobile */
        @media (pointer: coarse) {
            .btn {
                min-height: 44px;
            }

            .form-control, .form-select {
                min-height: 44px;
            }

            .btn-sm {
                min-height: 36px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="bi bi-droplet-fill fs-3"></i>
                </div>
                <div>
                    <h5 class="mb-0">HidroApp</h5>
                    <small class="opacity-75">v1.0</small>
                </div>
            </div>
        </div>
        
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <?= UserPermissions::generateSidebar($_SESSION['user_type'], 'equipamentos.php') ?>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center">
                <button class="btn d-lg-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h4 class="mb-0">Equipamentos</h4>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i>
                    <span class="d-none d-sm-inline"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#perfil"><i class="bi bi-person me-2"></i>Perfil</a></li>
                    <li><a class="dropdown-item" href="configuracoes.php"><i class="bi bi-gear me-2"></i>Configurações</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                </ul>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show fade-in" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <?php if (hasPermission('dashboard', 'full_stats')): ?>
                    <!-- Stats para Admin -->
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-hdd-stack"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total'] ?></h3>
                                    <p class="text-muted mb-0">Total</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['ativos'] ?></h3>
                                    <p class="text-muted mb-0">Ativos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--info-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['manutencao'] ?></h3>
                                    <p class="text-muted mb-0">Em Manutenção</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--info-color) 0%, var(--primary-color) 100%);">
                                    <i class="bi bi-x-circle"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['inativos'] ?></h3>
                                    <p class="text-muted mb-0">Inativos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif (hasPermission('dashboard', 'basic_stats')): ?>
                    <!-- Stats para Técnico -->
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-hdd-stack"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total'] ?></h3>
                                    <p class="text-muted mb-0">Total Ativos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['ativos'] ?></h3>
                                    <p class="text-muted mb-0">Funcionando</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--info-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['manutencao'] ?></h3>
                                    <p class="text-muted mb-0">Em Manutenção</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);">
                                    <i class="bi bi-person-gear"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['meus_equipamentos'] ?></h3>
                                    <p class="text-muted mb-0">Sob Responsabilidade</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Stats para Usuário comum -->
                    <div class="col-lg-4 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-hdd-stack"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total'] ?></h3>
                                    <p class="text-muted mb-0">Equipamentos Disponíveis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);">
                                    <i class="bi bi-cup-straw"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['bebedouros'] ?></h3>
                                    <p class="text-muted mb-0">Bebedouros</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                    <i class="bi bi-shower"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['duchas'] ?></h3>
                                    <p class="text-muted mb-0">Duchas</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Mensagem informativa para usuários limitados -->
            <?php if ($_SESSION['user_type'] === 'usuario'): ?>
            <div class="alert alert-info fade-in">
                <div class="d-flex align-items-start">
                    <i class="bi bi-info-circle fs-5 me-3 mt-1"></i>
                    <div>
                        <h6 class="mb-2">Visualização de Equipamentos</h6>
                        <p class="mb-0">
                            Você está visualizando apenas equipamentos ativos. Para gerenciar equipamentos, 
                            entre em contato com um administrador ou técnico.
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="search-filters fade-in">
                <form method="GET" class="row g-3">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label">Buscar Equipamento</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Código, localização..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="tipo">
                            <option value="">Todos</option>
                            <option value="bebedouro" <?= $tipo_filter === 'bebedouro' ? 'selected' : '' ?>>Bebedouro</option>
                            <option value="ducha" <?= $tipo_filter === 'ducha' ? 'selected' : '' ?>>Ducha</option>
                        </select>
                    </div>
                    <?php if (hasPermission('equipamentos', 'manage')): ?>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">Todos</option>
                            <option value="ativo" <?= $status_filter === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= $status_filter === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                            <option value="manutencao" <?= $status_filter === 'manutencao' ? 'selected' : '' ?>>Manutenção</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-lg-2 col-md-6 col-6">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary-custom w-100">
                            <i class="bi bi-funnel me-1"></i><span class="d-none d-sm-inline">Filtrar</span>
                        </button>
                    </div>
                    <div class="col-lg-2 col-md-6 col-6">
                        <label class="form-label">&nbsp;</label>
                        <a href="equipamentos.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-clockwise me-1"></i><span class="d-none d-sm-inline">Limpar</span>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Equipment Table -->
            <div class="card table-card fade-in">
                <div class="card-header">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
                        <h5 class="mb-0"><i class="bi bi-hdd-stack me-2"></i>Lista de Equipamentos</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if (hasPermission('relatorios', 'view')): ?>
                            <button class="btn btn-report btn-sm" onclick="generateReport()" 
                                    title="Gerar relatório">
                                <i class="bi bi-file-earmark-pdf me-1"></i>
                                <span class="d-none d-sm-inline">Relatório</span>
                            </button>
                            <?php endif; ?>
                            <?php if (hasPermission('equipamentos', 'create')): ?>
                            <button class="btn btn-primary-custom btn-sm" data-bs-toggle="modal" data-bs-target="#equipmentModal" onclick="openModal('create')">
                                <i class="bi bi-plus me-1"></i>
                                <span class="d-none d-sm-inline">Novo</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($equipamentos)): ?>
                        <div class="text-center p-5 text-muted">
                            <i class="bi bi-inbox fs-1 mb-3 opacity-50"></i>
                            <h5>Nenhum equipamento encontrado</h5>
                            <p>Não há equipamentos que correspondam aos filtros aplicados.</p>
                            <?php if (hasPermission('equipamentos', 'create')): ?>
                            <button class="btn btn-primary-custom mt-2" data-bs-toggle="modal" data-bs-target="#equipmentModal" onclick="openModal('create')">
                                <i class="bi bi-plus me-1"></i>Cadastrar Primeiro Equipamento
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="bi bi-tag me-1"></i>Código</th>
                                        <th><i class="bi bi-collection me-1"></i>Tipo</th>
                                        <th class="d-none d-md-table-cell"><i class="bi bi-geo-alt me-1"></i>Localização</th>
                                        <th><i class="bi bi-flag me-1"></i>Status</th>
                                        <?php if (hasPermission('equipamentos', 'manage')): ?>
                                        <th class="d-none d-xl-table-cell"><i class="bi bi-calendar me-1"></i>Instalação</th>
                                        <?php endif; ?>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($equipamentos as $eq): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($eq['codigo']) ?></strong>
                                                <div class="d-md-none">
                                                    <small class="text-muted"><?= htmlspecialchars($eq['localizacao']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-<?= $eq['tipo'] === 'bebedouro' ? 'cup-straw' : 'shower' ?> me-2 text-primary fs-5"></i>
                                                    <span class="d-none d-sm-inline"><?= ucfirst($eq['tipo']) ?></span>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div>
                                                    <?= htmlspecialchars($eq['localizacao']) ?>
                                                    <?php if ($eq['endereco'] && hasPermission('equipamentos', 'manage')): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars(substr($eq['endereco'], 0, 30)) ?><?= strlen($eq['endereco']) > 30 ? '...' : '' ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?= getStatusBadge($eq['status']) ?> rounded-pill">
                                                    <?php if ($eq['status'] === 'ativo'): ?>
                                                        Ativo
                                                    <?php elseif ($eq['status'] === 'manutencao'): ?>
                                                        Manutenção
                                                    <?php else: ?>
                                                        <?= hasPermission('equipamentos', 'manage') ? 'Inativo' : '-' ?>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            
                                            <?php if (hasPermission('equipamentos', 'manage')): ?>
                                            <td class="d-none d-xl-table-cell">
                                                <small class="text-muted">
                                                    <?= $eq['data_instalacao'] ? date('d/m/Y', strtotime($eq['data_instalacao'])) : '-' ?>
                                                </small>
                                            </td>
                                            <?php endif; ?>
                                            
                                            <td>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            onclick="viewEquipment(<?= htmlspecialchars(json_encode($eq)) ?>)"
                                                            title="Ver detalhes">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if (hasPermission('equipamentos', 'edit')): ?>
                                                    <button class="btn btn-sm btn-outline-warning" 
                                                            onclick="editEquipment(<?= htmlspecialchars(json_encode($eq)) ?>)"
                                                            title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if (hasPermission('equipamentos', 'delete')): ?>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteEquipment(<?= $eq['id'] ?>, '<?= htmlspecialchars($eq['codigo']) ?>')"
                                                            title="Excluir">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center p-3 border-top gap-3">
                                <small class="text-muted text-center">
                                    Mostrando <?= ($page - 1) * $per_page + 1 ?> a <?= min($page * $per_page, $total) ?> de <?= $total ?> registros
                                </small>
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&tipo=<?= urlencode($tipo_filter) ?>&status=<?= urlencode($status_filter) ?>">‹</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 1); $i <= min($total_pages, $page + 1); $i++): ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&tipo=<?= urlencode($tipo_filter) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&tipo=<?= urlencode($tipo_filter) ?>&status=<?= urlencode($status_filter) ?>">›</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer-area">
            <div class="container-fluid">
                <div class="text-center py-3">
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <p class="mb-1 text-muted">
                                <small>
                                    Desenvolvido por 
                                    <a href="https://i9script.com" target="_blank" class="footer-link">
                                        <strong>i9Script Technology</strong>
                                    </a>
                                </small>
                            </p>
                        </div>
                        <div class="col-12 col-md-6">
                            <p class="mb-1 text-muted">
                                <small>© Hidro Evolution 2025 - Todos os direitos reservados</small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <!-- Equipment Modal -->
    <?php if (hasPermission('equipamentos', 'create') || hasPermission('equipamentos', 'edit')): ?>
    <div class="modal fade" id="equipmentModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Equipamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="equipmentForm">
                    <input type="hidden" name="action" id="modalAction">
                    <input type="hidden" name="id" id="modalId">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Código *</label>
                                <input type="text" class="form-control" name="codigo" id="modalCodigo" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tipo *</label>
                                <select class="form-select" name="tipo" id="modalTipo" required>
                                    <option value="">Selecione...</option>
                                    <option value="bebedouro">Bebedouro</option>
                                    <option value="ducha">Ducha</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Localização *</label>
                                <input type="text" class="form-control" name="localizacao" id="modalLocalizacao" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Endereço</label>
                                <input type="text" class="form-control" name="endereco" id="modalEndereco">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Marca</label>
                                <input type="text" class="form-control" name="marca" id="modalMarca">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Modelo</label>
                                <input type="text" class="form-control" name="modelo" id="modalModelo">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Latitude</label>
                                <input type="number" step="any" class="form-control" name="latitude" id="modalLatitude">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Longitude</label>
                                <input type="number" step="any" class="form-control" name="longitude" id="modalLongitude">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="modalStatus">
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                    <option value="manutencao">Em Manutenção</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Data Instalação</label>
                                <input type="date" class="form-control" name="data_instalacao" id="modalDataInstalacao">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fotos</label>
                                <input type="file" class="form-control" name="equipment_photos[]" id="modalPhotos" multiple accept="image/*">
                                <div class="form-text">Máximo 10 fotos, 5MB cada</div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Google Maps URL</label>
                                <input type="url" class="form-control" name="google_maps_url" id="modalGoogleMapsUrl">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Observações</label>
                                <textarea class="form-control" name="observacoes" id="modalObservacoes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom" id="modalSubmit">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Equipamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewModalBody">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <?php if (hasPermission('equipamentos', 'edit')): ?>
                    <button type="button" class="btn btn-primary-custom" id="editFromViewBtn">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Form -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ========== VARIÁVEIS GLOBAIS ==========
        let currentViewEquipmentData = null;

        // ========== INICIALIZAÇÃO ==========
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
        });

        function initializeEventListeners() {
            // Sidebar toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    overlay.classList.toggle('show');
                });
            }

            // Close sidebar quando clicar no overlay
            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                });
            }

            // Event listener para botão de editar no modal de visualização
            const editFromViewBtn = document.getElementById('editFromViewBtn');
            if (editFromViewBtn) {
                editFromViewBtn.addEventListener('click', function() {
                    if (currentViewEquipmentData) {
                        // Fechar modal de visualização
                        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewModal'));
                        if (viewModal) viewModal.hide();
                        
                        // Aguardar o modal fechar completamente antes de abrir o de edição
                        setTimeout(() => {
                            editEquipment(currentViewEquipmentData);
                        }, 300);
                    }
                });
            }

            // Validação do formulário
            const equipmentForm = document.getElementById('equipmentForm');
            if (equipmentForm) {
                equipmentForm.addEventListener('submit', handleFormSubmit);
            }
        }

        // ========== FUNÇÕES PARA MODAL ==========
        function openModal(action, data = null) {
            const modal = document.getElementById('equipmentModal');
            const form = document.getElementById('equipmentForm');
            
            // Reset form
            form.reset();
            
            // Clear validações anteriores
            document.querySelectorAll('.is-invalid').forEach(field => {
                field.classList.remove('is-invalid');
            });
            document.querySelectorAll('.invalid-feedback').forEach(feedback => {
                feedback.remove();
            });
            
            if (action === 'create') {
                document.getElementById('modalTitle').textContent = 'Novo Equipamento';
                document.getElementById('modalAction').value = 'create';
                document.getElementById('modalSubmit').innerHTML = '<i class="bi bi-check me-1"></i>Cadastrar';
                document.getElementById('modalId').value = '';
            } else if (action === 'edit') {
                document.getElementById('modalTitle').textContent = 'Editar Equipamento';
                document.getElementById('modalAction').value = 'update';
                document.getElementById('modalSubmit').innerHTML = '<i class="bi bi-check me-1"></i>Atualizar';
                
                // Fill form with data
                if (data) {
                    document.getElementById('modalCodigo').value = data.codigo || '';
                    document.getElementById('modalTipo').value = data.tipo || '';
                    document.getElementById('modalLocalizacao').value = data.localizacao || '';
                    document.getElementById('modalEndereco').value = data.endereco || '';
                    document.getElementById('modalMarca').value = data.marca || '';
                    document.getElementById('modalModelo').value = data.modelo || '';
                    document.getElementById('modalLatitude').value = data.latitude || '';
                    document.getElementById('modalLongitude').value = data.longitude || '';
                    document.getElementById('modalDataInstalacao').value = data.data_instalacao || '';
                    document.getElementById('modalStatus').value = data.status || '';
                    document.getElementById('modalObservacoes').value = data.observacoes || '';
                    document.getElementById('modalGoogleMapsUrl').value = data.google_maps_url || '';
                    document.getElementById('modalId').value = data.id;
                }
            }
            
            new bootstrap.Modal(modal).show();
        }

        // ========== FUNÇÕES DE VISUALIZAÇÃO ==========
        function viewEquipment(data) {
            // Store current equipment data for editing
            currentViewEquipmentData = data;
            
            // Show loading
            document.getElementById('viewModalBody').innerHTML = `
                <div class="text-center p-5">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p>Carregando detalhes do equipamento...</p>
                </div>
            `;
            
            new bootstrap.Modal(document.getElementById('viewModal')).show();
            
            // Load complete equipment details
            loadCompleteEquipmentDetails(data.id);
        }

        async function loadCompleteEquipmentDetails(equipmentId) {
            try {
                // Load equipment photos
                const photosResponse = await fetch(`equipamentos.php?ajax_action=get_photos&equipamento_id=${equipmentId}`);
                const photosData = await photosResponse.json();
                const photos = photosData.success ? photosData.photos : [];
                
                // Load equipment materials (if endpoint exists)
                let materials = [];
                try {
                    const materialsResponse = await fetch(`equipamentos.php?ajax_action=get_equipment_materials&equipamento_id=${equipmentId}`);
                    if (materialsResponse.ok) {
                        const materialsData = await materialsResponse.json();
                        materials = materialsData.success ? materialsData.materials : [];
                    }
                } catch (e) {
                    console.log('Materials endpoint not available');
                }
                
                // Load equipment services (if endpoint exists)
                let services = [];
                try {
                    const servicesResponse = await fetch(`equipamentos.php?ajax_action=get_equipment_services&equipamento_id=${equipmentId}`);
                    if (servicesResponse.ok) {
                        const servicesData = await servicesResponse.json();
                        services = servicesData.success ? servicesData.services : [];
                    }
                } catch (e) {
                    console.log('Services endpoint not available');
                }
                
                renderCompleteEquipmentView(currentViewEquipmentData, photos, materials, services);
                
            } catch (error) {
                console.error('Error loading equipment details:', error);
                document.getElementById('viewModalBody').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        Erro ao carregar detalhes do equipamento.
                    </div>
                `;
            }
        }

        function renderCompleteEquipmentView(data, photos, materials, services) {
            const content = `
                <!-- Informações Básicas -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações Básicas</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr><td><strong>Código:</strong></td><td>${data.codigo || '-'}</td></tr>
                                    <tr><td><strong>Tipo:</strong></td><td>
                                        <i class="bi bi-${data.tipo === 'bebedouro' ? 'cup-straw' : 'shower'} me-2"></i>
                                        ${data.tipo ? data.tipo.charAt(0).toUpperCase() + data.tipo.slice(1) : '-'}
                                    </td></tr>
                                    <tr><td><strong>Localização:</strong></td><td>${data.localizacao || '-'}</td></tr>
                                    <tr><td><strong>Status:</strong></td><td>
                                        <span class="badge ${getStatusBadgeClass(data.status)} rounded-pill">
                                            ${data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : '-'}
                                        </span>
                                    </td></tr>
                                    <tr><td><strong>Data Instalação:</strong></td><td>${data.data_instalacao ? new Date(data.data_instalacao).toLocaleDateString('pt-BR') : '-'}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Detalhes Técnicos</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr><td><strong>Marca:</strong></td><td>${data.marca || '-'}</td></tr>
                                    <tr><td><strong>Modelo:</strong></td><td>${data.modelo || '-'}</td></tr>
                                    <tr><td><strong>Endereço:</strong></td><td>${data.endereco || '-'}</td></tr>
                                    <tr><td><strong>Coordenadas:</strong></td><td>
                                        ${data.latitude && data.longitude 
                                            ? `${data.latitude}, ${data.longitude}` 
                                            : '-'}
                                    </td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Fotos do Equipamento -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-camera me-2"></i>
                                    Fotos do Equipamento (${photos.length}/10)
                                </h6>
                            </div>
                            <div class="card-body">
                                ${photos.length > 0 ? `
                                    <div class="row g-3">
                                        ${photos.map((photo, index) => `
                                            <div class="col-lg-3 col-md-4 col-sm-6">
                                                <div class="photo-gallery-item">
                                                    <img src="${photo.thumb_url}" alt="Foto ${index + 1}" 
                                                         onclick="viewPhotoFull('${photo.photo_url}')"
                                                         style="cursor: pointer;">
                                                    <div class="photo-overlay">
                                                        <div class="photo-info">
                                                            <div>${photo.tipo_foto}</div>
                                                            <small>${photo.file_size_formatted}</small>
                                                            <small class="d-block">${formatDate(photo.data_upload)}</small>
                                                            ${photo.uploaded_by_name ? `<small class="d-block">Por: ${photo.uploaded_by_name}</small>` : ''}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : '<p class="text-muted text-center mb-0">Nenhuma foto disponível.</p>'}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Observações -->
                ${data.observacoes ? `
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="bi bi-chat-text me-2"></i>Observações</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0">${data.observacoes}</p>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                <!-- Materiais/Peças -->
                ${materials.length > 0 ? `
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="bi bi-tools me-2"></i>Materiais/Peças Utilizadas</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Material</th>
                                                <th>Quantidade</th>
                                                <th>Observações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${materials.map(material => `
                                                <tr>
                                                    <td>${material.nome}</td>
                                                    <td>${material.quantidade} ${material.unidade_medida || ''}</td>
                                                    <td>${material.observacoes || '-'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                <!-- Serviços -->
                ${services.length > 0 ? `
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header" style="background: #6f42c1; color: white;">
                                <h6 class="mb-0"><i class="bi bi-wrench me-2"></i>Serviços Executados</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Serviço</th>
                                                <th>Observações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${services.map(service => `
                                                <tr>
                                                    <td>${service.nome}</td>
                                                    <td>${service.observacoes || '-'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                <!-- Google Maps -->
                ${data.google_maps_url ? `
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header" style="background: #198754; color: white;">
                                <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Localização</h6>
                            </div>
                            <div class="card-body">
                                <a href="${data.google_maps_url}" target="_blank" class="btn btn-outline-success">
                                    <i class="bi bi-map me-1"></i>Abrir no Google Maps
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('viewModalBody').innerHTML = content;
        }

        function viewPhotoFull(photoUrl) {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Visualizar Foto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${photoUrl}" class="img-fluid" alt="Foto do equipamento">
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        }

        // Helper functions
        function getStatusBadgeClass(status) {
            const badges = {
                'ativo': 'bg-success',
                'inativo': 'bg-secondary',
                'manutencao': 'bg-warning'
            };
            return badges[status] || 'bg-secondary';
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        // ========== FUNÇÕES DE EDIÇÃO ==========
        function editEquipment(data) {
            openModal('edit', data);
        }

        // ========== OUTRAS FUNÇÕES ==========
        function deleteEquipment(id, codigo) {
            if (confirm(`Tem certeza que deseja excluir o equipamento "${codigo}"?\n\nEsta ação não pode ser desfeita.`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // ========== FUNÇÕES DE RELATÓRIO ==========
        function generateReport() {
            // Capturar filtros atuais
            const urlParams = new URLSearchParams(window.location.search);
            const search = urlParams.get('search') || '';
            const tipo = urlParams.get('tipo') || '';
            const status = urlParams.get('status') || '';
            
            // Mostrar loading
            const reportBtn = document.querySelector('.btn-report');
            if (reportBtn) {
                const originalText = reportBtn.innerHTML;
                reportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Gerando...';
                reportBtn.disabled = true;
                
                // Construir URL do relatório com os filtros
                let reportUrl = 'equipamentos.php?generate_report=true';
                if (search) reportUrl += '&search=' + encodeURIComponent(search);
                if (tipo) reportUrl += '&tipo=' + encodeURIComponent(tipo);
                if (status) reportUrl += '&status=' + encodeURIComponent(status);
                
                // Abrir em nova janela/aba para visualização
                const reportWindow = window.open(reportUrl, '_blank');
                
                // Restaurar botão após 2 segundos
                setTimeout(() => {
                    reportBtn.innerHTML = originalText;
                    reportBtn.disabled = false;
                }, 2000);
                
                // Se a janela não abrir (popup blocker), redirecionar na mesma janela
                if (!reportWindow || reportWindow.closed || typeof reportWindow.closed == 'undefined') {
                    setTimeout(() => {
                        window.open(reportUrl, '_blank') || (window.location.href = reportUrl);
                    }, 1000);
                }
            }
        }

        // ========== VALIDAÇÃO E ENVIO DE FORMULÁRIO ==========
        function handleFormSubmit(e) {
            const codigo = document.getElementById('modalCodigo').value.trim();
            const tipo = document.getElementById('modalTipo').value;
            const localizacao = document.getElementById('modalLocalizacao').value.trim();
            
            // Reset validações anteriores
            document.querySelectorAll('.is-invalid').forEach(field => {
                field.classList.remove('is-invalid');
            });
            document.querySelectorAll('.invalid-feedback').forEach(feedback => {
                feedback.remove();
            });
            
            let isValid = true;
            
            // Validação básica
            if (!codigo) {
                showFieldError('modalCodigo', 'Código é obrigatório');
                isValid = false;
            } else if (codigo.length > 50) {
                showFieldError('modalCodigo', 'Código deve ter no máximo 50 caracteres');
                isValid = false;
            }
            
            if (!tipo) {
                showFieldError('modalTipo', 'Tipo é obrigatório');
                isValid = false;
            }
            
            if (!localizacao) {
                showFieldError('modalLocalizacao', 'Localização é obrigatória');
                isValid = false;
            } else if (localizacao.length > 200) {
                showFieldError('modalLocalizacao', 'Localização deve ter no máximo 200 caracteres');
                isValid = false;
            }
            
            // Validação de fotos
            const photosInput = document.getElementById('modalPhotos');
            if (photosInput && photosInput.files.length > 0) {
                if (photosInput.files.length > 10) {
                    e.preventDefault();
                    showNotification('Máximo de 10 fotos permitidas.', 'danger');
                    return;
                }
                
                // Validação de tamanho de arquivos
                for (let file of photosInput.files) {
                    if (file.size > 5 * 1024 * 1024) { // 5MB
                        e.preventDefault();
                        showNotification(`Arquivo "${file.name}" é muito grande. Máximo: 5MB`, 'danger');
                        return;
                    }
                }
            }
            
            if (!isValid) {
                e.preventDefault();
            } else {
                // Loading state
                const submitBtn = document.getElementById('modalSubmit');
                if (submitBtn) {
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Salvando...';
                }
            }
        }

        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            
            field.classList.add('is-invalid');
            
            let feedback = field.parentNode.querySelector('.invalid-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                field.parentNode.appendChild(feedback);
            }
            feedback.textContent = message;
        }

        // ========== FUNÇÕES UTILITÁRIAS ==========
        function showNotification(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;';
            alertDiv.innerHTML = `
                <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // ========== AUTO-DISMISS ALERTS ==========
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            });
        }, 5000);

        // ========== ANIMATION ON SCROLL ==========
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // ========== RESPONSIVE HANDLING ==========
        function handleResize() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        }

        window.addEventListener('resize', handleResize);
        
        // ========== PERFORMANCE MONITORING ==========
        window.addEventListener('load', function() {
            const loadTime = performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart;
            console.log(`Equipamentos page loaded in ${loadTime}ms`);
            
            // Trigger animations after load
            setTimeout(() => {
                document.querySelectorAll('.fade-in').forEach(el => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                });
            }, 100);
        });

        // ========== TOUCH IMPROVEMENTS ==========
        document.addEventListener('touchstart', function() {
            // Empty function to improve touch responsiveness on iOS
        }, { passive: true });

    </script>

    <!-- Botão Flutuante (FAB) para Mobile - Novo Equipamento -->
    <?php if (hasPermission('equipamentos', 'create')): ?>
    <button class="fab-button d-md-none"
            onclick="openModal('create')"
            data-tooltip="Novo Equipamento"
            aria-label="Criar novo equipamento">
        <i class="bi bi-plus"></i>
    </button>
    <?php endif; ?>

</body>
</html>