<?php
// Configurações do banco de dados
$db_config = [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'name' => $_ENV['DB_NAME'] ?? 'u674882802_hidroapp',
    'user' => $_ENV['DB_USER'] ?? 'u674882802_hidro',
    'password' => $_ENV['DB_PASSWORD'] ?? 'rA+3d~GfH',
    'charset' => 'utf8mb4'
];

try {
    // Criar conexão PDO
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset={$db_config['charset']}";
    
    $pdo = new PDO($dsn, $db_config['user'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);

} catch (PDOException $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Erro na conexão com o banco de dados: " . $e->getMessage());
    } else {
        die("Erro: Sistema temporariamente indisponível. Tente novamente em alguns minutos.");
    }
}

// Funções auxiliares para operações no banco
class Database {
    private static $pdo;
    
    public static function init($pdo_instance) {
        self::$pdo = $pdo_instance;
    }
    
    public static function query($sql, $params = []) {
        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (function_exists('logMessage')) {
                logMessage("Erro na query: " . $e->getMessage() . " | SQL: " . $sql, 'ERROR');
            } else {
                error_log("HidroApp DB Error: " . $e->getMessage());
            }
            throw $e;
        }
    }
    
    public static function fetch($sql, $params = []) {
        return self::query($sql, $params)->fetch();
    }
    
    public static function fetchAll($sql, $params = []) {
        return self::query($sql, $params)->fetchAll();
    }
    
    public static function lastInsertId() {
        return self::$pdo->lastInsertId();
    }
    
    public static function beginTransaction() {
        return self::$pdo->beginTransaction();
    }
    
    public static function commit() {
        return self::$pdo->commit();
    }
    
    public static function rollback() {
        return self::$pdo->rollBack();
    }
    
    public static function getPdo() {
        return self::$pdo;
    }
}

// Inicializar a classe Database
Database::init($pdo);

