DROP FUNCTION IF EXISTS CAPITAL
;

CREATE FUNCTION CAPITAL(input VARCHAR(1024))
    RETURNS VARCHAR(1024)
DETERMINISTIC
    BEGIN
        DECLARE len INT
        ;

        DECLARE i INT
        ;

        SET len = CHAR_LENGTH(input)
        ;

        SET input = LOWER(input)
        ;

        SET i = 0
        ;

        SET input = CONCAT(LEFT(input, i), UPPER(MID(input, i + 1, 1)), right(input, len - i - 1))
        ;

        SET i = i + 1
        ;

        WHILE (i < len) DO
            SET i = LOCATE(' ', input, i)
        ;

            IF i = 0 OR i = len
            THEN
                SET i = len
                ;
            ELSE
                SET input = CONCAT(LEFT(input, i), UPPER(MID(input, i + 1, 1)), right(input, len - i - 1))
                ;

                SET i = i + 1
                ;
            END IF
        ;

        END WHILE
        ;

        RETURN input
        ;

    END
;

CREATE OR REPLACE VIEW en_translations AS
    SELECT *
    FROM ltm_translations
    WHERE locale = 'en'
;

CREATE OR REPLACE VIEW ru_translations AS
    SELECT *
    FROM ltm_translations
    WHERE locale = 'ru'
;


CREATE OR REPLACE VIEW missing_translations AS
    SELECT ru.id, ru.group, ru.key, ru.value ru_value, en.value en_value
    FROM ru_translations ru
        JOIN en_translations en
            ON ru.`group` = en.`group` AND ru.`key` = en.`key`
    WHERE 1 = 1
          AND ru.value IS NULL OR ru.value = en.value
                                  AND concat(en.`group`, '.', en.`key`) NOT IN ('messages.lang_en', 'messages.lang_ru', 'messages.use-site')
;

SELECT *
FROM missing_translations
;


CREATE OR REPLACE VIEW have_translations AS
    SELECT ru.id, ru.group, ru.key, ru.value ru_value, en.value en_value
    FROM ru_translations ru
        JOIN en_translations en
            ON ru.`group` = en.`group` AND ru.`key` = en.`key`
    WHERE 1 = 1
          AND ru.value IS NOT NULL AND ru.value <> en.value
          AND concat(en.`group`, '.', en.`key`) NOT IN ('messages.lang_en', 'messages.lang_ru', 'messages.use-site')
;

SELECT *
FROM have_translations
;

DROP TEMPORARY TABLE IF EXISTS ru_missing_translations
;

CREATE TEMPORARY TABLE ru_missing_translations
    AS SELECT *
       FROM missing_translations
;

DROP TEMPORARY TABLE IF EXISTS ru_have_translations
;

CREATE TEMPORARY TABLE ru_have_translations
    AS SELECT *
       FROM have_translations
;

SELECT *
FROM ru_missing_translations
;

SELECT *
FROM ru_have_translations
;


SELECT *
FROM ru_missing_translations rm JOIN ru_have_translations rh ON rm.key = rh.key
;

# and rm.en_value = rh.en_value;


SELECT mt.locale, mt.`group`, mt.`key`, mt.value, ht.value
FROM (SELECT ht.`group`, ht.locale, ht.`key`, ht.value
      FROM
          (SELECT DISTINCT ht.`key`, ht.locale
           FROM ltm_translations ht WHERE `group` = 'page-titles') kt
          JOIN ltm_translations ht
              ON ht.`group` = 'messages' AND kt.`key` = ht.`key` AND kt.locale = ht.locale) ht
    LEFT OUTER JOIN ltm_translations mt
        ON mt.`group` = ht.`group` AND mt.`key` = ht.`key` AND mt.locale = ht.locale
;

##
# get missing keys
SET @group = 'page-titles'
;

#list missing translations that are in messages.
SELECT kt.`group`, kt.locale, kt.`key`, ht.value
FROM
    (SELECT kt.`key`, lt.locale, @group `group`
     FROM
         (SELECT DISTINCT ht.`key`
          FROM ltm_translations ht WHERE `group` LIKE BINARY @group) kt
         CROSS JOIN (SELECT DISTINCT ht.locale
                     FROM ltm_translations ht WHERE `group` LIKE BINARY @group) lt) kt
    LEFT JOIN ltm_translations ht
        ON ht.`group` = 'messages' AND kt.`key` = ht.`key` AND kt.locale = ht.locale
WHERE NOT exists(SELECT *
                 FROM ltm_translations lt WHERE lt.locale = kt.locale AND lt.`group` LIKE BINARY kt.`group` AND lt.`key` = kt.`key`)
;

# copy ones that exist
UPDATE
        ltm_translations mt JOIN ltm_translations ht
            ON mt.`group` = 'page-titles' AND ht.`group` = 'messages' AND mt.`key` = ht.`key` AND mt.locale = ht.locale
SET
    mt.value  = ht.value,
    mt.status = 1
WHERE mt.value IS NULL
;

# now insert missing ones
INSERT INTO ltm_translations
    (
        SELECT NULL, 1 status, ht.locale, @group `group`, ht.`key`, ht.value, ht.created_at, ht.updated_at, ht.source, NULL saved_value
        FROM
            (SELECT kt.`key`, lt.locale, @group `group`
             FROM
                 (SELECT DISTINCT ht.`key`
                  FROM ltm_translations ht WHERE `group` LIKE BINARY @group) kt
                 CROSS JOIN (SELECT DISTINCT ht.locale
                             FROM ltm_translations ht WHERE `group` LIKE BINARY @group) lt) kt
            LEFT JOIN ltm_translations ht
                ON ht.`group` = 'messages' AND kt.`key` = ht.`key` AND kt.locale = ht.locale
        WHERE ht.`key` IS NOT NULL AND ht.locale IS NOT NULL
              AND NOT exists(SELECT *
                             FROM ltm_translations lt WHERE lt.locale = kt.locale AND lt.`group` LIKE BINARY kt.`group` AND lt.`key` = kt.`key`)
    )
;


# search query that fills in missing locale,key combinations for a group
SELECT *
FROM ltm_translations rt
WHERE `key` LIKE '%for-beta%' or value like '%for-beta%'
UNION ALL
SELECT NULL id, 0 status, lt.locale, kt.`group`, kt.`key`, NULL value, NULL created_at, NULL updated_at, NULL source, NULL saved_value
FROM (SELECT DISTINCT locale
      FROM ltm_translations) lt
    CROSS JOIN (SELECT DISTINCT `key`, `group`
                FROM ltm_translations) kt
WHERE NOT exists(SELECT *
                 FROM ltm_translations tr WHERE tr.`key` = kt.`key` AND tr.`group` = kt.`group` AND tr.locale = lt.locale)
      AND `key` LIKE '%for-beta%'
;

# insert missing keys for other locales to reduce hit on the database for missing keys
INSERT into ltm_translations
SELECT NULL id, 0 status, lt.locale, kt.`group`, kt.`key`, NULL value, NULL created_at, NULL updated_at, NULL source, NULL saved_value
FROM (SELECT DISTINCT locale
      FROM ltm_translations) lt
    CROSS JOIN (SELECT DISTINCT `key`, `group`
                FROM ltm_translations) kt
WHERE NOT exists(SELECT * FROM ltm_translations tr WHERE tr.`key` = kt.`key` AND tr.`group` = kt.`group` AND tr.locale = lt.locale)
;


