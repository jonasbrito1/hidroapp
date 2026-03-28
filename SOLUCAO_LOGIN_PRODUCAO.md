# 🔧 Solução para Problema de Login em Produção

**Data:** 17/10/2025
**Sistema:** HidroApp - Gestão de Manutenções
**Problema:** Não consegue acessar com admin@hidroapp.com e senha admin123 em produção

---

## 📋 Diagnóstico do Problema

O problema ocorre devido a uma ou mais das seguintes causas:

1. **Usuário admin não existe** no banco de dados de produção
2. **Senha hash incorreta** armazenada no banco
3. **Usuário está inativo** (campo `ativo = 0`)
4. **Configuração de banco errada** (apontando para banco local em vez de produção)

---

## ✅ SOLUÇÃO RÁPIDA (Recomendada)

### Passo 1: Fazer Upload do Script de Diagnóstico

Faça upload do arquivo **`check_admin.php`** para o servidor Hostinger (mesmo diretório do index.php).

### Passo 2: Acessar o Script no Navegador

Acesse no navegador:
```
https://seudominio.com.br/check_admin.php
```

### Passo 3: Seguir as Instruções na Tela

O script vai:
- ✅ Testar conexão com o banco de dados
- ✅ Verificar se a tabela `usuarios` existe
- ✅ Listar todos os usuários cadastrados
- ✅ Verificar se o admin existe
- ✅ Testar se a senha está correta
- ✅ Oferecer botões para corrigir automaticamente:
  - **Criar usuário admin** (se não existir)
  - **Resetar senha para admin123** (se estiver errada)
  - **Ativar usuário** (se estiver inativo)

### Passo 4: Testar Login

Após o script corrigir o problema, acesse:
```
https://seudominio.com.br/login.php
```

**Credenciais:**
- Email: `admin@hidroapp.com`
- Senha: `admin123`

### Passo 5: SEGURANÇA - DELETAR O SCRIPT

⚠️ **MUITO IMPORTANTE!**

Após resolver o problema, **DELETE imediatamente** o arquivo `check_admin.php` do servidor, pois ele contém informações sensíveis e pode ser usado por terceiros.

```bash
# Via FTP ou File Manager do Hostinger, delete:
check_admin.php
```

---

## 🔧 SOLUÇÃO ALTERNATIVA (Manual via SQL)

Se preferir fazer manualmente pelo phpMyAdmin do Hostinger:

### 1. Acessar phpMyAdmin

1. Entre no painel do Hostinger
2. Vá em **Databases** → **phpMyAdmin**
3. Selecione o banco: `u674882802_hidroapp`

### 2. Verificar se Usuário Existe

Execute a query:
```sql
SELECT * FROM usuarios WHERE email = 'admin@hidroapp.com';
```

### 3A. Se o Usuário NÃO EXISTE - Criar

Execute a query:
```sql
INSERT INTO usuarios (nome, email, senha, tipo, ativo, created_at)
VALUES (
    'Administrador',
    'admin@hidroapp.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    1,
    NOW()
);
```

> **Nota:** Este hash corresponde à senha `admin123`

### 3B. Se o Usuário EXISTE mas Senha Errada - Resetar

Execute a query:
```sql
UPDATE usuarios
SET senha = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    updated_at = NOW()
WHERE email = 'admin@hidroapp.com';
```

### 3C. Se o Usuário Está Inativo - Ativar

Execute a query:
```sql
UPDATE usuarios
SET ativo = 1,
    updated_at = NOW()
WHERE email = 'admin@hidroapp.com';
```

---

## 🔍 Verificação Adicional - Configuração do Banco

### Arquivo: config.php

Certifique-se de que o [config.php](config.php:7) tem `DEBUG_MODE = false` em produção:

```php
define('DEBUG_MODE', false);  // ← DEVE SER false EM PRODUÇÃO
```

### Arquivo: db.php

Verifique se as configurações estão corretas (linhas 1-10):

