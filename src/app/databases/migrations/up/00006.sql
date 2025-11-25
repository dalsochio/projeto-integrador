-- @description Create panel_table_form table and insert default data

CREATE TABLE IF NOT EXISTS `panel_table_form` (
    `id`                 int(11)     NOT NULL AUTO_INCREMENT,
    `table_id`           int(11)     NOT NULL COMMENT 'Referência ao módulo',
    `column_id`          int(11)              DEFAULT NULL COMMENT 'NULL para componentes estáticos (divisores, etc)',
    `component_type`     enum('field','divider_horizontal','divider_vertical') NOT NULL DEFAULT 'field',
    `row_index`          int(11)     NOT NULL COMMENT 'Índice da linha no layout',
    `row_size`           int(11)     NOT NULL DEFAULT 1 COMMENT 'Quantas colunas nesta linha (1-4)',
    `column_size`        int(11)     NOT NULL DEFAULT 12 COMMENT 'Largura da coluna em grid 12',
    `position`           int(11)     NOT NULL COMMENT 'Ordem exata de renderização',
    `config`             longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Configurações específicas do componente (JSON)' CHECK (json_valid(`config`)),
    `input_type`         varchar(32)          DEFAULT 'text' COMMENT 'Tipo de input visual',
    `input_options`      text                 DEFAULT NULL COMMENT 'Opções para select/radio/checkbox (JSON)',
    `input_placeholder`  varchar(255)         DEFAULT NULL COMMENT 'Placeholder do campo',
    `input_prefix`       varchar(32)          DEFAULT NULL COMMENT 'Prefixo visual (R$, @, etc)',
    `input_suffix`       varchar(32)          DEFAULT NULL COMMENT 'Sufixo visual (kg, %, etc)',
    `input_mask`         varchar(64)          DEFAULT NULL COMMENT 'Máscara de input',
    `validation_rules`   text                 DEFAULT NULL COMMENT 'Regras de validação (JSON)',
    `validation_message` text                 DEFAULT NULL COMMENT 'Mensagens de validação customizadas (JSON)',
    `help_text`          varchar(500)         DEFAULT NULL COMMENT 'Texto de ajuda do campo',
    `tooltip`            varchar(255)         DEFAULT NULL COMMENT 'Tooltip ao passar mouse',
    PRIMARY KEY (`id`),
    KEY `idx_table` (`table_id`),
    KEY `idx_column` (`column_id`),
    KEY `idx_position` (`table_id`, `position`),
    CONSTRAINT `fk_panel_table_form_table` FOREIGN KEY (`table_id`) REFERENCES `panel_table` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_panel_table_form_column` FOREIGN KEY (`column_id`) REFERENCES `panel_column` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO `panel_table_form` (`id`, `table_id`, `column_id`, `component_type`, `row_index`, `row_size`, `column_size`, `position`, `config`, `input_type`, `input_options`, `input_placeholder`, `input_prefix`, `input_suffix`, `input_mask`, `validation_rules`, `validation_message`, `help_text`, `tooltip`)
VALUES
    (1, 1, 263, 'field', 1, 1, 12, 1, NULL, 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
    (2, 1, 264, 'field', 2, 1, 12, 2, NULL, 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
