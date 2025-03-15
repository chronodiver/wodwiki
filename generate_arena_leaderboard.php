<?php
$apiKey = getenv('STEAM_API_KEY');
$arenaUrl = 'https://data.worldofdota.net/data/get_top_rating_pve_arena.php';

$arenaData = file_get_contents($arenaUrl);
$arenaPlayers = json_decode($arenaData, true);

$result = ['1' => [], '2' => [], '3' => []];

if (is_array($arenaPlayers)) {
    foreach ($arenaPlayers as $entry) {
        $playerCount = $entry['player_count'];
        $waveCount = $entry['wave_count'];
        $players = [];

        // Определяем SteamID3 и преобразуем в SteamID64
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
            continue; // Пропускаем, если player_count не 1, 2 или 3, или игроки некорректны
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
        }

        // Добавляем запись в соответствующую вкладку
        $result[$tab][] = [
            'wave_count' => (int)$waveCount, // Приводим к числу
            'players' => $playerData
        ];
    }

    // Сортировка по wave_count (убывание)
    foreach ($result as $tab => &$entries) {
        usort($entries, function($a, $b) { return $b['wave_count'] - $a['wave_count']; });
        foreach ($entries as $index => &$entry) {
            $entry['rank'] = $index + 1;
        }
    }
}

file_put_contents('arena_leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
?>
