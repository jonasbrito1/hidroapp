-- Criação do banco de dados HidroApp
-- Estrutura completa baseada no ambiente de produção

CREATE DATABASE IF NOT EXISTS hidroapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hidroapp;

-- ==================================================
-- TABELA: usuarios
-- ==================================================
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `senha` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('admin','tecnico','usuario') COLLATE utf8mb4_unicode_ci DEFAULT 'usuario',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `telefone` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cpf` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endereco` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_logout` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_telefone` (`telefone`),
  KEY `idx_cpf` (`cpf`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: tecnicos
-- ==================================================
CREATE TABLE IF NOT EXISTS `tecnicos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `especialidade` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_tecnicos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: equipamentos
-- ==================================================
CREATE TABLE IF NOT EXISTS `equipamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('bebedouro','ducha') COLLATE utf8mb4_unicode_ci NOT NULL,
  `localizacao` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endereco` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `marca` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_instalacao` date DEFAULT NULL,
  `status` enum('ativo','inativo','manutencao') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `observacoes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_maps_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_codigo` (`codigo`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: tipos_manutencao
-- ==================================================
CREATE TABLE IF NOT EXISTS `tipos_manutencao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` enum('limpeza','manutencao','instalacao','inspecao','reparo','outro') COLLATE utf8mb4_unicode_ci DEFAULT 'manutencao',
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `periodicidade_dias` int(11) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tipo_equipamento` enum('bebedouro','ducha','ambos') COLLATE utf8mb4_unicode_ci DEFAULT 'ambos',
  `tempo_estimado` int(11) DEFAULT 30,
  `prioridade_default` enum('baixa','media','alta','urgente') COLLATE utf8mb4_unicode_ci DEFAULT 'media',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: pecas_materiais
-- ==================================================
CREATE TABLE IF NOT EXISTS `pecas_materiais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidade` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preco_unitario` decimal(10,2) DEFAULT NULL,
  `estoque_minimo` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `categoria` enum('filtro','peca','consumivel','ferramenta','quimico','outro') COLLATE utf8mb4_unicode_ci DEFAULT 'consumivel',
  `unidade_medida` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'UN',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: manutencoes
-- ==================================================
CREATE TABLE IF NOT EXISTS `manutencoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipamento_id` int(11) NOT NULL,
  `tecnico_id` int(11) DEFAULT NULL,
  `tipo_manutencao_id` int(11) NOT NULL,
  `data_agendada` date NOT NULL,
  `data_realizada` datetime DEFAULT NULL,
  `status` enum('agendada','em_andamento','concluida','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'agendada',
  `tipo` enum('preventiva','corretiva') COLLATE utf8mb4_unicode_ci NOT NULL,
  `problema_relatado` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solucao_aplicada` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custo_total` decimal(10,2) DEFAULT 0.00,
  `tempo_execucao` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `prioridade` enum('baixa','media','alta','urgente') COLLATE utf8mb4_unicode_ci DEFAULT 'media' COMMENT 'Prioridade da manutenção',
  `descricao` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descrição detalhada da manutenção',
  `data_inicio` datetime DEFAULT NULL COMMENT 'Data e hora de início da manutenção',
  PRIMARY KEY (`id`),
  KEY `idx_equipamento_id` (`equipamento_id`),
  KEY `idx_tecnico_id` (`tecnico_id`),
  KEY `idx_tipo_manutencao_id` (`tipo_manutencao_id`),
  KEY `idx_data_agendada` (`data_agendada`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_manutencoes_equipamento` FOREIGN KEY (`equipamento_id`) REFERENCES `equipamentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_manutencoes_tecnico` FOREIGN KEY (`tecnico_id`) REFERENCES `tecnicos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_manutencoes_tipo` FOREIGN KEY (`tipo_manutencao_id`) REFERENCES `tipos_manutencao` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: manutencao_pecas
-- ==================================================
CREATE TABLE IF NOT EXISTS `manutencao_pecas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manutencao_id` int(11) NOT NULL,
  `peca_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_manutencao_id` (`manutencao_id`),
  KEY `idx_peca_id` (`peca_id`),
  CONSTRAINT `fk_manutencao_pecas_manutencao` FOREIGN KEY (`manutencao_id`) REFERENCES `manutencoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_manutencao_pecas_peca` FOREIGN KEY (`peca_id`) REFERENCES `pecas_materiais` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: manutencao_materiais
-- ==================================================
CREATE TABLE IF NOT EXISTS `manutencao_materiais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manutencao_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantidade_prevista` decimal(10,3) DEFAULT 0.000,
  `quantidade_utilizada` decimal(10,3) DEFAULT 0.000,
  `preco_unitario` decimal(10,2) DEFAULT 0.00,
  `observacoes` text COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ==================================================
-- TABELA: manutencao_servicos
-- ==================================================
CREATE TABLE IF NOT EXISTS `manutencao_servicos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manutencao_id` int(11) NOT NULL,
  `tipo_manutencao_id` int(11) NOT NULL,
  `quantidade` decimal(10,3) DEFAULT 1.000,
  `tempo_gasto` int(11) DEFAULT 0,
  `observacoes` text COLLATE utf8mb4_general_ci DEFAULT NULL,
  `executado` tinyint(1) DEFAULT 0,
  `executado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ==================================================
-- TABELA: fotos_manutencao
-- ==================================================
CREATE TABLE IF NOT EXISTS `fotos_manutencao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manutencao_id` int(11) NOT NULL,
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `momento` enum('antes','durante','depois') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_manutencao_id` (`manutencao_id`),
  CONSTRAINT `fk_fotos_manutencao` FOREIGN KEY (`manutencao_id`) REFERENCES `manutencoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: manutencao_fotos
-- ==================================================
CREATE TABLE IF NOT EXISTS `manutencao_fotos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manutencao_id` int(11) NOT NULL,
  `tipo_foto` enum('antes','durante','depois','problema','solucao','outro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_upload` datetime DEFAULT CURRENT_TIMESTAMP,
  `uploaded_by` int(11) DEFAULT NULL,
  `ordem` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_manutencao_id` (`manutencao_id`),
  KEY `idx_tipo_foto` (`tipo_foto`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: fotos_equipamento
-- ==================================================
CREATE TABLE IF NOT EXISTS `fotos_equipamento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipamento_id` int(11) NOT NULL,
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_equipamento_id` (`equipamento_id`),
  CONSTRAINT `fk_fotos_equipamento` FOREIGN KEY (`equipamento_id`) REFERENCES `equipamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: equipamento_fotos
-- ==================================================
CREATE TABLE IF NOT EXISTS `equipamento_fotos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipamento_id` int(11) NOT NULL,
  `tipo_foto` enum('geral','detalhes','problema','localizacao','outro') COLLATE utf8mb4_unicode_ci DEFAULT 'geral',
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_upload` datetime DEFAULT CURRENT_TIMESTAMP,
  `uploaded_by` int(11) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_equipamento_id` (`equipamento_id`),
  KEY `idx_tipo_foto` (`tipo_foto`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: equipamento_materiais
-- ==================================================
CREATE TABLE IF NOT EXISTS `equipamento_materiais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipamento_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantidade` decimal(10,2) NOT NULL DEFAULT 1.00,
  `observacoes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_equipamento_id` (`equipamento_id`),
  KEY `idx_material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- TABELA: contratos
-- ==================================================
CREATE TABLE IF NOT EXISTS `contratos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_contrato` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao_servicos` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- INSERÇÃO DE DADOS PADRÃO
-- ==================================================

-- Usuário admin padrão (senha: password)
INSERT INTO `usuarios` (`nome`, `email`, `senha`, `tipo`, `ativo`) VALUES
('Administrador', 'admin@hidroapp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1)
ON DUPLICATE KEY UPDATE nome=nome;

-- Tipos de manutenção padrão
INSERT INTO `tipos_manutencao` (`nome`, `categoria`, `descricao`, `periodicidade_dias`, `tipo_equipamento`, `tempo_estimado`, `prioridade_default`) VALUES
('Limpeza Geral', 'limpeza', 'Limpeza completa do equipamento', 30, 'ambos', 30, 'media'),
('Troca de Filtro', 'manutencao', 'Substituição dos filtros', 90, 'ambos', 45, 'media'),
('Verificação Elétrica', 'inspecao', 'Inspeção do sistema elétrico', 180, 'ambos', 60, 'alta'),
('Manutenção Preventiva Completa', 'manutencao', 'Revisão geral do equipamento', 365, 'ambos', 120, 'alta'),
('Reparo Corretivo', 'reparo', 'Correção de defeitos', NULL, 'ambos', 90, 'urgente'),
('Limpeza de Bebedouro', 'limpeza', 'Limpeza específica para bebedouros', 30, 'bebedouro', 30, 'media'),
('Manutenção de Ducha', 'manutencao', 'Manutenção específica para duchas', 60, 'ducha', 45, 'media')
ON DUPLICATE KEY UPDATE nome=nome;

-- Peças e materiais padrão
INSERT INTO `pecas_materiais` (`nome`, `codigo`, `categoria`, `unidade_medida`, `preco_unitario`, `estoque_minimo`) VALUES
('Filtro de Água Padrão', 'FLT-001', 'filtro', 'UN', 15.00, 10),
('Filtro de Carvão Ativado', 'FLT-002', 'filtro', 'UN', 25.00, 8),
('Torneira Cromada', 'PCA-001', 'peca', 'UN', 35.00, 5),
('Mangueira 1/2"', 'PCA-002', 'peca', 'M', 8.50, 20),
('Vedação de Borracha', 'PCA-003', 'peca', 'UN', 3.00, 30),
('Resistência 1500W', 'PCA-004', 'peca', 'UN', 45.00, 3),
('Detergente Neutro', 'CON-001', 'consumivel', 'L', 12.00, 5),
('Álcool 70%', 'CON-002', 'consumivel', 'L', 15.00, 5),
('Pano de Limpeza', 'CON-003', 'consumivel', 'UN', 5.00, 15),
('Chave de Fenda', 'FER-001', 'ferramenta', 'UN', 18.00, 2),
('Alicate Universal', 'FER-002', 'ferramenta', 'UN', 25.00, 2),
('Cloro Sanitizante', 'QUI-001', 'quimico', 'L', 20.00, 4)
ON DUPLICATE KEY UPDATE nome=nome;