```php
$db_config = [
    'host' => 'localhost',                          // ✅ Correto para Hostinger
    'name' => 'u674882802_hidroapp',                // ✅ Seu banco
    'user' => 'u674882802_hidro',                   // ✅ Seu usuário
    'password' => 'rA+3d~GfH',                      // ✅ Sua senha
    'charset' => 'utf8mb4'                          // ✅ Correto
];
```

---

## 📊 Hash de Senha Padrão

Para referência futura, aqui está o hash correto para a senha `admin123`:

```
$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
```

Este hash pode ser usado diretamente em queries SQL quando necessário.

---

## 🛡️ Recomendações de Segurança Pós-Login

Após conseguir fazer login com sucesso:

### 1. Alterar Senha Padrão

1. Faça login com `admin@hidroapp.com` / `admin123`
2. Vá em **Configurações** ou **Meu Perfil**
3. Altere a senha para uma senha forte:
   - Mínimo 8 caracteres
   - Letras maiúsculas e minúsculas
   - Números
   - Caracteres especiais

### 2. Verificar Configurações de Produção

Certifique-se de que estas configurações estão corretas:

**config.php:**
```php
define('DEBUG_MODE', false);        // ❌ Nunca true em produção
define('LOG_ERRORS', true);         // ✅ Manter logs
define('MAINTENANCE_MODE', false);  // ✅ Sistema em operação
```

### 3. Deletar Arquivos de Debug

Delete do servidor os seguintes arquivos (se existirem):
```
check_admin.php          ← CRIADO AGORA - DELETAR!
debug_login.php
debug_manutencoes.php
debug_mdf.php
SOLUCAO_LOGIN_PRODUCAO.md  ← Este arquivo também
```

### 4. Configurar .htaccess

Certifique-se de que o arquivo [.htaccess](.htaccess) está no servidor e protegendo arquivos sensíveis.

---

## ✅ Checklist Final

- [ ] Upload do `check_admin.php` para o servidor
- [ ] Executar o script no navegador
- [ ] Corrigir problema usando os botões do script
- [ ] Testar login com admin@hidroapp.com / admin123
- [ ] **DELETAR** `check_admin.php` do servidor
- [ ] Alterar senha padrão após primeiro login
- [ ] Verificar `DEBUG_MODE = false` no config.php
- [ ] Deletar arquivos de debug do servidor
- [ ] Testar funcionalidades do sistema

---

## 📞 Suporte Adicional

Se o problema persistir após seguir todos os passos:

1. **Verificar logs do servidor:**
   - Hostinger Panel → Files → logs/
   - Procurar por erros em `hidroapp.log`

2. **Verificar logs do PHP:**
   - Hostinger Panel → Advanced → Error Logs
   - Procurar por erros relacionados a PDO/Database

3. **Testar conexão do banco:**
   - Criar arquivo `test_connection.php`:
   ```php
   <?php
   try {
       $pdo = new PDO(
           'mysql:host=localhost;dbname=u674882802_hidroapp;charset=utf8mb4',
           'u674882802_hidro',
           'rA+3d~GfH'
       );
       echo "✅ Conexão OK!";
   } catch (Exception $e) {
       echo "❌ Erro: " . $e->getMessage();
   }
   ?>
   ```
   - Acessar no navegador
   - **Deletar após testar**

---

## 📄 Informações Técnicas

**Banco de Dados:**
- Host: `localhost`
- Database: `u674882802_hidroapp`
- User: `u674882802_hidro`
- Password: `rA+3d~GfH`
- Charset: `utf8mb4`

**Usuário Admin Padrão:**
- Nome: `Administrador`
- Email: `admin@hidroapp.com`
- Senha: `admin123`
- Tipo: `admin`
- Ativo: `1` (sim)

**Hash da Senha (admin123):**
```
$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
```

---

**Desenvolvido por:** i9Script Technology
**Sistema:** HidroApp v1.0.0
**© Hidro Evolution 2025**
