<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use MongoDB\BSON\Regex;
use MongoDB\BSON\ObjectId;

$twig = getTwig();
$manager = getMongoDbManager();
$redis = getRedisClient();
$es = getElasticSearchClient();

// Paramètres
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$limit = 10;

// Clé de cache
$cacheKey = "livres:search=" . md5($search) . ":page=" . $page;

$list = [];
$maxPages = 0;
$fromCache = false;
$sourceType = 'MongoDB';

// Logique data
if ($redis && $redis->exists($cacheKey)) {
    // On lit depuis Redis
    $data = json_decode($redis->get($cacheKey), true);
    $list = $data['list'];
    $maxPages = $data['maxPages'];
    $sourceType = 'Redis (Cache)';
    $fromCache = true;
} else {
    // On lit depuis MongoDB
    $collection = $manager->selectCollection('tp');
    $idsFromSearch = null;
    $totalDocuments = 0;

    // Cas de recherche avec index
    if (!empty($search)) {
        try {
            $sourceType = 'ElasticSearch';
            $params = [
                'index' => 'books',
                'body'  => [
                    'from' => ($page - 1) * $limit,
                    'size' => $limit,
                    'query' => [
                        'multi_match' => [
                            'query' => $search,
                            'fields' => ['titre^2', 'auteur'],
                            'fuzziness' => 'AUTO'
                        ]
                    ]
                ]
            ];
            $results = $es->search($params);
            $totalDocuments = $results['hits']['total']['value'];
            $idsFromSearch = [];
            foreach ($results['hits']['hits'] as $hit) {
                $idsFromSearch[] = new ObjectId($hit['_id']);
            }

        } catch (\Exception $e) {
            // Si erreur, on revient à MongoDB
            $sourceType = 'MongoDB (Fallback)';
            $idsFromSearch = null;
        }
    }

    // Cas recherche MongoDB
    // Si recherche par index à retourné des trucs
    if ($idsFromSearch !== null) {
        // On récupère les éléments via les ID retournés par ElasticSearch
        if (count($idsFromSearch) > 0) {
            $cursor = $collection->find(['_id' => ['$in' => $idsFromSearch]]);
        } else {
            $cursor = [];
        }
        $maxPages = ceil($totalDocuments / $limit);
    }
    // Si recherche par index n'a rien retourné
    else {
        if ($sourceType !== 'MongoDB (Fallback)') {
            $sourceType = 'MongoDB';
        }
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
            'skip' => $skip,
            'sort' => ['titre' => 1]
        ]);
    }

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
        'fromCache'=> $fromCache,
        'sourceType' => $sourceType
    ]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}