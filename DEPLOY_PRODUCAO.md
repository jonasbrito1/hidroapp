# HidroApp - Guia Completo de Deploy para Produção (Hostinger)

## 📅 Data: 17/10/2025

---

## ✅ PRÉ-REQUISITOS

Antes de iniciar o deploy, certifique-se de ter:

- [x] Acesso ao painel da Hostinger
- [x] Credenciais do banco de dados MySQL
- [x] Acesso FTP ou Gerenciador de Arquivos
- [x] Backup do banco de dados de desenvolvimento (se necessário)

### Informações do Banco de Dados (Hostinger)

```
Host: localhost
Database: u674882802_hidroapp
User: u674882802_hidro
Password: rA+3d~GfH
Port: 3306
```

---

## 📂 ARQUIVOS PARA ENVIAR

### 1. Arquivos PHP Principais (Raiz)

```
✅ index.php
✅ login.php
✅ logout.php
✅ register.php
✅ config.php
✅ db.php (usar db_host.php em produção)
✅ user_permissions.php
✅ traducoes_ptbr.php
✅ session_middleware.php
✅ relatorios.php          ← NOVO
✅ relatorios_api.php      ← NOVO
✅ equipamentos.php
✅ manutencoes.php
✅ configuracoes.php
✅ logs.php
✅ error.php
✅ .htaccess               ← NOVO (otimizado)
```

### 2. Diretório `assets/`

```
assets/
├── css/
│   └── responsive.css     ← NOVO
├── js/
│   └── responsive.js      ← NOVO
└── img/
    └── (suas imagens)
```

### 3. Diretório `vendor/` (Dependências Composer)

```
vendor/
├── autoload.php
├── mpdf/                  ← Para geração de PDF
└── (outras dependências)
```

### 4. Diretórios de Upload e Logs

```
uploads/           (criar com permissão 755)
logs/             (criar com permissão 755)
backups/          (criar com permissão 755)
temp/             (criar com permissão 755)
```

### 5. Documentação (Opcional - NÃO enviar)

```
❌ README.md
❌ RESPONSIVIDADE_E_RELATORIOS.md
❌ RESUMO_MELHORIAS_RESPONSIVIDADE.md
❌ GUIA_RAPIDO_IMPLEMENTACAO.md
❌ DEPLOY_PRODUCAO.md (este arquivo)
❌ VERIFICACAO_FINAL.md
❌ *.sql (arquivos de desenvolvimento)
❌ db_local.php
❌ docker-compose.yml
❌ Dockerfile
```

---

## 🚀 PASSO A PASSO DO DEPLOY

### **PASSO 1: Preparar Banco de Dados**

#### 1.1. Acessar phpMyAdmin da Hostinger

```
URL: https://seudominio.com:2083/cpsess.../phpMyAdmin
```

#### 1.2. Selecionar o banco `u674882802_hidroapp`

#### 1.3. Importar a estrutura do banco

Executar o arquivo `init_complete.sql` ou executar manualmente:

```sql
-- Já existe o banco, apenas verificar se tem as 15 tabelas
SHOW TABLES;

-- Deve retornar:
-- contratos
-- equipamento_fotos
-- equipamento_materiais
-- equipamentos
-- fotos_equipamento
-- fotos_manutencao
-- manutencao_fotos
-- manutencao_materiais
-- manutencao_pecas
-- manutencao_servicos
-- manutencoes
-- pecas_materiais
-- tecnicos
-- tipos_manutencao
-- usuarios
```

#### 1.4. Inserir dados iniciais

Executar os scripts na ordem:

```sql
-- 1. Tipos de manutenção (se não tiver)
SOURCE inserir_tipos_manutencao.sql;

-- 2. Peças e materiais (se não tiver)
-- Execute o script de peças

-- 3. Equipamentos (se não tiver)
SOURCE insert_equipamentos.sql;

-- 4. Usuário admin (se não tiver)
INSERT INTO usuarios (nome, email, senha, tipo, ativo, created_at)
VALUES ('Administrador', 'admin@hidroapp.com', '$2y$10$...hash...', 'admin', 1, NOW());
```

---

### **PASSO 2: Configurar Arquivos**

#### 2.1. Editar `config.php`

Alterar estas linhas:

```php
// MODO DE PRODUÇÃO
define('DEBUG_MODE', false);  // ← IMPORTANTE: false em produção
define('ENVIRONMENT', 'production');

// URLs
define('BASE_URL', 'https://seudominio.com');
define('SITE_NAME', 'HidroApp - Hidro Evolution');

// Banco de dados - já está configurado no db.php
```

#### 2.2. Verificar `db.php`

O arquivo `db.php` já está configurado para usar as variáveis de ambiente ou valores padrão da Hostinger:

