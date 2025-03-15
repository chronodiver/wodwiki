<?php
$apiKey = getenv('STEAM_API_KEY');
$arenaUrl = 'https://data.worldofdota.net/data/get_top_rating_pve_arena.php';

$arenaData = file_get_contents($arenaUrl);
$arenaPlayers = json_decode($arenaData, true);

$result = ['1' => [], '2' => [], '3' => []];

foreach ($arenaPlayers as $entry) {
    $waveCount = $entry['wave_count'];
    $players = [];
    
    // Собираем steamid игроков в зависимости от вкладки
    if (isset($entry['1'])) {
        $steamid3 = $entry['1']['p1']['steamid'];
        $steamid64 = '76561197960265728' + $steamid3;
        $players[] = $steamid64;
        $tab = '1';
    } elseif (isset($entry['2'])) {
        $steamid3_1 = $entry['2']['p1']['steamid'];
        $steamid3_2 = $entry['2']['p2']['steamid'];
        $steamid64_1 = '76561197960265728' + $steamid3_1;
        $steamid64_2 = '76561197960265728' + $steamid3_2;
        $players = [$steamid64_1, $steamid64_2];
        $tab = '2';
    } elseif (isset($entry['3'])) {
        $steamid3_1 = $entry['3']['p1']['steamid'];
        $steamid3_2 = $entry['3']['p2']['steamid'];
        $steamid3_3 = $entry['3']['p3']['steamid'];
        $steamid64_1 = '76561197960265728' + $steamid3_1;
        $steamid64_2 = '76561197960265728' + $steamid3_2;
        $steamid64_3 = '76561197960265728' + $steamid3_3;
        $players = [$steamid64_1, $steamid64_2, $steamid64_3];
        $tab = '3';
    }

    // Запрос к Steam API для всех игроков
    $steamUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=" . implode(',', $players);
    $steamData = file_get_contents($steamUrl);
    $steamProfile = json_decode($steamData, true);

    $playerData = [];
    foreach ($steamProfile['response']['players'] as $p) {
        $playerData[] = [
            'steamid' => $p['steamid'], // realSteamID64
            'name' => $p['personaname'] ?? 'Неизвестно',
            'avatar' => $p['avatarmedium'] ?? 'https://via.placeholder.com/64'
        ];
    }

    // Добавляем запись в соответствующую вкладку
    $result[$tab][] = [
        'wave_count' => $waveCount,
        'players' => $playerData
    ];
}

// Сортировка по wave_count (убывание)
foreach ($result as $tab => &$entries) {
    usort($entries, function($a, $b) {
        return $b['wave_count'] - $a['wave_count'];
    });
    // Добавляем rank
    foreach ($entries as $index => &$entry) {
        $entry['rank'] = $index + 1;
    }
}

file_put_contents('arena_leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
?>
