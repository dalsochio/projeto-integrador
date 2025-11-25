-- @description Create user table and insert default admin user

CREATE TABLE IF NOT EXISTS `user` (
    `id`         int(11)                          NOT NULL AUTO_INCREMENT,
    `username`   varchar(255)                     DEFAULT NULL,
    `email`      varchar(255)                     NOT NULL,
    `password`   varchar(255)                     NOT NULL,
    `theme`      enum('light','dark','system')    NOT NULL DEFAULT 'system',
    `is_active`  tinyint(1)                       NOT NULL DEFAULT 1,
    `created_at` timestamp                        NULL DEFAULT current_timestamp(),
    `created_by` int(11)                          DEFAULT NULL,
    `updated_at` timestamp                        NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `updated_by` int(11)                          DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user` (`id`, `username`, `email`, `password`, `theme`, `is_active`, `created_at`, `created_by`, `updated_at`, `updated_by`)
VALUES (1, 'admin', 'contato@example.com', '$2y$12$GAD2Z9L9OwR6MlEiIQeDAeYUsTLrRfP5ktJl3p4tdK84cMqX.3RTu', 'system', 1, NOW(), NULL, NOW(), NULL);
