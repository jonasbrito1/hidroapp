-- Script de Correção e Tradução para PT-BR
-- HidroApp - Data: 2025-10-17

USE hidroapp;

-- ============================================
-- CORREÇÃO DOS TIPOS DE MANUTENÇÃO
-- ============================================

-- Atualizar tipo de manutenção "Reparo Corretivo" para ter periodicidade
UPDATE tipos_manutencao
SET periodicidade_dias = 0,
    descricao = 'Correção de defeitos e problemas identificados (sem periodicidade definida)'
WHERE nome = 'Reparo Corretivo' OR id = 5;

-- Adicionar mais tipos de manutenção em PT-BR
INSERT INTO tipos_manutencao
    (codigo, nome, categoria, descricao, periodicidade_dias, tipo_equipamento, tempo_estimado, prioridade_default)
VALUES
    ('MNT-008', 'Higienização Completa', 'limpeza', 'Higienização profunda com produtos sanitizantes', 15, 'ambos', 45, 'alta'),
    ('MNT-009', 'Troca de Refil', 'manutencao', 'Substituição de refil do filtro de água', 60, 'bebedouro', 20, 'media'),
    ('MNT-010', 'Limpeza de Reservatório', 'limpeza', 'Limpeza interna do reservatório de água', 90, 'bebedouro', 60, 'alta'),
    ('MNT-011', 'Revisão de Vazamentos', 'inspecao', 'Inspeção e correção de vazamentos', 180, 'ambos', 30, 'alta'),
    ('MNT-012', 'Manutenção de Torneiras', 'manutencao', 'Revisão e troca de torneiras e registros', 180, 'ambos', 40, 'media'),
    ('MNT-013', 'Limpeza de Serpentina', 'limpeza', 'Limpeza da serpentina de refrigeração', 120, 'bebedouro', 90, 'alta'),
    ('MNT-014', 'Teste de Qualidade da Água', 'inspecao', 'Análise e teste de qualidade da água', 30, 'bebedouro', 15, 'alta'),
    ('MNT-015', 'Desinfecção Completa', 'limpeza', 'Processo completo de desinfecção do equipamento', 90, 'ambos', 120, 'alta')
ON DUPLICATE KEY UPDATE nome=nome;

-- Atualizar códigos dos tipos existentes
UPDATE tipos_manutencao SET codigo = 'MNT-001' WHERE id = 1 AND codigo IS NULL;
UPDATE tipos_manutencao SET codigo = 'MNT-002' WHERE id = 2 AND codigo IS NULL;
UPDATE tipos_manutencao SET codigo = 'MNT-003' WHERE id = 3 AND codigo IS NULL;
UPDATE tipos_manutencao SET codigo = 'MNT-004' WHERE id = 4 AND codigo IS NULL;
UPDATE tipos_manutencao SET codigo = 'MNT-005' WHERE id = 5 AND codigo IS NULL;
UPDATE tipos_manutencao SET codigo = 'MNT-006' WHERE id = 6 AND codigo IS NULL;
UPDATE tipos_manutencao SET codigo = 'MNT-007' WHERE id = 7 AND codigo IS NULL;

-- ============================================
-- TRADUÇÃO DE ENUMS E VALORES
-- ============================================

-- Nota: ENUMs não podem ser atualizados diretamente com UPDATE
-- Mas podemos garantir que os novos dados inseridos usem os valores corretos em PT-BR

-- ============================================
-- ADICIONAR MAIS PEÇAS E MATERIAIS EM PT-BR
-- ============================================

INSERT INTO pecas_materiais
    (nome, codigo, categoria, unidade_medida, preco_unitario, estoque_minimo)
VALUES
    ('Filtro Refil 3 Estágios', 'FLT-003', 'filtro', 'UN', 35.00, 8),
    ('Filtro de Sedimentos', 'FLT-004', 'filtro', 'UN', 18.00, 10),
    ('Boia para Reservatório', 'PCA-005', 'peca', 'UN', 22.00, 5),
    ('Registro de Pressão', 'PCA-006', 'peca', 'UN', 28.00, 4),
    ('Conexão T 1/2"', 'PCA-007', 'peca', 'UN', 4.50, 20),
    ('Abraçadeira Metálica', 'PCA-008', 'peca', 'UN', 2.50, 30),
    ('Tubo PVC 1/2"', 'PCA-009', 'peca', 'M', 12.00, 15),
    ('Desinfetante Hospitalar', 'CON-004', 'consumivel', 'L', 18.00, 6),
    ('Esponja Abrasiva', 'CON-005', 'consumivel', 'UN', 3.50, 20),
    ('Luva Descartável', 'CON-006', 'consumivel', 'CX', 25.00, 5),
    ('Fita Veda Rosca', 'CON-007', 'consumivel', 'UN', 5.00, 15),
    ('Chave Inglesa 10"', 'FER-003', 'ferramenta', 'UN', 35.00, 2),
    ('Alicate de Pressão', 'FER-004', 'ferramenta', 'UN', 42.00, 2),
    ('Multímetro Digital', 'FER-005', 'ferramenta', 'UN', 85.00, 1),
    ('Sanitizante Cloro 1%', 'QUI-002', 'quimico', 'L', 15.00, 8),
    ('Desincrustante Ácido', 'QUI-003', 'quimico', 'L', 22.00, 4)
ON DUPLICATE KEY UPDATE nome=nome;

-- ============================================
-- ATUALIZAR EQUIPAMENTOS COM NOMES CLAROS
-- ============================================

UPDATE equipamentos
SET
    localizacao = REPLACE(localizacao, 'https://maps.app.goo.gl/', ''),
    localizacao = CONCAT('Praia Brava Sul - Ponto ', SUBSTRING(codigo, 1, 2))
WHERE localizacao LIKE 'https://maps.app.goo.gl/%';

-- Garantir que equipamentos tenham localização descritiva
UPDATE equipamentos
SET observacoes = CONCAT(
    COALESCE(observacoes, ''),
    IF(observacoes IS NULL OR observacoes = '', '', ' | '),
    'Equipamento público localizado na orla da Praia Brava Sul'
)
WHERE tipo = 'bebedouro'
  AND codigo LIKE '%BRAVA SUL%'
  AND (observacoes IS NULL OR observacoes = '');

-- ============================================
-- VERIFICAÇÃO FINAL
-- ============================================

-- Listar tipos de manutenção atualizados
SELECT
    codigo,
    nome,
    categoria,
    COALESCE(periodicidade_dias, 0) as periodicidade_dias,
    tipo_equipamento,
    tempo_estimado,
    prioridade_default
FROM tipos_manutencao
ORDER BY id;

-- Listar materiais cadastrados
SELECT
    codigo,
    nome,
    categoria,
    unidade_medida,
    CONCAT('R$ ', FORMAT(preco_unitario, 2, 'pt_BR')) as preco,
    estoque_minimo
FROM pecas_materiais
ORDER BY categoria, codigo;

-- Listar equipamentos
SELECT
    codigo,
    tipo,
    localizacao,
    endereco,
    status,
    DATE_FORMAT(data_instalacao, '%d/%m/%Y') as instalacao
FROM equipamentos
ORDER BY codigo;

-- Estatísticas
SELECT
    'Tipos de Manutenção' as item,
    COUNT(*) as quantidade
FROM tipos_manutencao
UNION ALL
SELECT
    'Materiais Cadastrados',
    COUNT(*)
FROM pecas_materiais
UNION ALL
SELECT
    'Equipamentos Ativos',
    COUNT(*)
FROM equipamentos
WHERE status = 'ativo';
