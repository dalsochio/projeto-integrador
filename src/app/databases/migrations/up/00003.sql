-- @description Create panel_table table and insert default data

CREATE TABLE IF NOT EXISTS `panel_table`
(
    `id`            int(11)      NOT NULL AUTO_INCREMENT,
    `category_id`   int(11)      NOT NULL,
    `url_path`      varchar(255) NOT NULL,
    `name`          varchar(64)  NOT NULL COMMENT 'Nome da tabela no banco',
    `display_name`  varchar(255)                                DEFAULT NULL COMMENT 'Nome exibido na UI',
    `description`   text                                        DEFAULT NULL,
    `icon`          varchar(255)                                DEFAULT NULL,
    `icon_type`     enum ('text','url')                         DEFAULT 'text',
    `internal`      tinyint(1)   NOT NULL                       DEFAULT 0,
    `allow_create`  tinyint(1)                                  DEFAULT 1 COMMENT 'Permite criação de registros',
    `allow_edit`    tinyint(1)                                  DEFAULT 1 COMMENT 'Permite edição de registros',
    `allow_delete`  tinyint(1)                                  DEFAULT 1 COMMENT 'Permite exclusão de registros',
    `allow_export`  tinyint(1)                                  DEFAULT 1 COMMENT 'Permite exportação de dados',
    `allow_import`  tinyint(1)                                  DEFAULT 0 COMMENT 'Permite importação de dados',
    `form_layout`   enum ('single','tabs','accordion','wizard') DEFAULT 'single' COMMENT 'Layout do formulário',
    `form_tabs`     text                                        DEFAULT NULL COMMENT 'Configuração de abas (JSON): [{"name":"Dados Básicos","fields":["name","email"]}]',
    `before_create` text                                        DEFAULT NULL COMMENT 'Código/webhook executado antes de criar',
    `after_create`  text                                        DEFAULT NULL COMMENT 'Código/webhook executado após criar',
    `before_update` text                                        DEFAULT NULL COMMENT 'Código/webhook executado antes de atualizar',
    `after_update`  text                                        DEFAULT NULL COMMENT 'Código/webhook executado após atualizar',
    `before_delete` text                                        DEFAULT NULL COMMENT 'Código/webhook executado antes de deletar',
    `after_delete`  text                                        DEFAULT NULL COMMENT 'Código/webhook executado após deletar',
    `is_active`     tinyint(1)                                  DEFAULT 1,
    `menu_order`    int(11)                                     DEFAULT NULL,
    `created_at`    timestamp    NULL                           DEFAULT current_timestamp(),
    `created_by`    int(11)                                     DEFAULT NULL,
    `updated_at`    timestamp    NULL                           DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `updated_by`    int(11)                                     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`),
    UNIQUE KEY `url_path` (`url_path`),
    KEY `category_id` (`category_id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 64
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

INSERT INTO `panel_table`
VALUES (1, 1, 'user', 'user', 'Usuários', 'Gerenciar usuários do sistema', 'group', 'text', 1, 1, 1, 1, 1, 0, 'single',
        NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2025-10-19 23:16:09', NULL, '2025-11-24 04:17:27', NULL),
       (6, 2, 'module', 'panel_table', 'Módulos', 'Gerenciar módulos do sistema', 'table_chart', 'text', 1, 1, 1, 1, 1,
        0, 'single', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2025-11-10 05:31:35', NULL, '2025-11-10 05:31:35',
        NULL),
       (7, 2, 'category', 'panel_category', 'Categorias', 'Gerenciar categorias de módulos', 'category', 'text', 1, 1,
        1, 1, 1, 0, 'single', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 2, '2025-11-10 05:31:35', NULL,
        '2025-11-21 08:01:49', NULL),
       (8, 2, 'role', 'panel_role_info', 'Roles', 'Gerenciar roles e permissões do sistema', 'group', 'text', 1, 1, 1,
        1, 1, 0, 'single', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 3, '2025-11-10 05:47:59', NULL,
        '2025-11-21 08:01:49', NULL),
       (21, 2, 'uploads', 'uploads', 'Uploads', 'Gerenciar uploads gerais do sistema (imagens e arquivos públicos)',
        'upload_file', 'text', 1, 1, 0, 1, 0, 0, 'single', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 4,
        '2025-11-21 05:04:24', NULL, '2025-11-21 08:01:49', NULL),
       (49, 2, 'audit', 'panel_log', 'Auditoria', 'Visualizar logs de auditoria do sistema', 'shield', 'text', 1, 0, 0,
        0, 1, 0, 'single', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 100, '2025-11-24 03:03:51', NULL,
        '2025-11-24 03:16:06', NULL),
       (50, 2, 'config', 'panel_config', 'Configurações', 'Configurações gerais do sistema', 'settings', 'text', 1, 1,
        1, 1, 1, 0, 'single', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 999, '2025-11-24 03:28:51', NULL,
        '2025-11-24 03:29:10', NULL);
