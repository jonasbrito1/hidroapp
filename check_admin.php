<?php
/**
 * Script de Diagnóstico e Criação de Usuário Admin
 * HidroApp - Verificação de Login em Produção
 */

// Evitar timeout
set_time_limit(300);

// Mostrar todos os erros para diagnóstico
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>HidroApp - Diagnóstico Admin</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #0066cc; border-bottom: 3px solid #0066cc; padding-bottom: 10px; }
    h2 { color: #333; margin-top: 30px; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #28a745; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #dc3545; }
    .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #ffc107; }
    .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #17a2b8; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; border: 1px solid #dee2e6; }
    code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
    th { background: #0066cc; color: white; }
    tr:hover { background: #f8f9fa; }
    .btn { background: #0066cc; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
    .btn:hover { background: #0052a3; }
    .btn-danger { background: #dc3545; }
    .btn-danger:hover { background: #c82333; }
    .btn-success { background: #28a745; }
    .btn-success:hover { background: #218838; }
</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";

echo "<h1>🔧 HidroApp - Diagnóstico de Login Admin</h1>";
echo "<p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>";

// =====================================================
// 1. TESTAR CONEXÃO COM BANCO DE DADOS
// =====================================================
echo "<h2>1️⃣ Teste de Conexão com Banco de Dados</h2>";

// Configurações do banco (PRODUÇÃO HOSTINGER)
$db_config = [
    'host' => 'localhost',
    'port' => '3306',
    'name' => 'u674882802_hidroapp',
    'user' => 'u674882802_hidro',
    'password' => 'rA+3d~GfH',
    'charset' => 'utf8mb4'
];

echo "<div class='info'>";
echo "<strong>Configurações de Conexão:</strong><br>";
echo "Host: " . $db_config['host'] . "<br>";
echo "Database: " . $db_config['name'] . "<br>";
echo "User: " . $db_config['user'] . "<br>";
echo "Password: " . str_repeat('*', strlen($db_config['password'])) . "<br>";
echo "Charset: " . $db_config['charset'];
echo "</div>";

try {
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset={$db_config['charset']}";

    $pdo = new PDO($dsn, $db_config['user'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);

    echo "<div class='success'>✅ <strong>Conexão com banco de dados estabelecida com sucesso!</strong></div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ <strong>Erro na conexão:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</div></body></html>";
    exit;
}

// =====================================================
// 2. VERIFICAR SE TABELA USUARIOS EXISTE
// =====================================================
echo "<h2>2️⃣ Verificação da Tabela Usuários</h2>";

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='success'>✅ Tabela <code>usuarios</code> existe</div>";

        // Mostrar estrutura da tabela
        $columns = $pdo->query("DESCRIBE usuarios")->fetchAll();
        echo "<h3>Estrutura da Tabela:</h3>";
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";

    } else {
        echo "<div class='error'>❌ Tabela <code>usuarios</code> não existe!</div>";
        echo "<div class='warning'><strong>Ação necessária:</strong> Execute o script de criação do banco de dados (init.sql)</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Erro ao verificar tabela: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// =====================================================
// 3. LISTAR TODOS OS USUÁRIOS
// =====================================================
echo "<h2>3️⃣ Usuários Cadastrados</h2>";

try {
    $users = $pdo->query("SELECT id, nome, email, tipo, ativo, created_at FROM usuarios ORDER BY id")->fetchAll();

    if (count($users) > 0) {
        echo "<div class='success'>✅ Total de usuários: <strong>" . count($users) . "</strong></div>";

        echo "<table>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Tipo</th><th>Ativo</th><th>Criado em</th></tr>";
        foreach ($users as $user) {
            $statusClass = $user['ativo'] ? 'success' : 'error';
            $statusText = $user['ativo'] ? '✅ Sim' : '❌ Não';
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['nome']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td><strong>{$user['tipo']}</strong></td>";
            echo "<td>{$statusText}</td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ Nenhum usuário encontrado no banco de dados</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Erro ao listar usuários: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// =====================================================
// 4. VERIFICAR USUÁRIO ADMIN ESPECÍFICO
// =====================================================
echo "<h2>4️⃣ Verificação do Usuário Admin Padrão</h2>";

$admin_email = 'admin@hidroapp.com';
echo "<div class='info'>Procurando usuário: <code>{$admin_email}</code></div>";

try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$admin_email]);
    $admin = $stmt->fetch();

    if ($admin) {
        echo "<div class='success'>✅ Usuário admin encontrado!</div>";

        echo "<table>";
        echo "<tr><th>Campo</th><th>Valor</th></tr>";
        echo "<tr><td>ID</td><td>{$admin['id']}</td></tr>";
        echo "<tr><td>Nome</td><td>{$admin['nome']}</td></tr>";
        echo "<tr><td>Email</td><td>{$admin['email']}</td></tr>";
        echo "<tr><td>Tipo</td><td><strong>{$admin['tipo']}</strong></td></tr>";
        echo "<tr><td>Ativo</td><td>" . ($admin['ativo'] ? '✅ Sim' : '❌ Não') . "</td></tr>";
        echo "<tr><td>Senha Hash</td><td><code>" . substr($admin['senha'], 0, 50) . "...</code></td></tr>";
        echo "</table>";

        // Testar senha
        echo "<h3>Teste de Senha</h3>";
        $test_password = 'admin123';
        $password_ok = password_verify($test_password, $admin['senha']);

        if ($password_ok) {
            echo "<div class='success'>✅ Senha <code>admin123</code> está CORRETA!</div>";
        } else {
            echo "<div class='error'>❌ Senha <code>admin123</code> NÃO corresponde ao hash armazenado</div>";
            echo "<div class='warning'><strong>Problema encontrado:</strong> A senha hash no banco não corresponde a 'admin123'</div>";

            // Oferecer opção de resetar senha
            echo "<h3>Solução: Resetar Senha</h3>";
            echo "<form method='POST' style='margin: 20px 0;'>";
            echo "<input type='hidden' name='action' value='reset_password'>";
            echo "<input type='hidden' name='user_id' value='{$admin['id']}'>";
            echo "<button type='submit' class='btn btn-danger' onclick=\"return confirm('Tem certeza que deseja resetar a senha do admin para admin123?');\">🔄 Resetar Senha para 'admin123'</button>";
            echo "</form>";
        }

        // Verificar se está ativo
        if (!$admin['ativo']) {
            echo "<div class='error'>❌ O usuário está INATIVO!</div>";
            echo "<form method='POST' style='margin: 20px 0;'>";
            echo "<input type='hidden' name='action' value='activate_user'>";
            echo "<input type='hidden' name='user_id' value='{$admin['id']}'>";
            echo "<button type='submit' class='btn btn-success'>✅ Ativar Usuário</button>";
            echo "</form>";
        }

    } else {
        echo "<div class='error'>❌ Usuário <code>{$admin_email}</code> não encontrado!</div>";

        // Oferecer opção de criar
        echo "<h3>Solução: Criar Usuário Admin</h3>";
        echo "<form method='POST' style='margin: 20px 0;'>";
        echo "<input type='hidden' name='action' value='create_admin'>";
        echo "<button type='submit' class='btn btn-success'>➕ Criar Usuário Admin Padrão</button>";
        echo "</form>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Erro ao verificar admin: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// =====================================================
// 5. PROCESSAR AÇÕES DO FORMULÁRIO
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    echo "<h2>5️⃣ Resultado da Ação</h2>";

    $action = $_POST['action'];

    try {
        switch ($action) {
            case 'create_admin':
                // Criar usuário admin
                $nome = 'Administrador';
                $email = 'admin@hidroapp.com';
                $senha = password_hash('admin123', PASSWORD_DEFAULT);
                $tipo = 'admin';

                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo, ativo, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$nome, $email, $senha, $tipo]);

                echo "<div class='success'>✅ <strong>Usuário admin criado com sucesso!</strong><br>";
                echo "Email: <code>{$email}</code><br>";
                echo "Senha: <code>admin123</code></div>";
                echo "<a href='check_admin.php' class='btn'>🔄 Atualizar Página</a>";
                break;

            case 'reset_password':
                // Resetar senha do usuário
                $user_id = intval($_POST['user_id']);
                $nova_senha = password_hash('admin123', PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("UPDATE usuarios SET senha = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$nova_senha, $user_id]);

                echo "<div class='success'>✅ <strong>Senha resetada com sucesso!</strong><br>";
                echo "Nova senha: <code>admin123</code></div>";
                echo "<a href='check_admin.php' class='btn'>🔄 Atualizar Página</a>";
                break;

            case 'activate_user':
                // Ativar usuário
                $user_id = intval($_POST['user_id']);

                $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 1, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);

                echo "<div class='success'>✅ <strong>Usuário ativado com sucesso!</strong></div>";
                echo "<a href='check_admin.php' class='btn'>🔄 Atualizar Página</a>";
                break;
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Erro ao executar ação: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// =====================================================
// 6. INSTRUÇÕES FINAIS
// =====================================================
echo "<h2>6️⃣ Instruções de Login</h2>";
echo "<div class='info'>";
echo "<strong>Credenciais de Acesso:</strong><br>";
echo "Email: <code>admin@hidroapp.com</code><br>";
echo "Senha: <code>admin123</code><br><br>";
echo "<strong>URL de Login:</strong> <a href='login.php' class='btn'>🔐 Ir para Login</a>";
echo "</div>";

echo "<h2>7️⃣ Verificações de Segurança</h2>";
echo "<div class='warning'>";
echo "⚠️ <strong>IMPORTANTE - Segurança:</strong><br>";
echo "1. Após o login bem-sucedido, <strong>ALTERE A SENHA PADRÃO</strong> imediatamente<br>";
echo "2. <strong>DELETE este arquivo (check_admin.php)</strong> do servidor de produção<br>";
echo "3. Certifique-se de que DEBUG_MODE está como <code>false</code> em produção<br>";
echo "4. Configure o arquivo .htaccess para proteção adicional";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?>
