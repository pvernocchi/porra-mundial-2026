-- 0005_rename_venezuela_to_uzbekistan.sql -- Replace Venezuela with Uzbekistán in pot 5

UPDATE {prefix:teams} SET name = 'Uzbekistán' WHERE name = 'Venezuela' AND pot = 5;
