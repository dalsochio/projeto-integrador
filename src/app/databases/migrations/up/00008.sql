-- @description Create panel_log table for audit

CREATE TABLE IF NOT EXISTS `panel_log` (
    `id`          int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     int(10) UNSIGNED          DEFAULT NULL COMMENT 'ID do usuário que executou a ação',
    `action`      varchar(10)      NOT NULL COMMENT 'Método HTTP: POST, PUT, PATCH, DELETE',
    `table_name`  varchar(100)     NOT NULL COMMENT 'Nome da tabela afetada',
    `record_id`   varchar(100)              DEFAULT NULL COMMENT 'ID do registro afetado',
    `data`        longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Dados enviados na requisição (payload)' CHECK (json_valid(`data`)),
    `changes`     longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Mudanças aplicadas (antes/depois para UPDATE)' CHECK (json_valid(`changes`)),
    `ip_address`  varchar(45)               DEFAULT NULL COMMENT 'Endereço IP de origem',
    `user_agent`  varchar(500)              DEFAULT NULL COMMENT 'User agent do navegador/cliente',
    `route_type`  enum('api','resource')    DEFAULT 'resource' COMMENT 'Tipo de rota: api (PHP-CRUD-API) ou resource (FlightPHP)',
    `created_at`  timestamp        NULL     DEFAULT current_timestamp() COMMENT 'Data/hora da ação',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_table_name` (`table_name`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_action` (`action`),
    KEY `idx_record_id` (`record_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='label:Auditoria';
