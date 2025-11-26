import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { randomString } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';

// Configuration du test de charge
export const options = {
    // On définit des étapes pour simuler une montée en charge progressive
    stages: [
        { duration: '10s', target: 5 },  // Montée à 5 utilisateurs en 10s
        { duration: '20s', target: 20 }, // Maintien à 20 utilisateurs pendant 20s
        { duration: '10s', target: 0 },  // Descente à 0 (nettoyage)
    ],
    // Seuils de succès (le test échoue si on dépasse ces limites)
    /*thresholds: {
        http_req_duration: ['p(95)<500'], // 95% des requêtes doivent être sous 500ms
        http_req_failed: ['rate<0.01'],   // Moins de 1% d'erreurs
    },*/
};

const BASE_URL = 'http://tpmongo-php'; // Nom du service docker interne

export default function () {
    let bookId = null;

    // --- 1. SCÉNARIO DE LECTURE (Liste & Pagination) ---
    group('Navigation', function () {
        // A. Page d'accueil (Liste)
        let resIndex = http.get(BASE_URL + '/');

        check(resIndex, {
            'Homepage status is 200': (r) => r.status === 200,
            'Homepage has content': (r) => r.body.includes('Bibliothèque'),
        });

        // EXTRACTION D'ID : On cherche un ID de livre dans le HTML pour l'utiliser après
        // On cherche un lien du type <a href="get.php?id=...">
        let match = resIndex.body.match(/get\.php\?id=([a-zA-Z0-9]+)/);
        if (match) {
            bookId = match[1];
        }

        // B. Pagination (Page 2)
        let resPage = http.get(BASE_URL + '/?page=2');
        check(resPage, { 'Pagination status is 200': (r) => r.status === 200 });
    });

    sleep(1); // Pause utilisateur

    // --- 2. CONSULTATION (Détails) ---
    if (bookId) {
        group('Lecture Détail', function () {
            let resDetail = http.get(BASE_URL + '/get.php?id=' + bookId);
            check(resDetail, {
                'Detail status is 200': (r) => r.status === 200,
                // On vérifie qu'on n'a pas une erreur PHP
                'No error displayed': (r) => !r.body.includes('Fatal error'),
            });
        });
    }

    sleep(1);

    // --- 3. ÉCRITURE (Ajout d'un livre) ---
    group('Ajout Livre', function () {
        // On génère des données aléatoires pour ne pas créer de doublons parfaits
        let payload = {
            titre: 'K6 AutoTest ' + randomString(5),
            auteur: 'Robot K6',
        };

        // Note: Adaptez les noms des champs (titre, auteur) selon votre formulaire HTML (name="...")
        let resCreate = http.post(BASE_URL + '/create.php', payload);

        check(resCreate, {
            'Create status is 200 or 302': (r) => r.status === 200 || r.status === 302,
        });
    });

    // Note sur la suppression :
    // Dans un test de charge, la suppression est délicate car il faut connaître l'ID
    // du livre qu'on vient de créer. Sans parsing complexe, on évite de supprimer
    // des livres au hasard pour ne pas vider la base pendant le test.

    sleep(1);
}