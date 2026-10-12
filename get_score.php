<?php
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

foreach (['timeout_until', 'pause_until'] as $f) {
    if (!empty($settings[$f])) {

        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $settings[$f]);
        if ($dt) {
            $settings[$f] = $dt->format(DateTime::ATOM);
        } else {
            $ts = strtotime($settings[$f]);
            if ($ts !== false) $settings[$f] = date(DATE_ATOM, $ts);
        }
    }
}

$settings['header_color'] = $settings['header_color'] ?? '#f4f4f6';

$settings['display_scale'] = isset($settings['display_scale']) ? (int)$settings['display_scale'] : 100;

$teamsStmt = $pdo->query("SELECT * FROM teams ORDER BY id ASC");
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

$setsStmt = $pdo->query("SELECT * FROM sets ORDER BY set_number ASC");
$sets = $setsStmt->fetchAll(PDO::FETCH_ASSOC);

$currentSet = (int)$settings['current_set'];
$exists = false;
foreach ($sets as $s) {
    if ((int)$s['set_number'] === $currentSet) {
        $exists = true;
        break;
    }
}
if (!$exists && empty($settings['game_over'])) {
    $sets[] = [
        'set_number' => $currentSet,
        'team1_score' => $teams[0]['score'],
        'team2_score' => $teams[1]['score']
    ];
}

echo json_encode([
    'ok' => true,
    'settings' => $settings,
    'teams' => $teams,
    'sets' => $sets,
    'now' => date('c')
]);
?>
