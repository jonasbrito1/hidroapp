<?php
/**
 * HidroApp - Debug de Login
 * Este script vai diagnosticar e corrigir problemas de login
 * DELETAR APÓS O USO por segurança!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Chave de segurança
$debug_key = $_GET['key'] ?? '';
if ($debug_key !== 'debug2024') {
    die('❌ Acesso negado. Use: ?key=debug2024');
}

echo "<h1>🔍 HidroApp - Debug de Login</h1>";
echo "<hr>";

// Incluir arquivos necessários
if (file_exists('config.php')) {
    require_once 'config.php';
    echo "<p>✅ config.php carregado</p>";
} else {
    echo "<p>❌ config.php não encontrado</p>";
}

if (file_exists('db.php')) {
    require_once 'db.php';
    echo "<p>✅ db.php carregado</p>";
} else {
    echo "<p>❌ db.php não encontrado</p>";
}

echo "<hr>";

// ============= TESTE DE CONEXÃO =============
echo "<h2>🔌 Teste de Conexão</h2>";
try {
    $test = Database::fetch("SELECT 1 as test");
    if ($test && $test['test'] == 1) {
        echo "<p>✅ Conexão com banco funcionando</p>";
    } else {
        echo "<p>❌ Problema na conexão</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p>❌ Erro na conexão: " . $e->getMessage() . "</p>";
    exit;
}

// ============= LISTAR USUÁRIOS ADMIN =============
echo "<h2>👥 Usuários Administradores</h2>";
try {
    $admins = Database::fetchAll("SELECT id, nome, email, tipo, ativo, created_at FROM usuarios WHERE tipo = 'admin' ORDER BY id");
    
    if (empty($admins)) {
        echo "<p>❌ Nenhum usuário admin encontrado!</p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Tipo</th><th>Ativo</th><th>Criado</th></tr>";
        
        foreach ($admins as $admin) {
            $status_color = $admin['ativo'] ? 'green' : 'red';
            echo "<tr>";
            echo "<td>{$admin['id']}</td>";
            echo "<td>{$admin['nome']}</td>";
            echo "<td><strong>{$admin['email']}</strong></td>";
            echo "<td>{$admin['tipo']}</td>";
            echo "<td style='color: {$status_color};'>" . ($admin['ativo'] ? 'SIM' : 'NÃO') . "</td>";
            echo "<td>{$admin['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro ao buscar admins: " . $e->getMessage() . "</p>";
}

// ============= TESTE DE SENHAS =============
echo "<h2>🔐 Teste de Senhas</h2>";

$senhas_teste = [
    'admin123' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    '123456' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFl',
    'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
];

echo "<p><strong>Testando hashes conhecidos:</strong></p>";
foreach ($senhas_teste as $senha => $hash) {
    $resultado = password_verify($senha, $hash);
    $status = $resultado ? '✅' : '❌';
    echo "<p>{$status} Senha '<strong>{$senha}</strong>' com hash: " . substr($hash, 0, 30) . "...</p>";
}

// ============= GERAR NOVAS SENHAS =============
echo "<h2>🆕 Gerar Novas Senhas</h2>";

$novas_senhas = [
    'admin123' => password_hash('admin123', PASSWORD_DEFAULT),
    '123456' => password_hash('123456', PASSWORD_DEFAULT),
    'hidroapp2024' => password_hash('hidroapp2024', PASSWORD_DEFAULT),
    'admin' => password_hash('admin', PASSWORD_DEFAULT)
];

echo "<p><strong>Senhas geradas agora (funcionais):</strong></p>";
foreach ($novas_senhas as $senha => $hash) {
    $verificacao = password_verify($senha, $hash) ? '✅' : '❌';
    echo "<p>{$verificacao} <strong>{$senha}</strong>: <code style='font-size: 10px;'>{$hash}</code></p>";
}

// ============= BOTÕES DE AÇÃO =============
echo "<h2>⚡ Ações Rápidas</h2>";

if ($_POST['action'] ?? '' == 'fix_passwords') {
    echo "<div style='background: #ffffcc; padding: 15px; border: 2px solid #ffcc00; margin: 10px 0;'>";
    echo "<h3>🔧 Corrigindo senhas...</h3>";
    
    try {
        // Corrigir senha do admin principal
        $hash_admin = password_hash('admin123', PASSWORD_DEFAULT);
        Database::query("UPDATE usuarios SET senha = ? WHERE email = 'admin@hidroapp.com'", [$hash_admin]);
        echo "<p>✅ Senha do admin@hidroapp.com atualizada para: <strong>admin123</strong></p>";
        
        // Corrigir senha do superadmin
        $hash_super = password_hash('123456', PASSWORD_DEFAULT);
        Database::query("UPDATE usuarios SET senha = ? WHERE email = 'superadmin@hidroapp.com'", [$hash_super]);
        echo "<p>✅ Senha do superadmin@hidroapp.com atualizada para: <strong>123456</strong></p>";
        
        // Verificar se funcionou
        $admin_test = Database::fetch("SELECT senha FROM usuarios WHERE email = 'admin@hidroapp.com'");
        $super_test = Database::fetch("SELECT senha FROM usuarios WHERE email = 'superadmin@hidroapp.com'");
        
        if ($admin_test && password_verify('admin123', $admin_test['senha'])) {
            echo "<p>✅ Verificação: admin123 funciona para admin@hidroapp.com</p>";
        } else {
            echo "<p>❌ Verificação falhou para admin@hidroapp.com</p>";
        }
        
        if ($super_test && password_verify('123456', $super_test['senha'])) {
            echo "<p>✅ Verificação: 123456 funciona para superadmin@hidroapp.com</p>";
        } else {
            echo "<p>❌ Verificação falhou para superadmin@hidroapp.com</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Erro ao corrigir senhas: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
}

if ($_POST['action'] ?? '' == 'create_new_admin') {
    echo "<div style='background: #ccffcc; padding: 15px; border: 2px solid #00cc00; margin: 10px 0;'>";
    echo "<h3>👑 Criando novo admin...</h3>";
    
    $novo_nome = $_POST['novo_nome'] ?? 'Meu Admin';
    $novo_email = $_POST['novo_email'] ?? 'meuadmin@hidroapp.com';
    $nova_senha = $_POST['nova_senha'] ?? 'minhasenha123';
    
    try {
        // Verificar se email já existe
        $existe = Database::fetch("SELECT id FROM usuarios WHERE email = ?", [$novo_email]);
        
        if ($existe) {
            echo "<p>⚠️ Email {$novo_email} já existe! Atualizando senha...</p>";
            $hash_nova = password_hash($nova_senha, PASSWORD_DEFAULT);
            Database::query("UPDATE usuarios SET senha = ?, nome = ?, tipo = 'admin', ativo = 1 WHERE email = ?", 
                          [$hash_nova, $novo_nome, $novo_email]);
            echo "<p>✅ Usuário atualizado!</p>";
        } else {
            echo "<p>➕ Criando novo usuário...</p>";
            $hash_nova = password_hash($nova_senha, PASSWORD_DEFAULT);
            Database::query("INSERT INTO usuarios (nome, email, senha, tipo, ativo, created_at) VALUES (?, ?, ?, 'admin', 1, NOW())",
                          [$novo_nome, $novo_email, $hash_nova]);
            echo "<p>✅ Novo admin criado!</p>";
        }
        
        // Verificar se funcionou
        $teste_novo = Database::fetch("SELECT senha FROM usuarios WHERE email = ?", [$novo_email]);
        if ($teste_novo && password_verify($nova_senha, $teste_novo['senha'])) {
            echo "<p>✅ <strong>Login funcionando:</strong></p>";
            echo "<p>📧 <strong>Email:</strong> {$novo_email}</p>";
            echo "<p>🔑 <strong>Senha:</strong> {$nova_senha}</p>";
        } else {
            echo "<p>❌ Verificação falhou</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Erro ao criar admin: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
}

// Formulários
echo "<form method='POST' style='margin: 10px 0; padding: 15px; background: #f0f0f0; border-radius: 5px;'>";
echo "<input type='hidden' name='action' value='fix_passwords'>";
echo "<h3>🔧 Corrigir Senhas dos Admins Existentes</h3>";
echo "<p>Vai definir:</p>";
echo "<ul>";
echo "<li><strong>admin@hidroapp.com</strong> → senha: <strong>admin123</strong></li>";
echo "<li><strong>superadmin@hidroapp.com</strong> → senha: <strong>123456</strong></li>";
echo "</ul>";
echo "<button type='submit' style='background: #ff6600; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>🔧 CORRIGIR SENHAS</button>";
echo "</form>";

echo "<form method='POST' style='margin: 10px 0; padding: 15px; background: #f0f8ff; border-radius: 5px;'>";
echo "<input type='hidden' name='action' value='create_new_admin'>";
echo "<h3>👑 Criar/Atualizar Admin Personalizado</h3>";
echo "<p>Nome: <input type='text' name='novo_nome' value='Meu Admin' style='margin-left: 10px; padding: 5px;'></p>";
echo "<p>Email: <input type='email' name='novo_email' value='meuadmin@hidroapp.com' style='margin-left: 10px; padding: 5px;'></p>";
echo "<p>Senha: <input type='text' name='nova_senha' value='minhasenha123' style='margin-left: 10px; padding: 5px;'></p>";
echo "<button type='submit' style='background: #0066cc; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>👑 CRIAR/ATUALIZAR ADMIN</button>";
echo "</form>";

// ============= TESTE MANUAL DE LOGIN =============
echo "<h2>🧪 Teste Manual de Login</h2>";

if ($_POST['test_login'] ?? false) {
    $test_login = $_POST['login_teste'] ?? '';
    $test_password = $_POST['senha_teste'] ?? '';
    
    echo "<div style='background: #fff8dc; padding: 15px; border: 2px solid #daa520; margin: 10px 0;'>";
    echo "<h3>🧪 Testando login...</h3>";
    
    try {
        // Buscar usuário
        $user = Database::fetch(
            "SELECT id, nome, email, senha, tipo, ativo FROM usuarios WHERE (email = ? OR nome = ?) AND ativo = 1",
            [$test_login, $test_login]
        );
        
        if ($user) {
            echo "<p>✅ Usuário encontrado: <strong>{$user['nome']}</strong> ({$user['email']})</p>";
            echo "<p>📝 Tipo: <strong>{$user['tipo']}</strong></p>";
            
            if (password_verify($test_password, $user['senha'])) {
                echo "<p style='color: green; font-size: 18px;'>✅ <strong>SENHA CORRETA! LOGIN FUNCIONARIA!</strong></p>";
                echo "<p>🎉 Você pode fazer login com:</p>";
                echo "<ul>";
                echo "<li><strong>Email/Usuário:</strong> {$test_login}</li>";
                echo "<li><strong>Senha:</strong> {$test_password}</li>";
                echo "</ul>";
            } else {
                echo "<p style='color: red; font-size: 18px;'>❌ <strong>SENHA INCORRETA</strong></p>";
                echo "<p>Hash armazenado: <code style='font-size: 10px;'>" . substr($user['senha'], 0, 50) . "...</code></p>";
                
                // Tentar senhas comuns
                $senhas_comuns = ['admin123', '123456', 'admin', 'password', 'hidroapp'];
                echo "<p>🔍 Testando senhas comuns:</p>";
                foreach ($senhas_comuns as $senha_comum) {
                    if (password_verify($senha_comum, $user['senha'])) {
                        echo "<p style='color: green;'>✅ A senha correta é: <strong>{$senha_comum}</strong></p>";
                        break;
                    }
                }
            }
        } else {
            echo "<p style='color: red;'>❌ Usuário não encontrado com: <strong>{$test_login}</strong></p>";
            
            // Mostrar usuários disponíveis
            $usuarios = Database::fetchAll("SELECT nome, email FROM usuarios WHERE ativo = 1 ORDER BY tipo DESC, nome");
            echo "<p>📋 Usuários disponíveis:</p>";
            echo "<ul>";
            foreach ($usuarios as $u) {
                echo "<li><strong>{$u['nome']}</strong> - {$u['email']}</li>";
            }
            echo "</ul>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Erro no teste: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
}

echo "<form method='POST' style='margin: 10px 0; padding: 15px; background: #f5f5f5; border-radius: 5px;'>";
echo "<input type='hidden' name='test_login' value='1'>";
echo "<h3>🧪 Testar Login Manualmente</h3>";
echo "<p>Login: <input type='text' name='login_teste' placeholder='email ou nome' style='margin-left: 10px; padding: 5px; width: 200px;'></p>";
echo "<p>Senha: <input type='text' name='senha_teste' placeholder='sua senha' style='margin-left: 10px; padding: 5px; width: 200px;'></p>";
echo "<button type='submit' style='background: #8b4513; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>🧪 TESTAR LOGIN</button>";
echo "</form>";

// ============= CREDENCIAIS RESUMO =============
echo "<h2>📋 Resumo de Credenciais</h2>";
echo "<div style='background: #e6ffe6; padding: 15px; border-radius: 5px; border-left: 5px solid #00cc00;'>";
echo "<h3>🔑 Credenciais Disponíveis (após correção):</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr><th>Email/Usuário</th><th>Senha</th><th>Tipo</th></tr>";
echo "<tr><td><strong>admin@hidroapp.com</strong> ou <strong>admin</strong></td><td><strong>admin123</strong></td><td>Admin</td></tr>";
echo "<tr><td><strong>superadmin@hidroapp.com</strong> ou <strong>Super Admin</strong></td><td><strong>123456</strong></td><td>Admin</td></tr>";
echo "</table>";
echo "</div>";

echo "<hr>";
echo "<p><strong>⚠️ IMPORTANTE:</strong> Delete este arquivo após resolver o problema!</p>";
echo "<p><strong>🔗 Acesso:</strong> <a href='login.php'>login.php</a> | <a href='index.php'>index.php</a></p>";
?>