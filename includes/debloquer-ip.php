<?php
/* ==========================================================================
   InfoLeader — includes/debloquer-ip.php  (tâche EXP-1)
   Outil en ligne de commande (inaccessible depuis le navigateur) qui affiche
   et lève les blocages de l'anti-brute force, sans supprimer de fichier à la main.

   Utilisation, dans un terminal ouvert dans ce dossier :
     C:\wamp64\bin\php\php8.3.28\php.exe debloquer-ip.php            liste les adresses suivies
     C:\wamp64\bin\php\php8.3.28\php.exe debloquer-ip.php ::1        débloque une adresse
     C:\wamp64\bin\php\php8.3.28\php.exe debloquer-ip.php --toutes   débloque toutes les adresses

   En local, les connexions viennent de ::1 (IPv6) ou de 127.0.0.1 (IPv4).
   ========================================================================== */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Accès interdit.');
}
require __DIR__ . '/bootstrap.php';

$cible = $argv[1] ?? '';
$toutes = lireJson(FICHIER_LIMITATION);

// Sans argument : liste des adresses suivies et de leur état
if ($cible === '') {
    if (!$toutes) {
        echo "Aucune adresse IP n'est suivie : aucun échec de connexion récent.\n";
        exit(0);
    }
    echo "Adresses suivies par l'anti-brute force :\n";
    foreach ($toutes as $ip => $etat) {
        $bloque = ($etat['bloque_jusqua'] ?? 0) > time();
        printf(
            "  %-40s %2d échec(s)  %s\n",
            $ip,
            $etat['echecs'] ?? 0,
            $bloque ? 'BLOQUÉE jusqu\'au ' . date('d/m/Y à H:i', $etat['bloque_jusqua']) : 'non bloquée'
        );
    }
    echo "\nPour débloquer : php debloquer-ip.php <adresse>   ou   php debloquer-ip.php --toutes\n";
    exit(0);
}

// --toutes : on efface tout l'état de l'anti-brute force
if ($cible === '--toutes') {
    ecrireJson(FICHIER_LIMITATION, []);
    journaliser('evenement', 'Toutes les adresses IP ont été débloquées (outil en ligne de commande).');
    echo count($toutes) . " adresse(s) débloquée(s).\n";
    exit(0);
}

// Une adresse précise
if (!isset($toutes[$cible])) {
    fwrite(STDERR, "L'adresse « $cible » n'est pas suivie. Lancez le script sans argument pour voir la liste.\n");
    exit(1);
}
reinitialiserLimitation($cible);
journaliser('evenement', "Adresse IP $cible débloquée (outil en ligne de commande).");
echo "Adresse $cible débloquée : la connexion est de nouveau possible immédiatement.\n";
