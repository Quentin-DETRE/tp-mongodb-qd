<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();

if (!empty($_POST)) {
    $manager->selectCollection('tp')->updateOne(['_id' => new ObjectId($_POST["id"])], ['$set' => $_POST]);
    header('Location: index.php');
} else {
// render template
    try {
        echo $twig->render('update.html.twig');
    } catch (LoaderError|RuntimeError|SyntaxError $e) {
        echo $e->getMessage();
    }
}
