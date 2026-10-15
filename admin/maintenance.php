<?php
/* ==========================================================================
   InfoLeader — admin/maintenance.php
   Module « Maintenance » : 4 outils (?section=...)
     systeme    : état global + fichiers temporaires, environnement,
                  droits d'écriture, fichiers de configuration ;
     journaux   : journal de connexion (filtrable) et messages de contact ;
     sauvegarde : export JSON sans les secrets ;
     purge      : suppression des fichiers de storage/tmp.
   Toute action irréversible passe par une fenêtre de confirmation.
   ========================================================================== */
require __DIR__ . '/../includes/bootstrap.php';
$admin = exigerConnexion();

$sections = [
    'systeme'    => 'Système',
    'journaux'   => 'Journaux',
    'sauvegarde' => 'Sauvegarde',
    'purge'      => 'Purger storage/tmp',
];
$section = array_key_exists($_GET['section'] ?? '', $sections) ? $_GET['section'] : 'systeme';

// RGPD-2 : à l'ouverture du module, les données plus anciennes que les durées
// de conservation (config/securite.json) sont supprimées, même sans nouvelle écriture
$suppressionsRgpd = array_filter(appliquerDureesConservation());
if ($suppressionsRgpd) {
    $detail = [];
    foreach ($suppressionsRgpd as $nom => $nombre) {
        $detail[] = "$nombre entrée(s) — $nom";
    }
    journaliser('evenement', 'Durées de conservation appliquées : ' . implode(', ', $detail) . '.');
    flash('info', 'Durées de conservation appliquées : des données trop anciennes ont été supprimées.', $detail);
}

