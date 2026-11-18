ALTER TABLE school_courses
    ADD COLUMN IF NOT EXISTS acronym VARCHAR(20) NOT NULL DEFAULT '' AFTER name,
    ADD COLUMN IF NOT EXISTS duration_years TINYINT UNSIGNED NOT NULL DEFAULT 4 AFTER acronym;

ALTER TABLE school_courses
    DROP INDEX uq_courses_name,
    ADD UNIQUE KEY uq_school_courses_level_acronym_name (level_id, acronym, name);

UPDATE school_courses c
JOIN school_levels l ON l.id = c.level_id
SET c.acronym = UPPER(TRIM(SUBSTRING_INDEX(c.name, ' - ', 1))),
    c.name = TRIM(SUBSTRING_INDEX(c.name, ' - ', -1))
WHERE l.name = 'COLLEGE'
  AND c.acronym = ''
  AND c.name LIKE '% - %';

UPDATE school_courses c
JOIN school_levels l ON l.id = c.level_id
SET c.duration_years = LEAST(
    4,
    GREATEST(
        1,
        COALESCE(
            (SELECT MAX(y.year_number) FROM school_year_levels y WHERE y.course_id = c.id),
            4
        )
    )
)
WHERE l.name = 'COLLEGE';
