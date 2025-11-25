-- Migration 00012: Adicionar colunas row_index, row_size, column_size e migrar column_width

-- Adicionar novas colunas
ALTER TABLE panel_column 
ADD COLUMN row_index INT NULL COMMENT 'Índice da linha na grid (NULL = sem grid/legado)',
ADD COLUMN row_size INT DEFAULT 1 COMMENT 'Altura do campo em linhas (sempre 1 por enquanto)',
ADD COLUMN column_size INT DEFAULT 12 COMMENT 'Largura do campo em colunas (1-12)';

-- Migrar dados existentes de column_width para column_size
UPDATE panel_column 
SET column_size = COALESCE(column_width, 12)
WHERE column_size = 12;

-- Manter column_width por compatibilidade temporária, mas column_size é a fonte de verdade agora
