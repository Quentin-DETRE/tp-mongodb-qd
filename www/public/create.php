<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();

if (!empty($_POST)) {
    // Ajout en BDD
    $manager->selectCollection('tp')->insertOne($_POST);

    // Invalidation du cache Redis
    $redis = getRedisClient();
    if ($redis) {
        $keys = $redis->keys('livres:*');
        if (!empty($keys)) {
            $redis->del($keys);
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