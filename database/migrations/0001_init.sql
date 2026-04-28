-- 0001_init.sql -- Initial schema for Porra Mundial 2026
--
-- All identifiers use the {prefix:tablename} placeholder which is
-- substituted with the configured table prefix at runtime. This file
-- is also legal SQLite syntax (used in tests).

CREATE TABLE {prefix:users} (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    full_name     VARCHAR(150) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'user',
    status        VARCHAR(20)  NOT NULL DEFAULT 'active',
    mfa_enforced  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NULL,
    updated_at    DATETIME     NULL,
    last_login_at DATETIME     NULL,
    deleted_at    DATETIME     NULL,
    UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {prefix:invitations} (
    id          INTEGER PRIMARY KEY AUTO_INCREMENT,
    email       VARCHAR(190) NOT NULL,
    full_name   VARCHAR(150) NOT NULL,
    role        VARCHAR(20)  NOT NULL DEFAULT 'user',
    token_hash  CHAR(64)     NOT NULL,
    created_by  INTEGER      NOT NULL,
    created_at  DATETIME     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    revoked_at  DATETIME     NULL,
    UNIQUE (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {prefix:password_resets} (
    id          INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id     INTEGER      NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL,
    UNIQUE (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {prefix:mfa_credentials} (
    id                      INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id                 INTEGER      NOT NULL,
    type                    VARCHAR(20)  NOT NULL,
    label                   VARCHAR(100) NOT NULL,
    secret                  TEXT         NULL,
    webauthn_credential_id  TEXT         NULL,
    webauthn_public_key     TEXT         NULL,
    webauthn_sign_count     INTEGER      NOT NULL DEFAULT 0,
    webauthn_aaguid         VARCHAR(36)  NULL,
    transports              VARCHAR(255) NULL,
    created_at              DATETIME     NOT NULL,
    last_used_at            DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {prefix:mfa_recovery_codes} (
    id          INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id     INTEGER      NOT NULL,
    code_hash   VARCHAR(255) NOT NULL,
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {prefix:settings} (
    k VARCHAR(100) NOT NULL PRIMARY KEY,
    v TEXT         NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {prefix:audit_log} (
    id         INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id    INTEGER      NULL,
    event      VARCHAR(80)  NOT NULL,
    ip         VARCHAR(45)  NULL,
    ua         VARCHAR(255) NULL,
    data       TEXT         NULL,
    created_at DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
