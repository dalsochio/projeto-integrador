-- @description Create panel_role table and insert default data

CREATE TABLE IF NOT EXISTS `panel_role`
(
    `id`    int(11)      NOT NULL AUTO_INCREMENT,
    `ptype` varchar(255) NOT NULL,
    `v0`    varchar(255) DEFAULT NULL,
    `v1`    varchar(255) DEFAULT NULL,
    `v2`    varchar(255) DEFAULT NULL,
    `v3`    varchar(255) DEFAULT NULL,
    `v4`    varchar(255) DEFAULT NULL,
    `v5`    varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_casbin_unique` (`ptype`, `v0`(191), `v1`(191), `v2`(191), `v3`(191)) USING HASH
) ENGINE = InnoDB
  AUTO_INCREMENT = 55
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

INSERT INTO `panel_role`
VALUES (1, 'p', 'admin', '*', '*', '*', NULL, NULL),
       (2, 'g', 'user:1', 'admin', NULL, NULL, NULL, NULL);
