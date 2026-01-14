<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
$es = getElasticSearchClient();

if (!empty($_POST)) {
    $dataToUpdate = [
        'titre'  => $_POST['titre'],
        'auteur' => $_POST['auteur'],
        'siecle' => (int)$_POST['siecle']
    ];
    // Mise à jour BDD
    $manager->selectCollection('tp')->updateOne(
        ['_id' => new ObjectId($_POST["id"])],
        ['$set' => $_POST]
    );
    $es->index([
        'index' => 'books',
        'id'    => $_POST['id'],
        'body'  => $dataToUpdate,
    ]);

    // Invalidation du cache Redis
    $redis = getRedisClient();
    if ($redis) {
        $keys = $redis->keys('livres:*');
        if (!empty($keys)) {
            foreach ($keys as $key) $redis->del($key);
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
        echo $twig->render('update.html.twig');
    } catch (LoaderError|RuntimeError|SyntaxError $e) {
        echo $e->getMessage();
    }
}