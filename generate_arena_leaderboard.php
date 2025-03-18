<?php
$apiKey = getenv('STEAM_API_KEY');
$arenaUrl = 'https://data.worldofdota.net/data/get_top_rating_pve_arena.php';
$cacheFile = 'profiles_cache.json';

$arenaData = file_get_contents($arenaUrl);
$arenaPlayers = json_decode($arenaData, true);
$profilesCache = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];

$result = ['1' => [], '2' => [], '3' => []];
$allSteamIds = [];

foreach (['1', '2', '3'] as $key) {
    if (isset($arenaPlayers[$key]) && is_array($arenaPlayers[$key])) {
        foreach ($arenaPlayers[$key] as $entry) {
            $playerCount = isset($entry['player_count']) ? $entry['player_count'] : null;
            $waveCount = isset($entry['wave_count']) ? $entry['wave_count'] : null;
            $p1 = isset($entry['p1']) ? $entry['p1'] : '0';
            $p2 = isset($entry['p2']) ? $entry['p2'] : '0';
            $p3 = isset($entry['p3']) ? $entry['p3'] : '0';

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
                continue;
            }

            if ($waveCount === null) {
                continue;
            }

            $result[$tab][] = [
                'wave_count' => (int)$waveCount,
                'players' => $players
            ];
            $allSteamIds = array_merge($allSteamIds, $players);
        }
    }
}

$uniqueSteamIds = array_unique($allSteamIds);
$toFetch = array_filter($uniqueSteamIds, function($id) use ($profilesCache) {
    return !isset($profilesCache[$id]) || empty($profilesCache[$id]['name']);
});
$steamIdChunks = array_chunk($toFetch, 100);

foreach ($steamIdChunks as $chunk) {
    $steamUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=" . implode(',', $chunk);
    $steamData = @file_get_contents($steamUrl);
    $steamProfile = $steamData ? json_decode($steamData, true) : null;

    if (isset($steamProfile['response']['players'])) {
        foreach ($steamProfile['response']['players'] as $p) {
            $profilesCache[$p['steamid']] = [
                'steamid' => $p['steamid'],
                'name' => $p['personaname'] ?? 'Неизвестно',
                'avatar' => $p['avatarmedium'] ?? 'https://via.placeholder.com/64'
            ];
        }
    }
}

foreach ($result as $tab => &$entries) {
    usort($entries, function($a, $b) { return $b['wave_count'] - $a['wave_count']; });
    foreach ($entries as $index => &$entry) {
        $entry['rank'] = $index + 1;
        $playerData = [];
        foreach ($entry['players'] as $steamid) {
            $playerData[] = $profilesCache[$steamid] ?? [
                'steamid' => $steamid,
                'name' => 'Неизвестно',
                'avatar' => 'https://via.placeholder.com/64'
            ];
        }
        $entry['players'] = $playerData;
    }
}

file_put_contents('arena_leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
file_put_contents($cacheFile, json_encode($profilesCache, JSON_PRETTY_PRINT));
?>