// Função para sanitizar dados de entrada
if (!function_exists('sanitize')) {
    function sanitize($data) {
        if (is_array($data)) {
            return array_map('sanitize', $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// Função para validar email
if (!function_exists('validateEmail')) {
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

// Função para hash de senha
if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

// Função para verificar senha
if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

// Função para escapar dados para SQL
if (!function_exists('escapeData')) {
    function escapeData($data) {
        if (is_array($data)) {
            return array_map('escapeData', $data);
        }
        return trim(stripslashes($data));
    }
}

// Função para validar dados obrigatórios
if (!function_exists('validateRequired')) {
    function validateRequired($fields, $data) {
        $errors = [];
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $errors[] = "O campo '{$field}' é obrigatório.";
            }
        }
        return $errors;
    }
}

// Função para validar comprimento de campos
if (!function_exists('validateLength')) {
    function validateLength($data, $max_length, $field_name = 'campo') {
        if (strlen($data) > $max_length) {
            return "O {$field_name} deve ter no máximo {$max_length} caracteres.";
        }
        return null;
    }
}

// Função para limpar e validar entrada de busca
if (!function_exists('cleanSearchInput')) {
    function cleanSearchInput($search) {
        // Remove caracteres especiais que podem causar problemas
        $search = preg_replace('/[<>"\']/', '', $search);
        return trim($search);
    }
}

// Função para gerar token CSRF
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// Função para validar token CSRF
if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Função para log de operações no banco
if (!function_exists('logDatabaseOperation')) {
    function logDatabaseOperation($operation, $table, $data = [], $user_id = null) {
        if (function_exists('logMessage')) {
            $user_info = $user_id ? "User ID: {$user_id}" : 'Sistema';
            $data_info = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : '';
            logMessage("DB Operation: {$operation} on {$table} by {$user_info} - Data: {$data_info}", 'INFO');
        }
    }
}

// Função para verificar se tabela existe
if (!function_exists('tableExists')) {
    function tableExists($tableName) {
        try {
            $result = Database::query("SHOW TABLES LIKE ?", [$tableName]);
            return $result->rowCount() > 0;
        } catch (Exception $e) {
            if (function_exists('logMessage')) {
                logMessage("Erro ao verificar existência da tabela {$tableName}: " . $e->getMessage(), 'ERROR');
            }
            return false;
        }
    }
}

// Função para backup de tabela específica
if (!function_exists('backupTable')) {
    function backupTable($tableName) {
        try {
            if (!tableExists($tableName)) {
                throw new Exception("Tabela {$tableName} não existe");
            }

            $timestamp = date('Y_m_d_H_i_s');
            $backupTable = "{$tableName}_backup_{$timestamp}";
            
            Database::query("CREATE TABLE {$backupTable} AS SELECT * FROM {$tableName}");
            
            if (function_exists('logMessage')) {
                logMessage("Backup da tabela {$tableName} criado como {$backupTable}", 'INFO');
            }
            
            return $backupTable;
        } catch (Exception $e) {
            if (function_exists('logMessage')) {
                logMessage("Erro ao fazer backup da tabela {$tableName}: " . $e->getMessage(), 'ERROR');
            }
            throw $e;
        }
    }
}

// Função para contar registros com segurança
if (!function_exists('countRecords')) {
    function countRecords($table, $where = '', $params = []) {
        try {
            $sql = "SELECT COUNT(*) as total FROM {$table}";
            if ($where) {
                $sql .= " WHERE {$where}";
            }
            
            $result = Database::fetch($sql, $params);
            return $result ? (int)$result['total'] : 0;
        } catch (Exception $e) {
            if (function_exists('logMessage')) {
                logMessage("Erro ao contar registros da tabela {$table}: " . $e->getMessage(), 'ERROR');
            }
            return 0;
        }
    }
}

// Função para verificar integridade de dados
if (!function_exists('checkDataIntegrity')) {
    function checkDataIntegrity() {
        $issues = [];
        
        try {
            // Verificar se tabela de usuários existe
            if (tableExists('usuarios')) {
                // Verificar usuários sem tipo definido
                $usersWithoutType = Database::fetchAll("
                    SELECT id, nome 
                    FROM usuarios 
                    WHERE tipo IS NULL OR tipo = ''
                ");
                
                if (!empty($usersWithoutType)) {
                    $issues[] = "Usuários sem tipo definido: " . count($usersWithoutType);
                }
                
                // Verificar usuários inativos
                $inactiveUsers = Database::fetchAll("
                    SELECT id, nome, email 
                    FROM usuarios 
                    WHERE ativo = 0
                ");
                
                if (!empty($inactiveUsers)) {
                    $issues[] = "Usuários inativos: " . count($inactiveUsers);
                }
            }
            
            // Verificar equipamentos órfãos (se tabelas existirem)
            if (tableExists('equipamentos') && tableExists('manutencoes')) {
                $orphanEquipments = Database::fetchAll("
                    SELECT e.id, e.codigo 
                    FROM equipamentos e 
                    LEFT JOIN manutencoes m ON e.id = m.equipamento_id 
                    WHERE m.id IS NULL AND e.status = 'manutencao'
                ");
                
                if (!empty($orphanEquipments)) {
                    $issues[] = "Equipamentos com status 'manutenção' mas sem manutenções registradas: " . count($orphanEquipments);
                }
            }
            
        } catch (Exception $e) {
            $issues[] = "Erro ao verificar integridade: " . $e->getMessage();
        }
        
        return $issues;
    }
}

// Função para otimizar tabelas
if (!function_exists('optimizeTables')) {
    function optimizeTables() {
        $tables = ['equipamentos', 'usuarios', 'manutencoes', 'logs'];
        $results = [];
        
        foreach ($tables as $table) {
            try {
                if (tableExists($table)) {
                    Database::query("OPTIMIZE TABLE {$table}");
                    $results[$table] = 'OK';
                } else {
                    $results[$table] = 'Tabela não existe';
                }
            } catch (Exception $e) {
                $results[$table] = 'Erro: ' . $e->getMessage();
            }
        }
        
        return $results;
    }
}

// Função para validar CPF
if (!function_exists('validateCPF')) {
    function validateCPF($cpf) {
        // Remove qualquer caractere não numérico
        $cpf = preg_replace('/\D/', '', $cpf);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) !== 11) return false;
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) return false;
        
        // Valida primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($cpf[$i]) * (10 - $i);
        }
        $digit1 = ($sum * 10) % 11;
        if ($digit1 === 10) $digit1 = 0;
        if ($digit1 !== intval($cpf[9])) return false;
        
        // Valida segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += intval($cpf[$i]) * (11 - $i);
        }
        $digit2 = ($sum * 10) % 11;
        if ($digit2 === 10) $digit2 = 0;
        if ($digit2 !== intval($cpf[10])) return false;
        
        return true;
    }
}

// Função para criar usuário admin padrão se não existir
if (!function_exists('createDefaultAdmin')) {
    function createDefaultAdmin() {
        try {
            // Verificar se tabela usuarios existe
            if (!tableExists('usuarios')) {
                if (function_exists('logMessage')) {
                    logMessage('Tabela usuarios não existe. Admin padrão não pode ser criado.', 'WARNING');
                } else {
                    error_log('HidroApp: Tabela usuarios não existe.');
                }
                return false;
            }
            
            // Verificar se já existe um usuário admin
            $admin = Database::fetch("SELECT id FROM usuarios WHERE tipo = 'admin' LIMIT 1");
            
            if (!$admin) {
                // Verificar se as constantes estão definidas
                $admin_name = defined('DEFAULT_ADMIN_NAME') ? DEFAULT_ADMIN_NAME : 'admin';
                $admin_email = defined('DEFAULT_ADMIN_EMAIL') ? DEFAULT_ADMIN_EMAIL : 'admin@hidroapp.com';
                $admin_password = defined('DEFAULT_ADMIN_PASSWORD') ? DEFAULT_ADMIN_PASSWORD : 'admin123';
                
                // Criar usuário admin padrão
                $hashedPassword = hashPassword($admin_password);
                
                Database::query(
                    "INSERT INTO usuarios (nome, email, senha, tipo, ativo, created_at) VALUES (?, ?, ?, 'admin', 1, NOW())",
                    [$admin_name, $admin_email, $hashedPassword]
                );
                
                if (function_exists('logMessage')) {
                    logMessage('Usuário admin padrão criado: ' . $admin_email, 'INFO');
                } else {
                    error_log('HidroApp: Usuário admin padrão criado: ' . $admin_email);
                }
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            if (function_exists('logMessage')) {
                logMessage('Erro ao criar usuário admin padrão: ' . $e->getMessage(), 'ERROR');
            } else {
                error_log('HidroApp: Erro ao criar usuário admin padrão: ' . $e->getMessage());
            }
            return false;
        }
    }
}

// Função para testar conexão
if (!function_exists('testDatabaseConnection')) {
    function testDatabaseConnection() {
        try {
            $result = Database::fetch("SELECT 1 as test");
            return $result && $result['test'] == 1;
        } catch (Exception $e) {
            if (function_exists('logMessage')) {
                logMessage('Erro no teste de conexão: ' . $e->getMessage(), 'ERROR');
            } else {
                error_log('HidroApp: Erro no teste de conexão: ' . $e->getMessage());
            }
            return false;
        }
    }
}

// Função para criar estrutura básica da tabela usuarios
if (!function_exists('createUsersTable')) {
    function createUsersTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS `usuarios` (
                `id` int NOT NULL AUTO_INCREMENT,
                `nome` varchar(100) NOT NULL,
                `email` varchar(100) NOT NULL,
                `senha` varchar(255) NOT NULL,
                `tipo` enum('admin','tecnico','usuario') DEFAULT 'usuario',
                `ativo` tinyint(1) DEFAULT '1',
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `telefone` varchar(11) DEFAULT NULL,
                `cpf` varchar(11) DEFAULT NULL,
                `endereco` varchar(300) DEFAULT NULL,
                `observacoes` text,
                `created_by` int DEFAULT NULL,
                `last_login` datetime DEFAULT NULL,
                `last_logout` datetime DEFAULT NULL,
                `login_ip` varchar(45) DEFAULT NULL,
                `logout_reason` varchar(50) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `email` (`email`),
                UNIQUE KEY `nome` (`nome`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            Database::query($sql);
            
            if (function_exists('logMessage')) {
                logMessage('Tabela usuarios criada com sucesso', 'INFO');
            } else {
                error_log('HidroApp: Tabela usuarios criada com sucesso');
            }
            
            return true;
        } catch (Exception $e) {
            if (function_exists('logMessage')) {
                logMessage('Erro ao criar tabela usuarios: ' . $e->getMessage(), 'ERROR');
            } else {
                error_log('HidroApp: Erro ao criar tabela usuarios: ' . $e->getMessage());
            }
            return false;
        }
    }
}

// Inicialização final
try {
    // Testar conexão primeiro
    if (!testDatabaseConnection()) {
        throw new Exception("Falha no teste de conexão com o banco de dados");
    }
    
    // Verificar se a tabela usuarios existe, senão criar
    if (!tableExists('usuarios')) {
        if (function_exists('logMessage')) {
            logMessage("Tabela usuarios não encontrada. Tentando criar...", 'WARNING');
        }
        createUsersTable();
    }
    
    // Verificar outras tabelas essenciais
    $essentialTables = ['usuarios'];
    $missingTables = [];
    
    foreach ($essentialTables as $table) {
        if (!tableExists($table)) {
            $missingTables[] = $table;
            if (function_exists('logMessage')) {
                logMessage("Tabela essencial {$table} não encontrada!", 'WARNING');
            }
        }
    }
    
    // Se a tabela usuarios existe, tentar criar admin padrão
    if (!in_array('usuarios', $missingTables)) {
        createDefaultAdmin();
    }
    
    // Log de conexão bem-sucedida
    if (function_exists('logMessage')) {
        logMessage("Conexão com banco de dados estabelecida com sucesso", 'INFO');
        if (!empty($missingTables)) {
            logMessage("Tabelas faltando: " . implode(', ', $missingTables), 'WARNING');
        }
    }
    
} catch (Exception $e) {
    if (function_exists('logMessage')) {
        logMessage("Erro na inicialização do banco: " . $e->getMessage(), 'ERROR');
    } else {
        error_log("HidroApp DB Error: " . $e->getMessage());
    }
    
    // Em modo de desenvolvimento, mostrar erro detalhado
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Erro de banco de dados: " . $e->getMessage());
    }
}
?>