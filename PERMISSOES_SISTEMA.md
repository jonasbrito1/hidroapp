# Sistema de Permissões - HidroApp

## Níveis de Acesso

O HidroApp possui **3 níveis de usuário** com permissões distintas:

1. **Admin (Administrador)**
2. **Técnico**
3. **Usuário**

---

## 1. ADMINISTRADOR (admin)

### Descrição
Acesso completo ao sistema. Pode gerenciar usuários, equipamentos, manutenções e todas as configurações do sistema.

### Badge de Identificação
🔴 **Administrador** (vermelho/danger)

### Timeout de Sessão
⏱️ **2 horas** de inatividade

### Permissões Completas

#### Dashboard
- ✅ `view` - Visualizar dashboard
- ✅ `full_stats` - Estatísticas completas do sistema

#### Equipamentos
- ✅ `view` - Visualizar equipamentos
- ✅ `create` - Criar novos equipamentos
- ✅ `edit` - Editar equipamentos
- ✅ `delete` - Excluir equipamentos
- ✅ `manage` - Gerenciar todos os equipamentos

#### Usuários
- ✅ `view` - Visualizar todos os usuários
- ✅ `create` - Criar novos usuários (admin, técnico, usuário)
- ✅ `edit` - Editar qualquer usuário
- ✅ `delete` - Excluir usuários
- ✅ `manage` - Gerenciar usuários
- ✅ `reset_password` - Redefinir senhas
- ✅ `change_type` - Alterar tipo de usuário
- ✅ `activate` - Ativar usuários
- ✅ `deactivate` - Desativar usuários

#### Manutenções
- ✅ `view` - Visualizar todas as manutenções
- ✅ `create` - Criar manutenções
- ✅ `edit` - Editar qualquer manutenção
- ✅ `delete` - Excluir manutenções
- ✅ `manage` - Gerenciar manutenções

#### Relatórios
- ✅ `view` - Visualizar relatórios
- ✅ `generate` - Gerar relatórios
- ✅ `export` - Exportar relatórios (PDF, Excel)

#### Configurações
- ✅ `view` - Visualizar configurações
- ✅ `edit` - Editar configurações do sistema
- ✅ `edit_profile` - Editar próprio perfil
- ✅ `system_settings` - Alterar configurações do sistema

#### Logs do Sistema
- ✅ `view` - Visualizar logs
- ✅ `delete` - Limpar logs

#### Backup
- ✅ `create` - Criar backups
- ✅ `restore` - Restaurar backups
- ✅ `download` - Baixar backups

### Páginas Acessíveis
- ✅ index.php (Dashboard)
- ✅ equipamentos.php
- ✅ usuarios.php
- ✅ manutencoes.php
- ✅ relatorios.php
- ✅ configuracoes.php
- ✅ logs.php
- ✅ backup.php
- ✅ profile.php
- ✅ register.php
- ✅ user_api.php

### Menu Lateral
1. 📊 Dashboard
2. 💾 Equipamentos
3. 🔧 Manutenções
4. 👥 Usuários
5. 📄 Relatórios
6. ⚙️ Configurações
7. 📋 Logs do Sistema

### Limites e Restrições
- ✅ Usuários criáveis: **Ilimitado**
- ✅ Upload de arquivos: **Ilimitado**
- ✅ Sessões simultâneas: **5**
- ❌ Não pode deletar própria conta
- ❌ Não pode rebaixar próprio nível de acesso

### Visualização de Dados
- **Equipamentos:** Todos (ativos, inativos, em manutenção)
- **Manutenções:** Todas
- **Usuários:** Todos os tipos
- **Relatórios:** Acesso completo

---

## 2. TÉCNICO (tecnico)

### Descrição
Pode gerenciar equipamentos e manutenções, criar usuários comuns e acessar relatórios básicos. Nível intermediário de acesso.

### Badge de Identificação
🔵 **Técnico** (azul/primary)

### Timeout de Sessão
⏱️ **1 hora** de inatividade

### Permissões

#### Dashboard
- ✅ `view` - Visualizar dashboard
- ✅ `basic_stats` - Estatísticas básicas

#### Equipamentos
- ✅ `view` - Visualizar equipamentos
- ✅ `edit` - Editar equipamentos
- ✅ `manage` - Gerenciar equipamentos ativos e em manutenção
- ❌ `create` - Não pode criar equipamentos
- ❌ `delete` - Não pode excluir equipamentos

