-- @description Create panel_category table and insert default data

CREATE TABLE IF NOT EXISTS `panel_category` (
    `id`           int(11)      NOT NULL AUTO_INCREMENT,
    `url_path`     varchar(255) NOT NULL,
    `name`         varchar(64)  NOT NULL,
    `display_name` varchar(255)          DEFAULT NULL,
    `description`  text                  DEFAULT NULL,
    `icon`         varchar(255)          DEFAULT NULL,
    `icon_type`    enum('text','url')    DEFAULT 'text',
    `internal`     tinyint(1)   NOT NULL DEFAULT 0,
    `is_active`    tinyint(1)            DEFAULT 1,
    `menu_order`   int(11)               DEFAULT NULL,
    `created_at`   timestamp    NULL     DEFAULT current_timestamp(),
    `created_by`   int(11)               DEFAULT NULL,
    `updated_at`   timestamp    NULL     DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `updated_by`   int(11)               DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO `panel_category` (`id`, `url_path`, `name`, `display_name`, `description`, `icon`, `icon_type`, `internal`, `is_active`, `menu_order`, `created_at`, `created_by`, `updated_at`, `updated_by`)
VALUES
    (1, 'administration', 'administration', 'Administração', '', 'person_apron', 'text', 1, 1, 1, NOW(), NULL, NOW(), NULL),
    (2, 'panel', 'panel', 'Painel', 'Módulos de administração do sistema', 'settings', 'text', 1, 1, 0, NOW(), NULL, NOW(), NULL);
