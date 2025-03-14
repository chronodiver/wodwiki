<?php
$apiKey = getenv('STEAM_API_KEY'); // Ваш ключ Steam API из переменной окружения
$topRatingUrl = 'https://data.worldofdota.net/data/get_top_rating_150.php'; // Источник рейтинга

// Функция для преобразования SteamID3 в SteamID64
function steamid3_to_steamid64($steamid3) {
    if (preg_match('/\[U:1:(\d+)\]/', $steamid3, $matches)) {
        $account_id = $matches[1];
        return '76561197960265728' + $account_id;
    }
    return null; // Если формат неверный, вернем null
}

// Получаем данные рейтинга
$ratingData = file_get_contents($topRatingUrl);
$players = json_decode($ratingData, true);

$result = [];
if (is_array($players)) {
    foreach ($players as $index => $player) {
        $steamid3 = $player['steamid']; // SteamID3 из данных
        $rating = $player['rating'];

        // Преобразуем SteamID3 в SteamID64
        $steamid64 = steamid3_to_steamid64($steamid3);
        if ($steamid64) {
            // Запрос к Steam API для получения ника и аватара
            $steamUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$steamid64";
            $steamData = file_get_contents($steamUrl);
            $steamProfile = json_decode($steamData, true);

            // Извлекаем ник и аватар
            $playerName = $steamProfile['response']['players'][0]['personaname'] ?? 'Неизвестно';
            $avatarUrl = $steamProfile['response']['players'][0]['avatar'] ?? 'https://via.placeholder.com/32';

            // Добавляем данные в результат
            $result[] = [
                'rank' => $index + 1,
                'steamid' => $steamid64,
                'name' => $playerName,
                'avatar' => $avatarUrl,
                'rating' => $rating
            ];
        } else {
            // Если преобразование не удалось
            $result[] = [
                'rank' => $index + 1,
                'steamid' => $steamid3,
                'name' => 'Неизвестно',
                'avatar' => 'https://via.placeholder.com/32',
                'rating' => $rating
            ];
        }
    }
}

// Сохраняем результат в файл
file_put_contents('leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
?>