#### Usuários
- ✅ `view` - Visualizar usuários (apenas os que criou + usuários comuns)
- ✅ `create` - Criar apenas usuários tipo "usuário"
- ✅ `edit` - Editar apenas usuários comuns
- ❌ `delete` - Não pode excluir usuários
- ❌ `manage` - Não pode gerenciar admins/técnicos
- ❌ `reset_password` - Não pode redefinir senhas
- ❌ `change_type` - Não pode alterar tipo de usuário

#### Manutenções
- ✅ `view` - Visualizar manutenções (suas + pendentes)
- ✅ `create` - Criar manutenções
- ✅ `edit` - Editar manutenções
- ✅ `manage` - Gerenciar suas manutenções

#### Relatórios
- ✅ `view` - Visualizar relatórios
- ✅ `generate` - Gerar relatórios básicos
- ❌ `export` - Não pode exportar relatórios

#### Configurações
- ✅ `view` - Visualizar configurações
- ✅ `edit_profile` - Editar próprio perfil
- ❌ `edit` - Não pode editar configurações do sistema
- ❌ `system_settings` - Sem acesso a configurações do sistema

### Páginas Acessíveis
- ✅ index.php (Dashboard)
- ✅ equipamentos.php
- ✅ usuarios.php (limitado)
- ✅ manutencoes.php
- ✅ relatorios.php
- ✅ configuracoes.php (limitado)
- ✅ profile.php
- ✅ register.php (apenas usuário comum)
- ✅ user_api.php
- ❌ logs.php
- ❌ backup.php

### Menu Lateral
1. 📊 Dashboard
2. 💾 Equipamentos
3. 🔧 Manutenções
4. ➕ Usuários (criar)
5. 📄 Relatórios
6. ⚙️ Configurações (perfil)

### Limites e Restrições
- ✅ Usuários criáveis: **Até 50 usuários comuns**
- ✅ Upload de arquivos: **Até 10 arquivos**
- ✅ Sessões simultâneas: **3**
- ❌ Não pode deletar própria conta
- ❌ Não pode rebaixar próprio nível

### Visualização de Dados
- **Equipamentos:** Ativos e em manutenção (não vê inativos)
- **Manutenções:** Suas manutenções + agendadas/em andamento
- **Usuários:** Apenas usuários que criou + usuários comuns
- **Relatórios:** Relatórios básicos

---

## 3. USUÁRIO (usuario)

### Descrição
Visualização de equipamentos ativos e manutenções concluídas. Pode editar apenas seu próprio perfil. Acesso somente leitura.

### Badge de Identificação
⚫ **Usuário** (cinza/secondary)

### Timeout de Sessão
⏱️ **30 minutos** de inatividade

### Permissões

#### Dashboard
- ✅ `view` - Visualizar dashboard básico
- ❌ Sem estatísticas avançadas

#### Equipamentos
- ✅ `view` - Visualizar apenas equipamentos ativos
- ❌ `create` - Não pode criar
- ❌ `edit` - Não pode editar
- ❌ `delete` - Não pode excluir
- ❌ `manage` - Sem permissão de gestão

#### Usuários
- ❌ `view` - Não pode visualizar outros usuários
- ❌ `create` - Não pode criar usuários
- ❌ `edit` - Não pode editar outros
- ❌ Nenhuma permissão de gestão

#### Manutenções
- ✅ `view` - Visualizar apenas manutenções concluídas
- ❌ `create` - Não pode criar
- ❌ `edit` - Não pode editar
- ❌ `manage` - Sem gestão

#### Relatórios
- ✅ `view` - Visualizar relatórios básicos
- ❌ `generate` - Não pode gerar
- ❌ `export` - Não pode exportar

#### Configurações
- ✅ `view` - Visualizar próprio perfil
- ✅ `edit_profile` - Editar apenas próprio perfil
- ❌ Sem acesso a outras configurações

### Páginas Acessíveis
- ✅ index.php (Dashboard básico)
- ✅ equipamentos.php (somente leitura)
- ✅ manutencoes.php (somente concluídas)
- ✅ relatorios.php (limitado)
- ✅ configuracoes.php (apenas perfil)
- ✅ profile.php
- ❌ usuarios.php
- ❌ register.php
- ❌ user_api.php
- ❌ logs.php
- ❌ backup.php

