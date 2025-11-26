<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
// Important : pour la recherche "floue" (contient le mot)
use MongoDB\BSON\Regex;

$twig = getTwig();
$manager = getMongoDbManager();
$collection = $manager->selectCollection('tp');

// Récupération des paramètres
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : ''; // Récupère le mot-clé
$limit = 10;

// Construction du filtre de recherche
$filter = [];

if (!empty($search)) {
    // Création d'une Regex insensible à la casse ('i')
    // On cherche dans le Titre OU ($or) dans l'Auteur
    $regex = new Regex($search, 'i');
    $filter = [
        '$or' => [
            ['titre' => $regex],
            ['auteur' => $regex]
        ]
    ];
}

// Comptage
$totalDocuments = $collection->countDocuments($filter);
$maxPages = ceil($totalDocuments / $limit);

// Sécurités de page
if ($page < 1) $page = 1;
if ($page > $maxPages && $maxPages > 0) $page = $maxPages;

$skip = ($page - 1) * $limit;

// Récupération des données
$list = $collection->find($filter, [
    'limit' => $limit,
    'skip'  => $skip,
    'sort'  => ['titre' => 1]
])->toArray();

// render template
try {
    echo $twig->render('index.html.twig', [
        'list'     => $list,
        'page'     => $page,
        'maxPages' => $maxPages,
        'search'   => $search // On renvoie le mot clé à la vue pour pré-remplir le champ
    ]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}