// ----- Actions (formulaires des fenêtres de confirmation) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrfValide()) {
        flash('erreur', 'Le formulaire a expiré. Veuillez réessayer.');
        rediriger("maintenance.php?section=$section");
    }

    if ($action === 'purger_tmp') {
        $resultat = purgerTemporaires();
        journaliser('evenement', 'Purge de storage/tmp : ' . $resultat['nombre'] . ' fichier(s) supprimé(s).');
        if ($resultat['nombre'] > 0) {
            flash('succes', $resultat['nombre'] . ' fichier(s) temporaire(s) supprimé(s) définitivement ('
                . tailleLisible($resultat['octets']) . ' libérés).');
        } else {
            flash('info', 'Aucun fichier temporaire à supprimer.');
        }
        rediriger("maintenance.php?section=$section");
    }

    if ($action === 'vider_journal') {
        $nombre = count(lireJson(FICHIER_JOURNAL));
        ecrireJson(FICHIER_JOURNAL, []);
        journaliser('evenement', "Journal de connexion vidé ($nombre entrée(s) supprimée(s)).");
        flash('succes', "Le journal de connexion a été vidé ($nombre entrée(s) supprimée(s)).");
        rediriger('maintenance.php?section=journaux&journal=connexion');
    }

    if ($action === 'vider_contacts') {
        $nombre = count(lireJson(FICHIER_CONTACTS));
        ecrireJson(FICHIER_CONTACTS, []);
        journaliser('evenement', "Messages de contact supprimés ($nombre).");
        flash('succes', "$nombre message(s) de contact supprimé(s).");
        rediriger('maintenance.php?section=journaux&journal=contact');
    }

    // SEC-5 : vider les logs de débogage des e-mails (ils contiennent les codes de réinitialisation)
    if ($action === 'vider_mail_log') {
        $taille = tailleLogMail();
        file_put_contents(FICHIER_LOG_MAIL, '', LOCK_EX);
        journaliser('evenement', 'Logs de débogage des e-mails vidés (' . tailleLisible($taille) . ').');
        flash('succes', 'Le fichier mail.log a été vidé (' . tailleLisible($taille) . ' supprimés).');
        rediriger('maintenance.php?section=journaux&journal=mails');
    }

    if ($action === 'exporter') {
        $nomFichier = 'sauvegarde-infoleader-' . date('Ymd-His') . '.json';
        $json = json_encode(donneesSauvegarde(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Une copie reste dans storage/tmp (visible dans la section Système, supprimée par la purge)
        file_put_contents(DOSSIER_TMP . '/' . $nomFichier, $json, LOCK_EX);
        journaliser('evenement', 'Export de la sauvegarde JSON (secrets retirés).');

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    rediriger("maintenance.php?section=$section");
}

// Liste des fichiers temporaires (sections Système et Purger storage/tmp)
function afficherTemporaires(array $fichiers): void
{
    if (!$fichiers) {
        echo '<p class="vide">Aucun fichier temporaire : le dossier storage/tmp est vide.</p>';
        return;
    }
    ?>
    <div class="tableau-defilant">
        <table>
            <thead><tr><th>Fichier</th><th>Taille</th><th>Modifié le</th></tr></thead>
            <tbody>
                <?php foreach ($fichiers as $fichier): ?>
                    <tr>
                        <td><?= e($fichier['nom']) ?></td>
                        <td><?= tailleLisible($fichier['taille']) ?></td>
                        <td><?= date('d/m/Y à H:i', $fichier['modifie']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function boutonPurge(array $fichiers): void
{
    ?>
    <button type="button" class="btn btn-danger" data-modale="modale-purge"<?= $fichiers ? '' : ' disabled' ?>>
        Purger les fichiers temporaires
    </button>
    <?php
}

$diagnostic = diagnosticSysteme();
$temporaires = $diagnostic['sections']['tmp']['fichiers'];

afficherHautAdmin('Maintenance', 'maintenance', $admin);
?>
        <h1>Maintenance</h1>

        <nav class="onglets" aria-label="Outils de maintenance">
            <?php foreach ($sections as $cle => $libelle): ?>
                <a href="maintenance.php?section=<?= $cle ?>"<?= $cle === $section ? ' class="actif" aria-current="page"' : '' ?>><?= e($libelle) ?></a>
            <?php endforeach; ?>
        </nav>

<?php /* ================= SYSTÈME ================= */ ?>
<?php if ($section === 'systeme'): ?>
        <?php
        $messagesGlobaux = [
            'ok'        => ['succes', 'État global : tout fonctionne correctement (' . $diagnostic['total'] . ' vérifications réussies).'],
            'attention' => ['attention', 'État global : ' . $diagnostic['problemes'] . ' point(s) d\'attention sur ' . $diagnostic['total'] . ' vérifications.'],
            'erreur'    => ['erreur', 'État global : ' . $diagnostic['problemes'] . ' problème(s) détecté(s) sur ' . $diagnostic['total'] . ' vérifications.'],
        ];
        [$typeGlobal, $texteGlobal] = $messagesGlobaux[$diagnostic['global']];
        afficherEncart($typeGlobal, $texteGlobal, [], true);
        ?>

        <?php foreach ($diagnostic['sections'] as $cle => $bloc): ?>
            <details class="bloc-systeme"<?= $bloc['statut'] !== 'ok' ? ' open' : '' ?>>
                <summary>
                    <span class="bloc-titre"><?= e($bloc['titre']) ?></span>
                    <?= badgeStatut($bloc['statut']) ?>
                </summary>
                <div class="bloc-contenu">
                    <table class="table-verifs">
                        <tbody>
                            <?php foreach ($bloc['verifications'] as $verif): ?>
                                <tr>
                                    <th scope="row"><?= e($verif['libelle']) ?></th>
                                    <td><?= e($verif['valeur']) ?></td>
                                    <td class="col-statut"><?= badgeStatut($verif['statut']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($cle === 'tmp'): ?>
                        <h3>Liste des fichiers temporaires</h3>
                        <?php afficherTemporaires($temporaires); ?>
                        <?php boutonPurge($temporaires); ?>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>

<?php /* ================= JOURNAUX ================= */ ?>
<?php elseif ($section === 'journaux'): ?>
        <?php $journalActif = in_array($_GET['journal'] ?? '', ['contact', 'mails'], true) ? $_GET['journal'] : 'connexion'; ?>
        <nav class="sous-onglets" aria-label="Journaux">
            <a href="maintenance.php?section=journaux&amp;journal=connexion"<?= $journalActif === 'connexion' ? ' class="actif" aria-current="page"' : '' ?>>Journal de connexion</a>
            <a href="maintenance.php?section=journaux&amp;journal=contact"<?= $journalActif === 'contact' ? ' class="actif" aria-current="page"' : '' ?>>Messages de contact</a>
            <a href="maintenance.php?section=journaux&amp;journal=mails"<?= $journalActif === 'mails' ? ' class="actif" aria-current="page"' : '' ?>>Logs e-mails</a>
        </nav>

        <?php if ($journalActif === 'connexion'): ?>
            <?php
            $journal = lireJson(FICHIER_JOURNAL);
            $compteurs = ['succes' => 0, 'echecs' => 0, 'evenements' => 0];
            foreach ($journal as $entree) {
                if ($entree['type'] === 'succes') {
                    $compteurs['succes']++;
                } elseif (in_array($entree['type'], ['echec', 'blocage'], true)) {
                    $compteurs['echecs']++;
                } else {
                    $compteurs['evenements']++;
                }
            }
            $filtres = [
                'succes'     => 'Connexions réussies',
                'echecs'     => 'Échecs / blocages',
                'evenements' => 'Événements enregistrés',
            ];
            $filtre = array_key_exists($_GET['filtre'] ?? '', $filtres) ? $_GET['filtre'] : 'tous';
            $entrees = array_values(array_filter($journal, function ($entree) use ($filtre) {
                switch ($filtre) {
                    case 'succes':
                        return $entree['type'] === 'succes';
                    case 'echecs':
                        return in_array($entree['type'], ['echec', 'blocage'], true);
                    case 'evenements':
                        return $entree['type'] === 'evenement';
                    default:
                        return true;
                }
            }));
            ?>
            <p class="aide">Cliquez sur un compteur pour filtrer le journal.</p>
            <div class="compteurs">
                <?php foreach ($filtres as $cle => $libelle): ?>
                    <a href="maintenance.php?section=journaux&amp;journal=connexion&amp;filtre=<?= $cle ?>"
                       class="compteur compteur-<?= $cle ?><?= $filtre === $cle ? ' actif' : '' ?>"<?= $filtre === $cle ? ' aria-current="true"' : '' ?>>
                        <span class="compteur-nombre"><?= $compteurs[$cle] ?></span>
                        <span class="compteur-libelle"><?= e($libelle) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($filtre !== 'tous'): ?>
                <?php afficherEncart('info', 'Filtre actif : « ' . $filtres[$filtre] . ' » — ' . count($entrees) . ' entrée(s) affichée(s).', [], true); ?>
                <p><a href="maintenance.php?section=journaux&amp;journal=connexion">Afficher toutes les entrées</a></p>
            <?php endif; ?>

            <div class="carte">
                <div class="carte-entete">
                    <h2><?= $filtre === 'tous' ? 'Toutes les entrées' : e($filtres[$filtre]) ?> (<?= count($entrees) ?>)</h2>
                    <button type="button" class="btn btn-danger btn-petit" data-modale="modale-vider-journal"<?= $journal ? '' : ' disabled' ?>>Vider le journal de connexion</button>
                </div>
                <?php if (!$entrees): ?>
                    <p class="vide">Aucune entrée à afficher.</p>
                <?php else: ?>
                    <div class="tableau-defilant">
                        <table>
                            <thead><tr><th>Date</th><th>Type</th><th>Adresse IP</th><th>Détail</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($entrees, 0, 300) as $entree): ?>
                                    <tr>
                                        <td class="nowrap"><?= dateFr($entree['date']) ?><span class="secondes">:<?= substr($entree['date'], 17, 2) ?></span></td>
                                        <td><span class="type type-<?= e($entree['type']) ?>"><?= e(libelleTypeJournal($entree['type'])) ?></span></td>
                                        <td><?= e($entree['ip']) ?></td>
                                        <td><?= e($entree['message']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($entrees) > 300): ?><p class="aide">Seules les 300 entrées les plus récentes sont affichées.</p><?php endif; ?>
                <?php endif; ?>
            </div>
            <?php afficherModale('modale-vider-journal', 'Vider le journal de connexion ?',
                'Toutes les entrées du journal (' . count($journal) . ') seront supprimées. Cette action est irréversible.',
                'vider_journal', 'Vider le journal'); ?>

        <?php elseif ($journalActif === 'mails'): ?>
            <?php
            // SEC-5 : état et purge des logs de débogage des e-mails
            $logsActifs = configMail()['logs_debogage'];
            $tailleLog = tailleLogMail();
            ?>
            <div class="carte">
                <div class="carte-entete">
                    <h2>Logs de débogage des e-mails (storage/logs/mail.log)</h2>
                    <button type="button" class="btn btn-danger btn-petit" data-modale="modale-vider-mail-log"<?= $tailleLog > 0 ? '' : ' disabled' ?>>Vider mail.log</button>
                </div>
                <?php if ($logsActifs): ?>
                    <?php afficherEncart('attention', 'Les logs de débogage sont activés : chaque e-mail envoyé, y compris les codes « Mot de passe oublié », est écrit dans ce fichier. '
                        . 'Désactivez-les dans « Configuration des e-mails » une fois vos tests terminés.'); ?>
                <?php else: ?>
                    <?php afficherEncart('succes', 'Les logs de débogage sont désactivés : aucun nouvel e-mail n\'est écrit dans ce fichier.'); ?>
                <?php endif; ?>
                <table class="table-verifs">
                    <tbody>
                        <tr><th scope="row">Taille du fichier</th><td><?= tailleLisible($tailleLog) ?></td></tr>
                        <tr><th scope="row">Dernière écriture</th><td><?= $tailleLog > 0 ? date('d/m/Y à H:i', filemtime(FICHIER_LOG_MAIL)) : '—' ?></td></tr>
                    </tbody>
                </table>
            </div>
            <?php afficherModale('modale-vider-mail-log', 'Vider le fichier mail.log ?',
                'Le contenu des logs de débogage des e-mails (' . tailleLisible($tailleLog) . ') sera supprimé définitivement. Cette action est irréversible.',
                'vider_mail_log', 'Vider mail.log'); ?>

        <?php else: ?>
            <?php $messages = lireJson(FICHIER_CONTACTS); ?>
            <div class="carte">
                <div class="carte-entete">
                    <h2>Messages reçus par le formulaire de contact (<?= count($messages) ?>)</h2>
                    <button type="button" class="btn btn-danger btn-petit" data-modale="modale-vider-contacts"<?= $messages ? '' : ' disabled' ?>>Supprimer tous les messages</button>
                </div>
                <?php if (!$messages): ?>
                    <p class="vide">Aucun message pour le moment.</p>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <article class="message-contact">
                            <header>
                                <strong><?= e($message['nom']) ?></strong>
                                <span>&lt;<?= e($message['email']) ?>&gt;</span>
                                <span class="message-meta"><?= dateFr($message['date']) ?> — IP <?= e($message['ip']) ?></span>
                            </header>
                            <p><?= nl2br(e($message['message'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php afficherModale('modale-vider-contacts', 'Supprimer tous les messages de contact ?',
                'Les ' . count($messages) . ' message(s) seront supprimés définitivement. Cette action est irréversible.',
                'vider_contacts', 'Supprimer les messages'); ?>
        <?php endif; ?>

<?php /* ================= SAUVEGARDE ================= */ ?>
<?php elseif ($section === 'sauvegarde'): ?>
        <div class="carte">
            <h2>Exporter la configuration du site (JSON)</h2>
            <p>Le fichier exporté contient :</p>
            <ul class="liste">
                <li><strong>Application</strong> : nom, adresse, version de PHP, fuseau horaire, nombre de produits ;</li>
                <li><strong>Compte administrateur</strong> : e-mail et dates (création, modifications, dernière connexion) ;</li>
                <li><strong>Envoi des mails</strong> : mode, expéditeur, destinataire, serveur SMTP, port, sécurité ;</li>
                <li><strong>API</strong> : état (aucune API dans cette version) ;</li>
                <li><strong>Limitation de débit et sécurité</strong> : paliers d'attente, blocage, durée de session.</li>
            </ul>
            <div class="encart encart-attention">
                <p>Les secrets sont retirés de l'export : mot de passe administrateur, mot de passe SMTP et jeton d'API.</p>
            </div>
            <button type="button" class="btn btn-plein" data-modale="modale-export">Exporter en JSON</button>
        </div>
        <?php afficherModale('modale-export', 'Exporter la sauvegarde ?',
            'Un fichier JSON contenant la configuration du site (sans aucun secret) va être téléchargé.',
            'exporter', 'Télécharger le fichier JSON', [
                'style'              => 'btn-plein',
                'note'               => 'Le fichier est généré sur ce serveur puis téléchargé par votre navigateur : aucune donnée n\'est envoyée à un service tiers.',
                'fermer_apres_envoi' => true,
            ]); ?>

<?php /* ================= PURGER STORAGE/TMP ================= */ ?>
<?php else: ?>
        <div class="carte">
            <h2>Purger storage/tmp</h2>
            <p>Supprime définitivement les fichiers temporaires listés dans la section « Système »
                (par exemple les copies des sauvegardes JSON exportées).</p>
            <?php afficherTemporaires($temporaires); ?>
            <?php boutonPurge($temporaires); ?>
        </div>
<?php endif; ?>

<?php if ($section === 'systeme' || $section === 'purge'): ?>
        <?php afficherModale('modale-purge', 'Purger les fichiers temporaires ?',
            count($temporaires) . ' fichier(s) de storage/tmp (' . tailleLisible(array_sum(array_column($temporaires, 'taille')))
            . ') seront supprimés définitivement. Cette action est irréversible.',
            'purger_tmp', 'Supprimer les fichiers'); ?>
<?php endif; ?>
<?php
afficherBasAdmin();
