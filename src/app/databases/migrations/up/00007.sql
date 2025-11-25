-- @description Create panel_config table and insert default data

CREATE TABLE IF NOT EXISTS `panel_config` (
    `id`           int(11)      NOT NULL AUTO_INCREMENT,
    `config_key`   varchar(100) NOT NULL,
    `config_value` text                  DEFAULT NULL,
    `config_type`  enum('text','number','boolean','json','file') DEFAULT 'text',
    `description`  varchar(255)          DEFAULT NULL,
    `created_at`   timestamp    NULL     DEFAULT current_timestamp(),
    `updated_at`   timestamp    NULL     DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO `panel_config` (`id`, `config_key`, `config_value`, `config_type`, `description`, `created_at`, `updated_at`)
VALUES
    (1, 'system_name', 'Methone Painel', 'text', 'Nome do sistema', NOW(), NOW()),
    (2, 'system_short_name', 'Painel', 'text', 'Nome abreviado', NOW(), NOW()),
    (3, 'logo_url', '/public/assets/img/methone.png', 'file', 'URL do logotipo', NOW(), NOW()),
    (4, 'favicon_url', '/public/assets/img/methone.png', 'file', 'URL do favicon', NOW(), NOW()),
    (5, 'theme_light', 'fantasy', 'text', 'Tema DaisyUI para light mode', NOW(), NOW()),
    (6, 'theme_dark', 'night', 'text', 'Tema DaisyUI para dark mode', NOW(), NOW()),
    (7, 'login_column', 'username', 'text', 'Coluna usada para login', NOW(), NOW()),
    (8, 'allow_registration', '0', 'boolean', 'Permitir registro de usuários', NOW(), NOW()),
    (9, 'items_per_page', '10', 'number', 'Itens por página em listagens', NOW(), NOW()),
    (10, 'timezone', 'America/Sao_Paulo', 'text', 'Timezone do sistema', NOW(), NOW()),
    (11, 'date_format', 'd/m/Y', 'text', 'Formato de exibição de datas', NOW(), NOW()),
    (12, 'allow_user_role_panel_access', '0', 'boolean', 'Permitir acesso ao painel para usuários com role user', NOW(), NOW());
