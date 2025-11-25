-- @description Create panel_config table and insert default data

CREATE TABLE IF NOT EXISTS `panel_config`
(
    `id`           int(11)      NOT NULL AUTO_INCREMENT,
    `config_key`   varchar(100) NOT NULL,
    `config_value` text                                           DEFAULT NULL,
    `config_type`  enum ('text','number','boolean','json','file') DEFAULT 'text',
    `description`  varchar(255)                                   DEFAULT NULL,
    `created_at`   timestamp    NULL                              DEFAULT current_timestamp(),
    `updated_at`   timestamp    NULL                              DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `config_key` (`config_key`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 13
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

INSERT INTO `panel_config`
VALUES (1, 'system_name', 'Methone Painel', 'text', 'Nome do sistema', '2025-11-24 03:39:44', '2025-11-24 03:46:00'),
       (2, 'system_short_name', 'Painel', 'text', 'Nome abreviado', '2025-11-24 03:39:44', '2025-11-24 03:39:44'),
       (3, 'logo_url', '/public/assets/img/methone.png', 'file', 'URL do logotipo', '2025-11-24 03:39:44',
        '2025-11-24 23:32:44'),
       (4, 'favicon_url', '/public/assets/img/methone.png', 'file', 'URL do favicon', '2025-11-24 03:39:44',
        '2025-11-24 23:32:46'),
       (5, 'theme_light', 'light', 'text', 'Tema DaisyUI para light mode', '2025-11-24 03:39:44',
        '2025-11-24 03:46:48'),
       (6, 'theme_dark', 'dark', 'text', 'Tema DaisyUI para dark mode', '2025-11-24 03:39:44', '2025-11-24 03:46:48'),
       (7, 'login_column', 'username', 'text', 'Coluna usada para login', '2025-11-24 03:39:44', '2025-11-24 03:39:44'),
       (8, 'allow_registration', '0', 'boolean', 'Permitir registro de usuários', '2025-11-24 03:39:44',
        '2025-11-24 03:40:04'),
       (9, 'items_per_page', '10', 'number', 'Itens por página em listagens', '2025-11-24 03:39:44',
        '2025-11-24 03:39:44'),
       (10, 'timezone', 'America/Sao_Paulo', 'text', 'Timezone do sistema', '2025-11-24 03:39:44',
        '2025-11-24 03:39:44'),
       (11, 'date_format', 'd/m/Y', 'text', 'Formato de exibição de datas', '2025-11-24 03:39:44',
        '2025-11-24 03:39:44'),
       (12, 'test_cli', 'valor_teste_cli', 'text', NULL, '2025-11-24 03:44:48', '2025-11-24 03:44:48');
