<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'user_permissions.php';

$message = '';
$error = '';
$success = '';

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar permissões de acesso à gestão de usuários
if (!UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'view')) {
    logMessage("Tentativa de acesso não autorizado à gestão de usuários por: {$_SESSION['user_name']} (ID: {$_SESSION['user_id']}, Tipo: {$_SESSION['user_type']})", 'WARNING');
    header('Location: index.php?error=access_denied');
    exit;
}

// Inicializar variáveis
$users = [];
$total_users = 0;
$filters = [
    'search' => $_GET['search'] ?? '',
    'tipo' => $_GET['tipo'] ?? '',
    'status' => $_GET['status'] ?? '',
    'order' => $_GET['order'] ?? 'nome',
    'direction' => $_GET['direction'] ?? 'ASC'
];

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = getUserPaginationLimit($_SESSION['user_type']);
$offset = ($page - 1) * $per_page;

// ============ PROCESSAMENTO DE AÇÕES ============

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    // Verificar se o usuário alvo existe
    $target_user = null;
    if ($user_id > 0) {
        try {
            $target_user = Database::fetch("SELECT * FROM usuarios WHERE id = ? AND deleted_at IS NULL", [$user_id]);
            if (!$target_user) {
                $error = 'Usuário não encontrado.';
            }
        } catch (Exception $e) {
            $error = 'Erro ao buscar usuário: ' . $e->getMessage();
        }
    }
    
    if (!$error && $target_user) {
        switch ($action) {
            case 'edit_user':
                if (!UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'edit')) {
                    $error = 'Você não tem permissão para editar usuários.';
                } elseif (!UserPermissions::canManageUser($_SESSION['user_type'], $target_user['tipo'], 'edit')) {
                    $error = 'Você não pode editar este tipo de usuário.';
                } else {
                    $nome = sanitize($_POST['nome'] ?? '');
                    $email = sanitize($_POST['email'] ?? '');
                    $telefone = sanitize($_POST['telefone'] ?? '');
                    $cpf = sanitize($_POST['cpf'] ?? '');
                    $endereco = sanitize($_POST['endereco'] ?? '');
                    $observacoes = sanitize($_POST['observacoes'] ?? '');
                    
                    // Validações básicas
                    if (empty($nome) || empty($email)) {
                        $error = 'Nome e email são obrigatórios.';
                    } elseif (!validateEmail($email)) {
                        $error = 'Email inválido.';
                    } elseif (strlen($nome) < 2 || strlen($nome) > 100) {
                        $error = 'Nome deve ter entre 2 e 100 caracteres.';
                    } else {
                        // Verificar duplicatas
                        $existing = Database::fetch(
                            "SELECT id FROM usuarios WHERE (email = ? OR nome = ?) AND id != ? AND deleted_at IS NULL",
                            [$email, $nome, $user_id]
                        );
                        
                        if ($existing) {
                            $error = 'Email ou nome já estão em uso por outro usuário.';
                        } else {
                            // Validar CPF se fornecido
                            if (!empty($cpf)) {
                                $cpf_clean = preg_replace('/[^0-9]/', '', $cpf);
                                if (strlen($cpf_clean) !== 11 || !validateCPF($cpf_clean)) {
                                    $error = 'CPF inválido.';
                                } else {
                                    $existing_cpf = Database::fetch(
                                        "SELECT id FROM usuarios WHERE cpf = ? AND id != ? AND deleted_at IS NULL",
                                        [$cpf_clean, $user_id]
                                    );
                                    if ($existing_cpf) {
                                        $error = 'CPF já está em uso.';
                                    }
                                }
                            }
                            
                            if (!$error) {
                                try {
                                    $cpf_final = !empty($cpf) ? preg_replace('/[^0-9]/', '', $cpf) : null;
                                    $telefone_final = !empty($telefone) ? preg_replace('/[^0-9]/', '', $telefone) : null;
                                    
                                    Database::execute(
                                        "UPDATE usuarios SET nome = ?, email = ?, telefone = ?, cpf = ?, endereco = ?, observacoes = ?, updated_at = NOW() WHERE id = ?",
                                        [$nome, $email, $telefone_final, $cpf_final, $endereco, $observacoes, $user_id]
                                    );
                                    
                                    $success = "Usuário <strong>{$nome}</strong> atualizado com sucesso!";
                                    logMessage("Usuário editado por {$_SESSION['user_name']}: {$nome} (ID: {$user_id})", 'INFO');
                                    
                                } catch (Exception $e) {
                                    $error = 'Erro ao atualizar usuário: ' . $e->getMessage();
                                    logMessage('Erro ao editar usuário: ' . $e->getMessage(), 'ERROR');
                                }
                            }
                        }
                    }
                }
                break;
                
            case 'reset_password':
                if (!UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'reset_password')) {
                    $error = 'Você não tem permissão para redefinir senhas.';
                } elseif (!UserPermissions::canManageUser($_SESSION['user_type'], $target_user['tipo'], 'reset_password')) {
                    $error = 'Você não pode redefinir a senha deste usuário.';
                } elseif ($user_id == $_SESSION['user_id']) {
                    $error = 'Você não pode redefinir sua própria senha por aqui. Use as configurações.';
                } else {
                    $new_password = $_POST['new_password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';
                    
                    if (empty($new_password) || empty($confirm_password)) {
                        $error = 'Nova senha e confirmação são obrigatórias.';
                    } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
                        $error = 'Nova senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
                    } elseif ($new_password !== $confirm_password) {
                        $error = 'Nova senha e confirmação não coincidem.';
                    } else {
                        try {
                            $hashed_password = hashPassword($new_password);
                            
                            // Verificar se as colunas existem antes de usá-las
                            $columns_to_check = ['password_changed_at', 'force_password_change'];
                            $existing_columns = [];
                            
                            foreach ($columns_to_check as $column) {
                                try {
                                    $check_column = Database::fetch("SHOW COLUMNS FROM usuarios LIKE ?", [$column]);
                                    if ($check_column) {
                                        $existing_columns[] = $column;
                                    }
                                } catch (Exception $e) {
                                    // Coluna não existe, continua sem ela
                                }
                            }
                            
                            $update_query = "UPDATE usuarios SET senha = ?, updated_at = NOW()";
                            $params = [$hashed_password];
                            
                            if (in_array('password_changed_at', $existing_columns)) {
                                $update_query .= ", password_changed_at = NOW()";
                            }
                            
                            if (in_array('force_password_change', $existing_columns)) {
                                $update_query .= ", force_password_change = 1";
                            }
                            
                            $update_query .= " WHERE id = ?";
                            $params[] = $user_id;
                            
                            Database::execute($update_query, $params);
                            
                            $success = "Senha redefinida para <strong>{$target_user['nome']}</strong>. O usuário será obrigado a alterar na próxima entrada.";
                            logMessage("Senha redefinida pelo admin {$_SESSION['user_name']} para usuário: {$target_user['nome']} (ID: {$user_id})", 'INFO');
                            
                        } catch (Exception $e) {
                            $error = 'Erro ao redefinir senha: ' . $e->getMessage();
                            logMessage('Erro ao redefinir senha: ' . $e->getMessage(), 'ERROR');
                        }
                    }
                }
                break;
                
            case 'change_type':
                if (!UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'change_type')) {
                    $error = 'Você não tem permissão para alterar tipos de usuário.';
                } elseif ($user_id == $_SESSION['user_id']) {
                    $error = 'Você não pode alterar seu próprio tipo de usuário.';
                } else {
                    $new_type = $_POST['new_type'] ?? '';
                    $valid_types = UserPermissions::getAvailableUserTypes();
                    
                    if (!in_array($new_type, $valid_types)) {
                        $error = 'Tipo de usuário inválido.';
                    } elseif ($new_type === $target_user['tipo']) {
                        $error = 'O usuário já possui este tipo.';
                    } else {
                        try {
                            Database::execute(
                                "UPDATE usuarios SET tipo = ?, updated_at = NOW() WHERE id = ?",
                                [$new_type, $user_id]
                            );
                            
                            $old_type = ucfirst($target_user['tipo']);
                            $new_type_display = ucfirst($new_type);
                            
                            $success = "Tipo do usuário <strong>{$target_user['nome']}</strong> alterado de {$old_type} para {$new_type_display}!";
                            logMessage("Tipo de usuário alterado por {$_SESSION['user_name']}: {$target_user['nome']} (ID: {$user_id}) de {$old_type} para {$new_type_display}", 'INFO');
                            
                            // Atualizar também na tabela de técnicos se necessário
                            if ($new_type === 'tecnico' && $target_user['tipo'] !== 'tecnico') {
                                try {
                                    Database::execute(
                                        "INSERT INTO tecnicos (nome, email, telefone, usuario_id, ativo) VALUES (?, ?, ?, ?, 1) 
                                         ON DUPLICATE KEY UPDATE nome = VALUES(nome), email = VALUES(email), telefone = VALUES(telefone), ativo = 1",
                                        [$target_user['nome'], $target_user['email'], $target_user['telefone'], $user_id]
                                    );
                                } catch (Exception $e) {
                                    // Tabela tecnicos pode não existir
                                    logMessage('Erro ao inserir na tabela tecnicos: ' . $e->getMessage(), 'WARNING');
                                }
                            }
                            
                        } catch (Exception $e) {
                            $error = 'Erro ao alterar tipo de usuário: ' . $e->getMessage();
                            logMessage('Erro ao alterar tipo de usuário: ' . $e->getMessage(), 'ERROR');
                        }
                    }
                }
                break;
                
            case 'toggle_status':
                if (!UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'activate') || 
                    !UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'deactivate')) {
                    $error = 'Você não tem permissão para ativar/desativar usuários.';
                } elseif ($user_id == $_SESSION['user_id']) {
                    $error = 'Você não pode desativar sua própria conta.';
                } else {
                    $new_status = $target_user['ativo'] ? 0 : 1;
                    $status_text = $new_status ? 'ativado' : 'desativado';
                    
                    try {
                        Database::execute(
                            "UPDATE usuarios SET ativo = ?, updated_at = NOW() WHERE id = ?",
                            [$new_status, $user_id]
                        );
                        
                        $success = "Usuário <strong>{$target_user['nome']}</strong> {$status_text} com sucesso!";
                        logMessage("Usuário {$status_text} por {$_SESSION['user_name']}: {$target_user['nome']} (ID: {$user_id})", 'INFO');
                        
                    } catch (Exception $e) {
                        $error = 'Erro ao alterar status do usuário: ' . $e->getMessage();
                        logMessage('Erro ao alterar status do usuário: ' . $e->getMessage(), 'ERROR');
                    }
                }
                break;
                
            case 'delete_user':
                if (!UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'delete')) {
                    $error = 'Você não tem permissão para excluir usuários.';
                } elseif ($user_id == $_SESSION['user_id']) {
                    $error = 'Você não pode excluir sua própria conta.';
                } elseif (!UserPermissions::canManageUser($_SESSION['user_type'], $target_user['tipo'], 'delete')) {
                    $error = 'Você não pode excluir este tipo de usuário.';
                } else {
                    $confirm = $_POST['confirm_delete'] ?? '';
                    if ($confirm !== 'CONFIRMAR') {
                        $error = 'Confirmação necessária. Digite "CONFIRMAR" para excluir o usuário.';
                    } else {
                        try {
                            // Verificar se usuário tem dependências
                            $dependencies = [];
                            
                            try {
                                $manutencoes = Database::fetch("SELECT COUNT(*) as total FROM manutencoes WHERE tecnico_id = ?", [$user_id]);
                                if ($manutencoes && $manutencoes['total'] > 0) {
                                    $dependencies[] = "manutenções ({$manutencoes['total']})";
                                }
                            } catch (Exception $e) { 
                                // Tabela pode não existir 
                            }
                            
                            try {
                                $usuarios_criados = Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE created_by = ? AND deleted_at IS NULL", [$user_id]);
                                if ($usuarios_criados && $usuarios_criados['total'] > 0) {
                                    $dependencies[] = "usuários criados ({$usuarios_criados['total']})";
                                }
                            } catch (Exception $e) { 
                                // Coluna pode não existir 
                            }
                            
                            if (!empty($dependencies)) {
                                $error = 'Não é possível excluir este usuário pois possui dependências: ' . implode(', ', $dependencies);
                            } else {
                                // Fazer soft delete
                                Database::execute(
                                    "UPDATE usuarios SET deleted_at = NOW(), ativo = 0, updated_at = NOW(), email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP()) WHERE id = ?",
                                    [$user_id]
                                );
                                
                                $success = "Usuário <strong>{$target_user['nome']}</strong> excluído com sucesso!";
                                logMessage("Usuário excluído por {$_SESSION['user_name']}: {$target_user['nome']} (ID: {$user_id})", 'WARNING');
                            }
                            
                        } catch (Exception $e) {
                            $error = 'Erro ao excluir usuário: ' . $e->getMessage();
                            logMessage('Erro ao excluir usuário: ' . $e->getMessage(), 'ERROR');
                        }
                    }
                }
                break;
                
            default:
                $error = 'Ação inválida.';
                break;
        }
    }
}

