-- @description Create user table and insert default admin user

CREATE TABLE IF NOT EXISTS `user`
(
    `id`         int(11)                        NOT NULL AUTO_INCREMENT,
    `username`   varchar(255)                            DEFAULT NULL,
    `email`      varchar(255)                   NOT NULL,
    `password`   varchar(255)                   NOT NULL,
    `theme`      enum ('light','dark','system') NOT NULL DEFAULT 'system',
    `is_active`  tinyint(1)                     NOT NULL DEFAULT 1,
    `created_at` timestamp                      NULL     DEFAULT current_timestamp(),
    `created_by` int(11)                                 DEFAULT NULL,
    `updated_at` timestamp                      NULL     DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `updated_by` int(11)                                 DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    UNIQUE KEY `username` (`username`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 8
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

INSERT INTO `user`
VALUES (1, 'admin', 'contato@example.com', '$2y$12$vFywywrdrBHtV8ijXYD9OO9IbOzXQoljGzVDdSMyCTdPKI9c1PUxi', 'system', 1,
        '2025-10-13 03:10:55', NULL, '2025-11-24 13:37:11', NULL);
