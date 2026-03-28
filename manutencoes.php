<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'photo_functions.php';
require_once 'user_permissions.php';

$message = '';
$error = '';

// Verificar permissões de acesso
UserPermissions::enforcePageAccess($_SESSION['user_type'], 'manutencoes.php');

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Função para verificar se uma coluna existe na tabela
function columnExists($table, $column) {
    try {
        $result = Database::fetch(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", 
            [$table, $column]
        );
        return $result !== false && !empty($result);
    } catch (Exception $e) {
        return false;
    }
}

// Verificar e criar colunas necessárias
if (!columnExists('manutencoes', 'created_by')) {
    try {
        Database::query("ALTER TABLE manutencoes ADD COLUMN created_by INT NULL AFTER updated_at");
        logMessage("Coluna created_by adicionada à tabela manutencoes", 'INFO');
    } catch (Exception $e) {
        logMessage("Erro ao adicionar coluna created_by: " . $e->getMessage(), 'ERROR');
    }
}

// Verificar se tabela de fotos existe
try {
    Database::query("CREATE TABLE IF NOT EXISTS `manutencao_fotos` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `manutencao_id` int(11) NOT NULL,
        `tipo_foto` enum('antes','durante','depois','problema','solucao') NOT NULL DEFAULT 'durante',
        `caminho_arquivo` varchar(500) NOT NULL,
        `nome_original` varchar(255) NOT NULL,
        `descricao` text,
        `uploaded_by` int(11) NOT NULL,
        `data_upload` timestamp NOT NULL DEFAULT current_timestamp(),
        `ativo` tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_manutencao_fotos_manutencao` (`manutencao_id`),
        KEY `idx_manutencao_fotos_tipo` (`tipo_foto`),
        KEY `idx_manutencao_fotos_ativo` (`ativo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    logMessage("Erro ao criar tabela manutencao_fotos: " . $e->getMessage(), 'ERROR');
}

// Endpoint AJAX para buscar tipos de manutenção
if (isset($_GET['action']) && $_GET['action'] === 'get_tipos_manutencao') {
    header('Content-Type: application/json');
    $equipamento_tipo = $_GET['equipamento_tipo'] ?? null;
    $tipos = getTiposManutencaoByEquipamento($equipamento_tipo);
    echo json_encode($tipos);
    exit;
}

// Endpoint AJAX para buscar detalhes do equipamento
if (isset($_GET['action']) && $_GET['action'] === 'get_equipamento_details') {
    header('Content-Type: application/json');
    $equipamento_id = $_GET['equipamento_id'] ?? null;
    if ($equipamento_id) {
        $equipamento = Database::fetch("SELECT * FROM equipamentos WHERE id = ?", [$equipamento_id]);
        echo json_encode($equipamento);
    } else {
        echo json_encode(null);
    }
    exit;
}

// Endpoint AJAX para buscar materiais e serviços
if (isset($_GET['action']) && $_GET['action'] === 'get_materiais_servicos') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? '';
    
    try {
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
            echo json_encode(['error' => 'Tipo inválido']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
    }
    exit;
}

// Processamento de ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_tratativa') {
        // Verificar permissão para adicionar tratativas
        if (!hasPermission('manutencoes', 'edit')) {
            $error = 'Você não tem permissão para adicionar tratativas.';
        } else {
            $manutencao_id = $_POST['manutencao_id'] ?? null;
            $tratativa = sanitize($_POST['tratativa'] ?? '');
            
            if ($manutencao_id && $tratativa) {
                try {
                    // Verificar se o técnico pode editar esta manutenção
                    if ($_SESSION['user_type'] === 'tecnico') {
                        $maintenance = Database::fetch("SELECT tecnico_id FROM manutencoes WHERE id = ?", [$manutencao_id]);
                        if ($maintenance && $maintenance['tecnico_id'] != $_SESSION['user_id']) {
                            $error = 'Você só pode adicionar tratativas em suas próprias manutenções.';
                        } else {
                            Database::query(
                                "INSERT INTO manutencao_tratativas (manutencao_id, usuario_id, tratativa, data_tratativa) VALUES (?, ?, ?, NOW())",
                                [$manutencao_id, $_SESSION['user_id'], $tratativa]
                            );
                            $message = 'Tratativa adicionada com sucesso!';
                            logMessage("Tratativa adicionada na manutenção {$manutencao_id} por {$_SESSION['user_name']}", 'INFO');
                        }
                    } else {
                        Database::query(
                            "INSERT INTO manutencao_tratativas (manutencao_id, usuario_id, tratativa, data_tratativa) VALUES (?, ?, ?, NOW())",
                            [$manutencao_id, $_SESSION['user_id'], $tratativa]
                        );
                        $message = 'Tratativa adicionada com sucesso!';
                        logMessage("Tratativa adicionada na manutenção {$manutencao_id} por {$_SESSION['user_name']}", 'INFO');
                    }
                } catch (Exception $e) {
                    $error = 'Erro ao adicionar tratativa.';
                    logMessage('Erro ao adicionar tratativa: ' . $e->getMessage(), 'ERROR');
                }
            } else {
                $error = 'Dados incompletos para adicionar tratativa.';
            }
        }
    } elseif ($action === 'upload_maintenance_photos') {
        // Verificar permissão para upload de fotos
        if (!hasPermission('manutencoes', 'edit')) {
            $error = 'Você não tem permissão para fazer upload de fotos.';
        } else {
            try {
                $manutencao_id = (int)($_POST['manutencao_id'] ?? 0);
                $tipo_foto = sanitize($_POST['tipo_foto'] ?? '');
                $descricao = sanitize($_POST['descricao'] ?? '');
                
                if (!$manutencao_id || !$tipo_foto) {
                    $error = 'Dados obrigatórios não informados.';
                } else {
                    // Verificar se a manutenção existe e se o usuário pode editá-la
                    $manutencao = Database::fetch("SELECT * FROM manutencoes WHERE id = ?", [$manutencao_id]);
                    if (!$manutencao) {
                        $error = 'Manutenção não encontrada.';
                    } else {
                        // Técnicos só podem editar suas próprias manutenções
                        if ($_SESSION['user_type'] === 'tecnico' && $manutencao['tecnico_id'] != $_SESSION['user_id']) {
                            $error = 'Você só pode adicionar fotos em suas próprias manutenções.';
                        } else {
                            // Processar upload
                            if (isset($_FILES['fotos']) && !empty($_FILES['fotos']['name'][0])) {
                                $upload_dir = UPLOAD_PATH . '/manutencoes/' . $manutencao_id . '/';
                                
                                // Criar diretório se não existir
                                if (!file_exists($upload_dir)) {
                                    mkdir($upload_dir, 0755, true);
                                }
                                
                                $fotos_salvas = 0;
                                $fotos_enviadas = count($_FILES['fotos']['name']);
                                
                                for ($i = 0; $i < $fotos_enviadas; $i++) {
                                    $arquivo = [
                                        'name' => $_FILES['fotos']['name'][$i],
                                        'type' => $_FILES['fotos']['type'][$i],
                                        'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                                        'error' => $_FILES['fotos']['error'][$i],
                                        'size' => $_FILES['fotos']['size'][$i]
                                    ];
                                    
                                    if ($arquivo['error'] === UPLOAD_ERR_OK && $arquivo['size'] <= 5 * 1024 * 1024) {
                                        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
                                        if (in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'])) {
                                            $nome_arquivo = uniqid('manutencao_' . $manutencao_id . '_') . '.' . $extensao;
                                            $caminho_completo = $upload_dir . $nome_arquivo;
                                            $caminho_relativo = 'uploads/manutencoes/' . $manutencao_id . '/' . $nome_arquivo;
                                            
                                            if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
                                                Database::query(
                                                    "INSERT INTO manutencao_fotos (manutencao_id, tipo_foto, caminho_arquivo, nome_original, descricao, uploaded_by, data_upload, ativo) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)",
                                                    [$manutencao_id, $tipo_foto, $caminho_relativo, $arquivo['name'], $descricao, $_SESSION['user_id']]
                                                );
                                                $fotos_salvas++;
                                            }
                                        }
                                    }
                                }
                                
                                if ($fotos_salvas > 0) {
                                    $message = $fotos_salvas . ' foto(s) enviada(s) com sucesso!';
                                    logMessage("Upload de {$fotos_salvas} fotos na manutenção {$manutencao_id} por {$_SESSION['user_name']}", 'INFO');
                                } else {
                                    $error = 'Nenhuma foto foi salva. Verifique os formatos e tamanhos.';
                                }
                            } else {
                                $error = 'Nenhuma foto foi selecionada.';
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Erro ao fazer upload: ' . $e->getMessage();
                logMessage('Erro no upload de fotos: ' . $e->getMessage(), 'ERROR');
            }
        }
    } elseif ($action === 'create' || $action === 'update') {
        // Verificar permissão para criar/editar manutenções
        $required_permission = ($action === 'create') ? 'create' : 'edit';
        
        if (!hasPermission('manutencoes', $required_permission)) {
            $error = 'Você não tem permissão para ' . ($action === 'create' ? 'criar' : 'editar') . ' manutenções.';
        } else {
            // Sanitizar e validar dados de entrada
            $equipamento_id = intval($_POST['equipamento_id'] ?? 0);
            $tipo_manutencao_id = intval($_POST['tipo_manutencao_id'] ?? 0);
            $prioridade = sanitize($_POST['prioridade'] ?? 'media');
            $descricao = sanitize($_POST['descricao'] ?? '');
            $data_agendada = !empty($_POST['data_agendada']) ? $_POST['data_agendada'] : null;
            $tecnico_id = !empty($_POST['tecnico_id']) ? intval($_POST['tecnico_id']) : null;
            $status = sanitize($_POST['status'] ?? 'agendada');
            $observacoes = sanitize($_POST['observacoes'] ?? '');
            $problema_relatado = sanitize($_POST['problema_relatado'] ?? '');
            $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
            
            // Validações básicas
            if ($equipamento_id <= 0) {
                $error = 'Equipamento é obrigatório.';
            } elseif ($tipo_manutencao_id <= 0) {
                $error = 'Tipo de manutenção é obrigatório.';
            } elseif (empty($descricao)) {
                $error = 'Descrição é obrigatória.';
            } elseif (!in_array($prioridade, ['baixa', 'media', 'alta', 'urgente'])) {
                $error = 'Prioridade inválida.';
            } elseif (!in_array($status, ['agendada', 'em_andamento', 'concluida', 'cancelada'])) {
                $error = 'Status inválido.';
            } else {
                // Verificar se o tipo de manutenção existe e está ativo
                $tipo_exists = Database::fetch("SELECT id, categoria FROM tipos_manutencao WHERE id = ? AND ativo = 1", [$tipo_manutencao_id]);
                if (!$tipo_exists) {
                    $error = 'Tipo de manutenção inválido ou inativo.';
                } else {
                    try {
                        if ($action === 'create') {
                            // Definir o tipo baseado na categoria do tipo de manutenção
                            $tipo_categoria = $tipo_exists['categoria'] === 'manutencao' ? 'corretiva' : 'preventiva';
                            
                            // Verificar se o equipamento existe
                            $equipamento_exists = Database::fetch("SELECT id, tipo FROM equipamentos WHERE id = ?", [$equipamento_id]);
                            if (!$equipamento_exists) {
                                $error = 'Equipamento não encontrado.';
                            } else {
                                try {
                                    // Iniciar transação
                                    Database::query("START TRANSACTION");
                                    
                                    // Preparar campos e valores baseados na existência das colunas
                                    $campos = [
                                        'equipamento_id', 'tipo_manutencao_id', 'tipo', 'prioridade', 
                                        'descricao', 'problema_relatado', 'data_agendada', 'tecnico_id', 
                                        'status', 'observacoes'
                                    ];
                                    $valores = [
                                        $equipamento_id, $tipo_manutencao_id, $tipo_categoria, $prioridade, 
                                        $descricao, $problema_relatado, $data_agendada, $tecnico_id, 
                                        $status, $observacoes
                                    ];
                                    $placeholders = array_fill(0, count($campos), '?');
                                    
                                    // Adicionar created_by se a coluna existir
                                    if (columnExists('manutencoes', 'created_by')) {
                                        $campos[] = 'created_by';
                                        $valores[] = $_SESSION['user_id'];
                                        $placeholders[] = '?';
                                    }
                                    
                                    // Adicionar created_at se a coluna existir e não for auto-generated
                                    if (columnExists('manutencoes', 'created_at')) {
                                        $column_info = Database::fetch(
                                            "SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS 
                                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'manutencoes' AND COLUMN_NAME = 'created_at'"
                                        );
                                        
                                        if (!$column_info || strpos($column_info['EXTRA'], 'DEFAULT_GENERATED') === false) {
                                            $campos[] = 'created_at';
                                            $placeholders[] = 'NOW()';
                                        }
                                    }
                                    
                                    $sql = "INSERT INTO manutencoes (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $placeholders) . ")";
                                    
                                    // Inserir manutenção
                                    Database::query($sql, $valores);
                                    
                                    // Obter ID da manutenção inserida
                                    $result = Database::fetch("SELECT LAST_INSERT_ID() as id");
                                    $manutencao_id = $result['id'];
                                    
                                    // Processar materiais selecionados
                                    if (isset($_POST['materiais_selecionados']) && !empty($_POST['materiais_selecionados'])) {
                                        $materiais_json = json_decode($_POST['materiais_selecionados'], true);
                                        if ($materiais_json && is_array($materiais_json)) {
                                            foreach ($materiais_json as $materialData) {
                                                $materialId = intval($materialData['id'] ?? 0);
                                                $quantidade = floatval($materialData['quantidade'] ?? 0);
                                                $observacoes_material = sanitize($materialData['observacoes'] ?? '');
                                                
                                                if ($materialId > 0 && $quantidade > 0) {
                                                    // Buscar preço atual do material
                                                    $material_info = Database::fetch("SELECT preco_unitario FROM pecas_materiais WHERE id = ?", [$materialId]);
                                                    $preco_unitario = $material_info['preco_unitario'] ?? 0;
                                                    
                                                    Database::query(
                                                        "INSERT INTO manutencao_materiais (manutencao_id, material_id, quantidade_prevista, quantidade_utilizada, preco_unitario, observacoes) VALUES (?, ?, ?, ?, ?, ?)",
                                                        [$manutencao_id, $materialId, $quantidade, 0, $preco_unitario, $observacoes_material]
                                                    );
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Processar serviços selecionados
                                    if (isset($_POST['servicos_selecionados']) && !empty($_POST['servicos_selecionados'])) {
                                        $servicos_json = json_decode($_POST['servicos_selecionados'], true);
                                        if ($servicos_json && is_array($servicos_json)) {
                                            foreach ($servicos_json as $servicoData) {
                                                $servicoId = intval($servicoData['id'] ?? 0);
                                                $observacoes_servico = sanitize($servicoData['observacoes'] ?? '');
                                                
                                                if ($servicoId > 0) {
                                                    Database::query(
                                                        "INSERT INTO manutencao_servicos (manutencao_id, tipo_manutencao_id, quantidade, observacoes, executado, executado_por) VALUES (?, ?, ?, ?, ?, ?)",
                                                        [$manutencao_id, $servicoId, 1, $observacoes_servico, 0, $_SESSION['user_id']]
                                                    );
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Processar upload de fotos se houver
                                    if (isset($_FILES['maintenance_photos']) && !empty($_FILES['maintenance_photos']['name'][0])) {
                                        $upload_dir = UPLOAD_PATH . '/manutencoes/' . $manutencao_id . '/';
                                        
                                        // Criar diretório se não existir
                                        if (!file_exists($upload_dir)) {
                                            mkdir($upload_dir, 0755, true);
                                        }
                                        
                                        $photo_types = $_POST['photo_types'] ?? [];
                                        $upload_errors = [];
                                        
                                        foreach ($_FILES['maintenance_photos']['tmp_name'] as $key => $tmp_name) {
                                            if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                                                $original_name = $_FILES['maintenance_photos']['name'][$key];
                                                $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                                                
                                                // Verificar extensão
                                                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                                if (!in_array($file_extension, $allowed_extensions)) {
                                                    $upload_errors[] = "Arquivo {$original_name} tem extensão não permitida.";
                                                    continue;
                                                }
                                                
                                                // Verificar tamanho (5MB max)
                                                if ($_FILES['maintenance_photos']['size'][$key] > 5 * 1024 * 1024) {
                                                    $upload_errors[] = "Arquivo {$original_name} é muito grande (max 5MB).";
                                                    continue;
                                                }
                                                
                                                // Gerar nome único
                                                $unique_name = time() . '_' . $key . '_' . uniqid() . '.' . $file_extension;
                                                $full_path = $upload_dir . $unique_name;
                                                $relative_path = 'uploads/manutencoes/' . $manutencao_id . '/' . $unique_name;
                                                
                                                if (move_uploaded_file($tmp_name, $full_path)) {
                                                    // Salvar no banco
                                                    $tipo_foto = $photo_types[$key] ?? 'durante';
                                                    Database::query(
                                                        "INSERT INTO manutencao_fotos (manutencao_id, tipo_foto, caminho_arquivo, nome_original, uploaded_by, data_upload, ativo) VALUES (?, ?, ?, ?, ?, NOW(), 1)",
                                                        [$manutencao_id, $tipo_foto, $relative_path, $original_name, $_SESSION['user_id']]
                                                    );
                                                } else {
                                                    $upload_errors[] = "Erro ao fazer upload do arquivo {$original_name}.";
                                                }
                                            }
                                        }
                                        
                                        if (!empty($upload_errors)) {
                                            logMessage('Erros no upload de fotos: ' . implode('; ', $upload_errors), 'WARNING');
                                        }
                                    }
                                    
                                    // Commit da transação
                                    Database::query("COMMIT");
                                    
                                    $message = 'Manutenção criada com sucesso!';
                                    logMessage("Manutenção criada para equipamento {$equipamento_id} por {$_SESSION['user_name']}", 'INFO');
                                    
                                } catch (Exception $e) {
                                    // Rollback em caso de erro
                                    Database::query("ROLLBACK");
                                    $error = 'Erro ao salvar manutenção: ' . $e->getMessage();
                                    logMessage('Erro ao salvar manutenção: ' . $e->getMessage(), 'ERROR');
                                }
                            }
                        } else {
                            // EDIÇÃO DE MANUTENÇÃO
                            if ($action === 'update' && $id) {
                                // Verificação adicional para técnicos - só podem editar suas próprias manutenções
                                if ($_SESSION['user_type'] === 'tecnico') {
                                    $maintenance = Database::fetch("SELECT tecnico_id FROM manutencoes WHERE id = ?", [$id]);
                                    if ($maintenance && $maintenance['tecnico_id'] != $_SESSION['user_id'] && $maintenance['tecnico_id'] !== null) {
                                        $error = 'Você só pode editar suas próprias manutenções.';
                                    } else {
                                        $tipo_categoria = $tipo_exists['categoria'] === 'manutencao' ? 'corretiva' : 'preventiva';
                                        
                                        // Verificar se updated_at existe
                                        if (columnExists('manutencoes', 'updated_at')) {
                                            Database::query(
                                                "UPDATE manutencoes SET equipamento_id = ?, tipo_manutencao_id = ?, tipo = ?, prioridade = ?, descricao = ?, problema_relatado = ?, data_agendada = ?, tecnico_id = ?, status = ?, observacoes = ?, updated_at = NOW() WHERE id = ?",
                                                [$equipamento_id, $tipo_manutencao_id, $tipo_categoria, $prioridade, $descricao, $problema_relatado, $data_agendada, $tecnico_id, $status, $observacoes, $id]
                                            );
                                        } else {
                                            Database::query(
                                                "UPDATE manutencoes SET equipamento_id = ?, tipo_manutencao_id = ?, tipo = ?, prioridade = ?, descricao = ?, problema_relatado = ?, data_agendada = ?, tecnico_id = ?, status = ?, observacoes = ? WHERE id = ?",
                                                [$equipamento_id, $tipo_manutencao_id, $tipo_categoria, $prioridade, $descricao, $problema_relatado, $data_agendada, $tecnico_id, $status, $observacoes, $id]
                                            );
                                        }
                                        $message = 'Manutenção atualizada com sucesso!';
                                        logMessage("Manutenção {$id} atualizada por {$_SESSION['user_name']}", 'INFO');
                                    }
                                } else {
                                    $tipo_categoria = $tipo_exists['categoria'] === 'manutencao' ? 'corretiva' : 'preventiva';
                                    
                                    // Verificar se updated_at existe
                                    if (columnExists('manutencoes', 'updated_at')) {
                                        Database::query(
                                            "UPDATE manutencoes SET equipamento_id = ?, tipo_manutencao_id = ?, tipo = ?, prioridade = ?, descricao = ?, problema_relatado = ?, data_agendada = ?, tecnico_id = ?, status = ?, observacoes = ?, updated_at = NOW() WHERE id = ?",
                                            [$equipamento_id, $tipo_manutencao_id, $tipo_categoria, $prioridade, $descricao, $problema_relatado, $data_agendada, $tecnico_id, $status, $observacoes, $id]
                                        );
                                    } else {
                                        Database::query(
                                            "UPDATE manutencoes SET equipamento_id = ?, tipo_manutencao_id = ?, tipo = ?, prioridade = ?, descricao = ?, problema_relatado = ?, data_agendada = ?, tecnico_id = ?, status = ?, observacoes = ? WHERE id = ?",
                                            [$equipamento_id, $tipo_manutencao_id, $tipo_categoria, $prioridade, $descricao, $problema_relatado, $data_agendada, $tecnico_id, $status, $observacoes, $id]
                                        );
                                    }
                                    // Processar upload de novas fotos na edição se houver
                                    if (isset($_FILES['edit_maintenance_photos']) && !empty($_FILES['edit_maintenance_photos']['name'][0])) {
                                        $upload_dir = UPLOAD_PATH . '/manutencoes/' . $id . '/';
                                        
                                        // Criar diretório se não existir
                                        if (!file_exists($upload_dir)) {
                                            mkdir($upload_dir, 0755, true);
                                        }
                                        
                                        $photo_types = $_POST['edit_photo_types'] ?? [];
                                        $upload_errors = [];
                                        
                                        foreach ($_FILES['edit_maintenance_photos']['tmp_name'] as $key => $tmp_name) {
                                            if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                                                $original_name = $_FILES['edit_maintenance_photos']['name'][$key];
                                                $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                                                
                                                // Verificar extensão
                                                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                                if (!in_array($file_extension, $allowed_extensions)) {
                                                    $upload_errors[] = "Arquivo {$original_name} tem extensão não permitida.";
                                                    continue;
                                                }
                                                
                                                // Verificar tamanho (5MB max)
                                                if ($_FILES['edit_maintenance_photos']['size'][$key] > 5 * 1024 * 1024) {
                                                    $upload_errors[] = "Arquivo {$original_name} é muito grande (max 5MB).";
                                                    continue;
                                                }
                                                
                                                // Gerar nome único
                                                $unique_name = time() . '_edit_' . $key . '_' . uniqid() . '.' . $file_extension;
                                                $full_path = $upload_dir . $unique_name;
                                                $relative_path = 'uploads/manutencoes/' . $id . '/' . $unique_name;
                                                
                                                if (move_uploaded_file($tmp_name, $full_path)) {
                                                    // Salvar no banco
                                                    $tipo_foto = $photo_types[$key] ?? 'durante';
                                                    Database::query(
                                                        "INSERT INTO manutencao_fotos (manutencao_id, tipo_foto, caminho_arquivo, nome_original, uploaded_by, data_upload, ativo) VALUES (?, ?, ?, ?, ?, NOW(), 1)",
                                                        [$id, $tipo_foto, $relative_path, $original_name, $_SESSION['user_id']]
                                                    );
                                                } else {
                                                    $upload_errors[] = "Erro ao fazer upload do arquivo {$original_name}.";
                                                }
                                            }
                                        }
                                        
                                        if (!empty($upload_errors)) {
                                            logMessage('Erros no upload de fotos na edição: ' . implode('; ', $upload_errors), 'WARNING');
                                        }
                                    }

                                    $message = 'Manutenção atualizada com sucesso!';
                                    logMessage("Manutenção {$id} atualizada por {$_SESSION['user_name']}", 'INFO');
                                }
                            } else {
                                $error = 'ID da manutenção é obrigatório para edição.';
                            }
                        }
                    } catch (Exception $e) {
                        $error = 'Erro interno: ' . $e->getMessage();
                        logMessage('Erro ao salvar manutenção: ' . $e->getMessage(), 'ERROR');
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        // Verificar permissão para excluir
        if (!hasPermission('manutencoes', 'delete')) {
            $error = 'Você não tem permissão para excluir manutenções.';
        } else {
            $id = $_POST['id'] ?? null;
            if ($id) {
                try {
                    // Verificação adicional para técnicos
                    if ($_SESSION['user_type'] === 'tecnico') {
                        $maintenance = Database::fetch("SELECT tecnico_id FROM manutencoes WHERE id = ?", [$id]);
                        if ($maintenance && $maintenance['tecnico_id'] != $_SESSION['user_id']) {
                            $error = 'Você só pode excluir suas próprias manutenções.';
                        } else {
                            Database::query("DELETE FROM manutencoes WHERE id = ?", [$id]);
                            $message = 'Manutenção excluída com sucesso!';
                            logMessage("Manutenção {$id} excluída por {$_SESSION['user_name']}", 'INFO');
                        }
                    } else {
                        Database::query("DELETE FROM manutencoes WHERE id = ?", [$id]);
                        $message = 'Manutenção excluída com sucesso!';
                        logMessage("Manutenção {$id} excluída por {$_SESSION['user_name']}", 'INFO');
                    }
                } catch (Exception $e) {
                    $error = 'Erro ao excluir manutenção.';
                    logMessage('Erro ao excluir manutenção: ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    } elseif ($action === 'add_services_materials') {
        // Verificar permissão para editar manutenções
        if (!hasPermission('manutencoes', 'edit')) {
            $error = 'Você não tem permissão para adicionar serviços e materiais.';
        } else {
            $manutencao_id = $_POST['manutencao_id'] ?? null;
            $servicos = $_POST['servicos'] ?? [];
            $materiais = $_POST['materiais'] ?? [];
            
            if ($manutencao_id) {
                try {
                    // Verificar se é o técnico responsável ou admin
                    if ($_SESSION['user_type'] === 'tecnico') {
                        $maintenance = Database::fetch("SELECT tecnico_id FROM manutencoes WHERE id = ?", [$manutencao_id]);
                        if ($maintenance && $maintenance['tecnico_id'] != $_SESSION['user_id']) {
                            $error = 'Você só pode adicionar serviços/materiais em suas próprias manutenções.';
                        }
                    }
                    
                    if (!$error) {
                        // Usar transação manual se métodos não existirem
                        Database::query("START TRANSACTION");
                        
                        // Adicionar serviços
                        foreach ($servicos as $servico) {
                            if (!empty($servico['tipo_manutencao_id']) && !empty($servico['quantidade'])) {
                                Database::query(
                                    "INSERT INTO manutencao_servicos (manutencao_id, tipo_manutencao_id, quantidade, tempo_gasto, observacoes, executado, executado_por, executado_em) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                                    [
                                        $manutencao_id, 
                                        $servico['tipo_manutencao_id'], 
                                        $servico['quantidade'],
                                        $servico['tempo_gasto'] ?? 0,
                                        $servico['observacoes'] ?? '',
                                        isset($servico['executado']) ? 1 : 0,
                                        $_SESSION['user_id']
                                    ]
                                );
                            }
                        }
                        
                        // Adicionar materiais
                        foreach ($materiais as $material) {
                            if (!empty($material['material_id']) && !empty($material['quantidade_utilizada'])) {
                                // Buscar preço atual do material
                                $material_info = Database::fetch("SELECT preco_unitario, estoque_atual FROM pecas_materiais WHERE id = ?", [$material['material_id']]);
                                
                                Database::query(
                                    "INSERT INTO manutencao_materiais (manutencao_id, material_id, quantidade_prevista, quantidade_utilizada, preco_unitario, observacoes) VALUES (?, ?, ?, ?, ?, ?)",
                                    [
                                        $manutencao_id,
                                        $material['material_id'],
                                        $material['quantidade_prevista'] ?? $material['quantidade_utilizada'],
                                        $material['quantidade_utilizada'],
                                        $material_info['preco_unitario'] ?? 0,
                                        $material['observacoes'] ?? ''
                                    ]
                                );
                                
                                // Atualizar estoque se material foi encontrado
                                if ($material_info) {
                                    $novo_estoque = max(0, $material_info['estoque_atual'] - $material['quantidade_utilizada']);
                                    Database::query("UPDATE pecas_materiais SET estoque_atual = ? WHERE id = ?", [$novo_estoque, $material['material_id']]);
                                }
                            }
                        }
                        
                        // Calcular custo total da manutenção
                        $custo_materiais = Database::fetch(
                            "SELECT COALESCE(SUM(quantidade_utilizada * preco_unitario), 0) as total FROM manutencao_materiais WHERE manutencao_id = ?", 
                            [$manutencao_id]
                        )['total'] ?? 0;
                        
                        Database::query("UPDATE manutencoes SET custo_total = ? WHERE id = ?", [$custo_materiais, $manutencao_id]);
                        
                        Database::query("COMMIT");
                        $message = 'Serviços e materiais adicionados com sucesso!';
                        logMessage("Serviços/materiais adicionados à manutenção {$manutencao_id} por {$_SESSION['user_name']}", 'INFO');
                    }
                } catch (Exception $e) {
                    Database::query("ROLLBACK");
                    $error = 'Erro ao adicionar serviços e materiais: ' . $e->getMessage();
                    logMessage('Erro ao adicionar serviços/materiais: ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    }
}

// Processamento de relatórios fotográficos
if (isset($_GET['generate_photo_report']) && $_GET['generate_photo_report'] === 'true') {
    // Verificar permissão para gerar relatórios
    if (!hasPermission('relatorios', 'view')) {
        header('Location: manutencoes.php?error=no_permission');
        exit;
    }
    
    $download_pdf = isset($_GET['download_pdf']) && $_GET['download_pdf'] === 'true';
    
    // Incluir as funções de relatório
    require_once 'photo_report_functions.php';
    generateMaintenancePhotoReport($download_pdf);
    exit;
}

// Filtros e busca
$equipamento_filter = $_GET['equipamento'] ?? '';
$status_filter = $_GET['status'] ?? '';
$tipo_filter = $_GET['tipo'] ?? '';
$prioridade_filter = $_GET['prioridade'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$atribuicao_filter = $_GET['atribuicao'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 9; // 3x3 grid
$offset = ($page - 1) * $per_page;

// Filtros específicos por tipo de usuário
$where_conditions = [];
$params = [];

// Técnicos só veem suas manutenções ou não atribuídas
if ($_SESSION['user_type'] === 'tecnico') {
    if ($atribuicao_filter === 'minhas') {
        $where_conditions[] = "m.tecnico_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($atribuicao_filter === 'disponiveis') {
        $where_conditions[] = "m.tecnico_id IS NULL";
    } else {
        $where_conditions[] = "(m.tecnico_id = ? OR m.tecnico_id IS NULL)";
        $params[] = $_SESSION['user_id'];
    }
}

// Usuários comuns só veem manutenções concluídas
if ($_SESSION['user_type'] === 'usuario') {
    $where_conditions[] = "m.status IN ('concluida', 'cancelada')";
}

// Filtros adicionais
if ($equipamento_filter) {
    $where_conditions[] = "m.equipamento_id = ?";
    $params[] = $equipamento_filter;
}

if ($status_filter) {
    $where_conditions[] = "m.status = ?";
    $params[] = $status_filter;
}

if ($tipo_filter) {
    $where_conditions[] = "tm.categoria = ?";
    $params[] = $tipo_filter;
}

if ($prioridade_filter) {
    $where_conditions[] = "m.prioridade = ?";
    $params[] = $prioridade_filter;
}

if ($data_inicio) {
    $where_conditions[] = "m.data_agendada >= ?";
    $params[] = $data_inicio;
}

if ($data_fim) {
    $where_conditions[] = "m.data_agendada <= ?";
    $params[] = $data_fim;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Buscar manutenções com informações completas
$manutencoes = Database::fetchAll(
    "SELECT m.*, 
            e.codigo as equipamento_codigo, 
            e.localizacao as equipamento_localizacao,
            e.tipo as equipamento_tipo,
            u.nome as tecnico_nome,
            tm.nome as tipo_manutencao_nome,
            tm.categoria as tipo_manutencao_categoria,
            tm.codigo as tipo_manutencao_codigo,
            (SELECT COUNT(*) FROM manutencao_fotos mf WHERE mf.manutencao_id = m.id AND mf.ativo = 1) as total_fotos
     FROM manutencoes m 
     LEFT JOIN equipamentos e ON m.equipamento_id = e.id 
     LEFT JOIN usuarios u ON m.tecnico_id = u.id 
     LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
     $where_clause 
     ORDER BY 
        CASE m.prioridade 
            WHEN 'urgente' THEN 1 
            WHEN 'alta' THEN 2 
            WHEN 'media' THEN 3 
            WHEN 'baixa' THEN 4 
        END,
        m.data_agendada DESC 
     LIMIT $per_page OFFSET $offset",
    $params
);

// Contar total para paginação
$total = Database::fetch(
    "SELECT COUNT(*) as total FROM manutencoes m 
     LEFT JOIN equipamentos e ON m.equipamento_id = e.id 
     LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
     $where_clause",
    $params
)['total'];

$total_pages = ceil($total / $per_page);

// Buscar equipamentos para filtros
$equipamentos = Database::fetchAll("SELECT id, codigo, localizacao, tipo, status FROM equipamentos ORDER BY codigo");

// Buscar técnicos para seleção
$tecnicos = Database::fetchAll("SELECT id, nome FROM usuarios WHERE tipo = 'tecnico' AND ativo = 1 ORDER BY nome");

// Estatísticas específicas por tipo de usuário
$stats = [];

if (hasPermission('dashboard', 'full_stats')) {
    // Estatísticas completas para Admin
    $stats = [
        'total' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes")['total'],
        'agendadas' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE status = 'agendada'")['total'],
        'em_andamento' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE status = 'em_andamento'")['total'],
        'concluidas' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE status = 'concluida'")['total']
    ];
} elseif (hasPermission('dashboard', 'basic_stats')) {
    // Estatísticas para Técnico (suas manutenções)
    $stats = [
        'minhas_total' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE tecnico_id = ?", [$_SESSION['user_id']])['total'],
        'minhas_agendadas' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE tecnico_id = ? AND status = 'agendada'", [$_SESSION['user_id']])['total'],
        'minhas_andamento' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE tecnico_id = ? AND status = 'em_andamento'", [$_SESSION['user_id']])['total'],
        'disponiveis' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE tecnico_id IS NULL AND status = 'agendada'")['total']
    ];
} else {
    // Estatísticas básicas para Usuário comum
    $stats = [
        'concluidas_mes' => Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE status = 'concluida' AND MONTH(data_realizada) = MONTH(NOW())")['total'],
        'equipamentos_ok' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'ativo'")['total'],
        'em_manutencao' => Database::fetch("SELECT COUNT(*) as total FROM equipamentos WHERE status = 'manutencao'")['total']
    ];
}

function getStatusBadge($status) {
    $badges = [
        'agendada' => 'bg-warning',
        'em_andamento' => 'bg-info',
        'concluida' => 'bg-success',
        'cancelada' => 'bg-secondary'
    ];
    return $badges[$status] ?? 'bg-secondary';
}

function getPriorityBadge($prioridade) {
    $badges = [
        'baixa' => 'bg-secondary',
        'media' => 'bg-primary',
        'alta' => 'bg-warning',
        'urgente' => 'bg-danger'
    ];
    return $badges[$prioridade] ?? 'bg-secondary';
}

function getTypeIcon($categoria) {
    $icons = [
        'limpeza' => 'stars',
        'manutencao' => 'tools',
        'instalacao' => 'plus-circle',
        'inspecao' => 'search',
        'troca' => 'arrow-repeat'
    ];
    return $icons[$categoria] ?? 'tools';
}

// Função para buscar tipos de manutenção baseado no equipamento
function getTiposManutencaoByEquipamento($equipamento_tipo = null) {
    try {
        $where_clause = $equipamento_tipo ? "WHERE (tipo_equipamento = ? OR tipo_equipamento = 'ambos') AND ativo = 1" : "WHERE ativo = 1";
        $params = $equipamento_tipo ? [$equipamento_tipo] : [];
        
        return Database::fetchAll(
            "SELECT id, codigo, nome, categoria, tipo_equipamento, tempo_estimado, prioridade_default FROM tipos_manutencao $where_clause ORDER BY categoria, nome",
            $params
        );
    } catch (Exception $e) {
        logMessage('Erro ao buscar tipos de manutenção: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

// Função para buscar serviços de uma manutenção
function getMaintenanceServices($manutencao_id) {
    try {
        return Database::fetchAll(
            "SELECT ms.*, tm.codigo, tm.nome as servico_nome, tm.categoria, tm.unidade_medida, u.nome as executado_por_nome
             FROM manutencao_servicos ms
             LEFT JOIN tipos_manutencao tm ON ms.tipo_manutencao_id = tm.id
             LEFT JOIN usuarios u ON ms.executado_por = u.id
             WHERE ms.manutencao_id = ?
             ORDER BY ms.created_at",
            [$manutencao_id]
        );
    } catch (Exception $e) {
        logMessage('Erro ao buscar serviços da manutenção: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

// Função para buscar materiais de uma manutenção
function getMaintenanceMaterials($manutencao_id) {
    try {
        return Database::fetchAll(
            "SELECT mm.*, pm.codigo, pm.nome as material_nome, pm.categoria, pm.unidade_medida
             FROM manutencao_materiais mm
             LEFT JOIN pecas_materiais pm ON mm.material_id = pm.id
             WHERE mm.manutencao_id = ?
             ORDER BY mm.created_at",
            [$manutencao_id]
        );
    } catch (Exception $e) {
        logMessage('Erro ao buscar materiais da manutenção: ' . $e->getMessage(), 'ERROR');
        return [];
    }
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
    <title>Manutenções - HidroApp</title>
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
            --accent-color: #ffb800;
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
            --touch-target: 44px;
        }

        html {
            scroll-behavior: smooth;
            font-size: 16px;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
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
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Sidebar moderna com responsividade perfeita */
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
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            position: sticky;
            top: 0;
            z-index: 10;
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
            min-height: var(--touch-target);
            touch-action: manipulation;
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
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: var(--transition);
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
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .content-area {
            padding: 2rem;
            flex: 1;
            max-width: 100%;
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
            height: 100%;
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
            flex-shrink: 0;
        }
        
        .maintenance-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            overflow: hidden;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .maintenance-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }
        
        .maintenance-card .card-header {
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--bg-light), var(--bg-white));
            position: relative;
            padding: 1rem;
        }
        
        .maintenance-card .card-body {
            padding: 1rem;
            flex: 1;
            overflow: hidden;
        }
        
        .maintenance-card .card-footer {
            padding: 1rem;
            background: transparent;
            border-top: 1px solid var(--border-color);
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
            min-height: var(--touch-target);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            touch-action: manipulation;
        }
        
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
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
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border-bottom: none;
            position: sticky;
            top: 0;
            z-index: 10;
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
            min-height: var(--touch-target);
            font-size: 16px; /* Evita zoom no iOS */
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

        /* Priority indicators */
        .priority-urgente { border-left: 4px solid #dc3545; }
        .priority-alta { border-left: 4px solid #fd7e14; }
        .priority-media { border-left: 4px solid #0d6efd; }
        .priority-baixa { border-left: 4px solid #6c757d; }

        /* Botões responsivos */
        .btn {
            min-height: var(--touch-target);
            border-radius: var(--border-radius);
            transition: var(--transition);
            touch-action: manipulation;
        }

        .btn-group .btn {
            min-height: 38px;
        }

        /* Sidebar móvel melhorado */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* Estilos para os modais de seleção */
        .search-modal .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }

        .search-input {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 16px;
            width: 100%;
            margin-bottom: 20px;
        }

        .search-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }

        .search-results {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .search-item {
            padding: 12px 16px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-item:last-child {
            border-bottom: none;
        }

        .search-item:hover {
            background-color: #e3f2fd;
        }

        .search-item.selected {
            background-color: #bbdefb;
            color: #1976d2;
        }

        .search-item-info {
            flex: 1;
        }

        .search-item-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .search-item-details {
            font-size: 0.875rem;
            color: #666;
        }

        .search-item-price, .search-item-time {
            font-weight: 600;
            color: #28a745;
            margin-left: 12px;
        }

        .selected-items {
            margin-top: 20px;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .selected-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            margin-bottom: 8px;
            background: white;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .selected-item:last-child {
            margin-bottom: 0;
        }

        .selected-item-info {
            flex: 1;
        }

        .selected-item-title {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }

        .selected-item-details {
            font-size: 0.8rem;
            color: #666;
        }

        .quantity-input {
            width: 80px;
            padding: 4px 8px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin: 0 8px;
            text-align: center;
        }

        .remove-item {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 0.75rem;
        }

        .remove-item:hover {
            background: #c82333;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-style: italic;
        }

        /* Estilos para as listas de materiais e serviços selecionados */
        .selected-materials-list, .selected-services-list {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            min-height: 60px;
        }

        .selected-materials-list.empty, .selected-services-list.empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-style: italic;
        }

        .selected-item-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selected-item-card:last-child {
            margin-bottom: 0;
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
            min-height: var(--touch-target);
        }

        .btn-report:hover {
            background: linear-gradient(135deg, #e55a2e 0%, #ff6b35 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            color: white;
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

        .service-row, .material-row {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
            background: #f8f9fa;
            margin-bottom: 10px;
        }

        .form-control-sm, .form-select-sm {
            font-size: 0.85rem;
            min-height: 35px;
        }

        #servicesModal .modal-body {
            max-height: 70vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Estilos para upload de fotos */
        .photo-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            transition: var(--transition);
        }

        .photo-upload-area:hover {
            border-color: var(--primary-color);
            background: #e3f2fd;
            cursor: pointer;
        }

        .upload-placeholder {
            cursor: pointer;
            padding: 20px;
        }

        .photo-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .photo-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #dee2e6;
        }

        .photo-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview-item .remove-photo {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }

        .photo-preview-item .photo-type-selector {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.8);
            color: white;
        }

        .photo-preview-item .photo-type-selector select {
            background: transparent;
            border: none;
            color: white;
            font-size: 11px;
            width: 100%;
        }

        .photo-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .photo-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
        }

        .photo-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            cursor: pointer;
        }

        .photo-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .photo-gallery-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: var(--transition);
        }

        .photo-gallery-item:hover {
            transform: scale(1.05);
        }

        .photo-gallery-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .photo-gallery-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            padding: 10px 8px 5px;
            font-size: 0.75rem;
        }

        /* Responsividade progressiva e mobile-first */
        @media (max-width: 1400px) {
            :root {
                --sidebar-width: 260px;
            }
        }

        @media (max-width: 1200px) {
            .content-area {
                padding: 1.5rem;
            }
            .stat-card {
                padding: 1.5rem;
            }
            .search-filters {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 992px) {
            .content-area {
                padding: 1rem;
            }
            .search-filters {
                padding: 1rem;
            }
            .top-header {
                padding: 0 1rem;
            }
            
            .maintenance-card .card-body {
                padding: 0.75rem;
            }
            
            .btn-group {
                display: flex;
                flex-wrap: wrap;
            }
            
            .btn-group .btn {
                flex: 1;
                min-width: 40px;
                margin: 1px;
            }
        }
        
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 100%;
                --header-height: 60px;
            }

            body {
                padding-bottom: env(safe-area-inset-bottom);
            }

            .sidebar {
                transform: translateX(-100%);
                width: 300px;
                max-width: 85vw;
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
                padding-bottom: calc(1rem + env(safe-area-inset-bottom));
            }

            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .top-header {
                padding: 0 1rem;
                height: 60px;
            }

            .search-filters {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }
            
            .modal-xl-custom {
                max-width: calc(100% - 1rem);
                margin: 0.5rem;
            }

            .maintenance-card {
                margin-bottom: 1rem;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn-group .btn {
                border-radius: var(--border-radius) !important;
                margin-bottom: 2px;
            }

            /* Melhorar area de toque */
            .nav-link {
                padding: 1rem;
                min-height: 50px;
            }

            /* Otimizar formulários mobile */
            .form-control, .form-select {
                font-size: 16px; /* Evita zoom automático no iOS */
                padding: 1rem;
            }

            /* Ajustar grid de cards */
            .row > [class*="col-"] {
                margin-bottom: 1rem;
            }

            /* Modais responsivos para mobile */
            .search-results {
                max-height: 300px;
            }

            .selected-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .selected-item-actions {
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .photo-gallery {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }
        
        @media (max-width: 576px) {
            .content-area {
                padding: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .search-filters {
                padding: 0.75rem;
            }

            .modal-dialog {
                margin: 0.25rem;
                max-width: calc(100% - 0.5rem);
            }

            .maintenance-card .card-header,
            .maintenance-card .card-body,
            .maintenance-card .card-footer {
                padding: 0.75rem;
            }

            .btn-sm {
                padding: 0.375rem 0.5rem;
                font-size: 0.8rem;
            }

            /* Ajustar texto para telas pequenas */
            h4 {
                font-size: 1.1rem;
            }

            h5 {
                font-size: 1rem;
            }

            .modal-title {
                font-size: 1.1rem;
            }

            /* Scrollbar personalizada para mobile */
            ::-webkit-scrollbar {
                width: 4px;
            }

            ::-webkit-scrollbar-track {
                background: #f1f1f1;
            }

            ::-webkit-scrollbar-thumb {
                background: var(--primary-color);
                border-radius: 2px;
            }

            .photo-gallery {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Otimizações para iPhone */
        @media (max-width: 414px) {
            .sidebar {
                width: 280px;
            }
            
            .top-header h4 {
                font-size: 1rem;
            }
            
            .stat-card h3 {
                font-size: 1.5rem;
            }
        }

        /* Animações suaves */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Hover effects apenas para desktop */
        @media (hover: hover) {
            .hover-lift {
                transition: var(--transition);
            }

            .hover-lift:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-medium);
            }
        }

        /* Form validation styles */
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

        /* PWA e mobile improvements */
        @supports (-webkit-touch-callout: none) {
            /* iOS specific styles */
            .form-control, .form-select {
                border-radius: 0;
            }
        }

        /* Loading states */
        .loading {
            pointer-events: none;
            opacity: 0.7;
        }

        /* Safe area para notch */
        @supports (padding: max(0px)) {
            .content-area {
                padding-left: max(1rem, env(safe-area-inset-left));
                padding-right: max(1rem, env(safe-area-inset-right));
            }
            
            .top-header {
                padding-left: max(1rem, env(safe-area-inset-left));
                padding-right: max(1rem, env(safe-area-inset-right));
            }
        }

        /* Estilos para preview de fotos no modal de edição */
        .existing-photos-preview {
            min-height: 60px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .photo-gallery-small {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
        }

        .photo-item-small {
            position: relative;
            width: 60px;
            height: 60px;
            border-radius: 6px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .photo-item-small:hover {
            transform: scale(1.1);
            z-index: 10;
        }

        .photo-item-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-type-badge {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.8);
            color: white;
            font-size: 0.6rem;
            text-align: center;
            padding: 1px 2px;
            text-transform: capitalize;
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay para mobile -->
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
                <?= UserPermissions::generateSidebar($_SESSION['user_type'], 'manutencoes.php') ?>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center">
                <button class="btn d-md-none me-3" id="sidebarToggle" aria-label="Toggle navigation">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h4 class="mb-0">
                    <?php if ($_SESSION['user_type'] === 'tecnico'): ?>
                        Minhas Manutenções
                    <?php elseif ($_SESSION['user_type'] === 'usuario'): ?>
                        Manutenções Realizadas
                    <?php else: ?>
                        Gestão de Manutenções
                    <?php endif; ?>
                </h4>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-2"></i>
                    <span class="d-none d-sm-inline"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
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
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Mensagens informativas para usuários limitados -->
            <?php if ($_SESSION['user_type'] === 'usuario'): ?>
            <div class="alert alert-info fade-in">
                <div class="d-flex align-items-start">
                    <i class="bi bi-info-circle fs-5 me-3 mt-1"></i>
                    <div>
                        <h6 class="mb-2">Visualização de Manutenções</h6>
                        <p class="mb-0">
                            Você pode visualizar apenas manutenções concluídas e canceladas. 
                            Para reportar problemas ou solicitar manutenções, entre em contato com um técnico.
                        </p>
                    </div>
                </div>
            </div>
            <?php elseif ($_SESSION['user_type'] === 'tecnico'): ?>
            <div class="alert alert-success fade-in">
                <div class="d-flex align-items-start">
                    <i class="bi bi-tools fs-5 me-3 mt-1"></i>
                    <div>
                        <h6 class="mb-2">Painel do Técnico</h6>
                        <p class="mb-0">
                            Você pode gerenciar suas manutenções e assumir manutenções disponíveis. 
                            Use os filtros para encontrar rapidamente suas tarefas pendentes.
                        </p>
                    </div>
                </div>
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
                                    <i class="bi bi-tools"></i>
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
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--accent-color) 0%, #e6a800 100%);">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['agendadas'] ?></h3>
                                    <p class="text-muted mb-0">Agendadas</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);">
                                    <i class="bi bi-play-circle"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['em_andamento'] ?></h3>
                                    <p class="text-muted mb-0">Em Andamento</p>
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
                                    <h3 class="mb-1"><?= $stats['concluidas'] ?></h3>
                                    <p class="text-muted mb-0">Concluídas</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif (hasPermission('dashboard', 'basic_stats')): ?>
                    <!-- Stats para Técnico -->
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);">
                                    <i class="bi bi-person-check"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['minhas_total'] ?></h3>
                                    <p class="text-muted mb-0">Minhas Manutenções</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--accent-color) 0%, #e6a800 100%);">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['minhas_agendadas'] ?></h3>
                                    <p class="text-muted mb-0">Agendadas</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);">
                                    <i class="bi bi-play-circle"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['minhas_andamento'] ?></h3>
                                    <p class="text-muted mb-0">Em Andamento</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                    <i class="bi bi-plus-circle"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['disponiveis'] ?></h3>
                                    <p class="text-muted mb-0">Disponíveis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Stats para Usuário comum -->
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['concluidas_mes'] ?></h3>
                                    <p class="text-muted mb-0">Concluídas este Mês</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['equipamentos_ok'] ?></h3>
                                    <p class="text-muted mb-0">Equipamentos OK</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--accent-color) 0%, #e6a800 100%);">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['em_manutencao'] ?></h3>
                                    <p class="text-muted mb-0">Em Manutenção</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters fade-in">
                <form method="GET" class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Equipamento</label>
                        <select class="form-select" name="equipamento">
                            <option value="">Todos os equipamentos</option>
                            <?php foreach ($equipamentos as $eq): ?>
                                <option value="<?= $eq['id'] ?>" <?= $equipamento_filter == $eq['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($eq['codigo']) ?> - <?= htmlspecialchars($eq['localizacao']) ?>
                                    <?php if ($eq['status'] === 'manutencao'): ?>
                                        🔧
                                    <?php elseif ($eq['status'] === 'inativo'): ?>
                                        ❌
                                    <?php else: ?>
                                        ✅
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">Todos os status</option>
                            <?php if ($_SESSION['user_type'] !== 'usuario'): ?>
                                <option value="agendada" <?= $status_filter === 'agendada' ? 'selected' : '' ?>>Agendada</option>
                                <option value="em_andamento" <?= $status_filter === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                            <?php endif; ?>
                            <option value="concluida" <?= $status_filter === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                            <?php if ($_SESSION['user_type'] !== 'usuario'): ?>
                                <option value="cancelada" <?= $status_filter === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <?php if ($_SESSION['user_type'] === 'tecnico'): ?>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Atribuição</label>
                        <select class="form-select" name="atribuicao">
                            <option value="">Todas</option>
                            <option value="minhas" <?= $atribuicao_filter === 'minhas' ? 'selected' : '' ?>>Minhas Manutenções</option>
                            <option value="disponiveis" <?= $atribuicao_filter === 'disponiveis' ? 'selected' : '' ?>>Disponíveis</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Categoria</label>
                        <select class="form-select" name="tipo">
                            <option value="">Todas as categorias</option>
                            <option value="limpeza" <?= $tipo_filter === 'limpeza' ? 'selected' : '' ?>>Limpeza</option>
                            <option value="manutencao" <?= $tipo_filter === 'manutencao' ? 'selected' : '' ?>>Manutenção</option>
                            <option value="instalacao" <?= $tipo_filter === 'instalacao' ? 'selected' : '' ?>>Instalação</option>
                            <option value="inspecao" <?= $tipo_filter === 'inspecao' ? 'selected' : '' ?>>Inspeção</option>
                            <option value="troca" <?= $tipo_filter === 'troca' ? 'selected' : '' ?>>Troca</option>
                        </select>
                    </div>
                    
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Prioridade</label>
                        <select class="form-select" name="prioridade">
                            <option value="">Todas as prioridades</option>
                            <option value="baixa" <?= $prioridade_filter === 'baixa' ? 'selected' : '' ?>>🟢 Baixa</option>
                            <option value="media" <?= $prioridade_filter === 'media' ? 'selected' : '' ?>>🔵 Média</option>
                            <option value="alta" <?= $prioridade_filter === 'alta' ? 'selected' : '' ?>>🟡 Alta</option>
                            <option value="urgente" <?= $prioridade_filter === 'urgente' ? 'selected' : '' ?>>🔴 Urgente</option>
                        </select>
                    </div>
                    
                    <div class="col-lg-1 col-md-6">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary-custom w-100" aria-label="Filtrar resultados">
                            <i class="bi bi-funnel"></i>
                        </button>
                    </div>
                    
                    <div class="col-lg-1 col-md-6">
                        <label class="form-label">&nbsp;</label>
                        <a href="manutencoes.php" class="btn btn-outline-secondary w-100" aria-label="Limpar filtros">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Maintenance Cards -->
            <div class="card table-card fade-in">
                <div class="card-header">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
                        <h5 class="mb-0"><i class="bi bi-tools me-2"></i>Lista de Manutenções</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if (hasPermission('relatorios', 'view')): ?>
                            <button class="btn btn-report btn-sm" onclick="generatePhotoReport()" 
                                    title="Gerar relatório fotográfico" aria-label="Gerar relatório fotográfico">
                                <i class="bi bi-camera me-1"></i>
                                <span class="d-none d-sm-inline">Relatório Fotográfico</span>
                                <span class="d-sm-none">Relatório</span>
                            </button>
                            <?php endif; ?>
                            <?php if (hasPermission('manutencoes', 'create')): ?>
                            <button class="btn btn-primary-custom btn-sm" onclick="openModal('create')" aria-label="Nova manutenção">
                                <i class="bi bi-plus me-1"></i>
                                <span class="d-none d-sm-inline">Nova Manutenção</span>
                                <span class="d-sm-none">Nova</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($manutencoes)): ?>
                        <div class="text-center p-5 text-muted">
                            <i class="bi bi-inbox fs-1 mb-3 opacity-50"></i>
                            <h5>Nenhuma manutenção encontrada</h5>
                            <p>Não há manutenções que correspondam aos filtros aplicados.</p>
                            <?php if (hasPermission('manutencoes', 'create')): ?>
                            <button class="btn btn-primary-custom mt-2" onclick="openModal('create')">
                                <i class="bi bi-plus me-1"></i>Criar Primeira Manutenção
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php 
                            foreach ($manutencoes as $man) {
                            ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="maintenance-card fade-in priority-<?php echo $man['prioridade']; ?>">
                                        <div class="card-header">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1 pe-2">
                                                    <h6 class="mb-1">
                                                        <i class="bi bi-<?php echo getTypeIcon($man['tipo_manutencao_categoria'] ?? 'manutencao'); ?> me-2"></i>
                                                        <?php echo htmlspecialchars($man['tipo_manutencao_nome'] ?? 'Não definido'); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo $man['equipamento_codigo']; ?> - <?php echo htmlspecialchars($man['equipamento_localizacao']); ?>
                                                    </small>
                                                </div>
                                                <span class="badge <?php echo getPriorityBadge($man['prioridade']); ?> rounded-pill flex-shrink-0">
                                                    <?php echo ucfirst($man['prioridade']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="card-body">
                                            <p class="card-text">
                                                <?php echo htmlspecialchars(strlen($man['descricao']) > 100 ? substr($man['descricao'], 0, 100) . '...' : $man['descricao']); ?>
                                            </p>
                                            
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Status</small>
                                                    <span class="badge <?php echo getStatusBadge($man['status']); ?> rounded-pill">
                                                        <?php echo ucfirst(str_replace('_', ' ', $man['status'])); ?>
                                                    </span>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Agendado</small>
                                                    <small>
                                                        <?php echo $man['data_agendada'] ? date('d/m/Y', strtotime($man['data_agendada'])) : '-'; ?>
                                                    </small>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Fotos</small>
                                                    <small>
                                                        <i class="bi bi-camera"></i> <?php echo $man['total_fotos'] ?? 0; ?>
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <?php if ($man['tecnico_nome']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">Técnico: </small>
                                                <small><?php echo htmlspecialchars($man['tecnico_nome']); ?></small>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($man['tipo_manutencao_categoria']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">Categoria: </small>
                                                <span class="badge bg-info"><?php echo ucfirst($man['tipo_manutencao_categoria']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="card-footer bg-transparent">
                                            <div class="btn-group w-100 mb-2" role="group" aria-label="Ações da manutenção">
                                                <button type="button" class="btn btn-outline-info btn-sm" 
                                                        onclick="viewMaintenance(<?php echo htmlspecialchars(json_encode($man)); ?>)"
                                                        title="Visualizar" aria-label="Visualizar manutenção">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                
                                                <?php if (hasPermission('manutencoes', 'edit')): ?>
                                                    <?php 
                                                    $can_edit = true;
                                                    // Técnicos só podem editar suas próprias manutenções ou não atribuídas
                                                    if ($_SESSION['user_type'] === 'tecnico') {
                                                        $can_edit = ($man['tecnico_id'] == $_SESSION['user_id'] || $man['tecnico_id'] === null);
                                                    }
                                                    ?>
                                                    <?php if ($can_edit): ?>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                                            onclick="editMaintenance(<?php echo htmlspecialchars(json_encode($man)); ?>)"
                                                            title="Editar" aria-label="Editar manutenção">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-success btn-sm" 
                                                            onclick="openServicesModal(<?php echo $man['id']; ?>, '<?php echo $man['equipamento_tipo']; ?>')"
                                                            title="Gerenciar Serviços e Materiais" aria-label="Gerenciar serviços e materiais">
                                                        <i class="bi bi-tools"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <?php if (hasPermission('manutencoes', 'delete')): ?>
                                                    <?php 
                                                    $can_delete = true;
                                                    // Técnicos só podem excluir suas próprias manutenções
                                                    if ($_SESSION['user_type'] === 'tecnico') {
                                                        $can_delete = ($man['tecnico_id'] == $_SESSION['user_id']);
                                                    }
                                                    ?>
                                                    <?php if ($can_delete): ?>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                                            onclick="deleteMaintenance(<?php echo $man['id']; ?>, '<?php echo htmlspecialchars($man['equipamento_codigo']); ?>')"
                                                            title="Excluir" aria-label="Excluir manutenção">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div>
                                                <a href="manutencoes.php?equipamento=<?php echo $man['equipamento_id']; ?>" 
                                                   class="btn btn-outline-secondary btn-sm w-100">
                                                    <i class="bi bi-list me-1"></i>
                                                    <span class="d-none d-sm-inline">Ver Todas do Equipamento</span>
                                                    <span class="d-sm-none">Ver Todas</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                            }
                            ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center p-3 border-top gap-3">
                                <small class="text-muted text-center">
                                    Mostrando <?php echo ($page - 1) * $per_page + 1; ?> a <?php echo min($page * $per_page, $total); ?> de <?php echo $total; ?> registros
                                </small>
                                <nav aria-label="Navegação das páginas">
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">Anterior</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">Próximo</a>
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

    <!-- Maintenance Modal -->
    <?php if (hasPermission('manutencoes', 'create') || hasPermission('manutencoes', 'edit')): ?>
    <div class="modal fade" id="maintenanceModal" tabindex="-1" aria-labelledby="maintenanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl-custom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nova Manutenção</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" id="maintenanceForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="modalAction" value="create">
                        <input type="hidden" name="id" id="modalId">
                        <input type="hidden" name="materiais_selecionados" id="materiaisSelecionadosInput">
                        <input type="hidden" name="servicos_selecionados" id="servicosSelecionadosInput">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="modalEquipamento">Equipamento *</label>
                                    <select class="form-select" name="equipamento_id" id="modalEquipamento" required>
                                        <option value="">Selecione o equipamento</option>
                                        <?php foreach ($equipamentos as $eq): ?>
                                            <option value="<?= $eq['id'] ?>" data-tipo="<?= $eq['tipo'] ?>">
                                                <?= htmlspecialchars($eq['codigo']) ?> - <?= htmlspecialchars($eq['localizacao']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="modalTipoManutencao">Tipo de Manutenção *</label>
                                    <select class="form-select" name="tipo_manutencao_id" id="modalTipoManutencao" required>
                                        <option value="">Selecione o tipo</option>
                                        <!-- Será preenchido dinamicamente via JavaScript -->
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="modalPrioridade">Prioridade</label>
                                    <select class="form-select" name="prioridade" id="modalPrioridade">
                                        <option value="baixa">🟢 Baixa</option>
                                        <option value="media" selected>🔵 Média</option>
                                        <option value="alta">🟡 Alta</option>
                                        <option value="urgente">🔴 Urgente</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="modalDataAgendada">Data Agendada</label>
                                    <input type="date" class="form-control" name="data_agendada" id="modalDataAgendada">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="modalTecnico">Técnico Responsável</label>
                                    <select class="form-select" name="tecnico_id" id="modalTecnico">
                                        <option value="">Não atribuído</option>
                                        <?php foreach ($tecnicos as $tec): ?>
                                            <option value="<?= $tec['id'] ?>">
                                                <?= htmlspecialchars($tec['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="modalStatus">Status</label>
                                    <select class="form-select" name="status" id="modalStatus">
                                        <option value="agendada">📅 Agendada</option>
                                        <option value="em_andamento">⚡ Em Andamento</option>
                                        <option value="concluida">✅ Concluída</option>
                                        <option value="cancelada">❌ Cancelada</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="modalDescricao">Descrição *</label>
                            <textarea class="form-control" name="descricao" id="modalDescricao" rows="3" 
                                      placeholder="Descreva detalhadamente o problema ou serviço..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="modalProblemaRelatado">Problema Relatado</label>
                            <textarea class="form-control" name="problema_relatado" id="modalProblemaRelatado" rows="2" 
                                      placeholder="Descreva o problema específico reportado..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="modalObservacoes">Observações</label>
                            <textarea class="form-control" name="observacoes" id="modalObservacoes" rows="2" 
                                      placeholder="Informações adicionais (opcional)..."></textarea>
                        </div>

                        <!-- Seção Materiais/Peças -->
                        <div class="mb-4">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-tools me-2"></i>Materiais/Peças Necessários
                            </h6>
                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="openMaterialModal()">
                                <i class="bi bi-plus me-1"></i>Adicionar Material
                            </button>
                            <div class="selected-materials-list empty" id="selectedMaterialsList">
                                Nenhum material selecionado
                            </div>
                        </div>

                        <!-- Seção Serviços -->
                        <div class="mb-4">
                            <h6 class="text-success border-bottom pb-2 mb-3">
                                <i class="bi bi-gear me-2"></i>Serviços a Executar
                            </h6>
                            <button type="button" class="btn btn-outline-success btn-sm mb-3" onclick="openServiceModal()">
                                <i class="bi bi-plus me-1"></i>Adicionar Serviço
                            </button>
                            <div class="selected-services-list empty" id="selectedServicesList">
                                Nenhum serviço selecionado
                            </div>
</div>

                        <!-- Seção Upload de Fotos -->
                        <div class="mb-4" id="photoSectionCreate">
                            <h6 class="text-info border-bottom pb-2 mb-3">
                                <i class="bi bi-camera me-2"></i>Fotos da Manutenção
                            </h6>
                            <div class="photo-upload-area" id="photoUploadArea">
                                <input type="file" id="maintenancePhotos" name="maintenance_photos[]" multiple accept="image/*" style="display: none;">
                                <div class="upload-placeholder" onclick="document.getElementById('maintenancePhotos').click()">
                                    <i class="bi bi-camera-fill fs-1 mb-2"></i>
                                    <p class="mb-1">Clique aqui para selecionar fotos</p>
                                    <small class="text-muted">Selecione até 10 fotos (máx. 5MB cada)</small>
                                </div>
                            </div>
                            <div id="photoPreviewContainer" class="photo-preview-container mt-3" style="display: none;"></div>
                        </div>

                        <!-- Seção Upload de Fotos para Edição -->
                        <div class="mb-4" id="photoSectionEdit" style="display: none;">
                            <h6 class="text-info border-bottom pb-2 mb-3">
                                <i class="bi bi-camera me-2"></i>Fotos da Manutenção
                            </h6>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="photo-upload-area mb-3">
                                        <input type="file" id="editMaintenancePhotos" name="edit_maintenance_photos[]" multiple accept="image/*" style="display: none;">
                                        <div class="upload-placeholder" onclick="document.getElementById('editMaintenancePhotos').click()">
                                            <i class="bi bi-camera-fill fs-3 mb-2"></i>
                                            <p class="mb-1">Adicionar Novas Fotos</p>
                                            <small class="text-muted">Selecione até 10 fotos (máx. 5MB cada)</small>
                                        </div>
                                    </div>
                                    <div id="editPhotoPreviewContainer" class="photo-preview-container" style="display: none;"></div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadMaintenancePhotos(currentMaintenanceId)">
                                    <i class="bi bi-eye me-1"></i>Ver Fotos Existentes
                                </button>
                            </div>
                            <div id="existingPhotosPreview" class="existing-photos-preview mt-3"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom" id="modalSubmit">
                            <i class="bi bi-check me-1"></i>Salvar
                        </button>
                    </div>
                                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Material Selection Modal -->
    <div class="modal fade search-modal" id="materialModal" tabindex="-1" aria-labelledby="materialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-tools me-2"></i>Selecionar Materiais/Peças</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="search-input" id="materialSearchInput" 
                           placeholder="Digite para pesquisar materiais por código, nome ou categoria...">
                    
                    <div class="search-results" id="materialSearchResults">
                        <div class="no-results">Digite para pesquisar materiais...</div>
                    </div>

                    <div class="selected-items" id="selectedMaterialsTemp" style="display: none;">
                        <h6>Materiais Selecionados:</h6>
                        <div id="selectedMaterialsContainer"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary-custom" onclick="confirmMaterialSelection()">
                        <i class="bi bi-check me-1"></i>Confirmar Seleção
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Selection Modal -->
    <div class="modal fade search-modal" id="serviceModal" tabindex="-1" aria-labelledby="serviceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-gear me-2"></i>Selecionar Serviços</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="search-input" id="serviceSearchInput" 
                           placeholder="Digite para pesquisar serviços por código, nome ou categoria...">
                    
                    <div class="search-results" id="serviceSearchResults">
                        <div class="no-results">Digite para pesquisar serviços...</div>
                    </div>

                    <div class="selected-items" id="selectedServicesTemp" style="display: none;">
                        <h6>Serviços Selecionados:</h6>
                        <div id="selectedServicesContainer"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary-custom" onclick="confirmServiceSelection()">
                        <i class="bi bi-check me-1"></i>Confirmar Seleção
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Detalhes da Manutenção</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="viewModalBody">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Upload Modal -->
    <div class="modal fade" id="photoUploadModal" tabindex="-1" aria-labelledby="photoUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoUploadModalLabel">
                        <i class="bi bi-camera me-2"></i>Upload de Fotos da Manutenção
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" id="photoUploadForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="uploadManutencaoId" name="manutencao_id">
                        <input type="hidden" name="action" value="upload_maintenance_photos">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tipo de Foto *</label>
                                <select class="form-select" id="tipoFoto" name="tipo_foto" required>
                                    <option value="">Selecione o tipo</option>
                                    <option value="antes">Antes da Manutenção</option>
                                    <option value="durante">Durante a Manutenção</option>
                                    <option value="depois">Depois da Manutenção</option>
                                    <option value="problema">Problema Identificado</option>
                                    <option value="solucao">Solução Aplicada</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fotos (máx. 5 por tipo) *</label>
                                <input type="file" class="form-control" id="fotosInput" name="fotos[]" 
                                       accept="image/*" multiple required>
                                <small class="text-muted">Formatos aceitos: JPG, PNG, WebP. Máx. 5MB por foto.</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" name="descricao" rows="3" 
                                      placeholder="Descreva o que está sendo documentado nas fotos..."></textarea>
                        </div>
                        
                        <!-- Preview das fotos selecionadas -->
                        <div id="fotosPreview" style="display: none;">
                            <h6 class="text-primary">Fotos Selecionadas:</h6>
                            <div id="fotosPreviewContainer" class="photo-preview"></div>
                        </div>
                        
                        <!-- Progresso do upload -->
                        <div id="uploadProgress" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">Fazendo upload das fotos...</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom" id="uploadSubmitBtn">
                            <i class="bi bi-cloud-upload me-2"></i>Fazer Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Photo View Modal -->
    <div class="modal fade" id="photoViewModal" tabindex="-1" aria-labelledby="photoViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoViewModalLabel">Visualizar Foto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="photoViewImage" src="" class="img-fluid rounded" alt="Foto da manutenção" style="max-height: 70vh;">
                    <p id="photoViewDescription" class="mt-3 text-muted"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Services and Materials Modal -->
    <?php if (hasPermission('manutencoes', 'edit')): ?>
    <div class="modal fade" id="servicesModal" tabindex="-1" aria-labelledby="servicesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-tools me-2"></i>Gerenciar Serviços e Materiais</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" id="servicesForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_services_materials">
                        <input type="hidden" name="manutencao_id" id="servicesManutencaoId">
                        
                        <div class="row">
                            <!-- Serviços Executados -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Serviços Executados</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="servicesContainer">
                                            <!-- Serviços serão carregados aqui -->
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addServiceRow()">
                                            <i class="bi bi-plus me-1"></i>Adicionar Serviço
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Materiais Utilizados -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Materiais Utilizados</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="materialsContainer">
                                            <!-- Materiais serão carregados aqui -->
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="addMaterialRow()">
                                            <i class="bi bi-plus me-1"></i>Adicionar Material
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resumo de Custos -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <h6><i class="bi bi-calculator me-2"></i>Resumo de Custos</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Custo Materiais:</strong> R$ <span id="custoMateriais">0,00</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Tempo Total:</strong> <span id="tempoTotal">0</span> minutos
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Custo Total:</strong> R$ <span id="custoTotal">0,00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-check me-1"></i>Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Delete Form -->
    <?php if (hasPermission('manutencoes', 'delete')): ?>
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variáveis globais
        let availableServices = [];
        let availableMaterials = [];
        let selectedMaterials = [];
        let selectedServices = [];
        let tempSelectedMaterials = [];
        let tempSelectedServices = [];
        let serviceRowCounter = 0;
        let materialRowCounter = 0;
        let currentMaintenanceId = null;

        // Definir permissões globais do usuário
        const userPermissions = <?php echo json_encode($_SESSION['permissions'] ?? []); ?>;
        const userType = '<?php echo $_SESSION['user_type']; ?>';

        // Sidebar toggle com melhor responsividade
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
                document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
            });
        }

        // Close sidebar when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            });
        }

        // Close sidebar on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

        // Initialize system on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAvailableData();
            loadTiposManutencao();
            setupEventListeners();
            setupTouchEvents();
            setupMaintenancePhotoUpload();
            setupEditMaintenancePhotoUpload();
        });

        function setupTouchEvents() {
            // Prevent zoom on double tap for form controls
            let lastTouchEnd = 0;
            document.addEventListener('touchend', function (event) {
                const now = (new Date()).getTime();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, false);
        }

        function setupEventListeners() {
            // Event listener para mudança de equipamento
            const equipamentoSelect = document.getElementById('modalEquipamento');
            if (equipamentoSelect) {
                equipamentoSelect.addEventListener('change', function() {
                    const equipamentoId = this.value;
                    if (equipamentoId) {
                        const equipamentoOption = this.selectedOptions[0];
                        const equipamentoTipo = equipamentoOption.dataset.tipo;
                        loadTiposManutencao(equipamentoTipo);
                    } else {
                        loadTiposManutencao();
                    }
                });
            }

            // Event listener para tipo de manutenção
            const tipoManutencaoSelect = document.getElementById('modalTipoManutencao');
            if (tipoManutencaoSelect) {
                tipoManutencaoSelect.addEventListener('change', function() {
                    const selectedOption = this.selectedOptions[0];
                    if (selectedOption) {
                        const prioridadeDefault = selectedOption.dataset.prioridadeDefault;
                        if (prioridadeDefault) {
                            document.getElementById('modalPrioridade').value = prioridadeDefault;
                        }
                    }
                });
            }

            // Setup search inputs
            setupMaterialSearchInput();
            setupServiceSearchInput();
            
            // Setup photo upload preview
            setupPhotoUploadPreview();
        }

        // Função para carregar tipos de manutenção
        function loadTiposManutencao(equipamentoTipo = null) {
            return fetch(`manutencoes.php?action=get_tipos_manutencao&equipamento_tipo=${equipamentoTipo || ''}`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('modalTipoManutencao');
                    if (select) {
                        select.innerHTML = '<option value="">Selecione o tipo</option>';
                        
                        data.forEach(tipo => {
                            const option = document.createElement('option');
                            option.value = tipo.id;
                            option.textContent = `${tipo.codigo} - ${tipo.nome} (${tipo.categoria})`;
                            option.dataset.categoria = tipo.categoria;
                            option.dataset.tempoEstimado = tipo.tempo_estimado;
                            option.dataset.prioridadeDefault = tipo.prioridade_default;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Erro ao carregar tipos de manutenção:', error));
        }

        // Carregar dados de materiais e serviços
        async function loadAvailableData() {
            try {
                console.log('Iniciando carregamento de dados...');
                
                // Carregar materiais via AJAX
                const materiaisResponse = await fetch('manutencoes.php?action=get_materiais_servicos&type=materiais');
                if (materiaisResponse.ok) {
                    const materiaisResult = await materiaisResponse.json();
                    if (Array.isArray(materiaisResult)) {
                        availableMaterials = materiaisResult;
                        console.log('Materiais carregados:', availableMaterials.length);
                    } else {
                        console.error('Resposta de materiais inválida:', materiaisResult);
                        availableMaterials = [];
                    }
                } else {
                    console.error('Erro ao carregar materiais:', materiaisResponse.statusText);
                    availableMaterials = [];
                }
                
                // Carregar serviços via AJAX
                const servicosResponse = await fetch('manutencoes.php?action=get_materiais_servicos&type=servicos');
                if (servicosResponse.ok) {
                    const servicosResult = await servicosResponse.json();
                    if (Array.isArray(servicosResult)) {
                        availableServices = servicosResult;
                        console.log('Serviços carregados:', availableServices.length);
                    } else {
                        console.error('Resposta de serviços inválida:', servicosResult);
                        availableServices = [];
                    }
                } else {
                    console.error('Erro ao carregar serviços:', servicosResponse.statusText);
                    availableServices = [];
                }
                
                console.log('Carregamento concluído - Materiais:', availableMaterials.length, 'Serviços:', availableServices.length);
                
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
                availableMaterials = [];
                availableServices = [];
            }
        }

        // Função para abrir modal de criação/edição
        <?php if (hasPermission('manutencoes', 'create') || hasPermission('manutencoes', 'edit')): ?>
function openModal(action, data = null) {
            const modal = document.getElementById('maintenanceModal');
            const form = document.getElementById('maintenanceForm');
            
            // Reset form
            form.reset();
            selectedMaterials = [];
            selectedServices = [];
            updateSelectedMaterialsList();
            updateSelectedServicesList();
            
            // Clear validation states
            document.querySelectorAll('.is-invalid').forEach(field => {
                field.classList.remove('is-invalid');
            });
            document.querySelectorAll('.invalid-feedback').forEach(feedback => {
                feedback.remove();
            });
            
            if (action === 'create') {
                document.getElementById('modalTitle').textContent = 'Nova Manutenção';
                document.getElementById('modalAction').value = 'create';
                document.getElementById('modalSubmit').innerHTML = '<i class="bi bi-check me-1"></i>Salvar';
                document.getElementById('modalId').value = '';
                currentMaintenanceId = null;
                
                // Mostrar seção de fotos para criação e esconder para edição
                document.getElementById('photoSectionEdit').style.display = 'none';
                document.getElementById('photoSectionCreate').style.display = 'block';
                
                // Limpar preview de fotos
                document.getElementById('photoPreviewContainer').innerHTML = '';
                document.getElementById('photoPreviewContainer').style.display = 'none';
                document.getElementById('maintenancePhotos').value = '';
                
                loadTiposManutencao();
                
            } else if (action === 'edit' && data) {
                document.getElementById('modalTitle').textContent = 'Editar Manutenção';
                document.getElementById('modalAction').value = 'update';
                document.getElementById('modalSubmit').innerHTML = '<i class="bi bi-check me-1"></i>Atualizar';
                
                // Mostrar seção de fotos para edição e esconder para criação
                document.getElementById('photoSectionEdit').style.display = 'block';
                document.getElementById('photoSectionCreate').style.display = 'none';
                currentMaintenanceId = data.id;
                
                // Limpar preview de fotos de edição
                document.getElementById('editPhotoPreviewContainer').innerHTML = '';
                document.getElementById('editPhotoPreviewContainer').style.display = 'none';
                document.getElementById('editMaintenancePhotos').value = '';
                
                // Carregar fotos existentes
                loadMaintenancePhotos(data.id);
                document.getElementById('modalTitle').textContent = 'Editar Manutenção';
                document.getElementById('modalAction').value = 'update';
                document.getElementById('modalSubmit').innerHTML = '<i class="bi bi-check me-1"></i>Atualizar';
                
                // Fill form with data
                document.getElementById('modalEquipamento').value = data.equipamento_id || '';
                document.getElementById('modalPrioridade').value = data.prioridade || 'media';
                document.getElementById('modalDataAgendada').value = data.data_agendada || '';
                document.getElementById('modalTecnico').value = data.tecnico_id || '';
                document.getElementById('modalStatus').value = data.status || 'agendada';
                document.getElementById('modalDescricao').value = data.descricao || '';
                document.getElementById('modalProblemaRelatado').value = data.problema_relatado || '';
                document.getElementById('modalObservacoes').value = data.observacoes || '';
                document.getElementById('modalId').value = data.id;
                currentMaintenanceId = data.id;
                
                // Load tipos and set current
                const equipamentoSelect = document.getElementById('modalEquipamento');
                const equipamentoTipo = equipamentoSelect.selectedOptions[0]?.dataset.tipo;

                loadTiposManutencao(equipamentoTipo).then(() => {
                    if (data.tipo_manutencao_id) {
                        document.getElementById('modalTipoManutencao').value = data.tipo_manutencao_id;
                    }
                });
            }
            
            new bootstrap.Modal(modal).show();
        }

        function editMaintenance(data) {
            openModal('edit', data);
        }
        <?php endif; ?>

        // Função para visualizar manutenção completa
        function viewMaintenance(data) {
            // Mostrar loading primeiro
            document.getElementById('viewModalBody').innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-2">Carregando detalhes da manutenção...</p>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            modal.show();
            
            // Buscar detalhes completos via API
            fetch(`get_manutencao_details.php?id=${data.id}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        renderMaintenanceDetails(result);
                    } else {
                        document.getElementById('viewModalBody').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                Erro ao carregar detalhes: ${result.error || 'Erro desconhecido'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar detalhes:', error);
                    document.getElementById('viewModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            Erro de conexão. Tente novamente.
                        </div>
                    `;
                });
        }

        function renderMaintenanceDetails(result) {
            const { manutencao, servicos, materiais, tratativas, resumo, fotos } = result;
            
            const statusLabels = {
                'agendada': '📅 Agendada',
                'em_andamento': '⚡ Em Andamento',
                'concluida': '✅ Concluída',
                'cancelada': '❌ Cancelada'
            };
            
            const priorityLabels = {
                'baixa': '🟢 Baixa',
                'media': '🔵 Média',
                'alta': '🟡 Alta',
                'urgente': '🔴 Urgente'
            };
            
            const content = `
                <!-- Informações Básicas -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações Gerais</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr><td><strong>Equipamento:</strong></td><td>${escapeHtml(manutencao.equipamento_codigo)} - ${escapeHtml(manutencao.equipamento_localizacao || '')}</td></tr>
                                    <tr><td><strong>Tipo:</strong></td><td>${escapeHtml(manutencao.tipo_manutencao_nome || 'Não definido')}</td></tr>
                                    <tr><td><strong>Prioridade:</strong></td><td>${priorityLabels[manutencao.prioridade] || manutencao.prioridade}</td></tr>
                                    <tr><td><strong>Status:</strong></td><td>${statusLabels[manutencao.status] || manutencao.status}</td></tr>
                                    <tr><td><strong>Técnico:</strong></td><td>${escapeHtml(manutencao.tecnico_nome || 'Não atribuído')}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-calculator me-2"></i>Resumo Financeiro</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr><td><strong>Custo Materiais:</strong></td><td>R$ ${formatCurrency(resumo.custo_materiais)}</td></tr>
                                    <tr><td><strong>Tempo Total:</strong></td><td>${resumo.tempo_total} min</td></tr>
                                    <tr><td><strong>Serviços:</strong></td><td>${resumo.total_servicos} executados</td></tr>
                                    <tr><td><strong>Materiais:</strong></td><td>${resumo.total_materiais} utilizados</td></tr>
                                    <tr><td><strong>Fotos:</strong></td><td>${fotos ? fotos.length : 0} anexadas</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Descrição e Observações -->
                ${manutencao.descricao ? `
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="bi bi-chat-text me-2"></i>Descrição</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0">${escapeHtml(manutencao.descricao)}</p>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Fotos da Manutenção -->
                ${fotos && fotos.length > 0 ? `
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="bi bi-camera me-2"></i>Fotos da Manutenção (${fotos.length})</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    ${renderMaintenancePhotos(fotos)}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Serviços Executados -->
                ${servicos.length > 0 ? `
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Serviços Executados (${servicos.length})</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Serviço</th>
                                                <th>Categoria</th>
                                                <th>Quantidade</th>
                                                <th>Tempo</th>
                                                <th>Status</th>
                                                <th>Executado por</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${servicos.map(servico => `
                                                <tr>
                                                    <td><code>${escapeHtml(servico.codigo || '')}</code></td>
                                                    <td>${escapeHtml(servico.servico_nome || '')}</td>
                                                    <td><span class="badge bg-secondary">${escapeHtml(servico.categoria || '')}</span></td>
                                                    <td>${servico.quantidade || 0}</td>
                                                    <td>${servico.tempo_gasto || 0} min</td>
                                                    <td>${servico.executado ? '<span class="badge bg-success">Executado</span>' : '<span class="badge bg-warning">Pendente</span>'}</td>
                                                    <td>${escapeHtml(servico.executado_por_nome || 'N/A')}</td>
                                                </tr>
                                                ${servico.observacoes ? `
                                                <tr>
                                                    <td colspan="7"><small class="text-muted"><strong>Obs:</strong> ${escapeHtml(servico.observacoes)}</small></td>
                                                </tr>
                                                ` : ''}
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Materiais Utilizados -->
                ${materiais.length > 0 ? `
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Materiais Utilizados (${materiais.length})</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Material</th>
                                                <th>Categoria</th>
                                                <th>Previsto</th>
                                                <th>Utilizado</th>
                                                <th>Unidade</th>
                                                <th>Preço Unit.</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${materiais.map(material => `
                                                <tr>
                                                    <td><code>${escapeHtml(material.codigo || '')}</code></td>
                                                    <td>${escapeHtml(material.material_nome || '')}</td>
                                                    <td><span class="badge bg-info">${escapeHtml(material.categoria || '')}</span></td>
                                                    <td>${material.quantidade_prevista || 0}</td>
                                                    <td><strong>${material.quantidade_utilizada || 0}</strong></td>
                                                    <td>${escapeHtml(material.unidade_medida || '')}</td>
                                                    <td>R$ ${formatCurrency(material.preco_unitario)}</td>
                                                    <td><strong>R$ ${formatCurrency((material.quantidade_utilizada || 0) * (material.preco_unitario || 0))}</strong></td>
                                                </tr>
                                                ${material.observacoes ? `
                                                <tr>
                                                    <td colspan="8"><small class="text-muted"><strong>Obs:</strong> ${escapeHtml(material.observacoes)}</small></td>
                                                </tr>
                                                ` : ''}
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Tratativas/Comentários -->
                ${tratativas && tratativas.length > 0 ? `
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Tratativas e Comentários (${tratativas.length})</h6>
                            </div>
                            <div class="card-body">
                                ${tratativas.map(tratativa => `
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h6 class="mb-1">${escapeHtml(tratativa.usuario_nome)}</h6>
                                            <small class="text-muted">${formatDate(tratativa.data_tratativa)}</small>
                                        </div>
                                        <p class="mb-0">${escapeHtml(tratativa.tratativa)}</p>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Botão para Upload de Fotos (se permitido) -->
                ${hasPermission('manutencoes', 'edit') ? `
                <div class="row">
                    <div class="col-12 text-center">
                        <button class="btn btn-primary-custom" onclick="openPhotoUploadModal(${manutencao.id})">
                            <i class="bi bi-camera me-2"></i>Adicionar Fotos da Manutenção
                        </button>
                    </div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('viewModalBody').innerHTML = content;
        }

        function renderMaintenancePhotos(fotos) {
            const fotosPorTipo = {
                'antes': fotos.filter(f => f.tipo_foto === 'antes'),
                'durante': fotos.filter(f => f.tipo_foto === 'durante'),
                'depois': fotos.filter(f => f.tipo_foto === 'depois'),
                'problema': fotos.filter(f => f.tipo_foto === 'problema'),
                'solucao': fotos.filter(f => f.tipo_foto === 'solucao')
            };
            
            let html = '';
            
            Object.entries(fotosPorTipo).forEach(([tipo, fotos_tipo]) => {
                if (fotos_tipo.length > 0) {
                    html += `
                        <div class="col-12 mb-3">
                            <h6 class="text-primary">${tipo.charAt(0).toUpperCase() + tipo.slice(1)} (${fotos_tipo.length})</h6>
                            <div class="photo-gallery">
                                ${fotos_tipo.map(foto => `
                                    <div class="photo-gallery-item" onclick="openPhotoModal('${escapeHtml(foto.caminho_arquivo)}', '${escapeHtml(foto.descricao || '')}')">
                                        <img src="${escapeHtml(foto.caminho_arquivo)}" alt="${escapeHtml(foto.descricao || '')}">
                                        ${foto.descricao ? `
                                            <div class="photo-gallery-caption">
                                                ${escapeHtml(foto.descricao)}
                                            </div>
                                        ` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }
            });
            
            return html || '<div class="col-12 text-center text-muted">Nenhuma foto anexada</div>';
        }

        // Funções para upload de fotos
        function openPhotoUploadModal(manutencaoId) {
            document.getElementById('uploadManutencaoId').value = manutencaoId;
            document.getElementById('photoUploadForm').reset();
            document.getElementById('fotosPreview').style.display = 'none';
            document.getElementById('uploadProgress').style.display = 'none';
            
            new bootstrap.Modal(document.getElementById('photoUploadModal')).show();
        }

        function openPhotoModal(imageSrc, description) {
            document.getElementById('photoViewImage').src = imageSrc;
            document.getElementById('photoViewDescription').textContent = description || 'Sem descrição';
            new bootstrap.Modal(document.getElementById('photoViewModal')).show();
        }

        function setupPhotoUploadPreview() {
            const fotosInput = document.getElementById('fotosInput');
            if (!fotosInput) return;
            
            fotosInput.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                const previewContainer = document.getElementById('fotosPreviewContainer');
                const previewSection = document.getElementById('fotosPreview');
                
                if (files.length === 0) {
                    previewSection.style.display = 'none';
                    return;
                }
                
                if (files.length > 5) {
                    alert('Máximo de 5 fotos por upload.');
                    e.target.value = '';
                    return;
                }
                
                previewContainer.innerHTML = '';
                previewSection.style.display = 'block';
                
                files.forEach((file, index) => {
                    if (file.size > 5 * 1024 * 1024) {
                        alert(`Arquivo ${file.name} é muito grande. Máximo 5MB por foto.`);
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'photo-preview-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="${file.name}">
                            <button type="button" class="photo-preview-remove" onclick="removePreviewPhoto(${index})">×</button>
                        `;
                        previewContainer.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            });
        }

        // Funções para upload de fotos no cadastro de manutenção
        function setupMaintenancePhotoUpload() {
            const photosInput = document.getElementById('maintenancePhotos');
            if (!photosInput) return;
            
            photosInput.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                const previewContainer = document.getElementById('photoPreviewContainer');
                
                if (files.length === 0) {
                    previewContainer.style.display = 'none';
                    return;
                }
                
                if (files.length > 10) {
                    alert('Máximo de 10 fotos por upload.');
                    e.target.value = '';
                    return;
                }
                
                previewContainer.innerHTML = '';
                previewContainer.style.display = 'block';
                
                files.forEach((file, index) => {
                    if (file.size > 5 * 1024 * 1024) {
                        alert(`Arquivo ${file.name} é muito grande. Máximo 5MB por foto.`);
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'photo-preview-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="${file.name}">
                            <button type="button" class="remove-photo" onclick="removeMaintenancePhoto(${index})">×</button>
                            <div class="photo-type-selector">
                                <select name="photo_types[]" class="form-control">
                                    <option value="durante">Durante</option>
                                    <option value="antes">Antes</option>
                                    <option value="depois">Depois</option>
                                    <option value="problema">Problema</option>
                                    <option value="solucao">Solução</option>
                                </select>
                            </div>
                        `;
                        previewContainer.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            });
        }

        function removeMaintenancePhoto(index) {
            const photosInput = document.getElementById('maintenancePhotos');
            const previewContainer = document.getElementById('photoPreviewContainer');
            
            // Criar novo FileList sem o arquivo removido
            const dt = new DataTransfer();
            const files = Array.from(photosInput.files);
            
            files.forEach((file, i) => {
                if (i !== index) {
                    dt.items.add(file);
                }
            });
            
            photosInput.files = dt.files;
            
            // Disparar evento change para atualizar preview
            photosInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Função para upload de fotos na edição
        function setupEditMaintenancePhotoUpload() {
            const photosInput = document.getElementById('editMaintenancePhotos');
            if (!photosInput) return;
            
            photosInput.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                const previewContainer = document.getElementById('editPhotoPreviewContainer');
                
                if (files.length === 0) {
                    previewContainer.style.display = 'none';
                    return;
                }
                
                if (files.length > 10) {
                    alert('Máximo de 10 fotos por upload.');
                    e.target.value = '';
                    return;
                }
                
                previewContainer.innerHTML = '';
                previewContainer.style.display = 'block';
                
                files.forEach((file, index) => {
                    if (file.size > 5 * 1024 * 1024) {
                        alert(`Arquivo ${file.name} é muito grande. Máximo 5MB por foto.`);
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'photo-preview-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="${file.name}">
                            <button type="button" class="remove-photo" onclick="removeEditMaintenancePhoto(${index})">×</button>
                            <div class="photo-type-selector">
                                <select name="edit_photo_types[]" class="form-control">
                                    <option value="durante">Durante</option>
                                    <option value="antes">Antes</option>
                                    <option value="depois">Depois</option>
                                    <option value="problema">Problema</option>
                                    <option value="solucao">Solução</option>
                                </select>
                            </div>
                        `;
                        previewContainer.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            });
        }

        function removeEditMaintenancePhoto(index) {
            const photosInput = document.getElementById('editMaintenancePhotos');
            
            // Criar novo FileList sem o arquivo removido
            const dt = new DataTransfer();
            const files = Array.from(photosInput.files);
            
            files.forEach((file, i) => {
                if (i !== index) {
                    dt.items.add(file);
                }
            });
            
            photosInput.files = dt.files;
            
            // Disparar evento change para atualizar preview
            photosInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Upload de fotos via form
        document.getElementById('photoUploadForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = document.getElementById('uploadSubmitBtn');
            const progressDiv = document.getElementById('uploadProgress');
            const progressBar = progressDiv.querySelector('.progress-bar');
            
            // Validações
            const tipoFoto = document.getElementById('tipoFoto').value;
            const fotos = document.getElementById('fotosInput').files;
            
            if (!tipoFoto) {
                alert('Selecione o tipo de foto.');
                return;
            }
            
            if (fotos.length === 0) {
                alert('Selecione pelo menos uma foto.');
                return;
            }
            
            // Mostrar progress e desabilitar botão
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
            progressDiv.style.display = 'block';
            
            // Upload via fetch
            fetch(this.action || 'manutencoes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                console.log('Upload response:', data);
                
                // Se a resposta contém HTML, é uma resposta de sucesso da própria página
                if (data.includes('<!DOCTYPE html')) {
                    showSuccessMessage('Fotos enviadas com sucesso!');
                    bootstrap.Modal.getInstance(document.getElementById('photoUploadModal')).hide();
                    
                    // Recarregar detalhes da manutenção se o modal estiver aberto
                    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewModal'));
                    if (viewModal) {
                        const manutencaoId = document.getElementById('uploadManutencaoId').value;
                        // Simular clique para recarregar
                        fetch(`get_manutencao_details.php?id=${manutencaoId}`)
                            .then(res => res.json())
                            .then(result => {
                                if (result.success) {
                                    renderMaintenanceDetails(result);
                                }
                            });
                    }
                } else {
                    try {
                        const response = JSON.parse(data);
                        if (response.success) {
                            showSuccessMessage('Fotos enviadas com sucesso!');
                            bootstrap.Modal.getInstance(document.getElementById('photoUploadModal')).hide();
                        } else {
                            showErrorMessage(response.message || 'Erro ao enviar fotos.');
                        }
                    } catch (e) {
                        showSuccessMessage('Fotos enviadas com sucesso!');
                        bootstrap.Modal.getInstance(document.getElementById('photoUploadModal')).hide();
                    }
                }
            })
            .catch(error => {
                console.error('Erro no upload:', error);
                showErrorMessage('Erro de conexão ao enviar fotos.');
            })
            .finally(() => {
                // Reset form e estado
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-cloud-upload me-2"></i>Fazer Upload';
                progressDiv.style.display = 'none';
                progressBar.style.width = '0%';
            });
        });

        // Funções para os modais de pesquisa de materiais e serviços
        function openMaterialModal() {
            tempSelectedMaterials = [...selectedMaterials];
            updateTempMaterialsList();
            
            const modal = new bootstrap.Modal(document.getElementById('materialModal'));
            modal.show();
            
            // Focus on search input
            setTimeout(() => {
                document.getElementById('materialSearchInput').focus();
            }, 500);
        }

        function openServiceModal() {
            tempSelectedServices = [...selectedServices];
            updateTempServicesList();
            
            const modal = new bootstrap.Modal(document.getElementById('serviceModal'));
            modal.show();
            
            // Focus on search input
            setTimeout(() => {
                document.getElementById('serviceSearchInput').focus();
            }, 500);
        }

        function setupMaterialSearchInput() {
            const searchInput = document.getElementById('materialSearchInput');
            if (!searchInput) return;
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                filterMaterials(searchTerm);
            });

            searchInput.addEventListener('focus', function() {
                if (!this.value) {
                    showAllMaterials();
                }
            });
        }

        function setupServiceSearchInput() {
            const searchInput = document.getElementById('serviceSearchInput');
            if (!searchInput) return;
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                filterServices(searchTerm);
            });

            searchInput.addEventListener('focus', function() {
                if (!this.value) {
                    showAllServices();
                }
            });
        }

        function showAllMaterials() {
            const resultsContainer = document.getElementById('materialSearchResults');
            if (!availableMaterials || availableMaterials.length === 0) {
                resultsContainer.innerHTML = '<div class="no-results">Nenhum material disponível</div>';
                return;
            }
            
            displayMaterials(availableMaterials);
        }

        function filterMaterials(searchTerm) {
            const resultsContainer = document.getElementById('materialSearchResults');
            
            if (!availableMaterials || availableMaterials.length === 0) {
                resultsContainer.innerHTML = '<div class="no-results">Nenhum material disponível</div>';
                return;
            }
            
            if (searchTerm.length === 0) {
                showAllMaterials();
                return;
            }
            
            const filtered = availableMaterials.filter(material => {
                const codigo = (material.codigo || '').toLowerCase();
                const nome = (material.nome || '').toLowerCase();
                const categoria = (material.categoria || '').toLowerCase();
                
                return codigo.includes(searchTerm) || 
                       nome.includes(searchTerm) || 
                       categoria.includes(searchTerm);
            });
            
            if (filtered.length === 0) {
                resultsContainer.innerHTML = '<div class="no-results">Nenhum material encontrado</div>';
                return;
            }
            
            displayMaterials(filtered);
        }

        function displayMaterials(materials) {
            const resultsContainer = document.getElementById('materialSearchResults');
            
            const items = materials.map(material => {
                const isSelected = tempSelectedMaterials.some(selected => selected.id === material.id);
                const selectedClass = isSelected ? 'selected' : '';
                
                return `
                    <div class="search-item ${selectedClass}" onclick="toggleMaterialSelection(${material.id})">
                        <div class="search-item-info">
                            <div class="search-item-title">${escapeHtml(material.codigo || 'MAT-' + material.id)} - ${escapeHtml(material.nome)}</div>
                            <div class="search-item-details">
                                Categoria: ${escapeHtml(material.categoria || 'N/A')} | 
                                Unidade: ${escapeHtml(material.unidade_medida || 'UN')} | 
                                Estoque: ${material.estoque_atual || 0}
                            </div>
                        </div>
                        <div class="search-item-price">R$ ${formatCurrency(material.preco_unitario || 0)}</div>
                    </div>
                `;
            }).join('');
            
            resultsContainer.innerHTML = items;
        }

        function showAllServices() {
            const resultsContainer = document.getElementById('serviceSearchResults');
            if (!availableServices || availableServices.length === 0) {
                resultsContainer.innerHTML = '<div class="no-results">Nenhum serviço disponível</div>';
                return;
            }
            
            displayServices(availableServices);
        }

        function filterServices(searchTerm) {
            const resultsContainer = document.getElementById('serviceSearchResults');
            
            if (!availableServices || availableServices.length === 0) {
                resultsContainer.innerHTML = '<div class="no-results">Nenhum serviço disponível</div>';
                return;
            }
            
            if (searchTerm.length === 0) {
                showAllServices();
                return;
            }
            
            const filtered = availableServices.filter(service => {
                const codigo = (service.codigo || '').toLowerCase();
                const nome = (service.nome || '').toLowerCase();
                const categoria = (service.categoria || '').toLowerCase();
                
                return codigo.includes(searchTerm) || 
                       nome.includes(searchTerm) || 
                       categoria.includes(searchTerm);
            });
            
            if (filtered.length === 0) {
                resultsContainer.innerHTML = '<div class="no-results">Nenhum serviço encontrado</div>';
                return;
            }
            
            displayServices(filtered);
        }

        function displayServices(services) {
            const resultsContainer = document.getElementById('serviceSearchResults');
            
            const items = services.map(service => {
                const isSelected = tempSelectedServices.some(selected => selected.id === service.id);
                const selectedClass = isSelected ? 'selected' : '';
                
                return `
                    <div class="search-item ${selectedClass}" onclick="toggleServiceSelection(${service.id})">
                        <div class="search-item-info">
                            <div class="search-item-title">${escapeHtml(service.codigo || 'SRV-' + service.id)} - ${escapeHtml(service.nome)}</div>
                            <div class="search-item-details">
                                Categoria: ${escapeHtml(service.categoria || 'N/A')} | 
                                Tipo Equipamento: ${escapeHtml(service.tipo_equipamento || 'Ambos')}
                            </div>
                        </div>
                        <div class="search-item-time">${service.tempo_estimado || 30} min</div>
                    </div>
                `;
            }).join('');
            
            resultsContainer.innerHTML = items;
        }

        function toggleMaterialSelection(materialId) {
            const material = availableMaterials.find(m => m.id === materialId);
            if (!material) return;
            
            const existingIndex = tempSelectedMaterials.findIndex(selected => selected.id === materialId);
            
            if (existingIndex >= 0) {
                tempSelectedMaterials.splice(existingIndex, 1);
            } else {
                tempSelectedMaterials.push({
                    id: material.id,
                    codigo: material.codigo,
                    nome: material.nome,
                    categoria: material.categoria,
                    unidade_medida: material.unidade_medida,
                    preco_unitario: material.preco_unitario,
                    estoque_atual: material.estoque_atual,
                    quantidade: 1,
                    observacoes: ''
                });
            }
            
            updateTempMaterialsList();
            
            // Refresh search results to update selection state
            const searchTerm = document.getElementById('materialSearchInput').value.toLowerCase().trim();
            if (searchTerm) {
                filterMaterials(searchTerm);
            } else {
                showAllMaterials();
            }
        }

        function toggleServiceSelection(serviceId) {
            const service = availableServices.find(s => s.id === serviceId);
            if (!service) return;
            
            const existingIndex = tempSelectedServices.findIndex(selected => selected.id === serviceId);
            
            if (existingIndex >= 0) {
                tempSelectedServices.splice(existingIndex, 1);
            } else {
                tempSelectedServices.push({
                    id: service.id,
                    codigo: service.codigo,
                    nome: service.nome,
                    categoria: service.categoria,
                    tipo_equipamento: service.tipo_equipamento,
                    tempo_estimado: service.tempo_estimado,
                    observacoes: ''
                });
            }
            
            updateTempServicesList();
            
            // Refresh search results to update selection state
            const searchTerm = document.getElementById('serviceSearchInput').value.toLowerCase().trim();
            if (searchTerm) {
                filterServices(searchTerm);
            } else {
                showAllServices();
            }
        }

        function updateTempMaterialsList() {
            const container = document.getElementById('selectedMaterialsTemp');
            const listContainer = document.getElementById('selectedMaterialsContainer');
            
            if (tempSelectedMaterials.length === 0) {
                container.style.display = 'none';
                return;
            }
            
            container.style.display = 'block';
            
            const items = tempSelectedMaterials.map((material, index) => `
                <div class="selected-item">
                    <div class="selected-item-info">
                        <div class="selected-item-title">${escapeHtml(material.codigo || 'MAT-' + material.id)} - ${escapeHtml(material.nome)}</div>
                        <div class="selected-item-details">Estoque: ${material.estoque_atual || 0} ${escapeHtml(material.unidade_medida || 'UN')}</div>
                    </div>
                    <div class="selected-item-actions">
                        <input type="number" step="0.01" min="0.01" value="${material.quantidade}" 
                               class="quantity-input" 
                               onchange="updateMaterialQuantity(${index}, this.value)">
                        <input type="text" value="${escapeHtml(material.observacoes)}" 
                               class="form-control form-control-sm ms-2" 
                               placeholder="Observações"
                               style="width: 150px;"
                               onchange="updateMaterialObservations(${index}, this.value)">
                        <button type="button" class="remove-item ms-2" onclick="removeTempMaterial(${index})">×</button>
                    </div>
                </div>
            `).join('');
            
            listContainer.innerHTML = items;
        }

        function updateTempServicesList() {
            const container = document.getElementById('selectedServicesTemp');
            const listContainer = document.getElementById('selectedServicesContainer');
            
            if (tempSelectedServices.length === 0) {
                container.style.display = 'none';
                return;
            }
            
            container.style.display = 'block';
            
            const items = tempSelectedServices.map((service, index) => `
                <div class="selected-item">
                    <div class="selected-item-info">
                        <div class="selected-item-title">${escapeHtml(service.codigo || 'SRV-' + service.id)} - ${escapeHtml(service.nome)}</div>
                        <div class="selected-item-details">Tempo estimado: ${service.tempo_estimado || 30} min</div>
                    </div>
                    <div class="selected-item-actions">
                        <input type="text" value="${escapeHtml(service.observacoes)}" 
                               class="form-control form-control-sm" 
                               placeholder="Observações"
                               style="width: 200px;"
                               onchange="updateServiceObservations(${index}, this.value)">
                        <button type="button" class="remove-item ms-2" onclick="removeTempService(${index})">×</button>
                    </div>
                </div>
            `).join('');
            
            listContainer.innerHTML = items;
        }

        function updateMaterialQuantity(index, quantity) {
            if (tempSelectedMaterials[index]) {
                tempSelectedMaterials[index].quantidade = parseFloat(quantity) || 1;
            }
        }

        function updateMaterialObservations(index, observacoes) {
            if (tempSelectedMaterials[index]) {
                tempSelectedMaterials[index].observacoes = observacoes;
            }
        }

        function updateServiceObservations(index, observacoes) {
            if (tempSelectedServices[index]) {
                tempSelectedServices[index].observacoes = observacoes;
            }
        }

        function removeTempMaterial(index) {
            tempSelectedMaterials.splice(index, 1);
            updateTempMaterialsList();
            
            // Refresh search results
            const searchTerm = document.getElementById('materialSearchInput').value.toLowerCase().trim();
            if (searchTerm) {
                filterMaterials(searchTerm);
            } else {
                showAllMaterials();
            }
        }

        function removeTempService(index) {
            tempSelectedServices.splice(index, 1);
            updateTempServicesList();
            
            // Refresh search results
            const searchTerm = document.getElementById('serviceSearchInput').value.toLowerCase().trim();
            if (searchTerm) {
                filterServices(searchTerm);
            } else {
                showAllServices();
            }
        }

        function confirmMaterialSelection() {
            selectedMaterials = [...tempSelectedMaterials];
            updateSelectedMaterialsList();
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('materialModal'));
            modal.hide();
        }

        function confirmServiceSelection() {
            selectedServices = [...tempSelectedServices];
            updateSelectedServicesList();
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('serviceModal'));
            modal.hide();
        }

        function updateSelectedMaterialsList() {
            const container = document.getElementById('selectedMaterialsList');
            
            if (selectedMaterials.length === 0) {
                container.className = 'selected-materials-list empty';
                container.innerHTML = 'Nenhum material selecionado';
                return;
            }
            
            container.className = 'selected-materials-list';
            
            const items = selectedMaterials.map((material, index) => `
                <div class="selected-item-card">
                    <div class="selected-item-info">
                        <div class="selected-item-title">${escapeHtml(material.codigo || 'MAT-' + material.id)} - ${escapeHtml(material.nome)}</div>
                        <div class="selected-item-details">
                            Quantidade: ${material.quantidade} ${escapeHtml(material.unidade_medida || 'UN')} | 
                            Preço: R$ ${formatCurrency(material.preco_unitario || 0)}
                            ${material.observacoes ? ' | Obs: ' + escapeHtml(material.observacoes) : ''}
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeSelectedMaterial(${index})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `).join('');
            
            container.innerHTML = items;
            
            // Update hidden input
            document.getElementById('materiaisSelecionadosInput').value = JSON.stringify(selectedMaterials);
        }

        function updateSelectedServicesList() {
            const container = document.getElementById('selectedServicesList');
            
            if (selectedServices.length === 0) {
                container.className = 'selected-services-list empty';
                container.innerHTML = 'Nenhum serviço selecionado';
                return;
            }
            
            container.className = 'selected-services-list';
            
            const items = selectedServices.map((service, index) => `
                <div class="selected-item-card">
                    <div class="selected-item-info">
                        <div class="selected-item-title">${escapeHtml(service.codigo || 'SRV-' + service.id)} - ${escapeHtml(service.nome)}</div>
                        <div class="selected-item-details">
                            Tempo: ${service.tempo_estimado || 30} min | 
                            Categoria: ${escapeHtml(service.categoria || 'N/A')}
                            ${service.observacoes ? ' | Obs: ' + escapeHtml(service.observacoes) : ''}
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeSelectedService(${index})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `).join('');
            
            container.innerHTML = items;
            
            // Update hidden input
            document.getElementById('servicosSelecionadosInput').value = JSON.stringify(selectedServices);
        }

        function removeSelectedMaterial(index) {
            selectedMaterials.splice(index, 1);
            updateSelectedMaterialsList();
        }

        function removeSelectedService(index) {
            selectedServices.splice(index, 1);
            updateSelectedServicesList();
        }

        // Funções para modal de serviços e materiais existente
        function openServicesModal(manutencaoId, equipamentoTipo = 'ambos') {
            document.getElementById('servicesManutencaoId').value = manutencaoId;
            
            // Limpar containers
            document.getElementById('servicesContainer').innerHTML = '';
            document.getElementById('materialsContainer').innerHTML = '';
            
            serviceRowCounter = 0;
            materialRowCounter = 0;
            
            // Carregar serviços e materiais existentes da manutenção
            loadExistingServicesAndMaterials(manutencaoId);
            
            // Adicionar uma linha vazia para novos itens
            addServiceRow();
            addMaterialRow();
            
            new bootstrap.Modal(document.getElementById('servicesModal')).show();
        }

        async function loadExistingServicesAndMaterials(manutencaoId) {
            try {
                // Carregar serviços existentes
                const response = await fetch(`get_manutencao_details.php?id=${manutencaoId}`);
                if (response.ok) {
                    const data = await response.json();
                    
                    // Adicionar serviços existentes
                    if (data.servicos && data.servicos.length > 0) {
                        data.servicos.forEach(servico => {
                            addServiceRow(servico);
                        });
                    }
                    
                    // Adicionar materiais existentes
                    if (data.materiais && data.materiais.length > 0) {
                        data.materiais.forEach(material => {
                            addMaterialRow(material);
                        });
                    }
                    
                    updateCosts();
                }
            } catch (error) {
                console.error('Erro ao carregar serviços e materiais existentes:', error);
            }
        }

        function addServiceRow(data = null) {
            const container = document.getElementById('servicesContainer');
            if (!container) return;
            
            const rowId = 'service_' + serviceRowCounter;
            const currentCounter = serviceRowCounter++;
            
            // Popular opções de serviços
            let serviceOptions = '<option value="">Selecione o serviço</option>';
            if (availableServices && availableServices.length > 0) {
                availableServices.forEach(service => {
                    const selected = data && data.tipo_manutencao_id == service.id ? 'selected' : '';
                    serviceOptions += `<option value="${service.id}" data-tempo="${service.tempo_estimado || 30}" data-categoria="${service.categoria}" ${selected}>
                        ${service.codigo || 'SRV-' + service.id} - ${service.nome}
                    </option>`;
                });
            }
            
            const row = document.createElement('div');
            row.className = 'row mb-2 service-row';
            row.id = rowId;
            row.innerHTML = `
                <div class="col-md-5">
                    <select class="form-select form-select-sm" name="servicos[${currentCounter}][tipo_manutencao_id]" onchange="updateServiceInfo(this)">
                        ${serviceOptions}
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control form-control-sm" name="servicos[${currentCounter}][quantidade]" 
                           placeholder="Qtd" min="0.01" step="0.01" value="${data ? data.quantidade : '1'}" onchange="updateCosts()">
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control form-control-sm" name="servicos[${currentCounter}][tempo_gasto]" 
                           placeholder="Tempo (min)" min="0" value="${data ? data.tempo_gasto : ''}" onchange="updateCosts()">
                </div>
                <div class="col-md-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="servicos[${currentCounter}][executado]" 
                               ${data && data.executado ? 'checked' : ''}>
                        <label class="form-check-label">Executado</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow('${rowId}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="col-12 mt-1">
                    <input type="text" class="form-control form-control-sm" name="servicos[${currentCounter}][observacoes]" 
                           placeholder="Observações do serviço..." value="${data ? (data.observacoes || '') : ''}">
                </div>
            `;
            
            container.appendChild(row);
            updateCosts();
        }

        function addMaterialRow(data = null) {
            const container = document.getElementById('materialsContainer');
            if (!container) return;
            
            const rowId = 'material_' + materialRowCounter;
            const currentCounter = materialRowCounter++;
            
            // Popular opções de materiais
            let materialOptions = '<option value="">Selecione o material</option>';
            if (availableMaterials && availableMaterials.length > 0) {
                availableMaterials.forEach(material => {
                    const selected = data && data.material_id == material.id ? 'selected' : '';
                    materialOptions += `<option value="${material.id}" 
                        data-preco="${material.preco_unitario || 0}" 
                        data-estoque="${material.estoque_atual || 0}" 
                        data-unidade="${material.unidade_medida || 'UN'}" ${selected}>
                        ${material.codigo || 'MAT-' + material.id} - ${material.nome} (${material.unidade_medida || 'UN'})
                    </option>`;
                });
            }
            
            const row = document.createElement('div');
            row.className = 'row mb-2 material-row';
            row.id = rowId;
            row.innerHTML = `
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="materiais[${currentCounter}][material_id]" onchange="updateMaterialInfo(this)">
                        ${materialOptions}
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control form-control-sm" name="materiais[${currentCounter}][quantidade_prevista]" 
                           placeholder="Prev." min="0.01" step="0.01" value="${data ? (data.quantidade_prevista || '') : ''}">
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control form-control-sm" name="materiais[${currentCounter}][quantidade_utilizada]" 
                           placeholder="Usado" min="0.01" step="0.01" value="${data ? (data.quantidade_utilizada || '') : ''}" onchange="updateCosts()">
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm" readonly placeholder="Estoque" id="estoque_${currentCounter}">
                </div>
                <div class="col-md-1">
                    <input type="text" class="form-control form-control-sm" readonly placeholder="R$" id="preco_${currentCounter}">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow('${rowId}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="col-12 mt-1">
                    <input type="text" class="form-control form-control-sm" name="materiais[${currentCounter}][observacoes]" 
                           placeholder="Observações do material..." value="${data ? (data.observacoes || '') : ''}">
                </div>
            `;
            
            container.appendChild(row);
            
            // Se há dados, atualizar campos calculados
            if (data && data.material_id) {
                const select = row.querySelector('select');
                updateMaterialInfo(select);
            }
            
            updateCosts();
        }

        function updateServiceInfo(selectElement) {
            updateCosts();
        }

        function updateMaterialInfo(selectElement) {
            const selectedOption = selectElement.selectedOptions[0];
            const rowId = selectElement.closest('.material-row').id;
            const materialIndex = rowId.split('_')[1];
            
            const estoqueField = document.getElementById('estoque_' + materialIndex);
            const precoField = document.getElementById('preco_' + materialIndex);
            
            if (selectedOption && selectedOption.value && estoqueField && precoField) {
                estoqueField.value = selectedOption.dataset.estoque || '0';
                precoField.value = (parseFloat(selectedOption.dataset.preco) || 0).toFixed(2);
            } else if (estoqueField && precoField) {
                estoqueField.value = '';
                precoField.value = '';
            }
            
            updateCosts();
        }

        function updateCosts() {
            let custoMateriais = 0;
            let tempoTotal = 0;
            
            document.querySelectorAll('.material-row').forEach(row => {
                const select = row.querySelector('select');
                const qtdInput = row.querySelector('input[name*="quantidade_utilizada"]');
                
                if (select && qtdInput && select.value && qtdInput.value) {
                    const preco = parseFloat(select.selectedOptions[0]?.dataset.preco || 0);
                    const quantidade = parseFloat(qtdInput.value || 0);
                    custoMateriais += preco * quantidade;
                }
            });
            
            document.querySelectorAll('.service-row').forEach(row => {
                const tempoInput = row.querySelector('input[name*="tempo_gasto"]');
                const qtdInput = row.querySelector('input[name*="quantidade"]');
                
                if (tempoInput && qtdInput && tempoInput.value && qtdInput.value) {
                    const tempo = parseFloat(tempoInput.value || 0);
                    const quantidade = parseFloat(qtdInput.value || 1);
                    tempoTotal += tempo * quantidade;
                }
            });
            
            const custoMateriaisEl = document.getElementById('custoMateriais');
            const tempoTotalEl = document.getElementById('tempoTotal');
            const custoTotalEl = document.getElementById('custoTotal');
            
            if (custoMateriaisEl) custoMateriaisEl.textContent = formatCurrency(custoMateriais);
            if (tempoTotalEl) tempoTotalEl.textContent = tempoTotal.toFixed(0);
            if (custoTotalEl) custoTotalEl.textContent = formatCurrency(custoMateriais);
        }

        function removeRow(rowId) {
            const row = document.getElementById(rowId);
            if (row) {
                row.remove();
                updateCosts();
            }
        }

        // Funções de exclusão
        <?php if (hasPermission('manutencoes', 'delete')): ?>
        function deleteMaintenance(id, equipamento) {
            if (confirm(`Tem certeza que deseja excluir a manutenção do equipamento "${equipamento}"?\n\nEsta ação não pode ser desfeita.`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        <?php else: ?>
        function deleteMaintenance(id, equipamento) {
            showErrorMessage('Você não tem permissão para excluir manutenções.');
        }
        <?php endif; ?>

        // Validação de formulário
        <?php if (hasPermission('manutencoes', 'create') || hasPermission('manutencoes', 'edit')): ?>
        document.getElementById('maintenanceForm')?.addEventListener('submit', function(e) {
            const equipamento = document.getElementById('modalEquipamento').value;
            const tipo_manutencao = document.getElementById('modalTipoManutencao').value;
            const descricao = document.getElementById('modalDescricao').value.trim();
            
            document.querySelectorAll('.is-invalid').forEach(field => {
                field.classList.remove('is-invalid');
            });
            document.querySelectorAll('.invalid-feedback').forEach(feedback => {
                feedback.remove();
            });
            
            let isValid = true;
            
            if (!equipamento) {
                showFieldError('modalEquipamento', 'Equipamento é obrigatório');
                isValid = false;
            }
            
            if (!tipo_manutencao) {
                showFieldError('modalTipoManutencao', 'Tipo de manutenção é obrigatório');
                isValid = false;
            }
            
            if (!descricao) {
                showFieldError('modalDescricao', 'Descrição é obrigatória');
                isValid = false;
            } else if (descricao.length < 10) {
                showFieldError('modalDescricao', 'Descrição deve ter pelo menos 10 caracteres');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            } else {
                const submitBtn = document.getElementById('modalSubmit');
                if (submitBtn) {
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Salvando...';
                }
            }
        });

        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.classList.add('is-invalid');
                
                let feedback = field.parentNode.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    field.parentNode.appendChild(feedback);
                }
                feedback.textContent = message;
            }
        }
        <?php endif; ?>

        // Função para gerar relatório fotográfico
        <?php if (hasPermission('relatorios', 'view')): ?>
        function generatePhotoReport() {
            const urlParams = new URLSearchParams(window.location.search);
            const equipamento = urlParams.get('equipamento') || '';
            const status = urlParams.get('status') || '';
            const tipo = urlParams.get('tipo') || '';
            const prioridade = urlParams.get('prioridade') || '';
            const data_inicio = urlParams.get('data_inicio') || '';
            const data_fim = urlParams.get('data_fim') || '';
            const atribuicao = urlParams.get('atribuicao') || '';
            
            const reportBtn = document.querySelector('.btn-report');
            if (reportBtn) {
                const originalText = reportBtn.innerHTML;
                reportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Gerando...';
                reportBtn.disabled = true;
                
                let reportUrl = 'manutencoes.php?generate_photo_report=true';
                if (equipamento) reportUrl += '&equipamento=' + encodeURIComponent(equipamento);
                if (status) reportUrl += '&status=' + encodeURIComponent(status);
                if (tipo) reportUrl += '&tipo=' + encodeURIComponent(tipo);
                if (prioridade) reportUrl += '&prioridade=' + encodeURIComponent(prioridade);
                if (data_inicio) reportUrl += '&data_inicio=' + encodeURIComponent(data_inicio);
                if (data_fim) reportUrl += '&data_fim=' + encodeURIComponent(data_fim);
                if (atribuicao) reportUrl += '&atribuicao=' + encodeURIComponent(atribuicao);
                
                const reportWindow = window.open(reportUrl, '_blank');
                
                setTimeout(() => {
                    reportBtn.innerHTML = originalText;
                    reportBtn.disabled = false;
                }, 2000);
                
                if (!reportWindow || reportWindow.closed || typeof reportWindow.closed == 'undefined') {
                    setTimeout(() => {
                        window.open(reportUrl, '_blank') || (window.location.href = reportUrl);
                    }, 1000);
                }
            }
        }
        <?php endif; ?>

        // Função auxiliar para verificar permissões
        function hasPermission(module, action) {
            return userPermissions[module] && userPermissions[module].includes(action);
        }

        // Funções utilitárias
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR');
        }

        function formatCurrency(value) {
            return parseFloat(value || 0).toFixed(2).replace('.', ',');
        }

        function showSuccessMessage(message) {
            showMessage(message, 'success');
        }

        function showErrorMessage(message) {
            showMessage(message, 'danger');
        }

        function showWarningMessage(message) {
            showMessage(message, 'warning');
        }

        function showMessage(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'exclamation-triangle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.content-area');
            if (container) {
                container.insertBefore(alertDiv, container.firstChild);
                
                setTimeout(() => {
                    if (alertDiv && alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
        }

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            });
        }, 5000);

        // Animation on scroll
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

        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Prevent pull-to-refresh on mobile
        document.body.addEventListener('touchstart', e => {
            if (e.touches.length !== 1) { return; }
            const el = e.target;
            if (el.closest('.modal') || el.closest('.dropdown-menu')) {
                return;
            }
        });

        document.body.addEventListener('touchmove', e => {
            if (e.touches.length !== 1) { return; }
            if (document.body.scrollTop !== 0) { return; }
            e.preventDefault();
        }, { passive: false });

        // Função para abrir upload de fotos a partir do modal de edição
        function openPhotoUploadFromEdit() {
            if (!currentMaintenanceId) {
                alert('Salve a manutenção primeiro para adicionar fotos.');
                return;
            }
            openPhotoUploadModal(currentMaintenanceId);
        }

        // Função para carregar fotos existentes da manutenção
        async function loadMaintenancePhotos(manutencaoId) {
            if (!manutencaoId) return;
            
            const previewContainer = document.getElementById('existingPhotosPreview');
            if (!previewContainer) return;
            
            previewContainer.innerHTML = '<div class="text-center p-2"><span class="spinner-border spinner-border-sm"></span> Carregando fotos...</div>';
            
            try {
                const response = await fetch(`get_manutencao_details.php?id=${manutencaoId}`);
                const result = await response.json();
                
                if (result.success && result.fotos && result.fotos.length > 0) {
                    const fotosHtml = `
                        <div class="row">
                            <div class="col-12">
                                <small class="text-muted d-block mb-2">Fotos existentes (${result.fotos.length}):</small>
                                <div class="photo-gallery-small">
                                    ${result.fotos.map(foto => `
                                        <div class="photo-item-small" onclick="openPhotoModal('${foto.caminho_arquivo}', '${foto.descricao || ''}')">
                                            <img src="${foto.caminho_arquivo}" alt="${foto.descricao || ''}" title="${foto.tipo_foto}: ${foto.descricao || 'Sem descrição'}">
                                            <div class="photo-type-badge">${foto.tipo_foto}</div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    `;
                    previewContainer.innerHTML = fotosHtml;
                } else {
                    previewContainer.innerHTML = '<small class="text-muted">Nenhuma foto anexada ainda.</small>';
                }
            } catch (error) {
                console.error('Erro ao carregar fotos:', error);
                previewContainer.innerHTML = '<small class="text-danger">Erro ao carregar fotos.</small>';
            }
        }
    </script>

    <!-- Botão Flutuante (FAB) para Mobile - Nova Manutenção -->
    <?php if (hasPermission('manutencoes', 'create')): ?>
    <button class="fab-button d-md-none"
            onclick="openModal('create')"
            data-tooltip="Nova Manutenção"
            aria-label="Criar nova manutenção">
        <i class="bi bi-plus"></i>
    </button>
    <?php endif; ?>

</body>
</html>