<?php
$apiKey = getenv('STEAM_API_KEY');
$ratingUrl = 'https://data.worldofdota.net/data/get_top_rating_pve.php';
$cacheFile = 'profiles_cache.json';

$ratingData = file_get_contents($ratingUrl);
$ratingPlayers = json_decode($ratingData, true);
$profilesCache = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];

$players = [];
$steamIds = [];

foreach ($ratingPlayers as $player) {
    $steamId = '76561197960265728' + ($player['steamid'] ?? 0);
    $players[$steamId] = [
        'steamid' => $steamId,
        'rating' => $player['rating'] ?? 0
    ];
    $steamIds[] = $steamId;
}

$uniqueSteamIds = array_unique($steamIds);
$toFetch = array_filter($uniqueSteamIds, function($id) use ($profilesCache) {
    return !isset($profilesCache[$id]) || empty($profilesCache[$id]['name']);
});
$steamIdChunks = array_chunk($toFetch, 100);

foreach ($steamIdChunks as $chunk) {
    $steamUrl = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=" . implode(',', $chunk);
    $steamData = @file_get_contents($steamUrl);
    $steamProfiles = $steamData ? json_decode($steamData, true) : null;

    if (isset($steamProfiles['response']['players'])) {
        foreach ($steamProfiles['response']['players'] as $profile) {
            $steamId = $profile['steamid'];
            $profilesCache[$steamId] = [
                'steamid' => $steamId,
                'name' => $profile['personaname'] ?? 'Неизвестно',
                'avatar' => $profile['avatarmedium'] ?? 'https://via.placeholder.com/64'
            ];
        }
    }
}

$result = [];
foreach ($players as $steamId => $player) {
    $result[] = [
        'rank' => count($result) + 1,
        'steamid' => $steamId,
        'rating' => $player['rating'],
        'name' => $profilesCache[$steamId]['name'] ?? 'Неизвестно',
        'avatar' => $profilesCache[$steamId]['avatar'] ?? 'https://via.placeholder.com/64'
    ];
}

usort($result, function($a, $b) {
    return $b['rating'] - $a['rating'];
});
foreach ($result as $index => &$player) {
    $player['rank'] = $index + 1;
}

file_put_contents('leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
file_put_contents($cacheFile, json_encode($profilesCache, JSON_PRETTY_PRINT));
?>