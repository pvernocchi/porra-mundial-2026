-- 0004_add_team_name.sql -- Add funny team name to users

ALTER TABLE {prefix:users} ADD COLUMN team_name VARCHAR(150) NULL DEFAULT NULL;
