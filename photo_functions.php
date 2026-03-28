<?php
/**
 * Funções para gerenciamento de fotos no HidroApp
 */

// Diretório para upload de fotos
define('PHOTOS_DIR', 'uploads/photos/');
define('MAX_PHOTO_SIZE', 5242880); // 5MB
define('ALLOWED_PHOTO_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

/**
 * Obtém limites de upload baseado no tipo de usuário
 */
function getUserUploadLimits($user_type = 'usuario') {
    $limits = [
        'admin' => [
            'max_size' => 10485760, // 10MB
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'],
            'max_files' => 10
        ],
        'tecnico' => [
            'max_size' => 8388608, // 8MB
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_files' => 8
        ],
        'usuario' => [
            'max_size' => 5242880, // 5MB
            'allowed_types' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_files' => 5
        ]
    ];
    
    return isset($limits[$user_type]) ? $limits[$user_type] : $limits['usuario'];
}

/**
 * Formatar tamanho de arquivo em formato legível
 */
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Cria os diretórios necessários para fotos
 */
function createPhotosDirectories() {
    $dirs = [
        PHOTOS_DIR,
        PHOTOS_DIR . 'equipamentos/',
        PHOTOS_DIR . 'manutencoes/',
        PHOTOS_DIR . 'thumbs/',
        PHOTOS_DIR . 'thumbs/equipamentos/',
        PHOTOS_DIR . 'thumbs/manutencoes/'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                if (function_exists('logMessage')) {
                    logMessage("Erro ao criar diretório: $dir", 'ERROR');
                }
            }
        }
    }
    
    // Criar arquivo .htaccess para proteger o diretório
    $htaccess_content = "Options -Indexes\n";
    $htaccess_content .= "Order allow,deny\n";
    $htaccess_content .= "Allow from all\n";
    $htaccess_content .= "<Files ~ \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">\n";
    $htaccess_content .= "    deny from all\n";
    $htaccess_content .= "</Files>\n";
    
    $htaccess_path = PHOTOS_DIR . '.htaccess';
    if (!file_exists($htaccess_path)) {
        file_put_contents($htaccess_path, $htaccess_content);
    }
}

/**
 * Valida arquivo de foto
 */
function validatePhotoFile($file, $user_type = 'usuario') {
    $errors = [];
    
    // Verificar se o arquivo foi enviado
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'Arquivo muito grande.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'Upload incompleto.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'Nenhum arquivo enviado.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                $errors[] = 'Erro no servidor durante upload.';
                break;
            default:
                $errors[] = 'Erro desconhecido no upload.';
                break;
        }
        return $errors;
    }
    
    // Verificar se arquivo existe
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'Arquivo inválido.';
        return $errors;
    }
    
    // Verificar tamanho
    $upload_limits = getUserUploadLimits($user_type);
    if ($file['size'] > $upload_limits['max_size']) {
        $max_size_formatted = formatFileSize($upload_limits['max_size']);
        $errors[] = "Arquivo muito grande. Tamanho máximo: {$max_size_formatted}";
    }
    
    // Verificar tipo
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $upload_limits['allowed_types'])) {
        $errors[] = 'Tipo de arquivo não permitido. Permitidos: ' . implode(', ', $upload_limits['allowed_types']);
    }
    
    // Verificar se é realmente uma imagem (para arquivos de imagem)
    if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            $errors[] = 'O arquivo não é uma imagem válida.';
        } else {
            // Verificar dimensões mínimas
            if ($image_info[0] < 50 || $image_info[1] < 50) {
                $errors[] = 'Imagem muito pequena (mínimo 50x50 pixels).';
            }
            
            // Verificar dimensões máximas
            if ($image_info[0] > 5000 || $image_info[1] > 5000) {
                $errors[] = 'Imagem muito grande (máximo 5000x5000 pixels).';
            }
        }
    }
    
    return $errors;
}

/**
 * Faz upload de foto do equipamento
 */
