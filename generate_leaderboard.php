<?php
$apiKey = getenv('STEAM_API_KEY'); // Ваш Steam API ключ
$topRatingUrl = 'https://data.worldofdota.net/data/get_top_rating_150.php'; // URL с данными рейтинга

// Получаем данные
$ratingData = file_get_contents($topRatingUrl);
$players = json_decode($ratingData, true);

$result = [];
if (is_array($players)) {
    foreach ($players as $index => $player) {
        $account_id = $player['steamid']; // Число, например 123456 (AccountID)
        $rating = $player['rating'];

        // Преобразуем AccountID в SteamID64
        $steamid64 = '76561197960265728' + $account_id;

        // Запрос к Steam API
        $steamUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$steamid64";
        $steamData = file_get_contents($steamUrl);
        $steamProfile = json_decode($steamData, true);

        // Извлекаем ник и аватар
        $playerName = $steamProfile['response']['players'][0]['personaname'] ?? 'Неизвестно';
        $avatarUrl = $steamProfile['response']['players'][0]['avatar'] ?? 'https://via.placeholder.com/32';

        // Формируем результат
        $result[] = [
            'rank' => $index + 1,
            'steamid' => $steamid64,
            'name' => $playerName,
            'avatar' => $avatarUrl,
            'rating' => $rating
        ];
    }
}

// Сохраняем в файл
file_put_contents('leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
?>