```php
$db_config = [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'name' => $_ENV['DB_NAME'] ?? 'u674882802_hidroapp',
    'user' => $_ENV['DB_USER'] ?? 'u674882802_hidro',
    'password' => $_ENV['DB_PASSWORD'] ?? 'rA+3d~GfH',
    'charset' => 'utf8mb4'
];
```

✅ **Está pronto para produção!**

---

### **PASSO 3: Enviar Arquivos via FTP**

#### 3.1. Conectar via FTP

```
Host: ftp.seudominio.com (ou IP da Hostinger)
User: seu_usuario_ftp
Password: sua_senha_ftp
Port: 21 (ou 22 para SFTP)
```

#### 3.2. Navegar até o diretório `public_html/`

#### 3.3. Enviar arquivos

**Método 1: FTP Client (FileZilla, WinSCP)**

1. Conectar ao servidor
2. Arrastar todos os arquivos para `public_html/`
3. Aguardar upload completar

**Método 2: Gerenciador de Arquivos da Hostinger**

1. Acessar painel Hostinger
2. Ir em "Gerenciador de Arquivos"
3. Navegar até `public_html/`
4. Fazer upload dos arquivos
5. Extrair se enviou em ZIP

---

### **PASSO 4: Configurar Permissões**

Executar via SSH ou Gerenciador de Arquivos:

```bash
# Permissões dos diretórios
chmod 755 public_html
chmod 755 assets
chmod 755 assets/css
chmod 755 assets/js
chmod 755 assets/img
chmod 755 vendor

# Permissões de upload e logs (leitura/escrita)
chmod 777 uploads
chmod 777 logs
chmod 777 backups
chmod 777 temp

# Permissões dos arquivos PHP
chmod 644 *.php
chmod 644 .htaccess

# Proteger arquivo de configuração
chmod 600 config.php
chmod 600 db.php
```

---

### **PASSO 5: Testar o Sistema**

#### 5.1. Acessar o site

```
https://seudominio.com
```

#### 5.2. Fazer login

```
Email: admin@hidroapp.com
Senha: admin123
```

✅ **Se logou com sucesso, sistema está funcionando!**

#### 5.3. Testar funcionalidades

- [ ] Dashboard carrega corretamente
- [ ] Sidebar funciona (abrir/fechar)
- [ ] Equipamentos - listar, criar, editar
- [ ] Manutenções - listar, criar, editar
- [ ] Relatórios - gerar, visualizar, exportar
- [ ] Responsividade - testar em mobile
- [ ] Logout funciona

---

### **PASSO 6: Configurações de Segurança**

#### 6.1. Alterar senha do admin

```sql
UPDATE usuarios
SET senha = '$2y$10$NOVA_SENHA_HASH'
WHERE email = 'admin@hidroapp.com';
```

Ou fazer via interface após login.

#### 6.2. Ativar SSL (HTTPS)

No painel da Hostinger:

1. Ir em "SSL"
2. Ativar "Let's Encrypt SSL"
3. Aguardar ativação (1-2 minutos)

Depois, descomentar no `.htaccess`:

```apache
# Forçar HTTPS
RewriteCond %{HTTPS} !=on
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
```

#### 6.3. Proteger arquivos sensíveis

Criar arquivo `.htaccess` na raiz com o conteúdo fornecido.

✅ **Já criado!**

---

### **PASSO 7: Otimizações Finais**

#### 7.1. Habilitar cache do PHP (OPcache)

No `php.ini` ou via painel Hostinger:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

#### 7.2. Configurar cronjob para limpeza de logs

```bash
# Executar todo dia à meia-noite
0 0 * * * php /home/seu_usuario/public_html/cleanup_logs.php
```

#### 7.3. Criar arquivo `robots.txt`

```
User-agent: *
Disallow: /vendor/
Disallow: /logs/
Disallow: /backups/
Disallow: /uploads/
Disallow: /temp/
Allow: /

Sitemap: https://seudominio.com/sitemap.xml
```

---

## 📋 CHECKLIST FINAL

Antes de declarar o sistema em produção, verifique:

### Banco de Dados
- [ ] Todas as 15 tabelas criadas
- [ ] Charset utf8mb4_unicode_ci em todas as tabelas
- [ ] Usuário admin criado
- [ ] Tipos de manutenção inseridos (39 tipos)
- [ ] Equipamentos iniciais cadastrados
- [ ] Materiais e peças cadastrados

### Arquivos
- [ ] Todos os arquivos PHP enviados
- [ ] Diretório `assets/` completo
- [ ] Diretório `vendor/` completo
- [ ] `.htaccess` configurado
- [ ] Permissões corretas nos diretórios

### Configurações
- [ ] `DEBUG_MODE = false` no config.php
- [ ] Conexão com banco funcionando
- [ ] SSL ativado (HTTPS)
- [ ] Senha do admin alterada
- [ ] Timezone correto (America/Sao_Paulo)

