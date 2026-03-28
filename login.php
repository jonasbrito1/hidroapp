<?php
session_start();
require_once 'config.php';
require_once 'db.php';

// Se já estiver logado, redirecionar para dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error_message = '';
$success_message = '';

// Verificar mensagens da URL
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'timeout':
            $error_message = 'Sua sessão expirou. Faça login novamente.';
            break;
        case 'logout':
            $success_message = 'Logout realizado com sucesso.';
            break;
        case 'access_denied':
            $error_message = 'Acesso negado. Faça login para continuar.';
            break;
    }
}

// Processar login
if ($_POST) {
    try {
        $email = sanitize($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        
        if (empty($email) || empty($senha)) {
            $error_message = 'Por favor, preencha todos os campos.';
        } else {
            // Buscar usuário no banco
            $user = Database::fetch(
                "SELECT * FROM usuarios WHERE email = ? AND ativo = 1", 
                [$email]
            );
            
            if ($user && verifyPassword($senha, $user['senha'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['tipo'];
                $_SESSION['last_login'] = date('Y-m-d H:i:s');
                $_SESSION['timeout'] = time() + getUserSessionTimeout($user['tipo']);
                
                // Log do login
                logMessage("Login realizado: {$user['nome']} ({$user['tipo']})", 'INFO', $user['tipo']);
                
                header('Location: index.php');
                exit;
            } else {
                $error_message = 'Email ou senha incorretos.';
                logMessage("Tentativa de login falhada para: {$email}", 'WARNING');
            }
        }
    } catch (Exception $e) {
        $error_message = 'Erro interno. Tente novamente.';
        logMessage('Erro no login: ' . $e->getMessage(), 'ERROR');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HidroApp - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #0066cc, #00b4d8);
            color: white;
            text-align: center;
            padding: 2rem;
        }
        .login-body {
            padding: 2rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #0066cc, #00b4d8);
            border: none;
            width: 100%;
            padding: 12px;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-droplet-fill fs-1 mb-3"></i>
            <h3 class="mb-0">HidroApp</h3>
            <small class="opacity-75">Sistema de Gestão de Manutenções</small>
        </div>
        
        <div class="login-body">
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope me-1"></i>Email
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                           placeholder="seu@email.com" required>
                </div>
                
                <div class="mb-4">
                    <label for="senha" class="form-label">
                        <i class="bi bi-lock me-1"></i>Senha
                    </label>
                    <input type="password" class="form-control" id="senha" name="senha" 
                           placeholder="Digite sua senha" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
                </button>
            </form>

            <hr class="my-4">

            <div class="text-center">
                <small class="text-muted">
                    Desenvolvido por <strong>i9Script Technology</strong><br>
                    © Hidro Evolution 2025
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>