<?php
require 'db.php';
?>
<!doctype html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <title>Scoreboard Anzeige</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="assets/style.css">
        <style>
            html {
                font-size: 100%;
            }
            body {
                background: #111;
                color: #fff;
            }
            .tv-container {
                width: 1200px;
                height: 650px;
                border-radius: 15px;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                transform-origin: center center;
                box-shadow: 0 10px 40px rgba(0,0,0,0.6);
                overflow: hidden;
                box-sizing: border-box;
                padding: 28px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 18px;
                background: linear-gradient(180deg, #050505ff 0%, #0f1010ff 100%);
            }
            #title {
                text-align: center;
                margin-bottom: 0;
            }
            .logoSmall {
                width: 140px;
                height: 140px;
                object-fit: contain;
            }
            .logoTiny {
                width: 50px;
                height: 50px;
                object-fit: contain;
            }
            .serviceDot {
                width: 18px;
                height: 18px;
                border-radius: 50%;
                display: inline-block;
                margin-left: 8px;
                border: 3px solid #fff;
            }
            .sets {
                font-size: 1.7rem;
            }
            .sets span {
                margin-right: 10px;
            }
            .smallTimer {
                font-size: 2rem;
                margin-top: 10px;
            }
            .scoreBig {
                font-size: 17rem;
                font-weight: 500;
                letter-spacing: -8px;
                white-space: nowrap;
                line-height: 1;
                text-align: center;
            }
            .teamBox {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 20px;
                flex: 1 1 0;
                min-width: 0;
            }
            .teamBox > div {
                text-align: center;
                width: 220px;
            }
            .team-color-placeholder {
                width: 140px !important;
                height: 140px;
                margin: 0 auto;
                border-radius: 18px !important;
            }
            .tv-container > div[style*="display:flex"] {
                width: 100%;
                max-width: calc(100% - 48px);
            }
            .Sets_table {
                width: 100%;
                max-width: calc(100% - 48px);
                font-size: 1.8rem;
            }
            .display-team-name {
                font-size: 1.7rem;
                font-weight: 400;
                margin-top: 6px;
                text-align: center;
            }
            .display-team-meta {
                font-weight: 400;
                text-align: center;
                color: #c7c7cc;
            }
            @media (max-width: 700px) {
                .scoreBig { font-size: 12rem; }
            }
        </style>
    </head>
    <body>
        <div class="tv-container" id="tv">
            <div id="title" class="center"></div>
            <div
                style="display:flex; justify-content:space-between; align-items:center; gap:30px;">
                <div class="teamBox" id="leftTeam"></div>
                <div style="text-align:center;">
                    <div id="score" class="scoreBig">0 : 0</div>
                    <div id="setInfo" class="sets"></div>
                    <div id="timer" class="smallTimer"></div>
                </div>
                <div class="teamBox" id="rightTeam"></div>
            </div>
            <div id="Sets_table" class="Sets_table">
                <table>
                    <thead id="setsHeader">
                        <tr>
                            <th>Team</th>
                            <th>Satz 1</th>
                        </tr>
                    </thead>
                    <tbody id="setsBody">
                        <tr>
                            <td id="leftTeamInfo"></td>
                            <td id="leftTeamSet1">0</td>
                        </tr>
                        <tr>
                            <td id="rightTeamInfo"></td>
                            <td id="rightTeamSet1">0</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <script src="assets/script.js"></script>
        <script>
            function renderDisplay(data) {
                const settings = data.settings;
                const teams = data.teams;
                const left = teams.find(t => t.side === 'left') || {
                    name: 'Team 1',
                    color: '#fff',
                    logo: null,
                    score: 0,
                    sets_won: 0,
                    timeouts: 0
                };
                const right = teams.find(t => t.side === 'right') || {
                    name: 'Team 2',
                    color: '#fff',
                    logo: null,
                    score: 0,
                    sets_won: 0,
                    timeouts: 0
                };

                document
                    .getElementById('title')
                    .innerHTML = `<h1 style="margin:0">${escapeHtml(settings.game_title || 'Spiel')}</h1>`;

                document
                    .getElementById('leftTeam')
                    .innerHTML = `
    <div style="text-align:center">
        ${left.logo
                        ? `<img src="${left.logo}" class="logoSmall">`
                        : `<div class="team-color-placeholder" style="background:${left.color}"></div>`}
        <div class="display-team-name">${escapeHtml(
                            left.name
                        )}</div>
        <div class="display-team-meta">Timeouts: ${left.timeouts}</div>
      </div>
    `;

                document
                    .getElementById('rightTeam')
                    .innerHTML = `
      <div style="text-align:center">
        ${right.logo
                        ? `<img src="${right.logo}" class="logoSmall">`
                            : `<div class="team-color-placeholder" style="background:${right.color}"></div>`}
        <div class="display-team-name">${escapeHtml(
                            right.name
                        )}</div>
        <div class="display-team-meta">Timeouts: ${right.timeouts}</div>
      </div>
    `;

                document
                    .getElementById('score')
                    .innerHTML = `
      <span style="color:${left.color}">${left.score}</span> : <span style="color:${right.color}">${right.score}</span>
    `;

                let setTxt = `Satz ${settings.current_set || 1} - Sätze: ${left.sets_won} : ${right.sets_won}`;
                document
                    .getElementById('setInfo')
                    .textContent = setTxt;

                const now = new Date(data.now);
                let timerText = '';
                const untilTimeout = settings.timeout_until
                    ? new Date(settings.timeout_until)
                    : null;
                if (untilTimeout && untilTimeout > now) {
                    const seconds = Math.ceil((untilTimeout - now) / 1000);
                    const timeoutTeam = ('' + settings.timeout_team) === ('' + left.id)
                        ? left.name
                        : right.name;
                    timerText = `Timeout ${timeoutTeam} - ${seconds}s`;
                } else {
                    const untilPause = settings.pause_until
                        ? new Date(settings.pause_until)
                        : null;
                    if (untilPause && untilPause > now) {
                        const seconds = Math.ceil((untilPause - now) / 1000);
                        timerText = `Satzpause - ${seconds}s`;
                    }
                }
                document
                    .getElementById('timer')
                    .textContent = timerText;
            }
            function renderSetsTable(data) {
                const settings = data.settings;
                const teams = data.teams;
                const sets = data.sets;

                const teamA = teams[0] || {
                    name: 'Team 1',
                    logo: null
                };
                const teamB = teams[1] || {
                    name: 'Team 2',
                    logo: null
                };

                const header = document.getElementById('setsHeader');
                const body = document.getElementById('setsBody');

                header.innerHTML = '<tr><th>Team</th>' + sets
                    .map(
                        (_, i) => `<th>Satz ${i + 1}</th>`
                    )
                    .join('') + '</tr>';
                header.style.backgroundColor = settings.header_color || '#1d1d69ff';

                body.innerHTML = `
        <tr>
            <td style="display: flex; align-items: center; gap: 8px;">
                ${teamA.logo
                    ? `<img src="${teamA.logo}" class="logoTiny">`
                    : ''}
                ${escapeHtml(teamA.name)}
            </td>
            ${sets
                        .map(
                            s => `<td>${s.team1_score}</td>`
                        )
                        .join('')}
        </tr>
        <tr>
            <td style="display: flex; align-items: center; gap: 8px;">
                ${teamB
                        .logo
                            ? `<img src="${teamB.logo}" class="logoTiny">`
                            : ''}
                ${escapeHtml(teamB.name)}
            </td>
            ${sets
                                .map(
                                    s => `<td>${s.team2_score}</td>`
                                )
                                .join('')}
        </tr>
    `;
            }

            function fetchDisplay() {
                fetch('get_score.php')
                    .then(r => r.json())
                    .then(j => {

                        try {
                            const scale = (j.settings && j.settings.display_scale)
                                ? parseInt(j.settings.display_scale)
                                : 100;
                            if (!isNaN(scale))
                                document.documentElement.style.fontSize = scale + '%';
                            }
                        catch (e) {}
                        renderDisplay(j);
                        renderSetsTable(j);
                    });
            }

            fetchDisplay();
            setInterval(fetchDisplay, 1000);
        </script>
        <script>
            function scaleScoreboard() {
                const tv = document.getElementById('tv');
                if (!tv)
                    return;
                const BASE_W = 1200;
                const BASE_H = 650;
                const availW = window.innerWidth * 0.98;
                const availH = window.innerHeight * 0.96;
                let scale = Math.min(availW / BASE_W, availH / BASE_H);
                tv.style.transform = `translate(-50%, -50%) scale(${scale})`;
            }

            window.addEventListener('load', scaleScoreboard);
            window.addEventListener('resize', scaleScoreboard);
        </script>

    </body>
</html>
