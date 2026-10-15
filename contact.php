<?php
// ===== Traitement du formulaire de contact =====
// Chaque message valide est :
//   1. enregistré dans storage/data/messages_contact.json (panel : Maintenance > Journaux > Messages de contact) ;
//   2. envoyé par e-mail à la boîte de réception définie dans le panel (Configuration des e-mails),
//      avec l'adresse du visiteur comme adresse de réponse.
// Anti-spam : champ piège, CAPTCHA (calcul en image) et limite de 3 messages par IP en 10 minutes.
// PHP revérifie tout, car la validation JavaScript peut être contournée.
require __DIR__ . '/includes/bootstrap.php';
demarrerSessionPublique();  // la réponse du CAPTCHA est gardée dans la session du visiteur

// Durée de conservation annoncée dans la mention d'information (RGPD-1), réglée dans config/securite.json
$dureeConservation = (int) configSecurite()['contact_conservation_mois'];

$nom = '';
$email = '';
$message = '';
$erreurs = [];
$envoye = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = trim($_POST['nom'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Anti-abus (SEC-3) — 1. Champ piège : invisible pour un visiteur, seul un robot le remplit.
    // Le message est ignoré, mais on affiche quand même « envoyé » pour ne rien révéler au robot.
    if (trim((string) ($_POST['site_web'] ?? '')) !== '') {
        journaliser('blocage', 'Formulaire de contact : message ignoré (champ piège rempli, robot probable).');
        $envoye = true;
        $nom = $email = $message = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$envoye) {
    if ($nom === '') {
        $erreurs['nom'] = 'Veuillez indiquer votre nom.';
    }
    if ($email === '') {
        $erreurs['email'] = 'Veuillez indiquer votre adresse e-mail.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = "L'adresse e-mail n'est pas valide (ex. : nom@domaine.fr).";
    }
    if ($message === '') {
        $erreurs['message'] = 'Veuillez écrire votre message.';
    }

    // Anti-abus — CAPTCHA : le visiteur recopie le résultat du calcul affiché dans l'image.
    // Le calcul ne sert qu'une fois : en cas d'erreur, une nouvelle image est proposée.
    if (!captchaValide((string) ($_POST['captcha'] ?? ''))) {
        $erreurs['captcha'] = 'Le résultat du calcul est incorrect ou a expiré. Un nouveau calcul vous est proposé.';
        journaliser('blocage', 'Formulaire de contact : CAPTCHA incorrect.');
    }

    // Anti-abus (SEC-3) — 2. Limite par adresse IP : 3 messages au plus en 10 minutes (config/securite.json)
    $securite = configSecurite();
    if (empty($erreurs)
        && messagesRecentsDeIp(ipClient(), $securite['contact_fenetre_minutes']) >= $securite['contact_max_messages']) {
        $erreurs['limite'] = 'Vous avez déjà envoyé ' . $securite['contact_max_messages'] . ' messages en moins de '
            . $securite['contact_fenetre_minutes'] . ' minutes. Merci de patienter quelques minutes avant d\'en envoyer un nouveau.';
        journaliser('blocage', 'Formulaire de contact : limite de ' . $securite['contact_max_messages'] . ' messages en '
            . $securite['contact_fenetre_minutes'] . ' minutes atteinte, message refusé.');
    }

    // Formulaire valide : le message est enregistré, envoyé par e-mail, puis les champs sont vidés
    if (empty($erreurs)) {
        if (enregistrerMessageContact($nom, $email, $message)) {
            // Envoi à la boîte configurée dans le panel ; « Répondre » écrira directement au visiteur.
            // Si l'envoi échoue, le message reste enregistré : le visiteur n'a rien à refaire.
            $nomPourSujet = mb_substr(str_replace(["\r", "\n"], ' ', $nom), 0, 100);
            $envoiMail = envoyerMail(
                'InfoLeader — Nouveau message de contact de ' . $nomPourSujet,
                texteMailContact($nom, $email, $message),
                $email,
                $nomPourSujet
            );
            journaliser('evenement', 'Message de contact reçu'
                . ($envoiMail['ok'] ? ' et transmis par e-mail.' : ', e-mail non envoyé (' . $envoiMail['erreur'] . ').'));
            $envoye = true;
            $nom = $email = $message = '';
        } else {
            $erreurs['enregistrement'] = 'Une erreur technique empêche l\'envoi du message. Merci de réessayer plus tard.';
        }
    }
}

// Ajoute la classe et l'attribut d'erreur sur un champ invalide
function attributsErreur($champ, $erreurs)
{
    return isset($erreurs[$champ]) ? ' class="invalid" aria-invalid="true"' : '';
}

// En-tête et menu communs (QUAL-1)
$titrePage = 'Contact';
$descriptionPage = 'Contactez InfoLeader pour toute question sur nos périphériques informatiques.';
$pageActive = 'contact';
require __DIR__ . '/includes/entete-public.php';
?>

    <main>
        <!-- ===== Titre de la page ===== -->
        <section class="page-header">
            <div class="container fade-in">
                <h1>Contactez-nous</h1>
                <p>Une question sur un produit, une commande ou un devis ? Notre équipe vous répond sous 24 h ouvrées.</p>
                <a href="#formulaire" class="banner-link">Écrire un message &darr;</a>
            </div>
        </section>

        <section class="page-content">
            <div class="container contact-layout fade-in">

                <!-- ===== Formulaire de contact ===== -->
                <div class="card" id="formulaire">
                    <h2>Envoyer un message</h2>

                    <?php if ($envoye): ?>
                        <p class="alert alert-success" role="status">Merci pour votre message — il a bien été envoyé.</p>
                    <?php elseif (!empty($erreurs)): ?>
                        <div class="alert alert-error" role="alert">
                            <p>Votre message n'a pas été envoyé :</p>
                            <ul>
                                <?php foreach ($erreurs as $erreur): ?>
                                    <li><?= e($erreur) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- novalidate : c'est validateForm() (script.js) qui affiche les erreurs -->
                    <form id="contact-form" action="contact.php#formulaire" method="post" novalidate onsubmit="return validateForm()">
                        <div class="form-group">
                            <label for="nom">Nom <span class="required">*</span></label>
                            <input type="text" id="nom" name="nom" value="<?= e($nom) ?>" required autocomplete="name" aria-describedby="nom-error"<?= attributsErreur('nom', $erreurs) ?>>
                            <span class="error-message" id="nom-error"><?= e($erreurs['nom'] ?? '') ?></span>
                        </div>

                        <div class="form-group">
                            <label for="email">Adresse e-mail <span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email" aria-describedby="email-error"<?= attributsErreur('email', $erreurs) ?>>
                            <span class="error-message" id="email-error"><?= e($erreurs['email'] ?? '') ?></span>
                        </div>

                        <div class="form-group">
                            <label for="message">Message <span class="required">*</span></label>
                            <textarea id="message" name="message" rows="6" required aria-describedby="message-error"<?= attributsErreur('message', $erreurs) ?>><?= e($message) ?></textarea>
                            <span class="error-message" id="message-error"><?= e($erreurs['message'] ?? '') ?></span>
                        </div>

                        <!-- Champ piège anti-robots (SEC-3) : caché aux visiteurs, seuls les robots le remplissent -->
                        <div class="champ-piege" aria-hidden="true">
                            <label for="site_web">Ne pas remplir ce champ</label>
                            <input type="text" id="site_web" name="site_web" value="" tabindex="-1" autocomplete="off">
                        </div>

                        <!-- CAPTCHA anti-spam : recopier le résultat du calcul dessiné dans l'image (captcha.php) -->
                        <div class="form-group">
                            <label for="captcha">Vérification anti-spam <span class="required">*</span></label>
                            <div class="captcha">
                                <img src="captcha.php?v=<?= time() ?>" alt="Calcul à résoudre" width="180" height="56" id="captcha-image">
                                <button type="button" class="captcha-nouveau" id="captcha-nouveau">&#8635; Autre calcul</button>
                            </div>
                            <input type="text" id="captcha" name="captcha" required inputmode="numeric" maxlength="3" autocomplete="off"
                                   aria-describedby="captcha-aide captcha-error"<?= attributsErreur('captcha', $erreurs) ?>>
                            <p class="form-note" id="captcha-aide">Écrivez le résultat du calcul affiché dans l'image (exemple : 7 + 4 = ? → 11).
                                Si vous n'y parvenez pas, écrivez-nous à contact@infoleader.fr ou appelez le 04 78 00 00 00.</p>
                            <span class="error-message" id="captcha-error"><?= e($erreurs['captcha'] ?? '') ?></span>
                        </div>

                        <!-- Mention d'information sur les données personnelles (RGPD-1) -->
                        <p class="form-rgpd" id="mention-rgpd">
                            Les informations saisies (nom, e-mail, message) et votre adresse IP sont enregistrées par InfoLeader et
                            transmises par e-mail à notre équipe, uniquement pour répondre à votre demande et protéger ce formulaire
                            contre les abus. Elles ne sont ni vendues
                            ni transmises à des tiers, et sont conservées au plus <?= $dureeConservation ?> mois. Vous pouvez demander
                            à les consulter, les corriger ou les supprimer en écrivant à contact@infoleader.fr. Vous pouvez aussi
                            adresser une réclamation à la CNIL (www.cnil.fr).
                        </p>

                        <button type="submit" class="btn" aria-describedby="mention-rgpd">Envoyer le message</button>
                        <p class="form-note"><span class="required">*</span> Champs obligatoires</p>
                    </form>
                </div>

                <!-- ===== Coordonnées de l'entreprise ===== -->
                <aside class="card contact-info">
                    <h2>Nos coordonnées</h2>
                    <p><strong>Adresse</strong><br>12 rue de la République<br>69002 Lyon</p>
                    <p><strong>Téléphone</strong><br>04 78 00 00 00</p>
                    <p><strong>E-mail</strong><br>contact@infoleader.fr</p>
                    <p><strong>Horaires</strong><br>Du lundi au samedi<br>9 h – 19 h</p>
                </aside>

            </div>
        </section>
    </main>
<?php require __DIR__ . '/includes/pied-public.php'; ?>
