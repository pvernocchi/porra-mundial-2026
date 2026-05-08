-- 0006_leaderboard_snapshots.sql -- Snapshots of the leaderboard so the
-- broadcast report can highlight participants whose ranking position has
-- moved significantly since the previous snapshot.

CREATE TABLE {prefix:leaderboard_snapshots} (
    id          INTEGER PRIMARY KEY AUTO_INCREMENT,
    snapshot_at DATETIME       NOT NULL,
    user_id     INTEGER        NOT NULL,
    position    INTEGER        NOT NULL,
    total       DECIMAL(10, 2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_leaderboard_snapshots_at ON {prefix:leaderboard_snapshots} (snapshot_at);
CREATE INDEX idx_leaderboard_snapshots_user ON {prefix:leaderboard_snapshots} (user_id);