### Menu Lateral
1. 📊 Dashboard
2. 👁️ Equipamentos (visualização)
3. 🔍 Manutenções (visualização)
4. ⚙️ Configurações (perfil)

### Limites e Restrições
- ❌ Usuários criáveis: **0**
- ✅ Upload de arquivos: **Até 5 arquivos**
- ✅ Sessões simultâneas: **2**
- ❌ Não pode deletar própria conta
- ❌ Sem permissões administrativas

### Visualização de Dados
- **Equipamentos:** Apenas ativos
- **Manutenções:** Apenas concluídas
- **Usuários:** Nenhum
- **Relatórios:** Consulta básica

---

## Comparativo de Permissões

| Funcionalidade | Admin | Técnico | Usuário |
|----------------|-------|---------|---------|
| **Dashboard Completo** | ✅ | ✅ | ⚠️ Básico |
| **Criar Equipamentos** | ✅ | ❌ | ❌ |
| **Editar Equipamentos** | ✅ | ✅ | ❌ |
| **Excluir Equipamentos** | ✅ | ❌ | ❌ |
| **Ver Todos Equipamentos** | ✅ | ⚠️ Parcial | ⚠️ Só ativos |
| **Criar Manutenções** | ✅ | ✅ | ❌ |
| **Editar Manutenções** | ✅ | ✅ | ❌ |
| **Ver Todas Manutenções** | ✅ | ⚠️ Suas + pendentes | ⚠️ Só concluídas |
| **Criar Usuários Admin** | ✅ | ❌ | ❌ |
| **Criar Usuários Técnicos** | ✅ | ❌ | ❌ |
| **Criar Usuários Comuns** | ✅ | ✅ | ❌ |
| **Gerenciar Usuários** | ✅ | ⚠️ Limitado | ❌ |
| **Redefinir Senhas** | ✅ | ❌ | ❌ |
| **Alterar Tipo Usuário** | ✅ | ❌ | ❌ |
| **Gerar Relatórios** | ✅ | ✅ | ❌ |
| **Exportar Relatórios** | ✅ | ❌ | ❌ |
| **Ver Logs Sistema** | ✅ | ❌ | ❌ |
| **Fazer Backup** | ✅ | ❌ | ❌ |
| **Configurações Sistema** | ✅ | ❌ | ❌ |
| **Editar Próprio Perfil** | ✅ | ✅ | ✅ |
| **Timeout Sessão** | 2h | 1h | 30min |
| **Sessões Simultâneas** | 5 | 3 | 2 |

---

## Regras de Negócio

### Criação de Usuários

1. **Admin pode criar:**
   - ✅ Outros administradores
   - ✅ Técnicos
   - ✅ Usuários comuns

2. **Técnico pode criar:**
   - ❌ Administradores
   - ❌ Outros técnicos
   - ✅ Apenas usuários comuns (até 50)

3. **Usuário não pode criar:**
   - ❌ Nenhum tipo de usuário

### Edição de Usuários

1. **Admin pode editar:**
   - ✅ Qualquer usuário do sistema
   - ✅ Alterar tipo de usuário
   - ✅ Redefinir senhas
   - ✅ Ativar/desativar usuários

2. **Técnico pode editar:**
   - ✅ Usuários comuns que criou
   - ✅ Outros usuários comuns
   - ❌ Não pode alterar tipo
   - ❌ Não pode redefinir senhas
   - ❌ Não pode editar admins/técnicos

3. **Usuário pode editar:**
   - ✅ Apenas seu próprio perfil
   - ❌ Não pode alterar próprio tipo

### Gestão de Equipamentos

1. **Admin:**
   - Vê todos os equipamentos (ativos, inativos, manutenção)
   - Pode criar, editar e excluir

2. **Técnico:**
   - Vê equipamentos ativos e em manutenção
   - Pode editar mas não excluir
   - Não pode criar novos

3. **Usuário:**
   - Vê apenas equipamentos ativos
   - Somente visualização

### Gestão de Manutenções

1. **Admin:**
   - Vê todas as manutenções
   - Pode criar, editar, excluir

2. **Técnico:**
   - Vê suas manutenções
   - Vê manutenções agendadas e em andamento
   - Pode criar e editar
   - Pode atribuir para si mesmo

