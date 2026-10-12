<?php
header('Content-Type: application/json; charset=utf-8');
require 'db.php';
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
]);
session_start();

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Nicht autorisiert']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'msg' => 'Ungültiges Sicherheitstoken']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if (!$action) {
    echo json_encode(['ok'=>false,'msg'=>'no action']);
    exit;
}

function json($arr){ echo json_encode($arr); exit; }

function loadState() {
    $settings = $GLOBALS['pdo']->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $teams = $GLOBALS['pdo']->query("SELECT * FROM teams ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    return [$settings, $teams];
}

function ensureSideSwapColumn() {
    $pdo = $GLOBALS['pdo'];
    $col = $pdo->query("SHOW COLUMNS FROM settings LIKE 'side_swapped_set'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN side_swapped_set INT DEFAULT 0");
    }
}

function ensure_actions_table() {
    $pdo = $GLOBALS['pdo'];
    $pdo->exec("CREATE TABLE IF NOT EXISTS actions (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        action_type VARCHAR(100) DEFAULT NULL,
        payload LONGTEXT,
        undone TINYINT(1) DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function ensureDisplayScaleColumn() {
    $pdo = $GLOBALS['pdo'];
    $col = $pdo->query("SHOW COLUMNS FROM settings LIKE 'display_scale'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN display_scale INT DEFAULT 100");
    }
}

function record_action_before_change($action_type = null) {
    $pdo = $GLOBALS['pdo'];
    ensure_actions_table();
    $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $teams = $pdo->query("SELECT * FROM teams ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $sets = $pdo->query("SELECT * FROM sets ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $payload = ['settings' => $settings, 'teams' => $teams, 'sets' => $sets];
    $stmt = $pdo->prepare("INSERT INTO actions (action_type, payload) VALUES (?, ?)");
    $stmt->execute([$action_type, json_encode($payload)]);
}

if ($action === 'set_title') {
    record_action_before_change('set_title');
    $title = trim($_POST['title'] ?? '');
    $stmt = $GLOBALS['pdo']->prepare("UPDATE settings SET game_title = ? WHERE id = 1");
    $stmt->execute([$title]);
    json(['ok'=>true]);
}

if ($action === 'set_team') {
    record_action_before_change('set_team');
    $team = (int)($_POST['team'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#ffffff');
    $existingLogo = trim($_POST['existing_logo'] ?? '');
    if ($team <= 0) json(['ok' => false, 'msg' => 'Ungültiges Team']);
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) json(['ok' => false, 'msg' => 'Ungültige Teamfarbe']);
    $logoPath = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = $_FILES['logo'];
        $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
        if ($up['error'] !== UPLOAD_ERR_OK || !in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            json(['ok' => false, 'msg' => 'Ungültige Logodatei']);
        }
        if (($up['size'] ?? 0) > 5 * 1024 * 1024 || @getimagesize($up['tmp_name']) === false) {
            json(['ok' => false, 'msg' => 'Ungültige oder zu große Bilddatei']);
        }
        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
        $logoPath = 'uploads/logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($up['tmp_name'], $logoPath)) {
            json(['ok' => false, 'msg' => 'Fehler beim Speichern des Logos']);
        }
    }
    $stmt = $pdo->prepare("UPDATE teams SET name = ?, color = ? WHERE id = ?");
    $stmt->execute([$name, $color, $team]);
    if ($logoPath !== null) {
        $pdo->prepare("UPDATE teams SET logo = ? WHERE id = ?")->execute([$logoPath, $team]);
    } elseif ($existingLogo !== '') {
        $base = basename($existingLogo);
        $selectedPath = 'uploads/' . $base;
        if (!preg_match('/\.(png|jpe?g|gif|webp)$/i', $base) || !is_file(__DIR__ . DIRECTORY_SEPARATOR . $selectedPath)) {
            json(['ok' => false, 'msg' => 'Vorhandenes Logo nicht gefunden']);
        }
        $pdo->prepare("UPDATE teams SET logo = ? WHERE id = ?")->execute([$selectedPath, $team]);
    }
    json(['ok' => true]);
}

if ($action === 'upload_logo') {
    if (!isset($_FILES['logo']) || !isset($_POST['team'])) json(['ok' => false, 'msg' => 'Keine Datei hochgeladen']);
    $team = (int)$_POST['team'];
    record_action_before_change('upload_logo');
    $up = $_FILES['logo'];
    if ($up['error'] !== UPLOAD_ERR_OK) json(['ok' => false, 'msg' => 'Fehler beim Hochladen']);
    $ext = pathinfo($up['name'], PATHINFO_EXTENSION);
    $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    if (!in_array(strtolower($ext), $allowed)) json(['ok' => false, 'msg' => 'Ungültige Dateierweiterung']);
    if (($up['size'] ?? 0) > 5 * 1024 * 1024 || @getimagesize($up['tmp_name']) === false) {
        json(['ok' => false, 'msg' => 'Ungültige oder zu große Bilddatei']);
    }
    if (!is_dir('uploads')) mkdir('uploads', 0777, true);
    $filename = 'uploads/logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($up['tmp_name'], $filename)) json(['ok' => false, 'msg' => 'Fehler beim Verschieben der Datei']);
    $stmt = $pdo->prepare("UPDATE teams SET logo = ? WHERE id = ?");
    $stmt->execute([$filename, $team]);
    json(['ok' => true, 'logo' => $filename]);
}

if ($action === 'set_header_color') {
    record_action_before_change('set_header_color');
    $color = trim($_POST['color'] ?? '#f4f4f6');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) json(['ok' => false, 'msg' => 'Ungültige Kopfzeilenfarbe']);
    $pdo->prepare("UPDATE settings SET header_color = ? WHERE id = 1")->execute([$color]);
    json(['ok' => true, 'color' => $color]);
}

