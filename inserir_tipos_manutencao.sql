-- Inserção de Tipos de Manutenção
-- HidroApp - Data: 2025-10-17
-- Baseado no arquivo tipos_manutencao.sql

USE hidroapp;

-- Limpar tipos existentes (opcional - comentar se quiser manter os existentes)
-- TRUNCATE TABLE tipos_manutencao;

-- ============================================
-- INSERIR TIPOS DE MANUTENÇÃO
-- ============================================

-- Inserir com ON DUPLICATE KEY UPDATE para evitar duplicatas
INSERT INTO tipos_manutencao (id, codigo, nome, categoria, descricao, periodicidade_dias, ativo, tipo_equipamento, tempo_estimado, prioridade_default)
VALUES
-- Tipos Básicos (1-5)
(1, 'TM001', 'Limpeza Geral', 'limpeza', 'Limpeza completa do equipamento', 30, 1, 'ambos', 30, 'baixa'),
(2, 'TM002', 'Troca de Filtro', 'manutencao', 'Substituição dos filtros', 90, 1, 'ambos', 90, 'alta'),
(3, 'TM003', 'Verificação Elétrica', 'inspecao', 'Inspeção do sistema elétrico', 180, 1, 'ambos', 45, 'media'),
(4, 'TM004', 'Manutenção Preventiva Completa', 'manutencao', 'Revisão geral do equipamento', 365, 1, 'ambos', 30, 'media'),
(5, 'TM005', 'Reparo Corretivo', 'manutencao', 'Correção de defeitos', 0, 1, 'ambos', 30, 'media'),

-- Manutenções Específicas (6-8)
(6, 'BM001', 'Manutenção de Bomba', 'manutencao', 'Manutenção completa do sistema de bombeamento', 0, 1, 'ambos', 120, 'alta'),
(7, 'FL001', 'Limpeza de Filtro', 'limpeza', 'Limpeza detalhada do sistema de filtração', 0, 1, 'ambos', 30, 'media'),
(8, 'AQ001', 'Verificação de Aquecedor', 'inspecao', 'Inspeção do sistema de aquecimento', 0, 1, 'bebedouro', 60, 'media'),

-- Serviços de Limpeza (14-20)
(14, 'SRV-001', 'Limpeza de caixa de drenagem e deck de madeira - Duchas 1 a 9', 'limpeza', 'Limpeza completa incluindo deslocamento, material e equipamentos', 0, 1, 'ducha', 120, 'media'),
(15, 'SRV-002', 'Limpeza de caixa de drenagem e deck de madeira - Duchas 10 a 23', 'limpeza', 'Limpeza completa incluindo deslocamento, material e equipamentos', 0, 1, 'ducha', 180, 'media'),
(16, 'SRV-003', 'Limpeza de deck de madeira com varrição a seco', 'limpeza', 'Varrição a seco e remoção da vegetação ao redor', 0, 1, 'ducha', 30, 'baixa'),
(17, 'SRV-004', 'Limpeza do bebedouro e substituição de filtro 3/4"', 'limpeza', 'Limpeza completa e troca de filtro', 0, 1, 'bebedouro', 45, 'media'),
(18, 'SRV-005', 'Limpeza de contrapiso com vassoura a seco', 'limpeza', 'Limpeza básica do contrapiso', 0, 1, 'ambos', 20, 'baixa'),
(19, 'SRV-006', 'Limpeza de ladrilho hidráulico em parede com pano úmido', 'limpeza', 'Limpeza de revestimentos', 0, 1, 'ambos', 25, 'baixa'),
(20, 'SRV-007', 'Conservação de áreas verdes', 'limpeza', 'Capina com remoção de vegetação e extermínio de pragas', 0, 1, 'ambos', 60, 'baixa'),

-- Serviços de Manutenção (21-25)
(21, 'SRV-008', 'Demolição e reparo de estrutura de madeira para ducha', 'manutencao', 'Reparo com espuma expansiva e massa de madeira', 0, 1, 'ducha', 180, 'alta'),
(22, 'SRV-009', 'Reparo de tablado de madeira 1,00 x 2,00m', 'manutencao', 'Substituição do assoalho e estrutura danificados', 0, 1, 'ducha', 240, 'alta'),
(23, 'SRV-010', 'Demolição e reparo de estrutura de concreto para ducha', 'manutencao', 'Reparo com argamassa polimérica', 0, 1, 'ducha', 210, 'alta'),
(24, 'SRV-011', 'Reparo do acionador de torneira antifurto', 'manutencao', 'Reparo do sistema de acionamento', 0, 1, 'ambos', 60, 'media'),
(25, 'SRV-012', 'Demolição de concreto até 5cm de profundidade', 'manutencao', 'Demolição localizada para reparos', 0, 1, 'ambos', 90, 'alta'),

