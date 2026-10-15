<?php
/* ==========================================================================
   InfoLeader — includes/creer-admin.php
   Outil en ligne de commande (inaccessible depuis le navigateur) qui crée ou
   réinitialise le compte administrateur avec un mot de passe aléatoire.
   À utiliser pour l'installation, ou en dernier recours si le mot de passe
   est perdu et que l'envoi d'e-mails ne fonctionne pas.

   Utilisation, dans un terminal ouvert dans ce dossier :
     C:\wamp64\bin\php\php8.3.28\php.exe creer-admin.php admin@infoleader.local C:\chemin\identifiants.txt

   Le mot de passe généré est écrit dans le fichier indiqué, jamais affiché à l'écran.
   ========================================================================== */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Accès interdit.');
}
require __DIR__ . '/bootstrap.php';

[, $email, $fichierSortie] = array_pad($argv, 3, null);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$fichierSortie) {
    fwrite(STDERR, "Utilisation : php creer-admin.php <adresse e-mail> <fichier où écrire les identifiants>\n");
    exit(1);
}

// Mot de passe aléatoire de 24 caractères qui respecte toutes les règles
function genererMotDePasse(int $longueur = 24): string
{
    $groupes = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%&*-_?+='];
    $tous = implode('', $groupes);
    $caracteres = [];
    foreach ($groupes as $groupe) {
        $caracteres[] = $groupe[random_int(0, strlen($groupe) - 1)];  // au moins un de chaque groupe
    }
    while (count($caracteres) < $longueur) {
        $caracteres[] = $tous[random_int(0, strlen($tous) - 1)];
    }
    // Mélange avec random_int (shuffle() n'est pas assez aléatoire pour un mot de passe)
    for ($i = count($caracteres) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
    }
    return implode('', $caracteres);
}

$motDePasse = genererMotDePasse();
$admin = [
    'email'                   => $email,
    'password_hash'           => hacherMotDePasse($motDePasse),
    'cree_le'                 => maintenant(),
    'mot_de_passe_modifie_le' => maintenant(),
    'email_modifie_le'        => null,
    'derniere_connexion'      => null,
];
if (!enregistrerAdmin($admin)) {
    fwrite(STDERR, "Impossible d'écrire config/admin.json (droits d'écriture ?).\n");
    exit(1);
}

$contenu = "Panneau d'administration InfoLeader\r\n"
    . "===================================\r\n\r\n"
    . "Adresse      : http://infoleader.local/admin/  (ou http://localhost/infoleader/admin/)\r\n"
    . "Identifiant  : $email\r\n"
    . "Mot de passe : $motDePasse\r\n\r\n"
    . "Changez ce mot de passe dès la première connexion (module « Mot de passe »),\r\n"
    . "puis supprimez ce fichier.\r\n";
if (file_put_contents($fichierSortie, $contenu) === false) {
    fwrite(STDERR, "Compte créé, mais impossible d'écrire le fichier $fichierSortie.\n");
    exit(1);
}
echo "Compte administrateur créé pour $email. Identifiants écrits dans : $fichierSortie\n";
