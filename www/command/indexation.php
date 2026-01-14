<?php
$possiblePaths = [
    '/var/www/app/init.php',
    __DIR__ . '/../init.php'
];
foreach ($possiblePaths as $path) {
    if (file_exists($path)) { include_once $path; break; }
}

$mongo = getMongoDbManager();
$es = getElasticSearchClient();
$collection = $mongo->selectCollection('tp');

echo "------------------------------------------------\n";
echo "Démarrage de l'indexation...\n";

// Suppression de l'ancien index
try {
    $es->indices()->delete(['index' => 'books']);
    echo "Ancien index supprimé.\n";
} catch (\Exception $e) {
    echo "L'index n'existait pas encore.\n";
}

// Lecture des données
$cursor = $collection->find();
$total = 0;
$errors = 0;

echo "Traitement des documents...\n";

foreach ($cursor as $document) {
    $docArray = (array)$document;
    // --- NETTOYAGE DES DONNEES ---
    // On force la conversion en string
    $id = (string)$docArray['_id'];
    //Titre : S'il est vide on met un placeholder
    $titre = !empty($docArray['titre']) ? $docArray['titre'] : 'Titre inconnu';
    // Auteur : S'il est vide on met "Anonyme"
    $auteur = !empty($docArray['auteur']) ? $docArray['auteur'] : 'Anonyme';
    // Siècle : On s'assure que c'est un nombre ou 0
    $siecle = isset($docArray['siecle']) ? (int)$docArray['siecle'] : 0;

    $params = [
        'index' => 'books',
        'id'    => $id,
        'body'  => [
            'titre'  => $titre,
            'auteur' => $auteur,
            'siecle' => $siecle
        ]
    ];

    try {
        $es->index($params);
        $total++;
        if ($total % 200 == 0) echo "$total livres traités...\n";
    } catch (Exception $e) {
        $errors++;
        echo "Erreur sur l'ID $id : " . $e->getMessage() . "\n";
    }
}

echo "------------------------------------------------\n";
echo "TERMINE ! \n";
echo "Livres indexés avec succès : $total\n";
echo "Erreurs : $errors\n";