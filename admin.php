<?php
require 'db.php';
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
]);
session_start();

define('ADMIN_PASSWORD', getenv('SCOREBOARD_ADMIN_PASSWORD') ?: 'changeme');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (hash_equals(ADMIN_PASSWORD, (string)$_POST['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Falsches Passwort!';
        }
    }
    ?>
<!doctype html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <title>Admin Login</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="assets/style.css">
    </head>
    <body class="login-page">
        <main class="login-shell">
            <section class="login-card" aria-labelledby="loginTitle">
                <div class="login-mark" aria-hidden="true">S</div>
                <p class="login-kicker">Scoreboard</p>
                <h1 id="loginTitle">Adminbereich</h1>
                <p class="login-subtitle">Melde dich an, um das Spiel zu steuern.</p>
                <form method="post">
                    <label for="password">Passwort</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required="required">
                    <button type="submit">Einloggen</button>
                </form>
                <?php if (isset($error)): ?>
                <p class="login-error" role="alert"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
            </section>
        </main>
    </body>
</html>
<?php
    exit;
}
?>
<!doctype html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <title>Admin</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="mobile-web-app-capable" content="yes">
        <link rel="manifest" href="manifest.json">
        <link rel="stylesheet" href="assets/style.css">
    </head>
    <body>
        <div class="container">
            <header class="admin-header">
                <div>
                    <h1>Scoreboard</h1>
                </div>
            </header>

            <section class="card title-form">
                <label for="gameTitle">Spieltitel
                    <input id="gameTitle" type="text" placeholder="Titel des Spiels"></label>
                    <button id="saveTitle">Titel speichern</button>
            </section>

            <div class="dashboard-grid">
                <section class="card preview-card">
                    <div class="card-heading"><h2>Live-Vorschau</h2></div>
                    <div class="preview-shell">
                        <div id="previewTitle" class="preview-title">Spiel</div>
                        <div class="preview-scoreline">
                            <div class="preview-team"><div id="previewLogo1" class="preview-logo" aria-hidden="true">T1</div><div id="previewName1" class="preview-name">Team 1</div><div id="previewTimeouts1" class="preview-meta">Timeouts: 0</div></div>
                            <div><div class="preview-points"><span id="previewScore1">0</span> : <span id="previewScore2">0</span></div><div id="previewSetInfo" class="preview-meta">Satz 1 · Sätze 0 : 0</div></div>
                            <div class="preview-team"><div id="previewLogo2" class="preview-logo" aria-hidden="true">T2</div><div id="previewName2" class="preview-name">Team 2</div><div id="previewTimeouts2" class="preview-meta">Timeouts: 0</div></div>
                        </div>
                        <div id="previewTimer" class="preview-meta"></div>
                        <table class="preview-table"><thead id="previewSetsHeader"><tr><th>Team</th><th>Satz 1</th></tr></thead><tbody id="previewSetsBody"><tr><td>Team 1</td><td>0</td></tr><tr><td>Team 2</td><td>0</td></tr></tbody></table>
                    </div>
                </section>

                <section class="card controls">
                    <div class="card-heading"><h2>Spielsteuerung</h2><button id="openTeams" type="button">Teams bearbeiten</button></div>
                    <div class="team-control-grid">
                        <div class="control-group team-control-card team-control-left">
                            <h3 id="controlTeam1Name">Team 1</h3>
                            <button id="team1Plus">Punkt +</button>
                            <button id="team1Minus">Punkt -</button>
                            <button id="team1Timeout">Timeout</button>
                        </div>
                        <div class="control-group team-control-card team-control-right">
                            <h3 id="controlTeam2Name">Team 2</h3>
                            <button id="team2Plus">Punkt +</button>
                            <button id="team2Minus">Punkt -</button>
                            <button id="team2Timeout">Timeout</button>
                        </div>
                    </div>
                    <div class="other-controls">
                    <div class="control-group">
                        <h3>Manuelles</h3>
                        <button onclick="swapSides()">Seitenwechsel</button>
                    </div>
                    <div class="control-group set-navigation">
                        <h3>Sätze</h3>
                        <button id="previousSetBtn"> - Satz</button>
                        <button id="nextSetBtn">+ Satz</button>
                    </div>
                    <div class="control-group">
                        <h3>Rückgängig</h3>
                        <button id="undoLastBtn">Letzte Aktion rückgängig</button>
                    </div>
                    <div class="control-group">
                        <h3>Zurücksetzen</h3>
                        <button class="danger" onclick="resetScoreboard()">Scoreboard zurücksetzen</button>
                    </div>
                    <div class="control-group">
                        <h3>Zoom</h3>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <button id="zoomOut">-</button>
                            <div id="zoomDisplay" style="min-width:56px;text-align:center">100%</div>
                            <button id="zoomIn">+</button>
                        </div>
                    </div>
                    </div>
                </section>

            </div>
        </div>

        <div id="teamsModal" class="modal-backdrop" hidden>
            <section class="modal" role="dialog" aria-modal="true" aria-labelledby="teamsModalTitle">
                <div class="modal-header"><h2 id="teamsModalTitle">Teams bearbeiten</h2><button id="closeTeams" type="button" aria-label="Schließen">Schließen</button></div>
                <div id="teamsArea" class="team-list"></div>
            </section>
        </div>

        <div id="easterEgg" class="easter-egg-backdrop" hidden>
            <section class="easter-egg" role="dialog" aria-modal="true" aria-labelledby="easterEggTitle">
                <button id="closeEasterEgg" class="easter-egg-close" type="button" aria-label="Easter Egg schließen">Schließen</button>
                <div class="easter-egg-mark">✦</div>
                <h2 id="easterEggTitle">You found the easter egg</h2>
                <p>Developed by Elias</p>
            </section>
        </div>

        <div id="mobileScoreMode" class="mobile-score-mode" hidden>
            <div class="mobile-score-header">
                <span id="mobileGameTitle">Scoreboard</span>
                <span id="mobileTimer" class="mobile-timer"></span>
            </div>
            <div class="mobile-score-field">
                <button id="mobileLeftScore" class="mobile-score-side" type="button">
                    <span id="mobileLeftLogo" class="mobile-score-logo">T1</span>
                    <span id="mobileLeftName">Team 1</span>
                    <strong id="mobileLeftPoints">0</strong>
                    <small id="mobileLeftTimeouts">Timeouts: 0</small>
                </button>
                <div class="mobile-score-center"><span>:</span><small id="mobileSetInfo">Satz 1</small></div>
                <button id="mobileRightScore" class="mobile-score-side" type="button">
                    <span id="mobileRightLogo" class="mobile-score-logo">T2</span>
                    <span id="mobileRightName">Team 2</span>
                    <strong id="mobileRightPoints">0</strong>
                    <small id="mobileRightTimeouts">Timeouts: 0</small>
                </button>
            </div>
            <div class="mobile-score-dock" role="toolbar" aria-label="Spielsteuerung">
                <button id="mobileLeftMinus" type="button"><b>-</b><small>Punkt</small></button>
                <button id="mobileLeftTimeout" type="button"><b>◷</b><small>Timeout</small></button>
                <button id="mobileSwapSides" type="button"><b>⇄</b><small>Seiten</small></button>
                <button id="mobileMinimizeBottom" class="mobile-minimize" type="button" aria-label="Minimieren"><b>⌄</b><small>Minimieren</small></button>
                <button id="mobileRightTimeout" type="button"><b>◷</b><small>Timeout</small></button>
                <button id="mobileRightMinus" type="button"><b>-</b><small>Punkt</small></button>
            </div>
        </div>

        <script src="assets/script.js"></script>
        <script>
            let lastTeamSignature = '';
            let previewRequest = null;
            let leftControlTeamId = 1;
            let rightControlTeamId = 2;
            let easterEggActive = false;
            let mobileScoreModeOpen = false;
            let reopenMobileAfterLandscape = false;
            let orientationTimer = null;
            let wasPortrait = window.innerHeight >= window.innerWidth;
            const draftTeamNames = {};

            function renderTeamEditor(teams) {
                const area = document.getElementById('teamsArea');
                area.innerHTML = '';
                teams.forEach(t => {
                    const div = document.createElement('div');
                    div.className = 'teamCard';
                    div.innerHTML = `
                        <div class="team-card-heading"><span class="team-swatch" style="background:${escapeHtml(t.color || '#ffffff')}"></span><h3>${escapeHtml(t.name)}</h3></div>
                        <label>Name<input data-team="${t.id}" class="teamName" type="text" value="${escapeHtml(t.name)}"></label>
                        <div class="team-options"><label>Farbe<input data-team="${t.id}" class="teamColor" type="color" value="${t.color || '#ffffff'}"></label><label class="file-field">Neues Logo<input data-team="${t.id}" class="teamLogoFile" type="file" accept="image/png,image/jpeg,image/gif,image/webp"></label></div>
                        <label class="existing-logo-field">Logo vom Server<select data-team="${t.id}" class="existingLogo"><option value="">Aktuelles Logo behalten</option></select></label>
                        <div class="row team-actions"><button onclick="saveTeam(${t.id})" class="primary">Team speichern</button></div>
                        <div class="team-stats">${t.score} Punkte <span>·</span> ${t.sets_won} Sätze <span>·</span> ${t.timeouts} Timeouts</div>
                    `;
                    area.appendChild(div);
                    const nameInput = div.querySelector(`.teamName[data-team="${t.id}"]`);
                    nameInput.addEventListener('input', event => {
                        draftTeamNames[t.id] = event.target.value;
                        updateDraftTeamName(t.id, event.target.value);
                    });
                    const colorInput = div.querySelector(`.teamColor[data-team="${t.id}"]`);
                    colorInput.addEventListener('input', event => {
                        div.querySelector('.team-swatch').style.backgroundColor = event.target.value;
                    });
                    loadUploadedLogos(div.querySelector('.existingLogo'), t.logo);
                });
            }

            function updateDraftTeamName(teamId, name) {
                const team = String(teamId) === String(leftControlTeamId) ? 'left' : 'right';
                const previewName = document.getElementById(team === 'left' ? 'previewName1' : 'previewName2');
                const controlName = document.getElementById(team === 'left' ? 'controlTeam1Name' : 'controlTeam2Name');
                if (previewName) previewName.textContent = name || 'Team';
                if (controlName) controlName.textContent = name || 'Team';
            }

            function displayTeamName(team) {
                return draftTeamNames[team.id] ?? team.name;
            }

            function loadUploadedLogos(select, currentLogo) {
                const body = new URLSearchParams({ action: 'list_uploads', csrf_token: <?= json_encode($_SESSION['csrf_token']) ?> });
                fetch('update.php', { method: 'POST', body })
                    .then(response => response.json())
                    .then(result => {
                        if (!result.ok) return;
                        result.files.forEach(file => {
                            const option = document.createElement('option');
                            option.value = file;
                            option.textContent = file.split('/').pop();
                            if (file === currentLogo) option.selected = true;
                            select.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Logos konnten nicht geladen werden:', error));
            }

            function updatePreview(data) {
                const hasElias = data.teams.some(team => (team.name || '').trim().toLowerCase() === 'elias');
                setEasterEggState(hasElias);
                const left = data.teams.find(team => team.side === 'left') || data.teams[0];
                const right = data.teams.find(team => team.side === 'right') || data.teams[1];
                document.getElementById('previewTitle').textContent = data.settings.game_title || 'Spiel';
                document.getElementById('previewSetInfo').textContent = `Satz ${data.settings.current_set || 1} · Sätze ${left.sets_won} : ${right.sets_won}`;
                [left, right].forEach((team, index) => {
                    const suffix = index + 1;
                    const color = team.color || '#d8dee9';
                    const logo = document.getElementById(`previewLogo${suffix}`);
                    logo.textContent = team.logo ? '' : (team.name || `Team ${suffix}`).slice(0, 2).toUpperCase();
                    logo.style.backgroundColor = color;
                    logo.style.backgroundImage = team.logo ? `url("${encodeURI(team.logo)}")` : 'none';
                    logo.style.backgroundSize = team.logo ? 'contain' : 'cover';
                    logo.style.backgroundRepeat = 'no-repeat';
                    logo.style.backgroundColor = team.logo ? 'transparent' : color;
                    logo.style.color = readableTextColor(color);
                    document.getElementById(`previewName${suffix}`).textContent = displayTeamName(team);
                    document.getElementById(`previewName${suffix}`).style.color = color;
                    document.getElementById(`previewScore${suffix}`).textContent = team.score;
                    document.getElementById(`previewScore${suffix}`).style.color = color;
                    document.getElementById(`previewTimeouts${suffix}`).textContent = `Timeouts: ${team.timeouts}`;
                });
                const pauseUntil = data.settings.pause_until ? new Date(data.settings.pause_until) : null;
                const remainingPause = pauseUntil ? Math.max(0, Math.ceil((pauseUntil - new Date(data.now)) / 1000)) : 0;
                document.getElementById('previewTimer').textContent = remainingPause > 0 ? `Satzpause · ${remainingPause}s` : '';
                document.getElementById('previewSetsHeader').innerHTML = '<tr><th>Team</th>' + data.sets.map((_, i) => `<th>Satz ${i + 1}</th>`).join('') + '</tr>';
                document.getElementById('previewSetsBody').innerHTML = [left, right].map((team, index) => `<tr><td>${escapeHtml(team.name)}</td>${data.sets.map(set => `<td>${index ? set.team2_score : set.team1_score}</td>`).join('')}</tr>`).join('');
                updateMobileScoreMode(data, left, right);
            }

            function updateMobileScoreMode(data, left, right) {
                document.getElementById('mobileGameTitle').textContent = data.settings.game_title || 'Scoreboard';
                [left, right].forEach((team, index) => {
                    const suffix = index === 0 ? 'Left' : 'Right';
                    const logo = document.getElementById(`mobile${suffix}Logo`);
                    logo.textContent = team.logo ? '' : (team.name || `Team ${index + 1}`).slice(0, 2).toUpperCase();
                    logo.style.backgroundColor = team.color || '#0071e3';
                    logo.style.backgroundImage = team.logo ? `url("${encodeURI(team.logo)}")` : 'none';
                    logo.style.color = readableTextColor(team.color || '#0071e3');
                    document.getElementById(`mobile${suffix}Timeouts`).textContent = `Timeouts: ${team.timeouts}`;
                });
                document.getElementById('mobileLeftName').textContent = displayTeamName(left);
                document.getElementById('mobileRightName').textContent = displayTeamName(right);
                document.getElementById('mobileLeftPoints').textContent = left.score;
                document.getElementById('mobileRightPoints').textContent = right.score;
                document.getElementById('mobileLeftScore').style.setProperty('--team-color', left.color || '#0071e3');
                document.getElementById('mobileRightScore').style.setProperty('--team-color', right.color || '#0071e3');
                document.getElementById('mobileLeftPoints').style.color = left.color || '#0071e3';
                document.getElementById('mobileRightPoints').style.color = right.color || '#0071e3';
                document.getElementById('mobileSetInfo').textContent = `Satz ${data.settings.current_set || 1} · ${left.sets_won} : ${right.sets_won}`;
                const now = new Date(data.now);
                const timeoutUntil = data.settings.timeout_until ? new Date(data.settings.timeout_until) : null;
                const pauseUntil = data.settings.pause_until ? new Date(data.settings.pause_until) : null;
                let timer = '';
                if (timeoutUntil && timeoutUntil > now) {
                    const timeoutTeam = String(data.settings.timeout_team) === String(left.id) ? left.name : right.name;
                    timer = `Timeout ${timeoutTeam} · ${Math.ceil((timeoutUntil - now) / 1000)}s`;
                } else if (pauseUntil && pauseUntil > now) {
                    timer = `Satzpause · ${Math.ceil((pauseUntil - now) / 1000)}s`;
                }
                document.getElementById('mobileTimer').textContent = timer;
            }

            function setMobileScoreMode(open, force = false) {
                if (open && !force && !window.matchMedia('(max-width: 1200px)').matches) return;
                mobileScoreModeOpen = open;
                document.getElementById('mobileScoreMode').hidden = !open;
                document.body.classList.toggle('mobile-score-open', open);
                document.documentElement.classList.toggle('mobile-score-open', open);
                document.body.style.overflow = open ? 'hidden' : '';
                document.documentElement.style.overflow = open ? 'hidden' : '';
                if (open && window.matchMedia('(pointer: coarse)').matches) {
                    document.documentElement.requestFullscreen?.().catch(() => {});
                    screen.orientation?.lock?.('landscape').catch(() => {});
                } else if (!open && document.fullscreenElement) {
                    document.exitFullscreen?.().catch(() => {});
                }
            }

            function syncMobileOrientation() {
                if (window.innerWidth > 800) return;
                const portrait = window.innerHeight >= window.innerWidth;
                if (portrait && mobileScoreModeOpen) {
                    reopenMobileAfterLandscape = true;
                    setMobileScoreMode(false);
                } else if (!portrait && (reopenMobileAfterLandscape || wasPortrait)) {
                    reopenMobileAfterLandscape = false;
                    setMobileScoreMode(true);
                }
                wasPortrait = portrait;
            }

            function setEasterEggState(active) {
                document.body.classList.toggle('easter-egg-active', active);
                if (!active) {
                    easterEggActive = false;
                    return;
                }
                if (easterEggActive) return;
                easterEggActive = true;
                document.getElementById('easterEgg').hidden = false;
            }

            function fetchAndRender() {
                if (previewRequest) return;
                previewRequest = fetch(`get_score.php?_=${Date.now()}`, { cache: 'no-store' })
                    .then(r => r.json())
                    .then(data => {
                        const titleInput = document.getElementById('gameTitle');
                        if (document.activeElement !== titleInput) {
                            titleInput.value = data.settings.game_title || 'Spiel';
                        }
                        const team1 = data.teams[0];
                        const team2 = data.teams[1];
                        const leftTeam = data.teams.find(team => team.side === 'left') || team1;
                        const rightTeam = data.teams.find(team => team.side === 'right') || team2;
                        leftControlTeamId = Number(leftTeam.id);
                        rightControlTeamId = Number(rightTeam.id);
                        updatePreview(data);
                        const signature = JSON.stringify(data.teams.map(team => [team.id, team.name, team.color, team.logo]));
                        if (signature !== lastTeamSignature && document.getElementById('teamsModal').hidden) {
                            renderTeamEditor(data.teams);
                            lastTeamSignature = signature;
                        }
                        // update zoom display
                        const zoomDisplay = document.getElementById('zoomDisplay');
                        if (zoomDisplay) zoomDisplay.textContent = (data.settings.display_scale || 100) + '%';
                        document.getElementById('team1Plus').textContent = '+';
                        document
                            .getElementById('team1Minus')
                            .textContent = '-';
                        document
                            .getElementById('team2Plus')
                            .textContent = '+';
                        document
                            .getElementById('team2Minus')
                            .textContent = '-';
                        document
                            .getElementById('team1Timeout')
                            .textContent = `Timeout ${leftTeam.name} (${leftTeam.timeouts})`;
                        document
                            .getElementById('team2Timeout')
                            .textContent = `Timeout ${rightTeam.name} (${rightTeam.timeouts})`;
                        document.getElementById('controlTeam1Name').textContent = displayTeamName(leftTeam);
                        document.getElementById('controlTeam2Name').textContent = displayTeamName(rightTeam);

                        document
                            .getElementById('team1Plus')
                            .style
                            .backgroundColor = leftTeam.color;
                        document
                            .getElementById('team1Minus')
                            .style
                            .backgroundColor = leftTeam.color;
                        document
                            .getElementById('team2Plus')
                            .style
                            .backgroundColor = rightTeam.color;
                        document
                            .getElementById('team2Minus')
                            .style
                            .backgroundColor = rightTeam.color;
                        document
                            .getElementById('team1Timeout')
                            .style
                            .backgroundColor = leftTeam.color;
                        document
                            .getElementById('team2Timeout')
                            .style
                            .backgroundColor = rightTeam.color;
                        document.getElementById('controlTeam1Name').style.color = leftTeam.color;
                        document.getElementById('controlTeam2Name').style.color = rightTeam.color;
                        ['mobileLeftMinus', 'mobileLeftTimeout'].forEach(id => {
                            document.getElementById(id).style.color = leftTeam.color || '#0071e3';
                        });
                        ['mobileRightMinus', 'mobileRightTimeout'].forEach(id => {
                            document.getElementById(id).style.color = rightTeam.color || '#0071e3';
                        });
                    })
                    .catch(err => console.error('Fehler beim Laden der Daten:', err))
                    .finally(() => { previewRequest = null; });
            }

            function escapeHtml(s) {
                return (s + '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            // choose readable text color (black or white) for a hex background
            function readableTextColor(hex) {
                if (!hex) return '#000';
                const h = hex.replace('#','');
                const r = parseInt(h.substring(0,2),16);
                const g = parseInt(h.substring(2,4),16);
                const b = parseInt(h.substring(4,6),16);
                const lum = (0.2126*r + 0.7152*g + 0.0722*b)/255;
                return lum > 0.6 ? '#000' : '#fff';
            }

            document
                .getElementById('saveTitle')
                .addEventListener('click', () => {
                    const title = document
                        .getElementById('gameTitle')
                        .value;
                    post({
                        action: 'set_title',
                        title
                    }, () => fetchAndRender());
                });

            function addPoint(teamId) {
                updateMobileScoreImmediately(teamId, 1);
                post({
                    action: 'score',
                    team: teamId,
                    op: 'plus'
                }, () => fetchAndRender());
            }

            function updateMobileScoreImmediately(teamId, amount) {
                const scoreId = String(teamId) === String(leftControlTeamId)
                    ? 'mobileLeftPoints'
                    : String(teamId) === String(rightControlTeamId)
                        ? 'mobileRightPoints'
                        : null;
                const scoreElement = scoreId ? document.getElementById(scoreId) : null;
                if (!scoreElement) return;
                const currentScore = Number.parseInt(scoreElement.textContent, 10);
                if (Number.isNaN(currentScore)) return;
                scoreElement.textContent = Math.max(0, currentScore + amount);
            }

            function subtractPoint(teamId) {
                updateMobileScoreImmediately(teamId, -1);
                post({
                    action: 'score',
                    team: teamId,
                    op: 'minus'
                }, () => fetchAndRender());
            }

            function callTimeout(teamId) {
                post({
                    action: 'timeout',
                    team: teamId,
                    seconds: 30
                }, () => fetchAndRender());
            }

            function swapSides() {
                post({
                    action: 'swap_sides'
                }, () => fetchAndRender());
            }

            function saveTeam(id) {
                const name = document.querySelector(`.teamName[data-team="${id}"]`).value;
                const color = document.querySelector(`.teamColor[data-team="${id}"]`).value;
                const input = document.querySelector(`.teamLogoFile[data-team="${id}"]`);
                const existingLogo = document.querySelector(`.existingLogo[data-team="${id}"]`).value;
                const body = new FormData();
                body.append('action', 'set_team');
                body.append('team', id);
                body.append('name', name);
                body.append('color', color);
                body.append('existing_logo', existingLogo);
                body.append('csrf_token', <?= json_encode($_SESSION['csrf_token']) ?>);
                if (input && input.files[0]) body.append('logo', input.files[0]);
                fetch('update.php', { method: 'POST', body })
                    .then(r => r.json())
                    .then(response => {
                        if (!response.ok) alert('Fehler: ' + (response.msg || 'Speichern fehlgeschlagen'));
                        else delete draftTeamNames[id];
                        fetchAndRender();
                    })
                    .catch(err => console.error('Fehler beim Speichern des Teams:', err));
            }

            function uploadLogo(id) {
                const input = document.querySelector(`.teamLogoFile[data-team="${id}"]`);
                if (!input || !input.files[0])
                    return alert('Bitte wählen Sie eine Datei aus.');
                const fd = new FormData();
                fd.append('action', 'upload_logo');
                fd.append('team', id);
                fd.append('logo', input.files[0]);
                fd.append('csrf_token', <?= json_encode($_SESSION['csrf_token']) ?>);
                fetch('update.php', {
                    method: 'POST',
                    body: fd
                })
                    .then(r => r.json())
                    .then(j => {
                        if (j.ok)
                            alert('Logo erfolgreich hochgeladen.');
                        else
                            alert('Fehler: ' + (
                                j.msg || 'Unbekannter Fehler'
                            ));
                        fetchAndRender();
                    })
                    .catch(err => console.error('Fehler beim Hochladen des Logos:', err));
            }

            function resetScoreboard() {
                if (confirm('Möchten Sie das Scoreboard wirklich zurücksetzen?')) {
                    post({
                        action: 'reset_scoreboard'
                    }, () => fetchAndRender());
                }
            }

            function post(data, cb) {
                const body = new URLSearchParams();
                body.append('csrf_token', <?= json_encode($_SESSION['csrf_token']) ?>);
                for (let k in data)
                    body.append(k, data[k]);
                fetch('update.php', {
                    method: 'POST',
                    body
                })
                    .then(r => r.json())
                    .then(j => {
                        if (cb)
                            cb(j);
                        }
                    )
                    .catch(err => console.error('Fehler beim Senden der Daten:', err));
            }

            document
                .getElementById('team1Plus')
                .addEventListener('click', () => addPoint(leftControlTeamId));
            document
                .getElementById('team1Minus')
                .addEventListener('click', () => subtractPoint(leftControlTeamId));
            document
                .getElementById('team2Plus')
                .addEventListener('click', () => addPoint(rightControlTeamId));
            document
                .getElementById('team2Minus')
                .addEventListener('click', () => subtractPoint(rightControlTeamId));
            document
                .getElementById('team1Timeout')
                .addEventListener('click', () => callTimeout(leftControlTeamId));
            document
                .getElementById('team2Timeout')
                .addEventListener('click', () => callTimeout(rightControlTeamId));

            document
                .getElementById('undoLastBtn')
                .addEventListener('click', () => {
                    if (!confirm('Letzte Aktion wirklich rückgängig machen?')) return;
                    post({ action: 'undo_last' }, (response) => {
                        if (response && response.ok) {
                            alert('Letzte Aktion wurde rückgängig gemacht.');
                        } else {
                            alert('Fehler: ' + (response.msg || 'Unbekannter Fehler'));
                        }
                        fetchAndRender();
                    });
                });

            document.getElementById('zoomIn').addEventListener('click', () => {
                post({ action: 'set_zoom', op: 'in' }, (res) => { fetchAndRender(); });
            });
            document.getElementById('zoomOut').addEventListener('click', () => {
                post({ action: 'set_zoom', op: 'out' }, (res) => { fetchAndRender(); });
            });

            const teamsModal = document.getElementById('teamsModal');
            document.getElementById('openTeams').addEventListener('click', () => {
                teamsModal.hidden = false;
                document.body.classList.add('modal-open');
                document.documentElement.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
            });
            document.getElementById('closeTeams').addEventListener('click', () => {
                teamsModal.hidden = true;
                document.body.classList.remove('modal-open');
                document.documentElement.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.documentElement.style.overflow = '';
            });
            teamsModal.addEventListener('click', (event) => {
                if (event.target === teamsModal) {
                    teamsModal.hidden = true;
                    document.body.classList.remove('modal-open');
                    document.documentElement.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.documentElement.style.overflow = '';
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    teamsModal.hidden = true;
                    document.body.classList.remove('modal-open');
                    document.documentElement.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.documentElement.style.overflow = '';
                }
            });

            document.querySelector('.preview-shell').addEventListener('click', (event) => {
                if (!event.target.closest('button, a, input, select')) setMobileScoreMode(true, true);
            });
            document.getElementById('mobileLeftScore').addEventListener('click', () => addPoint(leftControlTeamId));
            document.getElementById('mobileRightScore').addEventListener('click', () => addPoint(rightControlTeamId));
            document.getElementById('mobileLeftMinus').addEventListener('click', () => subtractPoint(leftControlTeamId));
            document.getElementById('mobileRightMinus').addEventListener('click', () => subtractPoint(rightControlTeamId));
            document.getElementById('mobileLeftTimeout').addEventListener('click', () => callTimeout(leftControlTeamId));
            document.getElementById('mobileRightTimeout').addEventListener('click', () => callTimeout(rightControlTeamId));
            document.getElementById('mobileSwapSides').addEventListener('click', swapSides);
            document.getElementById('mobileMinimizeBottom').addEventListener('click', () => setMobileScoreMode(false));
            document.getElementById('previousSetBtn').addEventListener('click', () => {
                if (confirm('Zum vorherigen Satz zurückkehren? Der aktuelle Satz wird zurückgesetzt.')) {
                    post({ action: 'previous_set' }, response => {
                        if (!response.ok) alert(response.msg || 'Satz konnte nicht geändert werden.');
                        fetchAndRender();
                    });
                }
            });
            document.getElementById('nextSetBtn').addEventListener('click', () => {
                post({ action: 'new_set' }, response => {
                    if (!response.ok) alert(response.msg || 'Satz konnte nicht geändert werden.');
                    fetchAndRender();
                });
            });
            window.addEventListener('orientationchange', () => {
                window.clearTimeout(orientationTimer);
                orientationTimer = window.setTimeout(syncMobileOrientation, 180);
            });
            window.addEventListener('resize', () => {
                window.clearTimeout(orientationTimer);
                orientationTimer = window.setTimeout(syncMobileOrientation, 180);
            });

            document.getElementById('closeEasterEgg').addEventListener('click', () => {
                document.getElementById('easterEgg').hidden = true;
            });

            fetchAndRender();
            window.setInterval(fetchAndRender, 500);
        </script>
    </body>
</html>