// ============ BUSCAR USUÁRIOS ============

try {
    // Construir query base
    $where_conditions = ['1=1'];
    $params = [];
    
    // Aplicar filtros
    if (!empty($filters['search'])) {
        $where_conditions[] = "(u.nome LIKE ? OR u.email LIKE ? OR u.cpf LIKE ?)";
        $search_param = "%{$filters['search']}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($filters['tipo'])) {
        $where_conditions[] = "u.tipo = ?";
        $params[] = $filters['tipo'];
    }
    
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'ativo') {
            $where_conditions[] = "u.ativo = 1";
        } elseif ($filters['status'] === 'inativo') {
            $where_conditions[] = "u.ativo = 0";
        }
    }
    
    // Filtros específicos por permissão
    if ($_SESSION['user_type'] === 'tecnico') {
        // Técnico só vê usuários que criou ou usuários comuns
        $where_conditions[] = "(u.created_by = ? OR u.tipo = 'usuario')";
        $params[] = $_SESSION['user_id'];
    }
    
    // Não mostrar usuários excluídos
    $where_conditions[] = "u.deleted_at IS NULL";
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Validar ordem
    $valid_orders = ['nome', 'email', 'tipo', 'created_at', 'last_login'];
    if (!in_array($filters['order'], $valid_orders)) {
        $filters['order'] = 'nome';
    }
    
    $valid_directions = ['ASC', 'DESC'];
    if (!in_array($filters['direction'], $valid_directions)) {
        $filters['direction'] = 'ASC';
    }
    
    // Buscar total de usuários
    $total_users = Database::fetch("SELECT COUNT(*) as total FROM usuarios u WHERE {$where_clause}", $params)['total'];
    
    // Buscar usuários com paginação
    $users = Database::fetchAll("
        SELECT 
            u.*,
            CASE 
                WHEN u.last_login IS NOT NULL THEN u.last_login
                ELSE u.created_at
            END as last_activity,
            uc.nome as created_by_name
        FROM usuarios u
        LEFT JOIN usuarios uc ON u.created_by = uc.id AND uc.deleted_at IS NULL
        WHERE {$where_clause}
        ORDER BY u.{$filters['order']} {$filters['direction']}
        LIMIT ? OFFSET ?
    ", array_merge($params, [$per_page, $offset]));
    
} catch (Exception $e) {
    $error = 'Erro ao buscar usuários: ' . $e->getMessage();
    logMessage('Erro ao buscar usuários: ' . $e->getMessage(), 'ERROR');
    $users = [];
    $total_users = 0;
}