function uploadEquipmentPhoto($equipamento_id, $file, $tipo_foto = 'principal', $descricao = '') {
    try {
        createPhotosDirectories();
        
        $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'usuario';
        $errors = validatePhotoFile($file, $user_type);
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_filename = 'equip_' . $equipamento_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = PHOTOS_DIR . 'equipamentos/' . $new_filename;
        
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Erro ao salvar o arquivo.');
        }
        
        // Criar thumbnail
        createThumbnail($upload_path, PHOTOS_DIR . 'thumbs/equipamentos/' . $new_filename);
        
        // Salvar no banco de dados
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        Database::query(
            "INSERT INTO equipamento_fotos (equipamento_id, tipo_foto, nome_arquivo, caminho_arquivo, descricao, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)",
            [$equipamento_id, $tipo_foto, $file['name'], $upload_path, $descricao, $user_id]
        );
        
        // Obter o ID da foto inserida
        $result = Database::fetch("SELECT LAST_INSERT_ID() as id");
        $photo_id = $result['id'];
        
        if (function_exists('logMessage') && isset($_SESSION['user_name'])) {
            logMessage("Foto do equipamento {$equipamento_id} enviada por {$_SESSION['user_name']}", 'INFO');
        }
        
        return [
            'success' => true,
            'photo_id' => $photo_id,
            'filename' => $new_filename,
            'path' => $upload_path
        ];
        
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro no upload de foto: ' . $e->getMessage(), 'ERROR');
        }
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Faz upload de foto da manutenção
 */
function uploadMaintenancePhoto($manutencao_id, $file, $tipo_foto = 'durante', $descricao = '', $ordem = 0) {
    try {
        createPhotosDirectories();
        
        $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'usuario';
        $errors = validatePhotoFile($file, $user_type);
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_filename = 'manut_' . $manutencao_id . '_' . $tipo_foto . '_' . time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = PHOTOS_DIR . 'manutencoes/' . $new_filename;
        
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Erro ao salvar o arquivo.');
        }
        
        // Criar thumbnail
        createThumbnail($upload_path, PHOTOS_DIR . 'thumbs/manutencoes/' . $new_filename);
        
        // Salvar no banco
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        Database::query(
            "INSERT INTO manutencao_fotos (manutencao_id, tipo_foto, nome_arquivo, caminho_arquivo, descricao, uploaded_by, ordem) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$manutencao_id, $tipo_foto, $file['name'], $upload_path, $descricao, $user_id, $ordem]
        );
        
        // Obter o ID da foto inserida
        $result = Database::fetch("SELECT LAST_INSERT_ID() as id");
        $photo_id = $result['id'];
        
        if (function_exists('logMessage') && isset($_SESSION['user_name'])) {
            logMessage("Foto da manutenção {$manutencao_id} enviada por {$_SESSION['user_name']}", 'INFO');
        }
        
        return [
            'success' => true,
            'photo_id' => $photo_id,
            'filename' => $new_filename,
            'path' => $upload_path
        ];
        
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro no upload de foto: ' . $e->getMessage(), 'ERROR');
        }
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Cria thumbnail de uma imagem
 */
