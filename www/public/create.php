<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
$es = getElasticSearchClient();

if (!empty($_POST)) {
    $document = [
        'titre'  => $_POST['titre'],
        'auteur' => $_POST['auteur'],
        'siecle' => (int)$_POST['siecle']
    ];
    // Ajout en BDD
    $result = $manager->selectCollection('tp')->insertOne($_POST);
    $newId = (string) $result->getInsertedId();

    // Ajout ElasticSearch
    $es->index([
        'index' => 'books',
        'id'    => $newId,
        'body'  => $document,
    ]);


    // Invalidation du cache Redis
    $redis = getRedisClient();
    if ($redis) {
        $keys = $redis->keys('livres:*');
        if (!empty($keys)) {
            foreach ($keys as $key) {
                $redis->del($key);
            }
        }
    }

    // Récupération de la page et de la recherche
    $page = $_POST['origin_page'] ?? 1;
    $search = $_POST['origin_search'] ?? '';

    // Redirection
    header("Location: index.php?page=$page&search=$search");
    exit();
} else {
    try {
        echo $twig->render('create.html.twig');
    } catch (LoaderError|RuntimeError|SyntaxError $e) {
        echo $e->getMessage();
    }
}