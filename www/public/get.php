<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();

$entity = $manager->selectCollection('tp')->findOne(['_id' => new ObjectId($_GET['id'])]);

// gestion des pages et de la recherche
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// render template
try {
    echo $twig->render('get.html.twig', ['entity' => $entity, 'page' => $page, 'search' => $search]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}