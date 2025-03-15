<?php
$apiKey = getenv('STEAM_API_KEY');
$arenaUrl = 'https://data.worldofdota.net/data/get_top_rating_pve_arena.php';

$arenaData = file_get_contents($arenaUrl);
$arenaPlayers = json_decode($arenaData, true);

// Логируем сырые данные для отладки
file_put_contents('arena_debug.log', print_r($arenaPlayers, true));

$result = ['1' => [], '2' => [], '3' => []];

if (is_array($arenaPlayers)) {
    foreach ($arenaPlayers as $entry) {
        $playerCount = $entry['player_count'];
        $waveCount = $entry['wave_count'];
        $players = [];

        // Логируем каждую запись
        file_put_contents('arena_debug.log', "Processing entry: " . print_r($entry, true) . "\n", FILE_APPEND);

        if ($playerCount === '1' && $entry['p1'] !== '0') {
            $steamid3 = $entry['p1'];
            $steamid64 = '76561197960265728' + $steamid3;
            $players[] = $steamid64;
            $tab = '1';
        } elseif ($playerCount === '2' && $entry['p1'] !== '0' && $entry['p2'] !== '0') {
            $steamid3_1 = $entry['p1'];
            $steamid3_2 = $entry['p2'];
            $steamid64_1 = '76561197960265728' + $steamid3_1;
            $steamid64_2 = '76561197960265728' + $steamid3_2;
            $players = [$steamid64_1, $steamid64_2];
            $tab = '2';
        } elseif ($playerCount === '3' && $entry['p1'] !== '0' && $entry['p2'] !== '0' && $entry['p3'] !== '0') {
            $steamid3_1 = $entry['p1'];
            $steamid3_2 = $entry['p2'];
            $steamid3_3 = $entry['p3'];
            $steamid64_1 = '76561197960265728' + $steamid3_1;
            $steamid64_2 = '76561197960265728' + $steamid3_2;
            $steamid64_3 = '76561197960265728' + $steamid3_3;
            $players = [$steamid64_1, $steamid64_2, $steamid64_3];
            $tab = '3';
        } else {
            file_put_contents('arena_debug.log', "Skipped entry: player_count=$playerCount, p1={$entry['p1']}, p2={$entry['p2']}, p3={$entry['p3']}\n", FILE_APPEND);
            continue;
        }

        $steamUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=" . implode(',', $players);
        $steamData = file_get_contents($steamUrl);
        $steamProfile = json_decode($steamData, true);

        $playerData = [];
        if (isset($steamProfile['response']['players'])) {
            foreach ($steamProfile['response']['players'] as $p) {
                $playerData[] = [
                    'steamid' => $p['steamid'],
                    'name' => $p['personaname'] ?? 'Неизвестно',
                    'avatar' => $p['avatarmedium'] ?? 'https://via.placeholder.com/64'
                ];
            }
        } else {
            file_put_contents('arena_debug.log', "No Steam data for players: " . implode(',', $players) . "\n", FILE_APPEND);
        }

        $result[$tab][] = [
            'wave_count' => (int)$waveCount,
            'players' => $playerData
        ];
    }

    foreach ($result as $tab => &$entries) {
        usort($entries, function($a, $b) { return $b['wave_count'] - $a['wave_count']; });
        foreach ($entries as $index => &$entry) {
            $entry['rank'] = $index + 1;
        }
    }
}

file_put_contents('arena_leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
?>
