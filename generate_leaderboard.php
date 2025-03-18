<?php
$apiKey = getenv('STEAM_API_KEY');
$ratingUrl = 'https://data.worldofdota.net/data/get_top_rating_150.php'; // Предположительный URL

$ratingData = file_get_contents($ratingUrl);
$ratingPlayers = json_decode($ratingData, true);

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

$steamUrl = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=" . implode(',', $steamIds);
$steamData = file_get_contents($steamUrl);
$steamProfiles = json_decode($steamData, true);

if (isset($steamProfiles['response']['players'])) {
    foreach ($steamProfiles['response']['players'] as $profile) {
        $steamId = $profile['steamid'];
        $players[$steamId]['name'] = $profile['personaname'] ?? 'Неизвестно';
        $players[$steamId]['avatar'] = $profile['avatarmedium'] ?? 'https://via.placeholder.com/64';
    }
}

$result = [];
foreach ($players as $steamId => $player) {
    $result[] = [
        'rank' => count($result) + 1,
        'steamid' => $steamId,
        'rating' => $player['rating'],
        'name' => $player['name'] ?? 'Неизвестно',
        'avatar' => $player['avatar'] ?? 'https://via.placeholder.com/64'
    ];
}

usort($result, function($a, $b) {
    return $b['rating'] - $a['rating'];
});
foreach ($result as $index => &$player) {
    $player['rank'] = $index + 1;
}

file_put_contents('leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
?>