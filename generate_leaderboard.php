<?php
$apiKey = getenv('STEAM_API_KEY'); // Ключ будет браться из переменной окружения
$topRatingUrl = 'https://data.worldofdota.net/data/get_top_rating_150.php'; // Источник данных рейтинга

$ratingData = file_get_contents($topRatingUrl);
$players = json_decode($ratingData, true);

$result = [];
if (is_array($players)) {
    foreach ($players as $index => $player) {
        $steamid = $player['steamid'];
        $rating = $player['rating'];

        $steamUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$steamid";
        $steamData = file_get_contents($steamUrl);
        $steamProfile = json_decode($steamData, true);

        $playerName = $steamProfile['response']['players'][0]['personaname'] ?? 'Неизвестно';
        $avatarUrl = $steamProfile['response']['players'][0]['avatar'] ?? 'https://via.placeholder.com/32';

        $result[] = [
            'rank' => $index + 1,
            'steamid' => $steamid,
            'name' => $playerName,
            'avatar' => $avatarUrl,
            'rating' => $rating
        ];
    }
}

file_put_contents('leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
?>
