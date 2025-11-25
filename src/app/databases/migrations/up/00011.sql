-- @description Update default theme values to corporate and business

UPDATE `panel_config` SET `config_value` = 'corporate' WHERE `config_key` = 'theme_light';
UPDATE `panel_config` SET `config_value` = 'business' WHERE `config_key` = 'theme_dark';
