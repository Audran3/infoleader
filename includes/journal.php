<?php
/* ==========================================================================
   InfoLeader — includes/journal.php
   Journal de connexion (storage/data/journal_connexion.json).
   Types d'entrée :
     succes    : connexion réussie
     echec     : identifiants, code ou mot de passe incorrects
     blocage   : adresse IP bloquée ou tentative refusée
     evenement : autre action (déconnexion, changement de mot de passe, purge…)
   ========================================================================== */

const JOURNAL_TAILLE_MAX = 1000;

function journaliser(string $type, string $message): void
{
    // RGPD-2 : les entrées plus anciennes que la durée de conservation sont supprimées au passage
    $journal = filtrerParAnciennete(lireJson(FICHIER_JOURNAL), (int) configSecurite()['journal_conservation_mois']);
    // La plus récente en premier
    array_unshift($journal, [
        'date'    => maintenant(),
        'type'    => $type,
        'ip'      => ipClient(),
        'message' => $message,
    ]);
    ecrireJson(FICHIER_JOURNAL, array_slice($journal, 0, JOURNAL_TAILLE_MAX));
}

// SEC-6 : version masquée d'un identifiant saisi, destinée au journal.
// Un mot de passe tapé par erreur dans le champ e-mail n'est ainsi jamais enregistré.
//   « admin@infoleader.local » => « ad***@infoleader.local »
//   tout texte qui n'est pas une adresse e-mail => « (saisie non valide) »
function masquerIdentifiant(string $saisie): string
{
    $saisie = trim($saisie);
    if ($saisie === '') {
        return '(vide)';
    }
    if (!filter_var($saisie, FILTER_VALIDATE_EMAIL)) {
        return '(saisie non valide)';
    }
    $arobase = strrpos($saisie, '@');
    $partieLocale = substr($saisie, 0, $arobase);
    $domaine = substr($saisie, $arobase + 1);
    // 2 premiers caractères visibles (1 seul si la partie avant @ est très courte)
    $visibles = mb_strlen($partieLocale) > 3 ? 2 : 1;
    return mb_substr($partieLocale, 0, $visibles) . '***@' . $domaine;
}

// Libellé affiché pour chaque type d'entrée
function libelleTypeJournal(string $type): string
{
    $libelles = [
        'succes'    => 'Connexion réussie',
        'echec'     => 'Échec',
        'blocage'   => 'Blocage',
        'evenement' => 'Événement',
    ];
    return $libelles[$type] ?? $type;
}
