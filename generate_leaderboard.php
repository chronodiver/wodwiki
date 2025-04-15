<?php
$apiKey = getenv('STEAM_API_KEY');
$topRatingUrl = 'https://data.worldofdota.net/data/get_top_rating_150.php';
$cacheFile = 'players_cache.json';

// Загрузка кэша
$playerCache = [];
if (file_exists($cacheFile)) {
    $cacheContent = file_get_contents($cacheFile);
    $playerCache = json_decode($cacheContent, true)['players'] ?? [];
}

$ratingData = file_get_contents($topRatingUrl);
$players = json_decode($ratingData, true);

// Устанавливаем московский часовой пояс
date_default_timezone_set('Europe/Moscow');

$result = [
    'last_updated' => date('Y-m-d H:i:s'), // Время в MSK
    'players' => []
];

// Период обновления кэша (1 день в секундах)
$cacheExpiration = 24 * 60 * 60; // 86400 секунд

if (is_array($players)) {
    foreach ($players as $index => $player) {
        $account_id = $player['steamid'];
        $rating = $player['rating'];

        // Вычисляем SteamID64 для запроса
        $steamid64 = '76561197960265728' + $account_id;

        // Проверяем кэш
        $updateRequired = true;
        if (isset($playerCache[$steamid64])) {
            $lastUpdated = isset($playerCache[$steamid64]['last_updated']) 
                ? strtotime($playerCache[$steamid64]['last_updated']) 
                : 0;
            $currentTime = time();
            if ($lastUpdated && ($currentTime - $lastUpdated < $cacheExpiration)) {
                $playerName = $playerCache[$steamid64]['name'];
                $avatarUrl = $playerCache[$steamid64]['avatar'];
                $realSteamID64 = (string)$steamid64;
                $updateRequired = false;
            }
        }

        if ($updateRequired) {
            // Запрос к Steam API
            $steamUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$steamid64";
            $steamData = @file_get_contents($steamUrl);
            if ($steamData === false) {
                $playerName = 'Неизвестно';
                $avatarUrl = 'https://via.placeholder.com/64';
                $realSteamID64 = (string)$steamid64;
            } else {
                $steamProfile = json_decode($steamData, true);
                $playerName = $steamProfile['response']['players'][0]['personaname'] ?? 'Неизвестно';
                $avatarUrl = $steamProfile['response']['players'][0]['avatarmedium'] ?? 'https://via.placeholder.com/64';
                $realSteamID64 = $steamProfile['response']['players'][0]['steamid'] ?? (string)$steamid64;
            }
            // Обновляем кэш
            $playerCache[$steamid64] = [
                'name' => $playerName,
                'avatar' => $avatarUrl,
                'last_updated' => date('Y-m-d H:i:s')
            ];
        }

        $result['players'][] = [
            'rank' => $index + 1,
            'steamid' => $realSteamID64,
            'name' => $playerName,
            'avatar' => $avatarUrl,
            'rating' => $rating
        ];
    }
}

// Сохранение кэша
$cacheData = [
    'players' => $playerCache,
    'last_updated' => date('Y-m-d H:i:s')
];
file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));

// Сохранение leaderboard.json
file_put_contents('leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
?>