function createThumbnail($source_path, $thumb_path, $max_width = 300, $max_height = 300) {
    try {
        if (!file_exists($source_path)) {
            return false;
        }
        
        $image_info = getimagesize($source_path);
        if ($image_info === false) {
            return false;
        }
        
        $mime_type = $image_info['mime'];
        $source_image = null;
        
        // Criar imagem source baseada no tipo
        switch ($mime_type) {
            case 'image/jpeg':
                $source_image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $source_image = imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $source_image = imagecreatefromgif($source_path);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $source_image = imagecreatefromwebp($source_path);
                }
                break;
            default:
                return false;
        }
        
        if (!$source_image) {
            return false;
        }
        
        $source_width = $image_info[0];
        $source_height = $image_info[1];
        
        // Calcular dimensões do thumbnail mantendo a proporção
        $ratio = min($max_width / $source_width, $max_height / $source_height);
        $thumb_width = intval($source_width * $ratio);
        $thumb_height = intval($source_height * $ratio);
        
        // Criar thumbnail
        $thumb_image = imagecreatetruecolor($thumb_width, $thumb_height);
        if (!$thumb_image) {
            imagedestroy($source_image);
            return false;
        }
        
        // Preservar transparência para PNG, GIF e WebP
        if (in_array($mime_type, ['image/png', 'image/gif', 'image/webp'])) {
            imagealphablending($thumb_image, false);
            imagesavealpha($thumb_image, true);
            $transparent = imagecolorallocatealpha($thumb_image, 255, 255, 255, 127);
            imagefill($thumb_image, 0, 0, $transparent);
        }
        
        // Redimensionar imagem
        imagecopyresampled(
            $thumb_image, $source_image,
            0, 0, 0, 0,
            $thumb_width, $thumb_height,
            $source_width, $source_height
        );
        
        // Criar diretório se não existir
        $thumb_dir = dirname($thumb_path);
        if (!is_dir($thumb_dir)) {
            mkdir($thumb_dir, 0755, true);
        }
        
        // Salvar thumbnail baseado no tipo original
        $result = false;
        switch ($mime_type) {
            case 'image/jpeg':
                $result = imagejpeg($thumb_image, $thumb_path, 85);
                break;
            case 'image/png':
                $result = imagepng($thumb_image, $thumb_path);
                break;
            case 'image/gif':
                $result = imagegif($thumb_image, $thumb_path);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    $result = imagewebp($thumb_image, $thumb_path, 85);
                }
                break;
        }
        
        // Limpar memória
        imagedestroy($source_image);
        imagedestroy($thumb_image);
        
        return $result;
        
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro ao criar thumbnail: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Busca fotos do equipamento
 */
function getEquipmentPhotos($equipamento_id, $tipo_foto = null) {
    try {
        $where_clause = "equipamento_id = ? AND ativo = 1";
        $params = [$equipamento_id];
        
        if ($tipo_foto) {
            $where_clause .= " AND tipo_foto = ?";
            $params[] = $tipo_foto;
        }
        
        return Database::fetchAll(
            "SELECT * FROM equipamento_fotos WHERE {$where_clause} ORDER BY data_upload DESC",
            $params
        );
        
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro ao buscar fotos do equipamento: ' . $e->getMessage(), 'ERROR');
        }
        return [];
    }
}

/**
 * Busca fotos da manutenção
 */
function getMaintenancePhotos($manutencao_id, $tipo_foto = null) {
    try {
        $where_clause = "manutencao_id = ? AND ativo = 1";
        $params = [$manutencao_id];
        
        if ($tipo_foto) {
            $where_clause .= " AND tipo_foto = ?";
            $params[] = $tipo_foto;
        }
        
        return Database::fetchAll(
            "SELECT * FROM manutencao_fotos WHERE {$where_clause} ORDER BY tipo_foto, ordem, data_upload",
            $params
        );
        
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro ao buscar fotos da manutenção: ' . $e->getMessage(), 'ERROR');
        }
        return [];
    }
}

/**
 * Exclui foto do equipamento
 */
