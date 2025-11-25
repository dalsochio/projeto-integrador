-- @description Create panel_column table and insert default data

CREATE TABLE IF NOT EXISTS `panel_column` (
    `id`               int(11)     NOT NULL AUTO_INCREMENT,
    `table_id`         int(11)     NOT NULL,
    `name`             varchar(64) NOT NULL COMMENT 'Nome da coluna no banco',
    `display_name`     varchar(255)         DEFAULT NULL COMMENT 'Nome exibido na UI',
    `type`             enum('INT','VARCHAR','TEXT','DATE','DATETIME','DECIMAL','TINYINT','JSON','ENUM','BOOLEAN') NOT NULL,
    `length`           varchar(20)          DEFAULT NULL,
    `is_nullable`      tinyint(1)           DEFAULT 0,
    `default_value`    varchar(255)         DEFAULT NULL,
    `is_unique`        tinyint(1)           DEFAULT 0,
    `is_primary`       tinyint(1)           DEFAULT 0,
    `auto_increment`   tinyint(1)           DEFAULT 0,
    `comment`          text                 DEFAULT NULL COMMENT 'Comentário do banco (pode conter key:value)',
    `foreign_table`    varchar(64)          DEFAULT NULL,
    `foreign_column`   varchar(64)          DEFAULT NULL,
    `foreign_on_delete` enum('CASCADE','SET NULL','RESTRICT','NO ACTION') DEFAULT NULL,
    `foreign_on_update` enum('CASCADE','SET NULL','RESTRICT','NO ACTION') DEFAULT NULL,
    `is_visible_list`  tinyint(1)           DEFAULT 1 COMMENT 'Visível na listagem',
    `is_visible_form`  tinyint(1)           DEFAULT 1 COMMENT 'Visível no formulário',
    `is_visible_detail` tinyint(1)          DEFAULT 1 COMMENT 'Visível na visualização detalhada',
    `is_editable`      tinyint(1)           DEFAULT 1 COMMENT 'Editável (readonly se 0)',
    `is_searchable`    tinyint(1)           DEFAULT 0 COMMENT 'Aparece no campo de busca',
    `is_sortable`      tinyint(1)           DEFAULT 1 COMMENT 'Permite ordenação na listagem',
    `is_filterable`    tinyint(1)           DEFAULT 0 COMMENT 'Permite filtros na listagem',
    `display_format`   varchar(64)          DEFAULT NULL COMMENT 'Formato de exibição: date:d/m/Y, number:2, currency:BRL',
    `list_template`    text                 DEFAULT NULL COMMENT 'Template customizado na listagem (HTML/Twig)',
    `created_at`       timestamp   NULL     DEFAULT current_timestamp(),
    `created_by`       int(11)              DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `table_column` (`table_id`, `name`),
    KEY `table_id` (`table_id`),
    CONSTRAINT `fk_panel_column_table_id` FOREIGN KEY (`table_id`) REFERENCES `panel_table` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO `panel_column` (`id`, `table_id`, `name`, `display_name`, `type`, `length`, `is_nullable`, `default_value`, `is_unique`, `is_primary`, `auto_increment`, `comment`, `foreign_table`, `foreign_column`, `foreign_on_delete`, `foreign_on_update`, `is_visible_list`, `is_visible_form`, `is_visible_detail`, `is_editable`, `is_searchable`, `is_sortable`, `is_filterable`, `display_format`, `list_template`, `created_at`, `created_by`)
VALUES
    (263, 1, 'username', 'Nome de usuário', 'VARCHAR', '255', 1, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 1, 1, 1, 0, NULL, NULL, NOW(), NULL),
    (264, 1, 'email', 'E-mail', 'VARCHAR', '255', 0, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 1, 1, 1, 0, NULL, NULL, NOW(), NULL);
