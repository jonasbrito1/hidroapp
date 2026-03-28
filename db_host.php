<?php
// Configurações do banco de dados
$db_config = [
    'host' => $_ENV['DB_HOST'] ?? 'db',
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
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
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
            error_log("Erro na query: " . $e->getMessage());
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
}

// Inicializar a classe Database
Database::init($pdo);

// Função para sanitizar dados de entrada
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Função para validar email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Função para hash de senha
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Função para verificar senha
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
?>