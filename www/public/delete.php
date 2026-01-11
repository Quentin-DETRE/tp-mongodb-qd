<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;

$manager = getMongoDbManager();

// Suppression BDD
$manager->selectCollection('tp')->deleteOne(['_id' => new ObjectId($_GET['id'])]);

// Invalidation du cache Redis
$redis = getRedisClient();
if ($redis) {
    //On vide le cache pour que la liste se régénère sans le livre supprimé
    $keys = $redis->keys('livres:*');
    if (!empty($keys)) {
        $redis->del($keys);
    }
}
// Gestion des pages et de la recherche
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// redirection
header("Location: index.php?page=$page&search=$search");
exit();