// Calcular paginação
$total_pages = ceil($total_users / $per_page);

// Buscar estatísticas
try {
    $stats = [];
    
    if ($_SESSION['user_type'] === 'admin') {
        $stats = [
            'total_usuarios' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE deleted_at IS NULL")['total'],
            'usuarios_ativos' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1 AND deleted_at IS NULL")['total'],
            'usuarios_inativos' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 0 AND deleted_at IS NULL")['total'],
            'total_admins' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'admin' AND deleted_at IS NULL")['total'],
            'total_tecnicos' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'tecnico' AND deleted_at IS NULL")['total'],
            'total_usuarios_comuns' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'usuario' AND deleted_at IS NULL")['total'],
            'novos_este_mes' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND deleted_at IS NULL")['total'],
            'logins_hoje' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE DATE(last_login) = CURDATE() AND deleted_at IS NULL")['total']
        ];
    } elseif ($_SESSION['user_type'] === 'tecnico') {
        $stats = [
            'usuarios_criados_por_mim' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE created_by = ? AND deleted_at IS NULL", [$_SESSION['user_id']])['total'],
            'total_usuarios_comuns' => Database::fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'usuario' AND deleted_at IS NULL")['total']
        ];
    }
} catch (Exception $e) {
    $stats = [];
}

