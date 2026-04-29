-- 0003_game_tables.sql -- Game tables: teams, picks, matches, progress & awards

CREATE TABLE {prefix:teams} (
    id   INTEGER PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    pot  TINYINT      NOT NULL,
    UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {prefix:picks} (
    id         INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id    INTEGER  NOT NULL,
    team_id    INTEGER  NOT NULL,
    pot        TINYINT  NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE (user_id, pot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {prefix:matches} (
    id                   INTEGER PRIMARY KEY AUTO_INCREMENT,
    phase                VARCHAR(30) NOT NULL,
    match_date           DATETIME    NULL,
    home_team_id         INTEGER     NOT NULL,
    away_team_id         INTEGER     NOT NULL,
    home_goals           TINYINT     NULL,
    away_goals           TINYINT     NULL,
    home_yellows         TINYINT     NOT NULL DEFAULT 0,
    away_yellows         TINYINT     NOT NULL DEFAULT 0,
    home_double_yellows  TINYINT     NOT NULL DEFAULT 0,
    away_double_yellows  TINYINT     NOT NULL DEFAULT 0,
    home_reds            TINYINT     NOT NULL DEFAULT 0,
    away_reds            TINYINT     NOT NULL DEFAULT 0,
    home_comeback        TINYINT(1)  NOT NULL DEFAULT 0,
    away_comeback        TINYINT(1)  NOT NULL DEFAULT 0,
    played               TINYINT(1)  NOT NULL DEFAULT 0,
    created_at           DATETIME    NOT NULL,
    updated_at           DATETIME    NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {prefix:tournament_progress} (
    id          INTEGER PRIMARY KEY AUTO_INCREMENT,
    team_id     INTEGER     NOT NULL,
    achievement VARCHAR(30) NOT NULL,
    created_at  DATETIME    NOT NULL,
    UNIQUE (team_id, achievement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {prefix:tournament_awards} (
    id          INTEGER PRIMARY KEY AUTO_INCREMENT,
    award_type  VARCHAR(30)  NOT NULL,
    team_id     INTEGER      NOT NULL,
    player_name VARCHAR(150) NULL,
    created_at  DATETIME     NOT NULL,
    UNIQUE (award_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the 48 World Cup teams

INSERT INTO {prefix:teams} (name, pot) VALUES
    ('Alemania', 1),
    ('Argentina', 1),
    ('Brasil', 1),
    ('Francia', 1),
    ('España', 1),
    ('Inglaterra', 1),
    ('Países Bajos', 1),
    ('Portugal', 1),
    ('Bélgica', 2),
    ('Colombia', 2),
    ('Croacia', 2),
    ('Marruecos', 2),
    ('Rep. de Corea', 2),
    ('Senegal', 2),
    ('Suiza', 2),
    ('Uruguay', 2),
    ('Ecuador', 3),
    ('EEUU', 3),
    ('Japón', 3),
    ('México', 3),
    ('Noruega', 3),
    ('Paraguay', 3),
    ('Suecia', 3),
    ('Turquía', 3),
    ('Australia', 4),
    ('Austria', 4),
    ('Canadá', 4),
    ('Chequia', 4),
    ('Egipto', 4),
    ('Escocia', 4),
    ('Irán', 4),
    ('Túnez', 4),
    ('Argelia', 5),
    ('Catar', 5),
    ('Costa de Marfil', 5),
    ('Ghana', 5),
    ('Panamá', 5),
    ('RD Congo', 5),
    ('Sudáfrica', 5),
    ('Uzbekistán', 5),
    ('Arabia Saudí', 6),
    ('Bosnia y Herzegovina', 6),
    ('Curazao', 6),
    ('Haití', 6),
    ('Irak', 6),
    ('Islas del Cabo Verde', 6),
    ('Jordania', 6),
    ('Nueva Zelanda', 6);
