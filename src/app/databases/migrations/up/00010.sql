-- @description Add foreign key constraint to panel_column

ALTER TABLE `panel_column`
    ADD CONSTRAINT `fk_panel_column_table_id` FOREIGN KEY (`table_id`) REFERENCES `panel_table` (`id`) ON DELETE CASCADE;
