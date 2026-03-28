# HidroApp - Sistema Configurado e Rodando

## Status: ONLINE E OPERACIONAL

---

## Acesso ao Sistema

### Aplicação Web
- **URL:** http://localhost:8085
- **Status:** Funcionando
- **Redirecionamento:** Automático para página de login

### phpMyAdmin (Gerenciamento do Banco)
- **URL:** http://localhost:8889
- **Usuário:** `root`
- **Senha:** `hidroapp123`
- **Status:** Funcionando

### Banco de Dados MySQL
- **Host:** `localhost`
- **Porta:** `3310`
- **Database:** `hidroapp`
- **Charset:** `utf8mb4_unicode_ci`

#### Credenciais:
- **Root:**
  - Usuário: `root`
  - Senha: `hidroapp123`
- **Usuário da Aplicação:**
  - Usuário: `hidroapp_user`
  - Senha: `hidroapp123`

---

## Credenciais de Acesso ao Sistema

### Usuário Administrador Padrão
- **Email:** `admin@hidroapp.com`
- **Senha:** `password`
- **Tipo:** Administrador (acesso completo)

---

## Containers Docker Ativos

| Container | Serviço | Porta Externa | Porta Interna | Status |
|-----------|---------|---------------|---------------|--------|
| hidroapp-web-1 | Apache/PHP 8.1 | 8085 | 80 | UP |
| hidroapp-db-1 | MySQL 8.0 | 3310 | 3306 | UP |
| hidroapp-phpmyadmin-1 | phpMyAdmin | 8889 | 80 | UP |

---

## Estrutura do Banco de Dados

### Tabelas Criadas (15 tabelas):

1. **usuarios** - Gestão de usuários do sistema
   - Campos: id, nome, email, senha, tipo, ativo, created_at, updated_at, deleted_at, telefone, cpf, endereco, observacoes, created_by, last_login, last_logout
   - Tipos de usuário: admin, tecnico, usuario

2. **tecnicos** - Perfil dos técnicos
   - Campos: id, usuario_id, nome, telefone, especialidade, ativo, created_at

3. **equipamentos** - Cadastro de bebedouros e duchas
   - Campos: id, codigo, tipo, localizacao, endereco, latitude, longitude, marca, modelo, data_instalacao, status, observacoes, created_at, updated_at, photo_path, google_maps_url

4. **tipos_manutencao** - Tipos de serviços de manutenção
   - Campos: id, codigo, nome, categoria, descricao, periodicidade_dias, ativo, created_at, updated_at, tipo_equipamento, tempo_estimado, prioridade_default
   - Categorias: limpeza, manutencao, instalacao, inspecao, reparo, outro

5. **pecas_materiais** - Inventário de peças e materiais
   - Campos: id, nome, codigo, unidade, preco_unitario, estoque_minimo, ativo, created_at, updated_at, categoria, unidade_medida
   - Categorias: filtro, peca, consumivel, ferramenta, quimico, outro

6. **manutencoes** - Registro de manutenções
   - Campos: id, equipamento_id, tecnico_id, tipo_manutencao_id, data_agendada, data_realizada, status, tipo, problema_relatado, solucao_aplicada, observacoes, custo_total, tempo_execucao, created_at, updated_at, created_by, prioridade, descricao, data_inicio
   - Status: agendada, em_andamento, concluida, cancelada
   - Tipos: preventiva, corretiva

7. **manutencao_pecas** - Peças utilizadas nas manutenções
   - Campos: id, manutencao_id, peca_id, quantidade, preco_unitario, created_at

8. **manutencao_materiais** - Materiais planejados/utilizados
   - Campos: id, manutencao_id, material_id, quantidade_prevista, quantidade_utilizada, preco_unitario, observacoes, created_at

9. **manutencao_servicos** - Serviços executados na manutenção
   - Campos: id, manutencao_id, tipo_manutencao_id, quantidade, tempo_gasto, observacoes, executado, executado_por, created_at

10. **fotos_manutencao** - Fotos das manutenções (estrutura antiga)
    - Campos: id, manutencao_id, nome_arquivo, caminho, momento, descricao, created_at
    - Momentos: antes, durante, depois