-- Serviços de Troca (26-32)
(26, 'SRV-013', 'Substituição de registro ou válvula DN 25mm', 'manutencao', 'Troca de registro roscável incluindo material', 0, 1, 'ambos', 90, 'media'),
(27, 'SRV-014', 'Substituição de mangueira de filtro 3/4"', 'manutencao', 'Troca de mangueira do sistema de filtração', 0, 1, 'bebedouro', 30, 'media'),
(28, 'SRV-015', 'Substituição de ducha de parede alta segurança', 'manutencao', 'Troca de ducha antifurto cromada 1/2"', 0, 1, 'ducha', 75, 'media'),
(29, 'SRV-016', 'Substituição de ducha de teto de plástico', 'manutencao', 'Troca de ducha de teto incluindo material', 0, 1, 'ducha', 60, 'media'),
(30, 'SRV-017', 'Substituição de cuba inox 500ml', 'manutencao', 'Substituição de cuba fixada por rebite', 0, 1, 'bebedouro', 90, 'media'),
(31, 'SRV-018', 'Substituição de torneira antifurto com temporizador', 'manutencao', 'Troca de torneira de parede com temporizador', 0, 1, 'ambos', 120, 'media'),
(32, 'SRV-019', 'Substituição de conexões hidráulicas PVC DN25mm', 'manutencao', 'Troca de conexões do sistema hidráulico', 0, 1, 'ambos', 45, 'media'),

-- Serviços de Instalação (33-37)
(33, 'SRV-020', 'Instalação de tubo PVC soldável 25mm', 'instalacao', 'Instalação em ramal de água', 0, 1, 'ambos', 60, 'media'),
(34, 'SRV-021', 'Instalação de joelho 90° PVC DN 25mm', 'instalacao', 'Instalação em ramal de distribuição', 0, 1, 'ambos', 30, 'media'),
(35, 'SRV-022', 'Instalação de tê PVC DN 25mm', 'instalacao', 'Instalação em ramal de distribuição', 0, 1, 'ambos', 35, 'media'),
(36, 'SRV-023', 'Instalação de luva de correr PVC DN 25mm', 'instalacao', 'Instalação em ramal de água', 0, 1, 'ambos', 25, 'media'),
(37, 'SRV-024', 'Instalação de adaptador com rosca DN 25mm x 3/4"', 'instalacao', 'Instalação em ramal de água', 0, 1, 'ambos', 40, 'media'),

-- Serviços de Deslocamento (38-39)
(38, 'SRV-025', 'Deslocamento entre equipamentos', 'inspecao', 'Tempo de deslocamento entre duchas/bebedouros', 0, 1, 'ambos', 15, 'baixa'),
(39, 'SRV-026', 'Deslocamento do escritório até localidade', 'inspecao', 'Ida e volta - distância média 10km', 0, 1, 'ambos', 30, 'baixa')

ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    categoria = VALUES(categoria),
    descricao = VALUES(descricao),
    periodicidade_dias = VALUES(periodicidade_dias),
    tipo_equipamento = VALUES(tipo_equipamento),
    tempo_estimado = VALUES(tempo_estimado),
    prioridade_default = VALUES(prioridade_default),
    updated_at = CURRENT_TIMESTAMP;

-- ============================================
-- VERIFICAÇÃO
-- ============================================

SELECT '==== RESUMO DA INSERÇÃO ====' as '';

SELECT
    categoria,
    COUNT(*) as quantidade,
    GROUP_CONCAT(DISTINCT tipo_equipamento) as equipamentos
FROM tipos_manutencao
GROUP BY categoria
ORDER BY categoria;

SELECT '==== TIPOS POR EQUIPAMENTO ====' as '';

SELECT
    tipo_equipamento,
    COUNT(*) as quantidade
FROM tipos_manutencao
GROUP BY tipo_equipamento;

SELECT '==== TIPOS POR PRIORIDADE ====' as '';

SELECT
    prioridade_default,
    COUNT(*) as quantidade
FROM tipos_manutencao
GROUP BY prioridade_default
ORDER BY FIELD(prioridade_default, 'baixa', 'media', 'alta', 'urgente');

SELECT '==== TOTAL DE TIPOS ====' as '';

SELECT COUNT(*) as total_tipos_manutencao FROM tipos_manutencao;

SELECT '==== ÚLTIMOS 10 TIPOS CADASTRADOS ====' as '';

SELECT
    id,
    codigo,
    nome,
    categoria,
    tipo_equipamento,
    tempo_estimado,
    prioridade_default
FROM tipos_manutencao
ORDER BY id DESC
LIMIT 10;
