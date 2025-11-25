-- @description Create panel_table table and insert default data

CREATE TABLE IF NOT EXISTS `panel_table` (
    `id`           int(11)      NOT NULL AUTO_INCREMENT,
    `category_id`  int(11)      NOT NULL,
    `url_path`     varchar(255) NOT NULL,
    `name`         varchar(64)  NOT NULL COMMENT 'Nome da tabela no banco',
    `display_name` varchar(255)                                DEFAULT NULL COMMENT 'Nome exibido na UI',
    `description`  text                                        DEFAULT NULL,
    `icon`         varchar(255)                                DEFAULT NULL,
    `icon_type`    enum('text','url')                          DEFAULT 'text',
    `internal`     tinyint(1)   NOT NULL                       DEFAULT 0,
    `form_layout`  enum('single','tabs','accordion','wizard')  DEFAULT 'single' COMMENT 'Layout do formulário',
    `is_active`    tinyint(1)                                  DEFAULT 1,
    `menu_order`   int(11)                                     DEFAULT NULL,
    `created_at`   timestamp    NULL                           DEFAULT current_timestamp(),
    `created_by`   int(11)                                     DEFAULT NULL,
    `updated_at`   timestamp    NULL                           DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `updated_by`   int(11)                                     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`),
    UNIQUE KEY `url_path` (`url_path`),
    KEY `category_id` (`category_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO `panel_table` (`id`, `category_id`, `url_path`, `name`, `display_name`, `description`, `icon`, `icon_type`, `internal`, `form_layout`, `is_active`, `menu_order`, `created_at`, `created_by`, `updated_at`, `updated_by`)
VALUES
    (1, 1, 'user', 'user', 'Usuários', 'Gerenciar usuários do sistema', 'group', 'text', 1, 'single', 1, 0, NOW(), NULL, NOW(), NULL),
    (6, 2, 'module', 'panel_table', 'Módulos', 'Gerenciar módulos do sistema', 'table_chart', 'text', 1, 'single', 1, 0, NOW(), NULL, NOW(), NULL),
    (7, 2, 'category', 'panel_category', 'Categorias', 'Gerenciar categorias de módulos', 'category', 'text', 1, 'single', 1, 2, NOW(), NULL, NOW(), NULL),
    (8, 2, 'role', 'panel_role_info', 'Permissões', 'Gerenciar roles e permissões do sistema', 'badge', 'text', 1, 'single', 1, 3, NOW(), NULL, NOW(), NULL),
    (21, 2, 'uploads', 'uploads', 'Uploads', 'Gerenciar uploads gerais do sistema (imagens e arquivos públicos)', 'upload_file', 'text', 1, 'single', 1, 4, NOW(), NULL, NOW(), NULL),
    (49, 2, 'audit', 'panel_log', 'Auditoria', 'Visualizar logs de auditoria do sistema', 'shield', 'text', 1, 'single', 1, 100, NOW(), NULL, NOW(), NULL),
    (50, 2, 'config', 'panel_config', 'Configurações', 'Configurações gerais do sistema', 'settings', 'text', 1, 'single', 1, 999, NOW(), NULL, NOW(), NULL);
