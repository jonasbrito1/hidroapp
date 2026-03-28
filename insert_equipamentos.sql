-- Inserção de Equipamentos - Bebedouros Praia Brava Sul
-- Data: 2025-10-17

USE hidroapp;

-- Inserir equipamentos
INSERT INTO `equipamentos` (`codigo`, `tipo`, `localizacao`, `endereco`, `marca`, `modelo`, `data_instalacao`, `status`, `observacoes`, `google_maps_url`) VALUES
('01 BEBEDOURO BRAVA SUL', 'bebedouro', 'Praia Brava Sul', 'Av. José Mediros Viera, esquina com a rua Martim pescador, em frente ao quiosque', '', '', '2025-07-30', 'ativo', '', 'https://maps.app.goo.gl/x7Q3qtr5Y4QDBufj9'),
('02 BEBEDOURO BRAVA SUL', 'bebedouro', 'Praia Brava Sul', 'Av. José Medeiros Vieira (Sul) PRAIA BRAVA, Aproximadamente 10m ao norte do chuveiro da esquina com a Rua Jucilia Maria da Silva Miguel', '', '', '2025-07-30', 'ativo', '', 'https://maps.app.goo.gl/9koXT4fDzw9wCv5P9'),
('03 BEBEDOURO BRAVA SUL', 'bebedouro', 'Praia Brava Sul', 'Av. José Medeiros Vieira, PRAIA BRAVA, Ao lado do chuveiro esquina com a Rua Dulio Furlan', '', '', NULL, 'ativo', '', 'https://maps.app.goo.gl/1nT1846bZmYJKMCR8'),
('04 BEBEDOURO BRAVA SUL', 'bebedouro', 'Praia Brava Sul', 'Av. José Medeiros Vieira (Sul), Praia Brava, Esquina com a Rua João Manoel da Silva, junto ao equipamento para exercícios', '', '', '2025-07-30', 'ativo', '', 'https://maps.app.goo.gl/C63C8TyV7JUhrezNA')
ON DUPLICATE KEY UPDATE
    localizacao = VALUES(localizacao),
    endereco = VALUES(endereco),
    google_maps_url = VALUES(google_maps_url);

-- Verificar inserção
SELECT id, codigo, tipo, localizacao, status, data_instalacao
FROM equipamentos
WHERE codigo LIKE '%BEBEDOURO BRAVA SUL%'
ORDER BY codigo;
