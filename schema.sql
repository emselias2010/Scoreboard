CREATE TABLE teams (
    id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL DEFAULT '',
    color VARCHAR(20) NOT NULL DEFAULT '#1e40af',
    logo VARCHAR(255) DEFAULT NULL,
    score INT NOT NULL DEFAULT 0,
    sets_won INT NOT NULL DEFAULT 0,
    side ENUM('left','right') NOT NULL DEFAULT 'left',
    timeouts INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
    id INT PRIMARY KEY,
    game_title VARCHAR(150) NOT NULL DEFAULT '',
    service_team INT NOT NULL DEFAULT 1,
    current_set INT NOT NULL DEFAULT 1,
    timeout_team INT NOT NULL DEFAULT 0,
    timeout_until DATETIME DEFAULT NULL,
    pause_until DATETIME DEFAULT NULL,
    game_over TINYINT NOT NULL DEFAULT 0,
    header_color VARCHAR(20) NOT NULL DEFAULT '#f4f4f6',
    side_swapped_set TINYINT NOT NULL DEFAULT 0,
    display_scale INT NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    set_number INT NOT NULL,
    team1_score INT NOT NULL DEFAULT 0,
    team2_score INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_type VARCHAR(50) NOT NULL,
    payload TEXT,
    undone TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO teams (id, name, color, side) VALUES
    (1, 'Team 1', '#1e40af', 'left'),
    (2, 'Team 2', '#b91c1c', 'right');

INSERT INTO settings (id) VALUES (1);
