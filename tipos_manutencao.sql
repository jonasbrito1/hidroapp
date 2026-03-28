-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 17/10/2025 às 02:56
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u674882802_hidroapp`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_manutencao`
--

CREATE TABLE `tipos_manutencao` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `categoria` enum('limpeza','manutencao','instalacao','inspecao','troca') DEFAULT 'manutencao',
  `descricao` text DEFAULT NULL,
  `periodicidade_dias` int(11) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tipo_equipamento` enum('bebedouro','ducha','ambos') DEFAULT 'ambos',
  `tempo_estimado` int(11) DEFAULT 30,
  `prioridade_default` enum('baixa','media','alta','urgente') DEFAULT 'media'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `tipos_manutencao`
--

INSERT INTO `tipos_manutencao` (`id`, `codigo`, `nome`, `categoria`, `descricao`, `periodicidade_dias`, `ativo`, `created_at`, `updated_at`, `tipo_equipamento`, `tempo_estimado`, `prioridade_default`) VALUES
(1, 'TM001', 'Limpeza Geral', 'limpeza', 'Limpeza completa do equipamento', 30, 1, '2025-08-19 01:30:48', '2025-08-27 12:13:52', 'ambos', 30, 'baixa'),
(2, 'TM002', 'Troca de Filtro', 'troca', 'Substituição dos filtros', 90, 1, '2025-08-19 01:30:48', '2025-08-27 12:13:52', '', 90, 'alta'),
(3, 'TM003', 'Verificação Elétrica', 'inspecao', 'Inspeção do sistema elétrico', 180, 1, '2025-08-19 01:30:48', '2025-08-27 12:13:52', 'ambos', 45, 'media'),
(4, 'TM004', 'Manutenção Preventiva Completa', 'manutencao', 'Revisão geral do equipamento', 365, 1, '2025-08-19 01:30:48', '2025-08-27 12:13:38', 'ambos', 30, 'media'),
(5, 'TM005', 'Reparo Corretivo', 'manutencao', 'Correção de defeitos', NULL, 1, '2025-08-19 01:30:48', '2025-08-27 12:13:38', 'ambos', 30, 'media'),
(6, 'BM001', 'Manutenção de Bomba', 'manutencao', NULL, NULL, 1, '2025-08-27 12:13:52', '2025-08-27 12:13:52', '', 120, 'alta'),
(7, 'FL001', 'Limpeza de Filtro', 'limpeza', NULL, NULL, 1, '2025-08-27 12:13:52', '2025-08-27 12:13:52', '', 30, 'media'),
(8, 'AQ001', 'Verificação de Aquecedor', 'inspecao', NULL, NULL, 1, '2025-08-27 12:13:52', '2025-08-27 12:13:52', '', 60, 'media'),
(14, 'SRV-001', 'Limpeza de caixa de drenagem e deck de madeira - Duchas 1 a 9', 'limpeza', 'Limpeza completa incluindo deslocamento, material e equipamentos', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ducha', 120, 'media'),
(15, 'SRV-002', 'Limpeza de caixa de drenagem e deck de madeira - Duchas 10 a 23', 'limpeza', 'Limpeza completa incluindo deslocamento, material e equipamentos', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ducha', 180, 'media'),
(16, 'SRV-003', 'Limpeza de deck de madeira com varrição a seco', 'limpeza', 'Varrição a seco e remoção da vegetação ao redor', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ducha', 30, 'baixa'),
(17, 'SRV-004', 'Limpeza do bebedouro e substituição de filtro 3/4\"', 'limpeza', 'Limpeza completa e troca de filtro', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'bebedouro', 45, 'media'),
(18, 'SRV-005', 'Limpeza de contrapiso com vassoura a seco', 'limpeza', 'Limpeza básica do contrapiso', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 20, 'baixa'),
(19, 'SRV-006', 'Limpeza de ladrilho hidráulico em parede com pano úmido', 'limpeza', 'Limpeza de revestimentos', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 25, 'baixa'),
(20, 'SRV-007', 'Conservação de áreas verdes', 'limpeza', 'Capina com remoção de vegetação e extermínio de pragas', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 60, 'baixa'),
(21, 'SRV-008', 'Demolição e reparo de estrutura de madeira para ducha', 'manutencao', 'Reparo com espuma expansiva e massa de madeira', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ducha', 180, 'alta'),
(22, 'SRV-009', 'Reparo de tablado de madeira 1,00 x 2,00m', 'manutencao', 'Substituição do assoalho e estrutura danificados', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ducha', 240, 'alta'),
(23, 'SRV-010', 'Demolição e reparo de estrutura de concreto para ducha', 'manutencao', 'Reparo com argamassa polimérica', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ducha', 210, 'alta'),
(24, 'SRV-011', 'Reparo do acionador de torneira antifurto', 'manutencao', 'Reparo do sistema de acionamento', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 60, 'media'),
(25, 'SRV-012', 'Demolição de concreto até 5cm de profundidade', 'manutencao', 'Demolição localizada para reparos', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 90, 'alta'),
(26, 'SRV-013', 'Substituição de registro ou válvula DN 25mm', 'troca', 'Troca de registro roscável incluindo material', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 90, 'media'),
(27, 'SRV-014', 'Substituição de mangueira de filtro 3/4\"', 'troca', 'Troca de mangueira do sistema de filtração', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'bebedouro', 30, 'media'),
(28, 'SRV-015', 'Substituição de ducha de parede alta segurança', 'troca', 'Troca de ducha antifurto cromada 1/2\"', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ducha', 75, 'media'),
(29, 'SRV-016', 'Substituição de ducha de teto de plástico', 'troca', 'Troca de ducha de teto incluindo material', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ducha', 60, 'media'),
(30, 'SRV-017', 'Substituição de cuba inox 500ml', 'troca', 'Substituição de cuba fixada por rebite', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'bebedouro', 90, 'media'),
(31, 'SRV-018', 'Substituição de torneira antifurto com temporizador', 'troca', 'Troca de torneira de parede com temporizador', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 120, 'media'),
(32, 'SRV-019', 'Substituição de conexões hidráulicas PVC DN25mm', 'troca', 'Troca de conexões do sistema hidráulico', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 45, 'media'),
(33, 'SRV-020', 'Instalação de tubo PVC soldável 25mm', 'instalacao', 'Instalação em ramal de água', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 60, 'media'),
(34, 'SRV-021', 'Instalação de joelho 90° PVC DN 25mm', 'instalacao', 'Instalação em ramal de distribuição', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 30, 'media'),
(35, 'SRV-022', 'Instalação de tê PVC DN 25mm', 'instalacao', 'Instalação em ramal de distribuição', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 35, 'media'),
(36, 'SRV-023', 'Instalação de luva de correr PVC DN 25mm', 'instalacao', 'Instalação em ramal de água', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 25, 'media'),
(37, 'SRV-024', 'Instalação de adaptador com rosca DN 25mm x 3/4\"', 'instalacao', 'Instalação em ramal de água', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 40, 'media'),
(38, 'SRV-025', 'Deslocamento entre equipamentos', 'inspecao', 'Tempo de deslocamento entre duchas/bebedouros', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 15, 'baixa'),
(39, 'SRV-026', 'Deslocamento do escritório até localidade', 'inspecao', 'Ida e volta - distância média 10km', NULL, 1, '2025-09-08 17:02:57', '2025-09-08 17:02:57', 'ambos', 30, 'baixa');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `tipos_manutencao`
--
ALTER TABLE `tipos_manutencao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `tipos_manutencao`
--
ALTER TABLE `tipos_manutencao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
