-- Script de Correção de Encoding UTF-8
-- HidroApp - Data: 2025-10-17

USE hidroapp;

-- ============================================
-- CORRIGIR CHARSET DAS TABELAS
-- ============================================

ALTER TABLE tipos_manutencao CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE pecas_materiais CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE equipamentos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuarios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE manutencoes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================
-- CORRIGIR TIPOS DE MANUTENÇÃO
-- ============================================

-- ID 2
UPDATE tipos_manutencao
SET nome = 'Troca de Filtro',
    descricao = 'Substituição dos filtros'
WHERE id = 2;

-- ID 3
UPDATE tipos_manutencao
SET nome = 'Verificação Elétrica',
    descricao = 'Inspeção do sistema elétrico'
WHERE id = 3;

-- ID 4
UPDATE tipos_manutencao
SET nome = 'Manutenção Preventiva Completa',
    descricao = 'Revisão geral do equipamento'
WHERE id = 4;

-- ID 5
UPDATE tipos_manutencao
SET nome = 'Reparo Corretivo',
    descricao = 'Correção de defeitos e problemas identificados (sem periodicidade definida)'
WHERE id = 5;

-- ID 6
UPDATE tipos_manutencao
SET nome = 'Limpeza de Bebedouro',
    descricao = 'Limpeza específica para bebedouros'
WHERE id = 6;

-- ID 7
UPDATE tipos_manutencao
SET nome = 'Manutenção de Ducha',
    descricao = 'Manutenção específica para duchas'
WHERE id = 7;

-- ID 8
UPDATE tipos_manutencao
SET nome = 'Higienização Completa',
    descricao = 'Higienização profunda com produtos sanitizantes'
WHERE id = 8;

-- ID 9
UPDATE tipos_manutencao
SET nome = 'Troca de Refil',
    descricao = 'Substituição de refil do filtro de água'
WHERE id = 9;

-- ID 10
UPDATE tipos_manutencao
SET nome = 'Limpeza de Reservatório',
    descricao = 'Limpeza interna do reservatório de água'
WHERE id = 10;

-- ID 11
UPDATE tipos_manutencao
SET nome = 'Revisão de Vazamentos',
    descricao = 'Inspeção e correção de vazamentos'
WHERE id = 11;

-- ID 12
UPDATE tipos_manutencao
SET nome = 'Manutenção de Torneiras',
    descricao = 'Revisão e troca de torneiras e registros'
WHERE id = 12;

-- ID 13
UPDATE tipos_manutencao
SET nome = 'Limpeza de Serpentina',
    descricao = 'Limpeza da serpentina de refrigeração'
WHERE id = 13;

-- ID 14
UPDATE tipos_manutencao
SET nome = 'Teste de Qualidade da Água',
    descricao = 'Análise e teste de qualidade da água'
WHERE id = 14;

-- ID 15
UPDATE tipos_manutencao
SET nome = 'Desinfecção Completa',
    descricao = 'Processo completo de desinfecção do equipamento'
WHERE id = 15;

-- ============================================
-- CORRIGIR MATERIAIS E PEÇAS
-- ============================================

UPDATE pecas_materiais
SET nome = 'Filtro de Água Padrão'
WHERE codigo = 'FLT-001';

UPDATE pecas_materiais
SET nome = 'Filtro de Carvão Ativado'
WHERE codigo = 'FLT-002';

UPDATE pecas_materiais
SET nome = 'Filtro Refil 3 Estágios'
WHERE codigo = 'FLT-003';

UPDATE pecas_materiais
SET nome = 'Mangueira 1/2"'
WHERE codigo = 'PCA-002';

UPDATE pecas_materiais
SET nome = 'Vedação de Borracha'
WHERE codigo = 'PCA-003';

UPDATE pecas_materiais
SET nome = 'Resistência 1500W'
WHERE codigo = 'PCA-004';

UPDATE pecas_materiais
SET nome = 'Boia para Reservatório'
WHERE codigo = 'PCA-005';

UPDATE pecas_materiais
SET nome = 'Registro de Pressão'
WHERE codigo = 'PCA-006';

UPDATE pecas_materiais
SET nome = 'Conexão T 1/2"'
WHERE codigo = 'PCA-007';

UPDATE pecas_materiais
SET nome = 'Abraçadeira Metálica'
WHERE codigo = 'PCA-008';

UPDATE pecas_materiais
SET nome = 'Álcool 70%'
WHERE codigo = 'CON-002';

UPDATE pecas_materiais
SET nome = 'Pano de Limpeza'
WHERE codigo = 'CON-003';

UPDATE pecas_materiais
SET nome = 'Esponja Abrasiva'
WHERE codigo = 'CON-005';

UPDATE pecas_materiais
SET nome = 'Luva Descartável'
WHERE codigo = 'CON-006';

UPDATE pecas_materiais
SET nome = 'Chave Inglesa 10"'
WHERE codigo = 'FER-003';

UPDATE pecas_materiais
SET nome = 'Alicate de Pressão'
WHERE codigo = 'FER-004';

UPDATE pecas_materiais
SET nome = 'Multímetro Digital'
WHERE codigo = 'FER-005';

UPDATE pecas_materiais
SET nome = 'Sanitizante Cloro 1%'
WHERE codigo = 'QUI-002';

UPDATE pecas_materiais
SET nome = 'Desincrustante Ácido'
WHERE codigo = 'QUI-003';

-- ============================================
-- VERIFICAÇÃO FINAL
-- ============================================

SELECT '==== TIPOS DE MANUTENÇÃO ====' as '';
SELECT id, codigo, nome, descricao
FROM tipos_manutencao
ORDER BY id;

SELECT '==== MATERIAIS (SAMPLE) ====' as '';
SELECT id, codigo, nome, categoria
FROM pecas_materiais
WHERE nome LIKE '%á%'
   OR nome LIKE '%ç%'
   OR nome LIKE '%ã%'
   OR nome LIKE '%ó%'
   OR nome LIKE '%í%'
   OR nome LIKE '%é%'
ORDER BY codigo
LIMIT 10;

SELECT '==== CHARSET DAS TABELAS ====' as '';
SELECT
    TABLE_NAME,
    TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'hidroapp'
ORDER BY TABLE_NAME;
