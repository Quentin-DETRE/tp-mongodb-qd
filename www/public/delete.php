<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();

$entity = $manager->selectCollection('tp')->deleteOne(['_id' => new ObjectId($_GET['id'])]);
$list =  $manager->selectCollection('tp')->find([])->toArray();
// render template
try {
    echo $twig->render('index.html.twig', ['list' => $list]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}