3. **Usuário:**
   - Vê apenas manutenções concluídas
   - Somente visualização
   - Não pode interagir

---

## Configurações de Segurança

### Timeouts de Sessão

```php
Admin:    2 horas (7200 segundos)
Técnico:  1 hora  (3600 segundos)
Usuário:  30 min  (1800 segundos)
```

### Verificações de Segurança

- ✅ **Verificação de IP:** Opcional por tipo de usuário
- ✅ **Verificação de usuário ativo:** A cada requisição
- ✅ **Rate Limiting:** 100 requisições por minuto
- ✅ **CSRF Protection:** Em todos os formulários
- ✅ **XSS Protection:** Headers de segurança
- ✅ **SQL Injection:** Prepared statements
- ✅ **Session Hijacking:** Token de sessão

### Logs de Auditoria

Todas as ações são registradas:
- ✅ Login/Logout
- ✅ Alterações em usuários
- ✅ Criação/edição de equipamentos
- ✅ Criação/edição de manutenções
- ✅ Tentativas de acesso negado
- ✅ Alterações de senha
- ✅ Mudanças de configuração

---

## Dashboard Personalizado

### Admin
- 📊 Estatísticas completas
- 👥 Todos os usuários
- 💻 Informações do sistema
- 📈 Atividades recentes
- 📉 Gráficos completos
- 🔄 Atualização: 30 segundos

### Técnico
- 📊 Estatísticas básicas
- 🔧 Suas manutenções
- 📈 Atividades recentes
- 📉 Gráficos básicos
- 🔄 Atualização: 1 minuto

### Usuário
- 📋 Dashboard simples
- 👁️ Visualização básica
- 🔄 Atualização: 2 minutos

---

## Como Verificar Permissões no Código

### Verificação por Módulo e Ação
```php
// Verificar se pode editar equipamentos
if (hasPermission('equipamentos', 'edit')) {
    // Mostrar botão de editar
}

// Verificar se pode criar usuários
if (hasPermission('usuarios', 'create')) {
    // Mostrar formulário de cadastro
}
```

### Verificação de Tipo de Usuário
```php
// Verificar se é admin
if (isAdmin()) {
    // Código específico para admin
}

// Verificar se é técnico
if (isTecnico()) {
    // Código específico para técnico
}

// Verificar se é usuário comum
if (isUsuario()) {
    // Código específico para usuário
}
```

### Proteger Páginas
```php
// Proteger página para admin apenas
require_once 'session_middleware.php';
if (!requireAdminAccess('configuracoes.php')) {
    exit;
}

// Proteger para técnico ou admin
if (!requireTechnicianAccess('manutencoes.php')) {
    exit;
}
```

### Verificar Permissão Específica
```php
// Requerer permissão específica
requirePermission('equipamentos', 'delete');

// Verificar se pode gerenciar usuário específico
if (canManageUser('usuario', 'edit')) {
    // Permitir edição
}
```

---

## Credenciais Padrão

### Usuário Administrador
- **Email:** admin@hidroapp.com
- **Senha:** password
- **Tipo:** Administrador
- **Ativo:** Sim

**⚠️ IMPORTANTE:** Altere a senha padrão no primeiro acesso!

---

## Fluxo de Autorização

```
1. Usuário faz login
   ↓
2. Sistema valida credenciais
   ↓
3. Cria sessão com tipo de usuário
   ↓
4. A cada requisição:
   - Valida sessão
   - Verifica timeout
   - Verifica se usuário está ativo
   - Verifica permissão da página
   - Verifica permissão da ação
   ↓
5. Se tudo OK: Permite acesso
   Se falhar: Redireciona ou bloqueia
```

---

## Estrutura de Dados de Sessão

```php
$_SESSION = [
    'user_id' => 1,
    'user_name' => 'Admin',
    'user_email' => 'admin@hidroapp.com',
    'user_type' => 'admin',
    'login_time' => 1634567890,
    'last_activity' => 1634567890,
    'timeout' => 1634575090,
    'login_ip' => '192.168.1.1',
    'user_theme' => 'light',
    'pagination_limit' => 20
];
```

---

**Última Atualização:** 2025-10-17
**Versão do Sistema:** 1.0
**Autor:** HidroApp Development Team