function deleteEquipmentPhoto($photo_id, $user_type) {
    try {
        // Verificar permissões
        if (function_exists('hasPermission') && !hasPermission('equipamentos', 'edit')) {
            throw new Exception('Sem permissão para excluir fotos.');
        }
        
        // Buscar foto
        $photo = Database::fetch("SELECT * FROM equipamento_fotos WHERE id = ?", [$photo_id]);
        if (!$photo) {
            throw new Exception('Foto não encontrada.');
        }
        
        // Verificar se é o dono ou admin
        if ($user_type !== 'admin' && isset($_SESSION['user_id']) && $photo['uploaded_by'] != $_SESSION['user_id']) {
            throw new Exception('Você só pode excluir suas próprias fotos.');
        }
        
        // Excluir arquivos físicos
        if (file_exists($photo['caminho_arquivo'])) {
            unlink($photo['caminho_arquivo']);
        }
        
        $thumb_path = str_replace('/equipamentos/', '/thumbs/equipamentos/', $photo['caminho_arquivo']);
        if (file_exists($thumb_path)) {
            unlink($thumb_path);
        }
        
        // Excluir do banco
        Database::query("DELETE FROM equipamento_fotos WHERE id = ?", [$photo_id]);
        
        if (function_exists('logMessage')) {
            logMessage("Foto do equipamento excluída: {$photo['nome_arquivo']}", 'INFO');
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro ao excluir foto: ' . $e->getMessage(), 'ERROR');
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Exclui foto da manutenção
 */
function deleteMaintenancePhoto($photo_id, $user_type) {
    try {
        // Verificar permissões
        if (function_exists('hasPermission') && !hasPermission('manutencoes', 'edit')) {
            throw new Exception('Sem permissão para excluir fotos.');
        }
        
        // Buscar foto
        $photo = Database::fetch("SELECT * FROM manutencao_fotos WHERE id = ?", [$photo_id]);
        if (!$photo) {
            throw new Exception('Foto não encontrada.');
        }
        
        // Verificar se é o dono ou admin
        if ($user_type !== 'admin' && isset($_SESSION['user_id']) && $photo['uploaded_by'] != $_SESSION['user_id']) {
            throw new Exception('Você só pode excluir suas próprias fotos.');
        }
        
        // Excluir arquivos físicos
        if (file_exists($photo['caminho_arquivo'])) {
            unlink($photo['caminho_arquivo']);
        }
        
        $thumb_path = str_replace('/manutencoes/', '/thumbs/manutencoes/', $photo['caminho_arquivo']);
        if (file_exists($thumb_path)) {
            unlink($thumb_path);
        }
        
        // Excluir do banco
        Database::query("DELETE FROM manutencao_fotos WHERE id = ?", [$photo_id]);
        
        if (function_exists('logMessage')) {
            logMessage("Foto da manutenção excluída: {$photo['nome_arquivo']}", 'INFO');
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro ao excluir foto: ' . $e->getMessage(), 'ERROR');
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Gera URL segura para exibir foto
 */
function getPhotoUrl($photo_path, $thumb = false) {
    if (empty($photo_path)) {
        return 'assets/images/no-image.png'; // Imagem padrão
    }
    
    if ($thumb) {
        $photo_path = str_replace(['/equipamentos/', '/manutencoes/'], ['/thumbs/equipamentos/', '/thumbs/manutencoes/'], $photo_path);
    }
    
    if (!file_exists($photo_path)) {
        return 'assets/images/no-image.png'; // Imagem padrão
    }
    
    return $photo_path . '?t=' . filemtime($photo_path); // Cache busting
}

/**
 * Busca informações do contrato ativo
 */
function getActiveContract() {
    try {
        return Database::fetch("SELECT * FROM contratos WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro ao buscar contrato ativo: ' . $e->getMessage(), 'ERROR');
        }
        return null;
    }
}

/**
 * Limpa fotos órfãs (arquivos sem registro no banco)
 */
function cleanOrphanPhotos() {
    try {
        $cleaned = 0;
        
        // Limpar fotos de equipamentos
        $equipment_photos_dir = PHOTOS_DIR . 'equipamentos/';
        if (is_dir($equipment_photos_dir)) {
            $files = scandir($equipment_photos_dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                $file_path = $equipment_photos_dir . $file;
                if (is_file($file_path)) {
                    // Verificar se existe no banco
                    $exists = Database::fetch(
                        "SELECT id FROM equipamento_fotos WHERE caminho_arquivo = ?",
                        [$file_path]
                    );
                    
                    if (!$exists) {
                        unlink($file_path);
                        $cleaned++;
                    }
                }
            }
        }
        
        // Limpar fotos de manutenções
        $maintenance_photos_dir = PHOTOS_DIR . 'manutencoes/';
        if (is_dir($maintenance_photos_dir)) {
            $files = scandir($maintenance_photos_dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                $file_path = $maintenance_photos_dir . $file;
                if (is_file($file_path)) {
                    // Verificar se existe no banco
                    $exists = Database::fetch(
                        "SELECT id FROM manutencao_fotos WHERE caminho_arquivo = ?",
                        [$file_path]
                    );
                    
                    if (!$exists) {
                        unlink($file_path);
                        $cleaned++;
                    }
                }
            }
        }
        
        if (function_exists('logMessage')) {
            logMessage("Limpeza de fotos órfãs concluída. {$cleaned} arquivos removidos.", 'INFO');
        }
        return $cleaned;
        
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro na limpeza de fotos órfãs: ' . $e->getMessage(), 'ERROR');
        }
        return 0;
    }
}

/**
 * Verifica se uma foto pertence a um equipamento
 */
function isEquipmentPhoto($photo_id, $equipamento_id) {
    try {
        $result = Database::fetch(
            "SELECT id FROM equipamento_fotos WHERE id = ? AND equipamento_id = ? AND ativo = 1",
            [$photo_id, $equipamento_id]
        );
        return !empty($result);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verifica se uma foto pertence a uma manutenção
 */
function isMaintenancePhoto($photo_id, $manutencao_id) {
    try {
        $result = Database::fetch(
            "SELECT id FROM manutencao_fotos WHERE id = ? AND manutencao_id = ? AND ativo = 1",
            [$photo_id, $manutencao_id]
        );
        return !empty($result);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Conta o total de fotos de um equipamento
 */
function countEquipmentPhotos($equipamento_id) {
    try {
        $result = Database::fetch(
            "SELECT COUNT(*) as total FROM equipamento_fotos WHERE equipamento_id = ? AND ativo = 1",
            [$equipamento_id]
        );
        return $result ? intval($result['total']) : 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Conta o total de fotos de uma manutenção
 */
function countMaintenancePhotos($manutencao_id) {
    try {
        $result = Database::fetch(
            "SELECT COUNT(*) as total FROM manutencao_fotos WHERE manutencao_id = ? AND ativo = 1",
            [$manutencao_id]
        );
        return $result ? intval($result['total']) : 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Obtém estatísticas de fotos do sistema
 */
function getPhotoStatistics() {
    try {
        $stats = [
            'total_equipment_photos' => 0,
            'total_maintenance_photos' => 0,
            'total_size_mb' => 0,
            'average_photos_per_equipment' => 0
        ];
        
        // Contar fotos de equipamentos
        $result = Database::fetch("SELECT COUNT(*) as total FROM equipamento_fotos WHERE ativo = 1");
        $stats['total_equipment_photos'] = $result ? intval($result['total']) : 0;
        
        // Contar fotos de manutenções
        $result = Database::fetch("SELECT COUNT(*) as total FROM manutencao_fotos WHERE ativo = 1");
        $stats['total_maintenance_photos'] = $result ? intval($result['total']) : 0;
        
        // Calcular tamanho total aproximado
        $total_files = $stats['total_equipment_photos'] + $stats['total_maintenance_photos'];
        $stats['total_size_mb'] = round($total_files * 0.5, 2); // Estimativa de 0.5MB por foto
        
        // Calcular média de fotos por equipamento
        $result = Database::fetch("SELECT COUNT(*) as total FROM equipamentos");
        $total_equipments = $result ? intval($result['total']) : 1;
        $stats['average_photos_per_equipment'] = round($stats['total_equipment_photos'] / $total_equipments, 1);
        
        return $stats;
        
    } catch (Exception $e) {
        if (function_exists('logMessage')) {
            logMessage('Erro ao obter estatísticas de fotos: ' . $e->getMessage(), 'ERROR');
        }
        return [
            'total_equipment_photos' => 0,
            'total_maintenance_photos' => 0,
            'total_size_mb' => 0,
            'average_photos_per_equipment' => 0
        ];
    }
}

// Inicializar diretórios quando o arquivo for incluído
if (!defined('PHOTOS_INIT_DONE')) {
    createPhotosDirectories();
    define('PHOTOS_INIT_DONE', true);
}

?>