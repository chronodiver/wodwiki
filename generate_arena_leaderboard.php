<?php
$apiKey = getenv('STEAM_API_KEY');
$arenaUrl = 'https://data.worldofdota.net/data/get_top_rating_pve_arena.php';

$arenaData = file_get_contents($arenaUrl);
$arenaPlayers = json_decode($arenaData, true);

// Логируем сырые данные
file_put_contents('arena_debug.log', "Raw data: " . print_r($arenaPlayers, true) . "\n");

$result = ['1' => [], '2' => [], '3' => []];

if (is_array($arenaPlayers)) {
    // Проходим по всем возможным ключам (1, 2, 3)
    foreach (['1', '2', '3'] as $key) {
        if (isset($arenaPlayers[$key]) && is_array($arenaPlayers[$key])) {
            foreach ($arenaPlayers[$key] as $index => $entry) {
                $playerCount = isset($entry['player_count']) ? $entry['player_count'] : null;
                $waveCount = isset($entry['wave_count']) ? $entry['wave_count'] : null;
                $p1 = isset($entry['p1']) ? $entry['p1'] : '0';
                $p2 = isset($entry['p2']) ? $entry['p2'] : '0';
                $p3 = isset($entry['p3']) ? $entry['p3'] : '0';

                file_put_contents('arena_debug.log', "Entry #$key-$index: player_count=$playerCount, wave_count=$waveCount, p1=$p1, p2=$p2, p3=$p3\n", FILE_APPEND);

                $players = [];
                $tab = null;

                if ($playerCount == '1' && $p1 != '0') {
                    $steamid64 = '76561197960265728' + $p1;
                    $players[] = $steamid64;
                    $tab = '1';
                } elseif ($playerCount == '2' && $p1 != '0' && $p2 != '0') {
                    $steamid64_1 = '76561197960265728' + $p1;
                    $steamid64_2 = '76561197960265728' + $p2;
                    $players = [$steamid64_1, $steamid64_2];
                    $tab = '2';
                } elseif ($playerCount == '3' && $p1 != '0' && $p2 != '0' && $p3 != '0') {
                    $steamid64_1 = '76561197960265728' + $p1;
                    $steamid64_2 = '76561197960265728' + $p2;
                    $steamid64_3 = '76561197960265728' + $p3;
                    $players = [$steamid64_1, $steamid64_2, $steamid64_3];
                    $tab = '3';
                } else {
                    file_put_contents('arena_debug.log', "Skipped entry #$key-$index: invalid player_count or players\n", FILE_APPEND);
                    continue;
                }

                if ($waveCount === null) {
                    file_put_contents('arena_debug.log', "Skipped entry #$key-$index: no wave_count\n", FILE_APPEND);
                    continue;
                }

                // Запрос к Steam API
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
        }
    }

    // Сортировка
    foreach ($result as $tab => &$entries) {
        usort($entries, function($a, $b) { return $b['wave_count'] - $a['wave_count']; });
        foreach ($entries as $index => &$entry) {
            $entry['rank'] = $index + 1;
        }
    }
} else {
    file_put_contents('arena_debug.log', "No valid data from $arenaUrl\n", FILE_APPEND);
}

file_put_contents('arena_leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
?>
