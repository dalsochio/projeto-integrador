-- @description Create panel_column table and insert default data

CREATE TABLE IF NOT EXISTS `panel_column`
(
    `id`                 int(11)                                                                                     NOT NULL AUTO_INCREMENT,
    `table_id`           int(11)                                                                                     NOT NULL,
    `name`               varchar(64)                                                                                 NOT NULL COMMENT 'Nome da coluna no banco',
    `display_name`       varchar(255)                                                                                     DEFAULT NULL COMMENT 'Nome exibido na UI',
    `type`               enum ('INT','VARCHAR','TEXT','DATE','DATETIME','DECIMAL','TINYINT','JSON','ENUM','BOOLEAN') NOT NULL,
    `length`             varchar(20)                                                                                      DEFAULT NULL,
    `is_nullable`        tinyint(1)                                                                                       DEFAULT 0,
    `default_value`      varchar(255)                                                                                     DEFAULT NULL,
    `is_unique`          tinyint(1)                                                                                       DEFAULT 0,
    `is_primary`         tinyint(1)                                                                                       DEFAULT 0,
    `auto_increment`     tinyint(1)                                                                                       DEFAULT 0,
    `comment`            text                                                                                             DEFAULT NULL COMMENT 'Comentário do banco (pode conter key:value)',
    `foreign_table`      varchar(64)                                                                                      DEFAULT NULL,
    `foreign_column`     varchar(64)                                                                                      DEFAULT NULL,
    `foreign_on_delete`  enum ('CASCADE','SET NULL','RESTRICT','NO ACTION')                                               DEFAULT NULL,
    `foreign_on_update`  enum ('CASCADE','SET NULL','RESTRICT','NO ACTION')                                               DEFAULT NULL,
    `is_visible_list`    tinyint(1)                                                                                       DEFAULT 1 COMMENT 'Visível na listagem',
    `is_visible_form`    tinyint(1)                                                                                       DEFAULT 1 COMMENT 'Visível no formulário',
    `is_visible_detail`  tinyint(1)                                                                                       DEFAULT 1 COMMENT 'Visível na visualização detalhada',
    `is_editable`        tinyint(1)                                                                                       DEFAULT 1 COMMENT 'Editável (readonly se 0)',
    `is_searchable`      tinyint(1)                                                                                       DEFAULT 0 COMMENT 'Aparece no campo de busca',
    `is_sortable`        tinyint(1)                                                                                       DEFAULT 1 COMMENT 'Permite ordenação na listagem',
    `is_filterable`      tinyint(1)                                                                                       DEFAULT 0 COMMENT 'Permite filtros na listagem',
    `input_type`         varchar(32)                                                                                      DEFAULT 'text' COMMENT 'text, textarea, select, radio, checkbox, date, datetime, file, color, wysiwyg, markdown, code',
    `input_options`      text                                                                                             DEFAULT NULL COMMENT 'Opções para select/radio (JSON): [{"value":"1","label":"Ativo"}]',
    `input_placeholder`  varchar(255)                                                                                     DEFAULT NULL,
    `input_prefix`       varchar(32)                                                                                      DEFAULT NULL COMMENT 'Prefixo visual (ex: R$, @)',
    `input_suffix`       varchar(32)                                                                                      DEFAULT NULL COMMENT 'Sufixo visual (ex: kg, %)',
    `input_mask`         varchar(64)                                                                                      DEFAULT NULL COMMENT 'Máscara de input (ex: (99) 99999-9999)',
    `validation_rules`   text                                                                                             DEFAULT NULL COMMENT 'Regras (JSON): {"required":true,"min":3,"max":100,"regex":"^[A-Z]"}',
    `validation_message` text                                                                                             DEFAULT NULL COMMENT 'Mensagens customizadas (JSON): {"required":"Campo obrigatório"}',
    `help_text`          varchar(500)                                                                                     DEFAULT NULL COMMENT 'Texto de ajuda abaixo do campo',
    `tooltip`            varchar(255)                                                                                     DEFAULT NULL COMMENT 'Tooltip ao passar mouse',
    `display_format`     varchar(64)                                                                                      DEFAULT NULL COMMENT 'Formato de exibição: date:d/m/Y, number:2, currency:BRL',
    `list_template`      text                                                                                             DEFAULT NULL COMMENT 'Template customizado na listagem (HTML/Twig)',
    `position`           int(11)                                                                                          DEFAULT 0 COMMENT 'Ordem de exibição',
    `tab_group`          varchar(64)                                                                                      DEFAULT NULL COMMENT 'Grupo/aba do campo',
    `column_width`       int(11)                                                                                          DEFAULT 12 COMMENT 'Largura em grid 12 colunas',
    `created_at`         timestamp                                                                                   NULL DEFAULT current_timestamp(),
    `created_by`         int(11)                                                                                          DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `table_column` (`table_id`, `name`),
    KEY `table_id` (`table_id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 356
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

INSERT INTO `panel_column`
VALUES (263, 1, 'username', 'Nome de usuário', 'VARCHAR', '255', 1, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1, 1,
        1, 1, 1, 1, 0, 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 12,
        '2025-11-24 06:00:52', NULL),
       (264, 1, 'email', 'E-mail', 'VARCHAR', '255', 0, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 1, 1, 1,
        0, 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, NULL, 12, '2025-11-24 06:00:52',
        NULL);