if ($action === 'set_zoom') {

    ensureDisplayScaleColumn();
    record_action_before_change('set_zoom');
    $op = $_POST['op'] ?? ($_GET['op'] ?? 'in');
    $step = 10;
    $row = $pdo->query("SELECT display_scale FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $cur = intval($row['display_scale'] ?? 100);
    if ($op === 'in') $cur += $step;
    elseif ($op === 'out') $cur -= $step;
    elseif (is_numeric($op)) $cur = intval($op);
    if ($cur < 50) $cur = 50;
    if ($cur > 300) $cur = 300;
    $pdo->prepare("UPDATE settings SET display_scale = ? WHERE id = 1")->execute([$cur]);
    json(['ok' => true, 'display_scale' => $cur]);
}

if ($action === 'list_uploads') {
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    $files = [];
    if (is_dir($dir)) {
        $entries = scandir($dir);
        foreach ($entries as $e) {
            if ($e === '.' || $e === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $e;
            if (is_file($path) && preg_match('/\.(png|jpe?g|gif|webp)$/i', $e)) {
                $files[] = 'uploads/' . $e;
            }
        }
    }
    json(['ok' => true, 'files' => $files]);
}

if ($action === 'select_logo') {
    $team = (int)($_POST['team'] ?? 0);
    $file = $_POST['file'] ?? '';
    if ($team <= 0 || !$file) json(['ok' => false, 'msg' => 'Ungültige Daten']);

    $base = basename($file);
    $path = 'uploads/' . $base;
    if (!is_file(__DIR__ . DIRECTORY_SEPARATOR . $path)) json(['ok' => false, 'msg' => 'Datei nicht gefunden']);
    record_action_before_change('select_logo');
    $pdo->prepare("UPDATE teams SET logo = ? WHERE id = ?")->execute([$path, $team]);
    json(['ok' => true, 'logo' => $path]);
}

if ($action === 'score') {
    record_action_before_change('score');
    $team = (int)($_POST['team'] ?? 0);
    $op = $_POST['op'] ?? 'plus';
    if ($team <= 0) json(['ok' => false, 'msg' => 'Ungültiges Team']);
    if (!in_array($op, ['plus', 'minus'])) json(['ok' => false, 'msg' => 'Ungültige Operation']);

    list($settings, $teams) = loadState();
    if (count($teams) < 2) json(['ok' => false, 'msg' => 'Nicht genug Teams']);

    if (!empty($settings['game_over'])) {
        json(['ok' => false, 'msg' => 'Spiel bereits beendet']);
    }

    if ($op === 'plus') {
        $pdo->prepare("UPDATE teams SET score = score + 1 WHERE id = ?")->execute([$team]);
    } else {
        $pdo->prepare("UPDATE teams SET score = GREATEST(score - 1, 0) WHERE id = ?")->execute([$team]);
    }

    list($settings, $teams) = loadState();
    $team1 = $teams[0];
    $team2 = $teams[1];
    $score1 = (int)$team1['score'];
    $score2 = (int)$team2['score'];
    $currentSet = (int)$settings['current_set'];

    $winningScore = ($currentSet === 5) ? 15 : 25;

    ensureSideSwapColumn();
    $sideSwappedSet = intval($settings['side_swapped_set'] ?? 0);

    if ($currentSet === 5) {
        if (($score1 >= 8 || $score2 >= 8) && $sideSwappedSet !== $currentSet) {
            $pdo->prepare("UPDATE settings SET side_swapped_set = ? WHERE id = 1")->execute([$currentSet]);
            $pdo->prepare("UPDATE settings SET pause_until = ? WHERE id = 1")->execute([date('Y-m-d H:i:s', time() + 3)]);
            list($settings, $teams) = loadState();
            $team1 = $teams[0];
            $team2 = $teams[1];
            $score1 = (int)$team1['score'];
            $score2 = (int)$team2['score'];
        }
    }

    if ((($score1 >= $winningScore) || ($score2 >= $winningScore)) && abs($score1 - $score2) >= 2) {
        $existingSetStmt = $pdo->prepare("SELECT id FROM sets WHERE set_number = ? ORDER BY id DESC LIMIT 1");
        $existingSetStmt->execute([$currentSet]);
        $existingSetId = $existingSetStmt->fetchColumn();
        if ($existingSetId) {
            $pdo->prepare("UPDATE sets SET team1_score = ?, team2_score = ? WHERE id = ?")
                ->execute([$score1, $score2, $existingSetId]);
        } else {
            $pdo->prepare("INSERT INTO sets (set_number, team1_score, team2_score) VALUES (?, ?, ?)")
                ->execute([$currentSet, $score1, $score2]);
        }

        if ($score1 > $score2) {
            $pdo->prepare("UPDATE teams SET sets_won = sets_won + 1 WHERE id = ?")->execute([$team1['id']]);
        } elseif ($score2 > $score1) {
            $pdo->prepare("UPDATE teams SET sets_won = sets_won + 1 WHERE id = ?")->execute([$team2['id']]);
        }

        $teamsAfter = $pdo->query("SELECT * FROM teams ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $t1 = $teamsAfter[0];
        $t2 = $teamsAfter[1];
        $t1Sets = (int)$t1['sets_won'];
        $t2Sets = (int)$t2['sets_won'];

        if ($t1Sets >= 3 || $t2Sets >= 3) {

            $pause = date('Y-m-d H:i:s', time() + 180);
            $pdo->prepare("UPDATE settings SET game_over = 1, pause_until = ? WHERE id = 1")->execute([$pause]);
            $pdo->prepare("UPDATE settings SET side_swapped_set = 0 WHERE id = 1")->execute();
            json(['ok' => true, 'msg' => 'Spiel beendet', 'game_over' => true, 'pause_until' => $pause]);
        }

        $pdo->prepare("UPDATE settings SET side_swapped_set = 0 WHERE id = 1")->execute();

        $pdo->exec("UPDATE teams SET score = 0");
        $pdo->prepare("UPDATE settings SET current_set = current_set + 1, pause_until = ? WHERE id = 1")->execute([date('Y-m-d H:i:s', time() + 180)]);
        json(['ok' => true, 'msg' => 'Satz beendet']);
    }

    json(['ok' => true]);
}

if ($action === 'set_service') {
    record_action_before_change('set_service');
    $team = (int)($_POST['team'] ?? 1);
    $pdo->prepare("UPDATE settings SET service_team = ? WHERE id = 1")->execute([$team]);
    json(['ok'=>true]);
}

if ($action === 'new_set') {
    ensureSideSwapColumn();
    record_action_before_change('new_set');

    $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $teams = $pdo->query("SELECT * FROM teams ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $team1 = $teams[0] ?? null;
    $team2 = $teams[1] ?? null;
    if (!$team1 || !$team2) json(['ok' => false, 'msg' => 'Teams fehlen']);
    $currentSet = (int)$settings['current_set'];

    $existingSetStmt = $pdo->prepare("SELECT id FROM sets WHERE set_number = ? ORDER BY id DESC LIMIT 1");
    $existingSetStmt->execute([$currentSet]);
    $existingSetId = $existingSetStmt->fetchColumn();
    if ($existingSetId) {
        $pdo->prepare("UPDATE sets SET team1_score = ?, team2_score = ? WHERE id = ?")
            ->execute([$team1['score'], $team2['score'], $existingSetId]);
    } else {
        $pdo->prepare("INSERT INTO sets (set_number, team1_score, team2_score) VALUES (?, ?, ?)")
            ->execute([$currentSet, $team1['score'], $team2['score']]);
    }

    if ($team1['score'] > $team2['score']) {
        $pdo->prepare("UPDATE teams SET sets_won = sets_won + 1 WHERE id = ?")->execute([$team1['id']]);
    } elseif ($team2['score'] > $team1['score']) {
        $pdo->prepare("UPDATE teams SET sets_won = sets_won + 1 WHERE id = ?")->execute([$team2['id']]);
    }

    $pdo->prepare("UPDATE settings SET side_swapped_set = 0 WHERE id = 1")->execute();

    $pdo->exec("UPDATE teams SET score = 0");
    $pdo->prepare("UPDATE settings SET current_set = current_set + 1, pause_until = ? WHERE id = 1")->execute([date('Y-m-d H:i:s', time() + 180)]);
    json(['ok' => true]);
}

if ($action === 'previous_set') {
    ensureSideSwapColumn();
    record_action_before_change('previous_set');
    $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $teams = $pdo->query("SELECT * FROM teams ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $currentSet = (int)($settings['current_set'] ?? 1);
    if ($currentSet <= 1) json(['ok' => false, 'msg' => 'Bereits im ersten Satz']);

    $previousSet = $currentSet - 1;
    $setStmt = $pdo->prepare("SELECT * FROM sets WHERE set_number = ? ORDER BY id DESC LIMIT 1");
    $setStmt->execute([$currentSet]);
    $completedSet = $setStmt->fetch(PDO::FETCH_ASSOC);

    $preserveSetResult = false;
    if (!$completedSet) {
        $setStmt->execute([$previousSet]);
        $completedSet = $setStmt->fetch(PDO::FETCH_ASSOC);
        $preserveSetResult = true;
    }
    if (!$completedSet) json(['ok' => false, 'msg' => 'Kein gespeichertes Satzergebnis gefunden']);

    $winnerId = null;
    if ((int)$completedSet['team1_score'] > (int)$completedSet['team2_score']) $winnerId = $teams[0]['id'] ?? null;
    if ((int)$completedSet['team2_score'] > (int)$completedSet['team1_score']) $winnerId = $teams[1]['id'] ?? null;
    if ($winnerId !== null) $pdo->prepare("UPDATE teams SET sets_won = GREATEST(sets_won - 1, 0) WHERE id = ?")->execute([$winnerId]);
    if ($preserveSetResult) {
        $pdo->prepare("UPDATE teams SET score = CASE id WHEN ? THEN ? WHEN ? THEN ? ELSE score END")
            ->execute([$teams[0]['id'], $completedSet['team1_score'], $teams[1]['id'], $completedSet['team2_score']]);
    } else {
        $pdo->prepare("DELETE FROM sets WHERE id = ?")->execute([$completedSet['id']]);
        $pdo->exec("UPDATE teams SET score = 0");
    }
    $pdo->prepare("UPDATE settings SET current_set = ?, game_over = 0, timeout_team = 0, timeout_until = NULL, pause_until = NULL, side_swapped_set = 0 WHERE id = 1")
        ->execute([$previousSet]);
    json(['ok' => true, 'current_set' => $previousSet]);
}

if ($action === 'timeout') {
    record_action_before_change('timeout');
    $team = (int)($_POST['team'] ?? 0);
    $seconds = (int)($_POST['seconds'] ?? 30);
    if ($team <= 0) json(['ok' => false, 'msg' => 'Ungültiges Team']);
    $until = date('Y-m-d H:i:s', time() + $seconds);
    $pdo->prepare("UPDATE settings SET timeout_team = ?, timeout_until = ? WHERE id = 1")->execute([$team, $until]);
    $pdo->prepare("UPDATE teams SET timeouts = timeouts + 1 WHERE id = ?")->execute([$team]);
    json(['ok' => true, 'timeout_until' => $until]);
}

if ($action === 'clear_timeout') {
    record_action_before_change('clear_timeout');
    $pdo->prepare("UPDATE settings SET timeout_team = 0, timeout_until = NULL WHERE id = 1")->execute();
    json(['ok' => true]);
}

if ($action === 'swap_sides') {
    record_action_before_change('swap_sides');
    $pdo->exec("UPDATE teams SET side = CASE WHEN side = 'left' THEN 'right' ELSE 'left' END");
    json(['ok' => true]);
}

if ($action === 'reset_scoreboard') {
    ensureSideSwapColumn();
    record_action_before_change('reset_scoreboard');
    $pdo->exec("TRUNCATE TABLE sets");
    $pdo->exec("UPDATE teams SET score = 0, sets_won = 0, logo = NULL, timeouts = 0");
    $pdo->prepare("UPDATE settings SET game_title = ?, service_team = 1, current_set = 1, timeout_team = 0, timeout_until = NULL, pause_until = NULL, game_over = 0, side_swapped_set = 0 WHERE id = 1")
        ->execute(['Match']);
    json(['ok' => true]);
}

if ($action === 'undo_last') {
    ensure_actions_table();
    $row = $pdo->query("SELECT * FROM actions WHERE undone = 0 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$row) json(['ok' => false, 'msg' => 'Keine Aktion zum Rückgängigmachen']);
    $payload = json_decode($row['payload'], true);
    if (!$payload || !isset($payload['settings'])) json(['ok' => false, 'msg' => 'Ungültige Aktionsdaten']);

    try {
        $pdo->beginTransaction();

        $s = $payload['settings'];
        $stmt = $pdo->prepare("UPDATE settings SET game_title = ?, service_team = ?, current_set = ?, timeout_team = ?, timeout_until = ?, pause_until = ?, game_over = ?, header_color = ?, side_swapped_set = ? WHERE id = 1");
        $stmt->execute([
            $s['game_title'] ?? null,
            $s['service_team'] ?? 1,
            $s['current_set'] ?? 1,
            $s['timeout_team'] ?? 0,
            $s['timeout_until'] ?? null,
            $s['pause_until'] ?? null,
            $s['game_over'] ?? 0,
            $s['header_color'] ?? null,
            $s['side_swapped_set'] ?? 0
        ]);

        if (!empty($payload['teams']) && is_array($payload['teams'])) {
            foreach ($payload['teams'] as $t) {
                if (!isset($t['id'])) continue;
                $exists = $pdo->prepare("SELECT id FROM teams WHERE id = ?");
                $exists->execute([$t['id']]);
                if ($exists->fetch()) {
                    $pdo->prepare("UPDATE teams SET name = ?, color = ?, logo = ?, score = ?, sets_won = ?, side = ?, timeouts = ? WHERE id = ?")
                        ->execute([
                            $t['name'] ?? null,
                            $t['color'] ?? null,
                            $t['logo'] ?? null,
                            $t['score'] ?? 0,
                            $t['sets_won'] ?? 0,
                            $t['side'] ?? 'left',
                            $t['timeouts'] ?? 0,
                            $t['id']
                        ]);
                } else {
                    $pdo->prepare("INSERT INTO teams (id, name, color, logo, score, sets_won, side, timeouts) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([
                            $t['id'], $t['name'] ?? null, $t['color'] ?? null, $t['logo'] ?? null, $t['score'] ?? 0, $t['sets_won'] ?? 0, $t['side'] ?? 'left', $t['timeouts'] ?? 0
                        ]);
                }
            }
        }

        $pdo->exec("DELETE FROM sets");
        if (!empty($payload['sets']) && is_array($payload['sets'])) {
            $insWithId = $pdo->prepare("INSERT INTO sets (id, set_number, team1_score, team2_score, created_at) VALUES (?, ?, ?, ?, ?)");
            $insNoId = $pdo->prepare("INSERT INTO sets (set_number, team1_score, team2_score, created_at) VALUES (?, ?, ?, ?)");
            foreach ($payload['sets'] as $st) {
                if (isset($st['id'])) {
                    $insWithId->execute([
                        $st['id'], $st['set_number'] ?? null, $st['team1_score'] ?? 0, $st['team2_score'] ?? 0, $st['created_at'] ?? date('Y-m-d H:i:s')
                    ]);
                } else {
                    $insNoId->execute([
                        $st['set_number'] ?? null, $st['team1_score'] ?? 0, $st['team2_score'] ?? 0, $st['created_at'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            }
        }

        $pdo->prepare("UPDATE actions SET undone = 1 WHERE id = ?")->execute([$row['id']]);

        $pdo->commit();
        json(['ok' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        json(['ok' => false, 'msg' => 'Fehler beim Rückgängigmachen', 'error' => $e->getMessage()]);
    }
}

json(['ok'=>false,'msg'=>'unknown action']);
