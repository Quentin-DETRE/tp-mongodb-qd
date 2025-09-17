<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();

// @todo implementez la récupération des données d'une entité et la passer au template
// petite aide : https://github.com/VSG24/mongodb-php-examples
$entity = $manager->selectCollection('tp')->deleteOne(['_id' => new ObjectId($_GET['id'])]);
$list =  $manager->selectCollection('tp')->find([])->toArray();
// render template
try {
    echo $twig->render('index.html.twig', ['list' => $list]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}