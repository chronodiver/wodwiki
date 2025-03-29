<?php
$votesUrl = 'https://data.worldofdota.net/data/get_heroes_votes.php';
$maxVotes = 500000; // Максимум голосов

$votesData = @file_get_contents($votesUrl);
if ($votesData === false) {
    file_put_contents('heroes_votes.json', json_encode([], JSON_PRETTY_PRINT));
    exit("Failed to fetch heroes votes data from $votesUrl");
}

$heroesVotes = json_decode($votesData, true);
if ($heroesVotes === null) {
    file_put_contents('heroes_votes.json', json_encode([], JSON_PRETTY_PRINT));
    exit("Failed to decode JSON from $votesUrl");
}

$result = [];
foreach ($heroesVotes as $hero) {
    $votes = (int)($hero['votes'] ?? 0);
    $heroName = str_replace('npc_dota_hero_', '', $hero['hero_name'] ?? ''); // Убираем префикс

    // Пропускаем героев с 500,000 голосов
    if ($votes >= $maxVotes) {
        continue;
    }

    $result[] = [
        'hero_name' => $heroName,
        'votes' => $votes,
        'image_url' => "https://cdn.akamai.steamstatic.com/apps/dota2/images/dota_react/heroes/{$heroName}.png"
    ];
}

// Сортируем по убыванию голосов (опционально)
usort($result, function($a, $b) {
    return $b['votes'] - $a['votes'];
});

file_put_contents('heroes_votes.json', json_encode($result, JSON_PRETTY_PRINT));
?>
