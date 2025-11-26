<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();

// Configuration
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$collection = $manager->selectCollection('tp');

// Compter le total
$totalDocuments = $collection->countDocuments([]);
$maxPages = ceil($totalDocuments / $limit);

// Sécurité : Gestion des dépassements de page
// Si on demande la page 0 ou moins, on force la 1
if ($page < 1) {
    $page = 1;
}
// Si on demande une page supérieure au max (ex: 192 sur 42), on force la dernière page
if ($page > $maxPages && $maxPages > 0) {
    $page = $maxPages;
}

// Calcul du skip
$skip = ($page - 1) * $limit;

// Récupération des données
$list = $collection->find([], [
    'limit' => $limit,
    'skip'  => $skip,
    'sort'  => ['titre' => 1]
])->toArray();

// render template
try {
    echo $twig->render('index.html.twig', [
        'list'     => $list,
        'page'     => $page,
        'maxPages' => $maxPages
    ]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}