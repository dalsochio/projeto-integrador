-- @description Create panel_role_info table and insert default data

CREATE TABLE IF NOT EXISTS `panel_role_info` (
    `id`           int(11)      NOT NULL AUTO_INCREMENT,
    `role_name`    varchar(64)  NOT NULL COMMENT 'Nome único da role (ex: admin, user, guest)',
    `display_name` varchar(255) NOT NULL COMMENT 'Nome exibido na UI',
    `description`  text                  DEFAULT NULL COMMENT 'Descrição da role',
    `is_system`    tinyint(1)   NOT NULL DEFAULT 0 COMMENT 'Role de sistema (não pode ser deletada)',
    `is_locked`    tinyint(1)   NOT NULL DEFAULT 0 COMMENT 'Permissões travadas (não podem ser alteradas)',
    `created_at`   timestamp    NULL     DEFAULT current_timestamp(),
    `created_by`   int(11)               DEFAULT NULL,
    `updated_at`   timestamp    NULL     DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `updated_by`   int(11)               DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Informações complementares das roles do Casbin';

INSERT INTO `panel_role_info` (`id`, `role_name`, `display_name`, `description`, `is_system`, `is_locked`, `created_at`, `created_by`, `updated_at`, `updated_by`)
VALUES
    (1, 'guest', 'Visitante', 'Acesso público sem autenticação', 1, 0, NOW(), NULL, NOW(), NULL),
    (2, 'user', 'Usuário', 'Usuário autenticado com permissões padrão', 1, 0, NOW(), NULL, NOW(), NULL),
    (3, 'admin', 'Administrador', 'Acesso total ao sistema (não pode ser modificado)', 1, 1, NOW(), NULL, NOW(), NULL);