### Funcionalidades
- [ ] Login funciona
- [ ] Dashboard carrega
- [ ] CRUD de Equipamentos funciona
- [ ] CRUD de Manutenções funciona
- [ ] Relatórios geram corretamente
- [ ] Exportação PDF funciona
- [ ] Exportação Excel/CSV funciona
- [ ] Responsividade mobile OK
- [ ] Permissões de usuários funcionando

### Segurança
- [ ] Arquivos sensíveis protegidos
- [ ] SQL Injection protegido
- [ ] XSS protegido
- [ ] CSRF tokens implementados
- [ ] Sessões seguras
- [ ] Logs funcionando

### Performance
- [ ] Gzip habilitado
- [ ] Cache do navegador configurado
- [ ] OPcache ativado
- [ ] Imagens otimizadas
- [ ] CSS e JS minificados (opcional)

---

## 🔧 TROUBLESHOOTING

### Erro: "Erro na conexão com o banco de dados"

**Solução:**

1. Verificar credenciais no `db.php`
2. Verificar se o host é `localhost` (não IP)
3. Testar conexão via phpMyAdmin

### Erro: "Internal Server Error 500"

**Solução:**

1. Verificar logs de erro PHP: `logs/php_errors.log`
2. Verificar permissões dos diretórios
3. Verificar sintaxe do `.htaccess`
4. Ativar `display_errors` temporariamente

```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Erro: "Page not found 404"

**Solução:**

1. Verificar se `index.php` está na raiz de `public_html/`
2. Verificar se `.htaccess` está presente
3. Verificar rewrite rules

### CSS/JS não carrega

**Solução:**

1. Verificar caminho dos arquivos: `assets/css/`, `assets/js/`
2. Verificar permissões: `chmod 755 assets`
3. Limpar cache do navegador
4. Verificar console do navegador (F12)

### Relatórios não geram PDF

**Solução:**

1. Verificar se `vendor/mpdf/` existe
2. Executar: `composer require mpdf/mpdf`
3. Verificar permissão do diretório `temp/`
4. Verificar logs: `logs/php_errors.log`

### Sidebar não abre no mobile

**Solução:**

1. Verificar se `assets/js/responsive.js` foi enviado
2. Verificar console do navegador (F12)
3. Limpar cache
4. Testar em modo anônimo

---

## 📊 MONITORAMENTO PÓS-DEPLOY

### Logs a Monitorar

```bash
# Logs de erro PHP
tail -f logs/php_errors.log

# Logs do sistema
tail -f logs/system.log

# Logs de acesso Apache (Hostinger)
tail -f ~/access_log
```

### Métricas de Performance

Usar ferramentas:

- **GTmetrix**: https://gtmetrix.com
- **PageSpeed Insights**: https://pagespeed.web.dev
- **Pingdom**: https://tools.pingdom.com

**Metas:**

- Load Time: < 3s
- Performance Score: > 90
- Mobile Score: > 85

---

## 🔄 BACKUP E MANUTENÇÃO

### Backup Automático

Criar script PHP `backup.php`:

```php
<?php
// Backup do banco de dados
$filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
exec("mysqldump -u u674882802_hidro -p'rA+3d~GfH' u674882802_hidroapp > backups/$filename");

// Compactar
exec("gzip backups/$filename");

// Remover backups antigos (> 30 dias)
exec("find backups/ -name '*.gz' -mtime +30 -delete");
?>
```

Adicionar ao cronjob:

```bash
0 2 * * * php /home/seu_usuario/public_html/backup.php
```

### Atualização do Sistema

1. Fazer backup completo
2. Testar em desenvolvimento
3. Enviar novos arquivos
4. Executar migrations (se houver)
5. Testar funcionalidades
6. Monitorar logs

---

## 📞 SUPORTE

### Contatos

**Hostinger:**
- Suporte 24/7: https://www.hostinger.com.br/suporte
- Chat online
- Tickets

**Desenvolvedor:**
- Email: suporte@i9script.com
- Site: https://i9script.com

---

## 🎉 CONCLUSÃO

Seguindo este guia, seu sistema HidroApp estará:

✅ **Funcionando em produção**
✅ **Seguro e otimizado**
✅ **Pronto para uso real**
✅ **Monitorado e com backup**

**Próximos passos:**

1. Treinar usuários
2. Cadastrar equipamentos reais
3. Iniciar registros de manutenção
4. Monitorar performance
5. Coletar feedback dos usuários

---

**Sistema HidroApp v2.0.0**
**Status:** ✅ Pronto para Produção
**Data de Deploy:** __/__/____

---

© Hidro Evolution 2025 - Desenvolvido por [i9Script Technology](https://i9script.com)
