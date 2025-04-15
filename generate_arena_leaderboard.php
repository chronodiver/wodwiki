<?php
$apiKey = getenv('STEAM_API_KEY');
$arenaUrl = 'https://data.worldofdota.net/data/get_top_rating_pve_arena.php';
$cacheFile = 'players_cache.json';

// Загрузка кэша
$playerCache = [];
if (file_exists($cacheFile)) {
    $cacheContent = file_get_contents($cacheFile);
    $playerCache = json_decode($cacheContent, true)['players'] ?? [];
}

$arenaData = file_get_contents($arenaUrl);
$arenaPlayers = json_decode($arenaData, true);

// Устанавливаем московский часовой пояс
date_default_timezone_set('Europe/Moscow');

$result = [
    'last_updated' => date('Y-m-d H:i:s'),
    'tabs' => ['1' => [], '2' => [], '3' => []]
];

// Период обновления кэша (1 день в секундах)
$cacheExpiration = 24 * 60 * 60; // 86400 секунд

if (is_array($arenaPlayers)) {
    foreach (['1', '2', '3'] as $key) {
        if (isset($arenaPlayers[$key]) && is_array($arenaPlayers[$key])) {
            foreach ($arenaPlayers[$key] as $entry) {
                $playerCount = isset($entry['player_count']) ? $entry['player_count'] : null;
                $waveCount = isset($entry['wave_count']) ? $entry['wave_count'] : null;
                $p1 = isset($entry['p1']) ? $entry['p1'] : '0';
                $p2 = isset($entry['p2']) ? $entry['p2'] : '0';
                $p3 = isset($entry['p3']) ? $entry['p3'] : '0';

                // Извлекаем героев
                $heroes = [];
                if (isset($entry['hero_1']) && preg_match('/npc_dota_hero_(.+)/', $entry['hero_1'], $match)) {
                    $heroes[] = $match[1];
                }
                if ($key >= 2 && isset($entry['hero_2']) && preg_match('/npc_dota_hero_(.+)/', $entry['hero_2'], $match)) {
                    $heroes[] = $match[1];
                }
                if ($key == 3 && isset($entry['hero_3']) && preg_match('/npc_dota_hero_(.+)/', $entry['hero_3'], $match)) {
                    $heroes[] = $match[1];
                }

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

                $playerData = [];
                foreach ($players as $steamid64) {
                    $updateRequired = true;
                    if (isset($playerCache[$steamid64])) {
                        $lastUpdated = isset($playerCache[$steamid64]['last_updated']) 
                            ? strtotime($playerCache[$steamid64]['last_updated']) 
                            : 0;
                        $currentTime = time();
                        if ($lastUpdated && ($currentTime - $lastUpdated < $cacheExpiration)) {
                            $playerData[] = [
                                'steamid' => (string)$steamid64,
                                'name' => $playerCache[$steamid64]['name'],
                                'avatar' => $playerCache[$steamid64]['avatar']
                            ];
                            $updateRequired = false;
                        }
                    }

                    if ($updateRequired) {
                        $steamUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$steamid64";
                        $steamData = @file_get_contents($steamUrl);
                        if ($steamData === false) {
                            $playerName = 'Неизвестно';
                            $avatarUrl = 'https://via.placeholder.com/64';
                        } else {
                            $steamProfile = json_decode($steamData, true);
                            $playerName = $steamProfile['response']['players'][0]['personaname'] ?? 'Неизвестно';
                            $avatarUrl = $steamProfile['response']['players'][0]['avatarmedium'] ?? 'https://via.placeholder.com/64';
                        }
                        $playerData[] = [
                            'steamid' => (string)$steamid64,
                            'name' => $playerName,
                            'avatar' => $avatarUrl
                        ];
                        // Обновляем кэш
                        $playerCache[$steamid64] = [
                            'name' => $playerName,
                            'avatar' => $avatarUrl,
                            'last_updated' => date('Y-m-d H:i:s')
                        ];
                    }
                }

                $result['tabs'][$tab][] = [
                    'wave_count' => (int)$waveCount,
                    'players' => $playerData,
                    'heroes' => $heroes // Добавляем героев
                ];
            }
        }
    }

    foreach ($result['tabs'] as $tab => &$entries) {
        usort($entries, function($a, $b) { return $b['wave_count'] - $a['wave_count']; });
        foreach ($entries as $index => &$entry) {
            $entry['rank'] = $index + 1;
        }
    }
}

// Сохранение кэша
$cacheData = [
    'players' => $playerCache,
    'last_updated' => date('Y-m-d H:i:s')
];
file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));

// Сохранение arena_leaderboard.json
file_put_contents('arena_leaderboard.json', json_encode($result, JSON_PRETTY_PRINT));
?>
