<?php
// ARQUIVO: debug_manutencoes.php
// Execute este arquivo para diagnosticar problemas na aplicação

session_start();
require_once 'config.php';
require_once 'db.php';

echo "<h2>Diagnóstico da Aplicação de Manutenções</h2>";

// 1. Verificar conexão com banco
echo "<h3>1. Conexão com Banco de Dados</h3>";
try {
    $test = Database::fetch("SELECT 1 as test");
    echo "✅ Conexão OK<br>";
} catch (Exception $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "<br>";
}

// 2. Verificar estrutura das tabelas
echo "<h3>2. Estrutura das Tabelas</h3>";

$tables = ['manutencoes', 'manutencao_materiais', 'manutencao_servicos', 'pecas_materiais', 'tipos_manutencao'];

foreach ($tables as $table) {
    try {
        $result = Database::fetchAll("DESCRIBE $table");
        echo "✅ Tabela $table existe (" . count($result) . " colunas)<br>";
        
        // Mostrar algumas colunas importantes
        if ($table === 'manutencoes') {
            $columns = array_column($result, 'Field');
            $required = ['id', 'equipamento_id', 'tipo_manutencao_id', 'created_by', 'custo_total'];
            foreach ($required as $req) {
                if (in_array($req, $columns)) {
                    echo "&nbsp;&nbsp;✅ Coluna $req existe<br>";
                } else {
                    echo "&nbsp;&nbsp;❌ Coluna $req FALTANDO<br>";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Tabela $table: " . $e->getMessage() . "<br>";
    }
}

// 3. Verificar dados básicos
echo "<h3>3. Dados Básicos</h3>";

try {
    $equipamentos = Database::fetch("SELECT COUNT(*) as total FROM equipamentos");
    echo "✅ Equipamentos cadastrados: " . $equipamentos['total'] . "<br>";
} catch (Exception $e) {
    echo "❌ Erro ao contar equipamentos: " . $e->getMessage() . "<br>";
}

try {
    $tipos = Database::fetch("SELECT COUNT(*) as total FROM tipos_manutencao WHERE ativo = 1");
    echo "✅ Tipos de manutenção ativos: " . $tipos['total'] . "<br>";
} catch (Exception $e) {
    echo "❌ Erro ao contar tipos de manutenção: " . $e->getMessage() . "<br>";
}

try {
    $materiais = Database::fetch("SELECT COUNT(*) as total FROM pecas_materiais WHERE ativo = 1");
    echo "✅ Materiais ativos: " . $materiais['total'] . "<br>";
} catch (Exception $e) {
    echo "❌ Erro ao contar materiais: " . $e->getMessage() . "<br>";
}

// 4. Testar consultas específicas
echo "<h3>4. Teste de Consultas</h3>";

try {
    $tipos_test = Database::fetchAll(
        "SELECT id, codigo, nome, categoria FROM tipos_manutencao WHERE ativo = 1 LIMIT 3"
    );
    echo "✅ Query tipos_manutencao OK - " . count($tipos_test) . " registros<br>";
    foreach ($tipos_test as $tipo) {
        echo "&nbsp;&nbsp;- " . $tipo['codigo'] . ": " . $tipo['nome'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro na query tipos_manutencao: " . $e->getMessage() . "<br>";
}

try {
    $materiais_test = Database::fetchAll(
        "SELECT id, codigo, nome, categoria FROM pecas_materiais WHERE ativo = 1 LIMIT 3"
    );
    echo "✅ Query pecas_materiais OK - " . count($materiais_test) . " registros<br>";
    foreach ($materiais_test as $material) {
        echo "&nbsp;&nbsp;- " . $material['codigo'] . ": " . $material['nome'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro na query pecas_materiais: " . $e->getMessage() . "<br>";
}

// 5. Teste do arquivo get_materiais_servicos.php
echo "<h3>5. Teste do Endpoint AJAX</h3>";

echo "Testando get_materiais_servicos.php:<br>";

$test_url_materiais = "get_materiais_servicos.php?type=materiais";
$test_url_servicos = "get_materiais_servicos.php?type=servicos";

echo "URLs de teste:<br>";
echo "- <a href='$test_url_materiais' target='_blank'>$test_url_materiais</a><br>";
echo "- <a href='$test_url_servicos' target='_blank'>$test_url_servicos</a><br>";

// 6. Teste de simulação de POST
echo "<h3>6. Simulação de Dados POST</h3>";

$example_post = [
    'action' => 'create',
    'equipamento_id' => '1',
    'tipo_manutencao_id' => '1',
    'prioridade' => 'media',
    'descricao' => 'Teste de criação de manutenção',
    'status' => 'agendada',
    'materiais' => [
        [
            'id' => '1',
            'quantidade' => '2',
            'observacoes' => 'Material de teste'
        ]
    ],
    'servicos' => [
        [
            'id' => '1',
            'observacoes' => 'Serviço de teste'
        ]
    ]
];

echo "Exemplo de dados POST que devem ser enviados:<br>";
echo "<pre>" . print_r($example_post, true) . "</pre>";

// 7. Verificar permissões
echo "<h3>7. Verificação de Permissões</h3>";

if (isset($_SESSION['user_id'])) {
    echo "✅ Usuário logado: " . $_SESSION['user_name'] . " (" . $_SESSION['user_type'] . ")<br>";
    
    if (function_exists('hasPermission')) {
        $permissions = ['create', 'edit', 'view', 'delete'];
        foreach ($permissions as $perm) {
            $has_perm = hasPermission('manutencoes', $perm);
            echo ($has_perm ? "✅" : "❌") . " Permissão manutencoes.$perm<br>";
        }
    } else {
        echo "❌ Função hasPermission não encontrada<br>";
    }
} else {
    echo "❌ Usuário não está logado<br>";
}

// 8. Verificar arquivos necessários
echo "<h3>8. Arquivos Necessários</h3>";

$files = [
    'config.php',
    'db.php', 
    'user_permissions.php',
    'get_materiais_servicos.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe<br>";
    } else {
        echo "❌ $file FALTANDO<br>";
    }
}

echo "<h3>9. Logs de Erro Recentes</h3>";
echo "Verifique os logs de erro do PHP para mais detalhes sobre falhas.<br>";
echo "Arquivo de log comum: /var/log/apache2/error.log ou /var/log/php_errors.log<br>";

?>