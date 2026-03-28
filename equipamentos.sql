-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 17/10/2025 às 02:28
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
-- Estrutura para tabela `equipamentos`
--

CREATE TABLE `equipamentos` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `tipo` enum('bebedouro','ducha') NOT NULL,
  `localizacao` varchar(200) NOT NULL,
  `endereco` varchar(300) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `data_instalacao` date DEFAULT NULL,
  `status` enum('ativo','inativo','manutencao') DEFAULT 'ativo',
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `photo_path` varchar(255) DEFAULT NULL,
  `google_maps_url` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `equipamentos`
--

INSERT INTO `equipamentos` (`id`, `codigo`, `tipo`, `localizacao`, `endereco`, `latitude`, `longitude`, `marca`, `modelo`, `data_instalacao`, `status`, `observacoes`, `created_at`, `updated_at`, `photo_path`, `google_maps_url`) VALUES
(3, '01 BEBEDOURO BRAVA SUL', 'bebedouro', 'https://maps.app.goo.gl/x7Q3qtr5Y4QDBufj9', 'Av. José Mediros Viera, esquina com a rua Martim pescador, em frente ao quiosque', NULL, NULL, '', '', '2025-07-30', 'ativo', '', '2025-08-26 14:20:58', '2025-08-26 14:20:58', NULL, NULL),
(4, '02 BEBEDOURO BRAVA SUL', 'bebedouro', 'https://maps.app.goo.gl/9koXT4fDzw9wCv5P9', 'Av. José Medeiros Vieira (Sul) PRAIA BRAVA, Aproximadamente 10m ao norte do chuveiro da esquina com a Rua Jucilia Maria da Silva Miguel', NULL, NULL, '', '', '2025-07-30', 'ativo', '', '2025-08-26 14:23:23', '2025-08-26 14:23:23', NULL, NULL),
(5, '03 BEBEDOURO BRAVA SUL', 'bebedouro', 'https://maps.app.goo.gl/1nT1846bZmYJKMCR8', 'Av. José Medeiros Vieira, PRAIA BRAVA, Ao lado do chuveiro esquina com a Rua Dulio Furlan', NULL, NULL, '', '', NULL, 'ativo', '', '2025-08-26 14:25:34', '2025-08-26 14:25:34', NULL, NULL),
(6, '04 BEBEDOURO BRAVA SUL', 'bebedouro', 'https://maps.app.goo.gl/C63C8TyV7JUhrezNA', 'Av. José Medeiros Vieira (Sul), Praia Brava, Esquina com a Rua João Manoel da Silva, junto ao equipamento para exercícios', NULL, NULL, '', '', '2025-07-30', 'ativo', '', '2025-08-26 14:26:39', '2025-08-26 14:26:39', NULL, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `equipamentos`
--
ALTER TABLE `equipamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_equipamentos_tipo` (`tipo`),
  ADD KEY `idx_equipamentos_status` (`status`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `equipamentos`
--
ALTER TABLE `equipamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