11. **manutencao_fotos** - Fotos das manutenções (estrutura nova)
    - Campos: id, manutencao_id, tipo_foto, nome_arquivo, caminho_arquivo, descricao, data_upload, uploaded_by, ordem, ativo
    - Tipos: antes, durante, depois, problema, solucao, outro

12. **fotos_equipamento** - Fotos dos equipamentos (estrutura antiga)
    - Campos: id, equipamento_id, nome_arquivo, caminho, descricao, created_at

13. **equipamento_fotos** - Fotos dos equipamentos (estrutura nova)
    - Campos: id, equipamento_id, tipo_foto, nome_arquivo, caminho_arquivo, descricao, data_upload, uploaded_by, ativo
    - Tipos: geral, detalhes, problema, localizacao, outro

14. **equipamento_materiais** - Materiais associados a equipamentos
    - Campos: id, equipamento_id, material_id, quantidade, observacoes, created_at

15. **contratos** - Gestão de contratos
    - Campos: id, numero_contrato, cliente, descricao_servicos, data_inicio, data_fim, ativo, created_at

---

## Dados Iniciais Inseridos

### Tipos de Manutenção (7 registros):
1. Limpeza Geral
2. Troca de Filtro
3. Verificação Elétrica
4. Manutenção Preventiva Completa
5. Reparo Corretivo
6. Limpeza de Bebedouro
7. Manutenção de Ducha

### Peças e Materiais (12 registros):
1. Filtro de Água Padrão (FLT-001)
2. Filtro de Carvão Ativado (FLT-002)
3. Torneira Cromada (PCA-001)
4. Mangueira 1/2" (PCA-002)
5. Vedação de Borracha (PCA-003)
6. Resistência 1500W (PCA-004)
7. Detergente Neutro (CON-001)
8. Álcool 70% (CON-002)
9. Pano de Limpeza (CON-003)
10. Chave de Fenda (FER-001)
11. Alicate Universal (FER-002)
12. Cloro Sanitizante (QUI-001)

---

## Portas Utilizadas (evitando conflitos)

| Serviço | Porta Original | Porta Ajustada | Motivo |
|---------|---------------|----------------|--------|
| Web (Apache) | 8000 | **8085** | Porta 8000 em uso |
| MySQL | 3306 | **3310** | Portas 3306-3309 em uso |
| phpMyAdmin | 8888 | **8889** | Porta 8888 pode estar em uso |

---

## Comandos Úteis

### Gerenciamento dos Containers

```bash
# Ver status dos containers
docker-compose ps

# Ver logs em tempo real
docker-compose logs -f

# Ver logs de um serviço específico
docker-compose logs -f web
docker-compose logs -f db
docker-compose logs -f phpmyadmin

# Parar todos os containers
docker-compose down

# Parar e remover volumes (CUIDADO: apaga dados!)
docker-compose down -v

# Reiniciar todos os containers
docker-compose restart

# Reiniciar um container específico
docker-compose restart web
docker-compose restart db

# Iniciar containers
docker-compose up -d

# Rebuild e iniciar
docker-compose up -d --build
```

### Acesso ao MySQL via linha de comando

```bash
# Acessar MySQL dentro do container
docker exec -it hidroapp-db-1 mysql -uroot -phidroapp123 hidroapp

# Executar comando SQL direto
docker exec hidroapp-db-1 mysql -uroot -phidroapp123 -e "USE hidroapp; SELECT * FROM usuarios;"

# Fazer backup do banco
docker exec hidroapp-db-1 mysqldump -uroot -phidroapp123 hidroapp > backup_hidroapp.sql

# Restaurar backup
docker exec -i hidroapp-db-1 mysql -uroot -phidroapp123 hidroapp < backup_hidroapp.sql
```

### Acesso aos logs do Apache

```bash
# Ver logs de acesso
docker exec hidroapp-web-1 tail -f /var/log/apache2/access.log

# Ver logs de erro
docker exec hidroapp-web-1 tail -f /var/log/apache2/error.log
```

