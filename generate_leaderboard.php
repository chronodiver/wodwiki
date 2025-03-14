<?php
$apiKey = getenv('STEAM_API_KEY');
$topRatingUrl = 'https://data.worldofdota.net/data/get_top_rating_150.php';

$ratingData = file_get_contents($topRatingUrl);
$players = json_decode($ratingData, true);

$result = [];
if (is_array($players)) {
    foreach ($players as $index => $player) {
        $account_id = $player['steamid'];
        $rating = $player['rating'];
        $steamid64 = '76561197960265728' + $account_id;

        $steamUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$steamid64";
        $steamData = file_get_contents($steamUrl);
        $steamProfile = json_decode($steamData, true);

        $playerName = $steamProfile['response']['players'][0]['personaname'] ?? 'Неизвестно';
        $avatarUrl = $steamProfile['response']['players'][0]['avatarmedium'] ?? 'https://via.placeholder.com/64'; // Используем avatarmedium

        $result[] = [
            'rank' => $index + 1,
            'steamid' => $steamid64,
            'name' => $playerName,
            'avatar' => $avatarUrl,
            'rating' => $rating
        ];
    }
}

file_put_contents('leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
?>
