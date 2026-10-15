<?php
/* ==========================================================================
   InfoLeader — includes/stockage.php
   Lecture et écriture des fichiers JSON qui remplacent la base de données :
   configuration (config/) et données de l'application (storage/data/).
   ========================================================================== */

// Lit un fichier JSON et renvoie son contenu sous forme de tableau
function lireJson(string $chemin, array $defaut = []): array
{
    if (!is_file($chemin)) {
        return $defaut;
    }
    $donnees = json_decode((string) file_get_contents($chemin), true);
    return is_array($donnees) ? $donnees : $defaut;
}

// Écrit un tableau dans un fichier JSON lisible
function ecrireJson(string $chemin, array $donnees): bool
{
    $dossier = dirname($chemin);
    if (!is_dir($dossier) && !mkdir($dossier, 0775, true)) {
        return false;
    }
    $json = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // LOCK_EX : deux requêtes simultanées ne peuvent pas écrire le fichier en même temps
    return $json !== false && file_put_contents($chemin, $json, LOCK_EX) !== false;
}

// ----- Compte administrateur (config/admin.json) -----
function lireAdmin(): array
{
    return lireJson(FICHIER_ADMIN);
}

function enregistrerAdmin(array $admin): bool
{
    return ecrireJson(FICHIER_ADMIN, $admin);
}

// ----- Réglages de sécurité (config/securite.json), avec valeurs par défaut -----
function configSecurite(): array
{
    return array_merge([
        // Attente imposée après chaque échec, selon le nombre d'échecs déjà commis
        'paliers_attente' => [
            ['a_partir_de' => 1, 'secondes' => 3],
            ['a_partir_de' => 4, 'secondes' => 10],
            ['a_partir_de' => 6, 'secondes' => 30],
            ['a_partir_de' => 8, 'secondes' => 60],
        ],
        'echecs_avant_blocage'       => 10,  // au 10e échec, l'adresse IP est bloquée
        'duree_blocage_heures'       => 4,
        'oubli_echecs_minutes'       => 60,  // les échecs plus anciens sont oubliés
        'duree_session_minutes'      => 30,  // déconnexion après 30 min d'inactivité
        'validite_code_minutes'      => 5,   // durée de vie du code « mot de passe oublié »
        'essais_code_max'            => 5,
        'delai_renvoi_code_secondes' => 60,
        'demandes_code_max'          => 3,   // « mot de passe oublié » : 3 demandes de code au plus...
        'demandes_code_fenetre_minutes' => 60, // ... par adresse IP et par heure (SEC-4)
        'contact_max_messages'       => 3,   // formulaire de contact : 3 messages au plus...
        'contact_fenetre_minutes'    => 10,  // ... par adresse IP et par tranche de 10 minutes
        'contact_conservation_mois'  => 12,  // durée de conservation annoncée aux visiteurs (RGPD)
        'journal_conservation_mois'  => 6,   // durée de conservation du journal de connexion (RGPD-2)
    ], lireJson(FICHIER_SECURITE));
}

// ----- Configuration des e-mails (config/mail.json), avec valeurs par défaut -----
function configMail(): array
{
    $cfg = array_merge([
        'mode'              => 'phpmail',   // 'phpmail' ou 'smtp'
        'smtp_configure'    => false,
        'expediteur_nom'    => 'InfoLeader',
        'expediteur_email'  => '',
        'destinataire'      => '',
        'smtp_hote'         => 'mail.olivier-dutoit.fr',
        'smtp_port'         => 465,
        'smtp_securite'     => 'ssl',       // 'ssl' (port 465) ou 'tls' = STARTTLS (port 587)
        'smtp_identifiant'  => '',
        'smtp_mot_de_passe' => '',
        'logs_debogage'     => false,       // désactivés par défaut (SEC-5) : mail.log contient les codes envoyés
        'modifie_le'        => null,
    ], lireJson(FICHIER_MAIL));

    // Anciennes versions du fichier (mode « brevo ») : même fonctionnement, nouveau nom
    if ($cfg['mode'] === 'brevo') {
        $cfg['mode'] = 'smtp';
    }
    if (!empty($cfg['brevo_configure'])) {
        $cfg['smtp_configure'] = true;
    }
    unset($cfg['brevo_configure']);
    return $cfg;
}

// ----- Messages du formulaire de contact (storage/data/messages_contact.json) -----
function enregistrerMessageContact(string $nom, string $email, string $message): bool
{
    // RGPD-2 : les messages plus anciens que la durée annoncée aux visiteurs sont supprimés au passage
    $messages = filtrerParAnciennete(lireJson(FICHIER_CONTACTS), (int) configSecurite()['contact_conservation_mois']);
    array_unshift($messages, [
        'date'    => maintenant(),
        'nom'     => mb_substr($nom, 0, 100),
        'email'   => mb_substr($email, 0, 254),
        'message' => mb_substr($message, 0, 5000),
        'ip'      => ipClient(),
    ]);
    // On conserve les 500 messages les plus récents
    return ecrireJson(FICHIER_CONTACTS, array_slice($messages, 0, 500));
}

// ----- Durées de conservation (RGPD-2) -----

// Garde seulement les entrées (avec un champ « date ») de moins de $mois mois
function filtrerParAnciennete(array $entrees, int $mois): array
{
    $limite = strtotime("-$mois months");
    return array_values(array_filter($entrees, function ($entree) use ($limite) {
        $date = strtotime($entree['date'] ?? '');
        return $date === false || $date >= $limite;  // date illisible : on garde par prudence
    }));
}

// Applique les durées de conservation aux messages de contact et au journal de connexion.
// Renvoie le nombre d'entrées supprimées par fichier.
function appliquerDureesConservation(): array
{
    $cfg = configSecurite();
    $supprimees = [];
    $fichiers = [
        'messages de contact'  => [FICHIER_CONTACTS, (int) $cfg['contact_conservation_mois']],
        'journal de connexion' => [FICHIER_JOURNAL, (int) $cfg['journal_conservation_mois']],
    ];
    foreach ($fichiers as $nom => [$fichier, $mois]) {
        $avant = lireJson($fichier);
        $apres = filtrerParAnciennete($avant, $mois);
        if (count($apres) < count($avant)) {
            ecrireJson($fichier, $apres);
        }
        $supprimees[$nom] = count($avant) - count($apres);
    }
    return $supprimees;
}

// Anti-abus (SEC-3) : nombre de messages enregistrés depuis cette adresse IP
// pendant les $minutes dernières minutes
function messagesRecentsDeIp(string $ip, int $minutes): int
{
    $depuis = time() - $minutes * 60;
    $nombre = 0;
    foreach (lireJson(FICHIER_CONTACTS) as $message) {
        if (($message['ip'] ?? '') === $ip && strtotime($message['date'] ?? '') >= $depuis) {
            $nombre++;
        }
    }
    return $nombre;
}