---

## Estrutura de Arquivos do Projeto

```
hidroapp/
├── Configuration Files
│   ├── config.php                  - Configurações gerais
│   ├── db.php                      - Conexão com banco
│   ├── db_host.php                 - Config para produção
│   ├── db_local.php                - Config para desenvolvimento
│   └── session_middleware.php      - Gerenciamento de sessões
│
├── Core Application
│   ├── index.php                   - Dashboard principal
│   ├── login.php                   - Página de login
│   ├── logout.php                  - Logout
│   ├── register.php                - Cadastro de usuários
│   └── configuracoes.php           - Configurações do sistema
│
├── Modules
│   ├── equipamentos.php            - Gestão de equipamentos
│   ├── manutencoes.php             - Gestão de manutenções
│   ├── usuarios.php                - Gestão de usuários
│   ├── materiais.php               - Gestão de materiais
│   ├── servicos.php                - Gestão de serviços
│   └── relatorios.php              - Relatórios
│
├── Database
│   ├── init.sql                    - Schema completo do banco
│   ├── init_complete.sql           - Backup do schema
│   └── uploads/                    - Arquivos enviados
│
├── Docker
│   ├── Dockerfile                  - Definição do container web
│   ├── docker-compose.yml          - Orquestração dos containers
│   └── composer.json               - Dependências PHP
│
└── Documentation
    └── SISTEMA_CONFIGURADO.md      - Este arquivo
```

---

## Tecnologias Utilizadas

### Backend
- **PHP 8.1** - Linguagem de programação
- **PDO** - Abstração de banco de dados
- **MySQL 8.0** - Banco de dados relacional
- **Apache 2.4** - Servidor web
- **Composer** - Gerenciador de dependências
- **mPDF** - Geração de relatórios PDF

### Frontend
- **HTML5** - Marcação
- **CSS3** - Estilização
- **Bootstrap 5.3.0** - Framework CSS
- **Bootstrap Icons 1.10.0** - Ícones
- **JavaScript** - Interatividade
- **Chart.js** - Gráficos e visualização

### DevOps
- **Docker** - Containerização
- **Docker Compose** - Orquestração
- **Git** - Controle de versão

---

## Próximos Passos Recomendados

1. **Primeiro Acesso:**
   - Acesse http://localhost:8085
   - Faça login com admin@hidroapp.com / password
   - Altere a senha do administrador

2. **Configuração Inicial:**
   - Cadastre técnicos
   - Cadastre equipamentos
   - Configure tipos de manutenção adicionais
   - Atualize inventário de materiais

3. **Testes:**
   - Crie uma manutenção de teste
   - Teste upload de fotos
   - Gere relatórios
   - Verifique permissões por tipo de usuário

4. **Segurança:**
   - Alterar senhas padrão
   - Revisar permissões de pasta
   - Configurar backup automático
   - Configurar SSL/HTTPS para produção

---

## Suporte e Troubleshooting

### Problema: Containers não iniciam
```bash
# Verificar logs
docker-compose logs

# Verificar portas em uso
netstat -ano | findstr "8085 3310 8889"

# Parar e reiniciar
docker-compose down
docker-compose up -d
```

### Problema: Erro de conexão com banco
```bash
# Verificar se o container do banco está rodando
docker-compose ps

# Verificar logs do MySQL
docker-compose logs db

# Testar conexão
docker exec hidroapp-db-1 mysql -uroot -phidroapp123 -e "SELECT 1;"
```

### Problema: Página em branco ou erro 500
```bash
# Ver logs do Apache
docker-compose logs web

# Ver logs de erro PHP
docker exec hidroapp-web-1 tail -f /var/log/apache2/error.log
```

---

## Informações de Licença

Sistema desenvolvido para gestão de manutenções de equipamentos hidráulicos.

**Data de Configuração:** 2025-10-16
**Versão do Sistema:** 1.0
**Ambiente:** Desenvolvimento Local (Docker)

---

**Sistema operacional e pronto para uso!**

Para acessar: http://localhost:8085
