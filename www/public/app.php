<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use MongoDB\BSON\Regex;

$twig = getTwig();
$manager = getMongoDbManager();
$redis = getRedisClient();

// Paramètres
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$limit = 10;

// Clé de cache
$cacheKey = "livres:search=" . md5($search) . ":page=" . $page;

$list = [];
$maxPages = 0;
$fromCache = false;

// Logique data
if ($redis && $redis->exists($cacheKey)) {
    // On lit depuis Redis
    $data = json_decode($redis->get($cacheKey), true);
    $list = $data['list'];
    $maxPages = $data['maxPages'];
    $fromCache = true;
} else {
    // On lit depuis MongoDB
    $collection = $manager->selectCollection('tp');

    // Filtre
    $filter = [];
    if (!empty($search)) {
        $regex = new Regex($search, 'i');
        $filter = [
            '$or' => [
                ['titre' => $regex],
                ['auteur' => $regex]
            ]
        ];
    }

    // Pagination MongoDB
    $totalDocuments = $collection->countDocuments($filter);
    $maxPages = ceil($totalDocuments / $limit);
    if ($page < 1) $page = 1;
    if ($page > $maxPages && $maxPages > 0) $page = $maxPages;
    $skip = ($page - 1) * $limit;

    // Récupération
    $cursor = $collection->find($filter, [
        'limit' => $limit,
        'skip'  => $skip,
        'sort'  => ['titre' => 1]
    ]);

    $list = [];
    foreach ($cursor as $document) {
        $docArray = (array) $document;
        if (isset($docArray['_id'])) {
            $docArray['_id'] = (string) $docArray['_id'];
        }
        $list[] = $docArray;
    }

    // Mise en cache
    if ($redis) {
        $dataToCache = [
            'list' => $list,
            'maxPages' => $maxPages
        ];
        // Stockage pour 60 secondes
        $redis->set($cacheKey, json_encode($dataToCache));
        $redis->expire($cacheKey, 60);
    }
}

// Affichage
try {
    echo $twig->render('index.html.twig', [
        'list'     => $list,
        'page'     => $page,
        'maxPages' => $maxPages,
        'search'   => $search,
        'fromCache'=> $fromCache
    ]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}