// Obter informações de boas-vindas
$welcome_info = UserPermissions::getWelcomeMessage($_SESSION['user_type'], $_SESSION['user_name']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - HidroApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Dinâmico do Tema do Usuário -->
    <?php if (function_exists('includeUserThemeCSS')) includeUserThemeCSS(); ?>
    
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
        
        .management-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            overflow: hidden;
            position: relative;
        }
        
        .management-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .management-card .card-header {
            background: linear-gradient(135deg, var(--bg-light), var(--bg-white));
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
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

        /* Filtros de pesquisa */
        .filters-section {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }

        /* Tabelas modernizadas */
        .table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background-color: rgba(0, 102, 204, 0.05);
            transform: translateY(-1px);
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        /* User badges */
        .user-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-badge.admin {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
        }

        .user-badge.tecnico {
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            color: white;
        }

        .user-badge.usuario {
            background: linear-gradient(135deg, #55a3ff, #2d3436);
            color: white;
        }

        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-ativo {
            background: #d4edda;
            color: #155724;
        }

        .status-inativo {
            background: #f8d7da;
            color: #721c24;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.5rem;
            border-radius: 8px;
            border: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-edit {
            background: #e3f2fd;
            color: #1976d2;
        }

        .btn-edit:hover {
            background: #1976d2;
            color: white;
        }

        .btn-reset {
            background: #fff3e0;
            color: #f57c00;
        }

        .btn-reset:hover {
            background: #f57c00;
            color: white;
        }

        .btn-toggle {
            background: #e8f5e8;
            color: #4caf50;
        }

        .btn-toggle:hover {
            background: #4caf50;
            color: white;
        }

        .btn-danger-action {
            background: #ffebee;
            color: #d32f2f;
        }

        .btn-danger-action:hover {
            background: #d32f2f;
            color: white;
        }

        /* Pagination */
        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }

        .page-link {
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin: 0 0.25rem;
            transition: var(--transition);
        }

        .page-link:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* Modal styles */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-heavy);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.5rem;
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

        /* Welcome section */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }

        /* Responsividade completa */
        @media (max-width: 1400px) {
            :root {
                --sidebar-width: 250px;
            }
        }

        @media (max-width: 1200px) {
            .content-area {
                padding: 1.5rem;
            }
            .stat-card {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 992px) {
            .content-area {
                padding: 1rem;
            }
            .top-header {
                padding: 0 1rem;
            }
            .management-card {
                margin-bottom: 1rem;
            }

            .action-buttons {
                justify-content: center;
            }

            .table-responsive {
                font-size: 0.9rem;
            }
        }
        
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

            .management-card {
                margin-bottom: 1rem;
            }

            .top-header {
                padding: 0 1rem;
                height: 60px;
            }

            .welcome-section {
                padding: 1.5rem;
                text-align: center;
            }

            .filters-section {
                padding: 1rem;
            }

            .table thead th {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
            }

            .table tbody td {
                padding: 0.75rem 0.5rem;
                font-size: 0.85rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
        
        @media (max-width: 576px) {
            .content-area {
                padding: 0.75rem;
            }

            .management-card .card-header {
                padding: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .welcome-section {
                padding: 1rem;
            }

            .filters-section {
                padding: 1rem;
            }

            .stat-icon {
                width: 60px;
                height: 60px;
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

        /* Estados de foco para acessibilidade */
        .nav-link:focus,
        .btn:focus,
        .form-control:focus,
        .form-select:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
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

        /* Loading states */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Hover effects */
        .hover-lift {
            transition: var(--transition);
        }

        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
    </style>
</head>
<body>
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
                <?= UserPermissions::generateSidebar($_SESSION['user_type'], 'usuarios.php') ?>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center">
                <button class="btn d-md-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h4 class="mb-0">Gestão de Usuários</h4>
                <div class="ms-3">
                    <span class="<?= $welcome_info['badge_class'] ?>">
                        <?= $welcome_info['user_type_display'] ?>
                    </span>
                </div>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle position-relative" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($_SESSION['user_name']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="configuracoes.php"><i class="bi bi-person me-2"></i>Perfil</a></li>
                    <li><a class="dropdown-item" href="configuracoes.php"><i class="bi bi-gear me-2"></i>Configurações</a></li>
                    <?php if (UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'create')): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="register.php"><i class="bi bi-person-plus me-2"></i>Cadastrar Usuário</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                </ul>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Welcome Section -->
            <div class="welcome-section fade-in">
                <div class="position-relative">
                    <h2 class="mb-2">
                        <i class="bi bi-people-fill me-3"></i>
                        <?php if ($_SESSION['user_type'] === 'admin'): ?>
                            Gestão Completa de Usuários
                        <?php else: ?>
                            Gestão de Usuários (Limitada)
                        <?php endif; ?>
                    </h2>
                    <p class="mb-0 opacity-90">
                        <?php if ($_SESSION['user_type'] === 'admin'): ?>
                            Gerencie todos os usuários do sistema: criar, editar, desativar e controlar permissões.
                        <?php else: ?>
                            Visualize e gerencie os usuários que você tem permissão para administrar.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Alertas -->
            <?php if ($message): ?>
                <div class="alert alert-info alert-dismissible fade show fade-in" role="alert">
                    <i class="bi bi-info-circle me-2"></i><?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show fade-in" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <?php if (!empty($stats)): ?>
            <div class="row mb-4">
                <?php if ($_SESSION['user_type'] === 'admin'): ?>
                    <!-- Stats completas para Admin -->
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total_usuarios'] ?></h3>
                                    <p class="text-muted mb-0">Total de Usuários</p>
                                    <small class="text-success"><?= $stats['usuarios_ativos'] ?> ativos</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total_admins'] ?></h3>
                                    <p class="text-muted mb-0">Administradores</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total_tecnicos'] ?></h3>
                                    <p class="text-muted mb-0">Técnicos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #55a3ff 0%, #2d3436 100%);">
                                    <i class="bi bi-person"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total_usuarios_comuns'] ?></h3>
                                    <p class="text-muted mb-0">Usuários Comuns</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%);">
                                    <i class="bi bi-person-plus"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['novos_este_mes'] ?></h3>
                                    <p class="text-muted mb-0">Novos Este Mês</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['logins_hoje'] ?></h3>
                                    <p class="text-muted mb-0">Logins Hoje</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #ff4757 0%, #c44569 100%);">
                                    <i class="bi bi-person-x"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['usuarios_inativos'] ?></h3>
                                    <p class="text-muted mb-0">Usuários Inativos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($_SESSION['user_type'] === 'tecnico'): ?>
                    <!-- Stats limitadas para Técnico -->
                    <div class="col-lg-6 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);">
                                    <i class="bi bi-person-plus"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['usuarios_criados_por_mim'] ?></h3>
                                    <p class="text-muted mb-0">Usuários que Criei</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 col-md-6 mb-3">
                        <div class="stat-card hover-lift fade-in">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-3" style="background: linear-gradient(135deg, #55a3ff 0%, #2d3436 100%);">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= $stats['total_usuarios_comuns'] ?></h3>
                                    <p class="text-muted mb-0">Total de Usuários</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Filtros e Ações -->
            <div class="filters-section fade-in">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Buscar por nome, email ou CPF" 
                                   value="<?= htmlspecialchars($filters['search']) ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="tipo" class="form-select">
                            <option value="">Todos os tipos</option>
                            <option value="admin" <?= $filters['tipo'] === 'admin' ? 'selected' : '' ?>>Administradores</option>
                            <option value="tecnico" <?= $filters['tipo'] === 'tecnico' ? 'selected' : '' ?>>Técnicos</option>
                            <option value="usuario" <?= $filters['tipo'] === 'usuario' ? 'selected' : '' ?>>Usuários</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">Todos os status</option>
                            <option value="ativo" <?= $filters['status'] === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                            <option value="inativo" <?= $filters['status'] === 'inativo' ? 'selected' : '' ?>>Inativos</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="order" class="form-select">
                            <option value="nome" <?= $filters['order'] === 'nome' ? 'selected' : '' ?>>Nome</option>
                            <option value="email" <?= $filters['order'] === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="tipo" <?= $filters['order'] === 'tipo' ? 'selected' : '' ?>>Tipo</option>
                            <option value="created_at" <?= $filters['order'] === 'created_at' ? 'selected' : '' ?>>Data Criação</option>
                            <option value="last_login" <?= $filters['order'] === 'last_login' ? 'selected' : '' ?>>Último Login</option>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <select name="direction" class="form-select">
                            <option value="ASC" <?= $filters['direction'] === 'ASC' ? 'selected' : '' ?>>↑</option>
                            <option value="DESC" <?= $filters['direction'] === 'DESC' ? 'selected' : '' ?>>↓</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary-custom">
                                <i class="bi bi-search me-1"></i>Filtrar
                            </button>
                            <a href="usuarios.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </div>
                </form>
                
                <?php if (UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'create')): ?>
                <div class="mt-3">
                    <a href="register.php" class="btn btn-primary-custom">
                        <i class="bi bi-person-plus me-2"></i>Novo Usuário
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Lista de Usuários -->
            <div class="management-card fade-in">
                <div class="card-header">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                        <h5 class="mb-0">
                            <i class="bi bi-people me-2"></i>
                            Lista de Usuários (<?= $total_users ?> encontrados)
                        </h5>
                        <div class="d-flex align-items-center gap-3">
                            <small class="text-muted">
                                Página <?= $page ?> de <?= max(1, $total_pages) ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($users)): ?>
                        <div class="text-center p-5 text-muted">
                            <i class="bi bi-person-x fs-1 mb-3 opacity-50"></i>
                            <h5>Nenhum usuário encontrado</h5>
                            <p>
                                <?php if (!empty($filters['search']) || !empty($filters['tipo']) || !empty($filters['status'])): ?>
                                    Tente ajustar os filtros de pesquisa.
                                <?php else: ?>
                                    <?php if (UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'create')): ?>
                                        Cadastre o primeiro usuário para começar.
                                    <?php else: ?>
                                        Não há usuários disponíveis para visualização.
                                    <?php endif; ?>
                                <?php endif; ?>
                            </p>
                            <?php if (UserPermissions::hasPermission($_SESSION['user_type'], 'usuarios', 'create')): ?>
                                <a href="register.php" class="btn btn-primary-custom mt-2">
                                    <i class="bi bi-person-plus me-1"></i>Cadastrar Usuário
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-person me-1"></i>Usuário</th>
                                        <th><i class="bi bi-envelope me-1"></i>Email</th>
                                        <th class="d-none d-md-table-cell"><i class="bi bi-shield me-1"></i>Tipo</th>
                                        <th class="d-none d-lg-table-cell"><i class="bi bi-clock me-1"></i>Último Login</th>
                                        <th class="d-none d-xl-table-cell"><i class="bi bi-person-plus me-1"></i>Criado Por</th>
                                        <th><i class="bi bi-toggle-on me-1"></i>Status</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <?php 
                                        $can_manage = UserPermissions::canManageUser($_SESSION['user_type'], $user['tipo'], 'edit');
                                        $allowed_actions = UserPermissions::getAllowedUserActions($_SESSION['user_type'], $user['tipo']);
                                        $is_current_user = $user['id'] == $_SESSION['user_id'];
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="bi bi-person text-white"></i>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($user['nome']) ?></strong>
                                                        <?php if ($is_current_user): ?>
                                                            <span class="badge bg-info ms-2">Você</span>
                                                        <?php endif; ?>
                                                        <div class="d-md-none">
                                                            <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                                        </div>
                                                        <div class="d-md-none">
                                                            <span class="user-badge <?= $user['tipo'] ?>"><?= ucfirst($user['tipo']) ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?= htmlspecialchars($user['email']) ?>
                                                <?php if ($user['telefone']): ?>
                                                    <br><small class="text-muted"><?= formatPhone($user['telefone']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="user-badge <?= $user['tipo'] ?>">
                                                    <?= ucfirst($user['tipo']) ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-lg-table-cell">
                                                <?php if ($user['last_login']): ?>
                                                    <small><?= date('d/m/Y H:i', strtotime($user['last_login'])) ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">Nunca</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none d-xl-table-cell">
                                                <small><?= htmlspecialchars($user['created_by_name'] ?? 'Sistema') ?></small>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $user['ativo'] ? 'ativo' : 'inativo' ?>">
                                                    <?= $user['ativo'] ? 'Ativo' : 'Inativo' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if (in_array('view', $allowed_actions)): ?>
                                                        <button class="btn-action btn-edit" onclick="viewUser(<?= $user['id'] ?>)" 
                                                                title="Ver detalhes">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array('edit', $allowed_actions) && !$is_current_user): ?>
                                                        <button class="btn-action btn-edit" onclick="editUser(<?= $user['id'] ?>)" 
                                                                title="Editar usuário">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array('reset_password', $allowed_actions) && !$is_current_user): ?>
                                                        <button class="btn-action btn-reset" onclick="resetPassword(<?= $user['id'] ?>)" 
                                                                title="Redefinir senha">
                                                            <i class="bi bi-key"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array('change_type', $allowed_actions) && !$is_current_user): ?>
                                                        <button class="btn-action btn-toggle" onclick="changeUserType(<?= $user['id'] ?>)" 
                                                                title="Alterar tipo">
                                                            <i class="bi bi-arrow-left-right"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ((in_array('activate', $allowed_actions) || in_array('deactivate', $allowed_actions)) && !$is_current_user): ?>
                                                        <button class="btn-action btn-toggle" onclick="toggleUserStatus(<?= $user['id'] ?>)" 
                                                                title="<?= $user['ativo'] ? 'Desativar' : 'Ativar' ?> usuário">
                                                            <i class="bi bi-<?= $user['ativo'] ? 'toggle-off' : 'toggle-on' ?>"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array('delete', $allowed_actions) && !$is_current_user): ?>
                                                        <button class="btn-action btn-danger-action" onclick="deleteUser(<?= $user['id'] ?>)" 
                                                                title="Excluir usuário">
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
                        
                        <!-- Paginação -->
                        <?php if ($total_pages > 1): ?>
                            <div class="d-flex justify-content-center mt-4 mb-3">
                                <nav aria-label="Paginação de usuários">
                                    <ul class="pagination">
                                        <!-- Primeira página -->
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => 1])) ?>">
                                                    <i class="bi bi-chevron-double-left"></i>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>">
                                                    <i class="bi bi-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Páginas numeradas -->
                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($total_pages, $page + 2);
                                        
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <!-- Última página -->
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>">
                                                    <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $total_pages])) ?>">
                                                    <i class="bi bi-chevron-double-right"></i>
                                                </a>
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

    <!-- Modal: Ver Usuário -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person me-2"></i>Detalhes do Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewUserContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Editar Usuário -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="modal-body" id="editUserContent">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-check me-1"></i>Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Redefinir Senha -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key me-2"></i>Redefinir Senha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="resetPasswordForm">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Atenção:</strong> O usuário será obrigado a alterar a senha na próxima entrada.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nova Senha *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" name="new_password" id="reset_new_password" 
                                       required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('reset_new_password')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirmar Nova Senha *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" class="form-control" name="confirm_password" id="reset_confirm_password" 
                                       required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('reset_confirm_password')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div id="resetPasswordStrength" class="password-strength"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key me-1"></i>Redefinir Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Alterar Tipo -->
    <div class="modal fade" id="changeTypeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Alterar Tipo de Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="changeTypeForm">
                    <input type="hidden" name="action" value="change_type">
                    <input type="hidden" name="user_id" id="change_type_user_id">
                    
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Cuidado:</strong> Alterar o tipo de usuário mudará suas permissões no sistema.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo Atual</label>
                            <input type="text" class="form-control" id="current_user_type" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Novo Tipo *</label>
                            <select name="new_type" id="new_user_type" class="form-select" required>
                                <option value="">Selecione o novo tipo</option>
                                <option value="usuario">👤 Usuário</option>
                                <option value="tecnico">🔧 Técnico</option>
                                <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                    <option value="admin">🛡️ Administrador</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-arrow-left-right me-1"></i>Alterar Tipo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Confirmar Exclusão -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Confirmar Exclusão</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteUserForm">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Atenção:</strong> Esta ação não pode ser desfeita!
                        </div>
                        
                        <p>Você tem certeza que deseja excluir o usuário <strong id="delete_user_name"></strong>?</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Para confirmar, digite "CONFIRMAR":</label>
                            <input type="text" class="form-control" name="confirm_delete" id="confirm_delete" 
                                   placeholder="CONFIRMAR" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                            <i class="bi bi-trash me-1"></i>Excluir Usuário
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============ CONFIGURAÇÕES GLOBAIS ============
        const USER_TYPE = '<?= $_SESSION['user_type'] ?>';
        
        // ============ SIDEBAR E NAVEGAÇÃO ============
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');
            
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle?.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });

        // ============ FUNÇÕES DE MODAL ============
        
        async function viewUser(userId) {
            const modal = new bootstrap.Modal(document.getElementById('viewUserModal'));
            const content = document.getElementById('viewUserContent');
            
            // Mostrar loading
            content.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            try {
                const response = await fetch(`user_api.php?action=get_user&id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    const user = data.user;
                    content.innerHTML = `
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                     style="width: 100px; height: 100px;">
                                    <i class="bi bi-person text-white fs-1"></i>
                                </div>
                                <h5>${user.nome}</h5>
                                <span class="user-badge ${user.tipo}">${user.tipo.charAt(0).toUpperCase() + user.tipo.slice(1)}</span>
                            </div>
                            <div class="col-md-8">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="30%">Email:</th>
                                        <td>${user.email}</td>
                                    </tr>
                                    <tr>
                                        <th>Telefone:</th>
                                        <td>${user.telefone || 'Não informado'}</td>
                                    </tr>
                                    <tr>
                                        <th>CPF:</th>
                                        <td>${user.cpf || 'Não informado'}</td>
                                    </tr>
                                    <tr>
                                        <th>Endereço:</th>
                                        <td>${user.endereco || 'Não informado'}</td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <span class="status-badge status-${user.ativo ? 'ativo' : 'inativo'}">
                                                ${user.ativo ? 'Ativo' : 'Inativo'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Criado em:</th>
                                        <td>${formatDate(user.created_at)}</td>
                                    </tr>
                                    <tr>
                                        <th>Último login:</th>
                                        <td>${user.last_login ? formatDate(user.last_login) : 'Nunca'}</td>
                                    </tr>
                                    <tr>
                                        <th>Observações:</th>
                                        <td>${user.observacoes || 'Nenhuma observação'}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    `;
                } else {
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            Erro ao carregar dados do usuário: ${data.message}
                        </div>
                    `;
                }
            } catch (error) {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        Erro de conexão. Tente novamente.
                    </div>
                `;
            }
        }

        async function editUser(userId) {
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            const content = document.getElementById('editUserContent');
            const userIdInput = document.getElementById('edit_user_id');
            
            userIdInput.value = userId;
            
            // Mostrar loading
            content.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            try {
                const response = await fetch(`user_api.php?action=get_user&id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    const user = data.user;
                    content.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nome Completo *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" class="form-control" name="nome" value="${user.nome}" required maxlength="100">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">E-mail *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" name="email" value="${user.email}" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Telefone</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                        <input type="tel" class="form-control" name="telefone" value="${user.telefone || ''}" maxlength="15">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">CPF</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                                        <input type="text" class="form-control" name="cpf" value="${user.cpf || ''}" maxlength="14">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Endereço</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                <input type="text" class="form-control" name="endereco" value="${user.endereco || ''}" maxlength="300">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" name="observacoes" rows="3" maxlength="500">${user.observacoes || ''}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo de Usuário</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield"></i></span>
                                <input type="text" class="form-control" value="${user.tipo.charAt(0).toUpperCase() + user.tipo.slice(1)}" disabled>
                            </div>
                            <small class="text-muted">Para alterar o tipo, use a ação "Alterar Tipo"</small>
                        </div>
                    `;
                    
                    // Aplicar máscaras
                    applyMasks();
                } else {
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            Erro ao carregar dados do usuário: ${data.message}
                        </div>
                    `;
                }
            } catch (error) {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        Erro de conexão. Tente novamente.
                    </div>
                `;
            }
        }

        function resetPassword(userId) {
            const modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
            const userIdInput = document.getElementById('reset_user_id');
            
            userIdInput.value = userId;
            
            // Limpar campos
            document.getElementById('reset_new_password').value = '';
            document.getElementById('reset_confirm_password').value = '';
            document.getElementById('resetPasswordStrength').innerHTML = '';
            
            modal.show();
        }

        async function changeUserType(userId) {
            const modal = new bootstrap.Modal(document.getElementById('changeTypeModal'));
            const userIdInput = document.getElementById('change_type_user_id');
            const currentTypeInput = document.getElementById('current_user_type');
            const newTypeSelect = document.getElementById('new_user_type');
            
            userIdInput.value = userId;
            
            try {
                const response = await fetch(`user_api.php?action=get_user&id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    const user = data.user;
                    currentTypeInput.value = user.tipo.charAt(0).toUpperCase() + user.tipo.slice(1);
                    
                    // Limpar seleção anterior
                    newTypeSelect.value = '';
                    
                    // Desabilitar o tipo atual
                    Array.from(newTypeSelect.options).forEach(option => {
                        option.disabled = option.value === user.tipo;
                    });
                    
                    modal.show();
                } else {
                    alert('Erro ao carregar dados do usuário: ' + data.message);
                }
            } catch (error) {
                alert('Erro de conexão. Tente novamente.');
            }
        }

        function toggleUserStatus(userId) {
            if (confirm('Tem certeza que deseja alterar o status deste usuário?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        async function deleteUser(userId) {
            const modal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            const userIdInput = document.getElementById('delete_user_id');
            const userNameSpan = document.getElementById('delete_user_name');
            const confirmInput = document.getElementById('confirm_delete');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            
            userIdInput.value = userId;
            
            try {
                const response = await fetch(`user_api.php?action=get_user&id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    userNameSpan.textContent = data.user.nome;
                    
                    // Limpar campo de confirmação
                    confirmInput.value = '';
                    confirmBtn.disabled = true;
                    
                    modal.show();
                } else {
                    alert('Erro ao carregar dados do usuário: ' + data.message);
                }
            } catch (error) {
                alert('Erro de conexão. Tente novamente.');
            }
        }

        // ============ VALIDAÇÕES E MÁSCARAS ============

        function applyMasks() {
            // Máscara para CPF
            const cpfInputs = document.querySelectorAll('input[name="cpf"]');
            cpfInputs.forEach(input => {
                input.addEventListener('input', function() {
                    let value = this.value.replace(/\D/g, '');
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                    this.value = value;
                });
            });

            // Máscara para telefone
            const telefoneInputs = document.querySelectorAll('input[name="telefone"]');
            telefoneInputs.forEach(input => {
                input.addEventListener('input', function() {
                    let value = this.value.replace(/\D/g, '');
                    if (value.length <= 10) {
                        value = value.replace(/(\d{2})(\d{4})(\d)/, '($1) $2-$3');
                    } else {
                        value = value.replace(/(\d{2})(\d{5})(\d)/, '($1) $2-$3');
                    }
                    this.value = value;
                });
            });
        }

        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        // Validador de força da senha
        document.getElementById('reset_new_password')?.addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('resetPasswordStrength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            // Verificações de força
            if (password.length >= 6) strength++;
            else feedback.push('pelo menos 6 caracteres');
            
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) {
                strength++;
            } else {
                feedback.push('letras maiúsculas e minúsculas');
            }
            
            if (password.match(/\d/)) {
                strength++;
            } else {
                feedback.push('números');
            }
            
            if (password.match(/[^a-zA-Z\d]/)) {
                strength++;
            } else {
                feedback.push('caracteres especiais');
            }
            
            const messages = [
                { text: 'Muito fraca', class: 'strength-weak' },
                { text: 'Fraca', class: 'strength-weak' },
                { text: 'Média', class: 'strength-medium' },
                { text: 'Forte', class: 'strength-strong' },
                { text: 'Muito forte', class: 'strength-strong' }
            ];
            
            const strengthInfo = messages[strength];
            strengthDiv.innerHTML = `
                Força: <span class="${strengthInfo.class}">${strengthInfo.text}</span>
                ${feedback.length > 0 ? '<br><small>Adicione: ' + feedback.join(', ') + '</small>' : ''}
            `;
        });

        // Validação do campo de confirmação de exclusão
        document.getElementById('confirm_delete')?.addEventListener('input', function() {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.disabled = this.value !== 'CONFIRMAR';
        });

        // ============ VALIDAÇÕES DE FORMULÁRIO ============

        // Validação do formulário de edição
        document.getElementById('editUserForm')?.addEventListener('submit', function(e) {
            const nome = this.querySelector('input[name="nome"]');
            const email = this.querySelector('input[name="email"]');
            const cpf = this.querySelector('input[name="cpf"]');
            const telefone = this.querySelector('input[name="telefone"]');
            
            let isValid = true;

            // Reset validações
            [nome, email, cpf, telefone].forEach(field => {
                if (field) field.classList.remove('is-invalid');
            });

            // Validar nome
            if (!nome.value.trim()) {
                showFieldError(nome, 'Nome é obrigatório');
                isValid = false;
            } else if (nome.value.trim().length < 2) {
                showFieldError(nome, 'Nome deve ter pelo menos 2 caracteres');
                isValid = false;
            }

            // Validar email
            if (!email.value.trim()) {
                showFieldError(email, 'Email é obrigatório');
                isValid = false;
            } else if (!isValidEmail(email.value)) {
                showFieldError(email, 'Email inválido');
                isValid = false;
            }

            // Validar CPF se preenchido
            if (cpf && cpf.value.trim()) {
                const cpfNumbers = cpf.value.replace(/\D/g, '');
                if (cpfNumbers.length !== 11) {
                    showFieldError(cpf, 'CPF deve ter 11 dígitos');
                    isValid = false;
                } else if (!isValidCPF(cpfNumbers)) {
                    showFieldError(cpf, 'CPF inválido');
                    isValid = false;
                }
            }

            // Validar telefone se preenchido
            if (telefone && telefone.value.trim()) {
                const phoneNumbers = telefone.value.replace(/\D/g, '');
                if (phoneNumbers.length < 10 || phoneNumbers.length > 11) {
                    showFieldError(telefone, 'Telefone deve ter 10 ou 11 dígitos');
                    isValid = false;
                }
            }

            if (!isValid) {
                e.preventDefault();
            }
        });

        // Validação do formulário de redefinição de senha
        document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('reset_new_password');
            const confirmPassword = document.getElementById('reset_confirm_password');
            
            let isValid = true;

            // Reset validações
            [newPassword, confirmPassword].forEach(field => {
                field.classList.remove('is-invalid');
            });

            // Validar nova senha
            if (!newPassword.value) {
                showFieldError(newPassword, 'Nova senha é obrigatória');
                isValid = false;
            } else if (newPassword.value.length < 6) {
                showFieldError(newPassword, 'Nova senha deve ter pelo menos 6 caracteres');
                isValid = false;
            }

            // Validar confirmação
            if (!confirmPassword.value) {
                showFieldError(confirmPassword, 'Confirmação de senha é obrigatória');
                isValid = false;
            } else if (newPassword.value !== confirmPassword.value) {
                showFieldError(confirmPassword, 'Senhas não coincidem');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
            }
        });

        // ============ FUNÇÕES UTILITÁRIAS ============

        function showFieldError(field, message) {
            field.classList.add('is-invalid');
            
            // Remover feedback anterior
            const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
            if (existingFeedback) {
                existingFeedback.remove();
            }
            
            // Adicionar novo feedback
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = message;
            field.parentNode.appendChild(feedback);
        }

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function isValidCPF(cpf) {
            // Remove any non-digit characters
            cpf = cpf.replace(/\D/g, '');
            
            // Check if CPF has 11 digits
            if (cpf.length !== 11) return false;
            
            // Check if all digits are the same
            if (/^(\d)\1{10}$/.test(cpf)) return false;
            
            // Validate first check digit
            let sum = 0;
            for (let i = 0; i < 9; i++) {
                sum += parseInt(cpf.charAt(i)) * (10 - i);
            }
            let digit1 = (sum * 10) % 11;
            if (digit1 === 10) digit1 = 0;
            if (digit1 !== parseInt(cpf.charAt(9))) return false;
            
            // Validate second check digit
            sum = 0;
            for (let i = 0; i < 10; i++) {
                sum += parseInt(cpf.charAt(i)) * (11 - i);
            }
            let digit2 = (sum * 10) % 11;
            if (digit2 === 10) digit2 = 0;
            if (digit2 !== parseInt(cpf.charAt(10))) return false;
            
            return true;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }

        // ============ INICIALIZAÇÃO ============

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            });
        }, 8000);

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

        // Observe elements for animation
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Aplicar máscaras na inicialização
        document.addEventListener('DOMContentLoaded', function() {
            applyMasks();
        });

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart;
            console.log(`Users management page loaded in ${loadTime}ms`);
        });

        // Clear errors on input
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
                const feedback = e.target.parentNode.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.remove();
                }
            }
        });

        // Password strength styles
        const style = document.createElement('style');
        style.textContent = `
            .strength-weak { color: #dc3545; }
            .strength-medium { color: #ffc107; }
            .strength-strong { color: #198754; }
            .password-strength { font-size: 0.875rem; margin-top: 0.25rem; }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>