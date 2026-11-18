ALTER TABLE student_import_rows
    ADD COLUMN source_data LONGTEXT NULL AFTER student_identifier;
