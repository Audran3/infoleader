/* ==========================================================================
   InfoLeader — admin/admin.js
   Interactions du panel, en JavaScript natif (aucune bibliothèque) :
     1. Messages qui disparaissent au bout de quelques secondes
     2. Fenêtres modales de confirmation
     3. Comptes à rebours (attente anti-brute force, validité du code)
     4. Vérification en direct des règles du mot de passe
     5. Boutons « Afficher » des mots de passe
     6. Contrôle des adresses e-mail (module Utilisateur)
   Tous ces contrôles sont refaits par PHP : JavaScript n'apporte que du confort.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
    initMessages();
    initModales();
    initComptesARebours();
    initReglesMotDePasse();
    initAffichageMotDePasse();
    initControleEmails();
    initPortSecurite();
});


/* ----- 1. Messages temporaires ----- */
function initMessages() {
    document.querySelectorAll('.encart').forEach(function (encart) {
        const bouton = encart.querySelector('.encart-fermer');
        if (bouton) {
            bouton.addEventListener('click', function () { masquer(encart); });
        }
        // Dans une fenêtre modale, le délai démarre à l'ouverture de la fenêtre
        if (!encart.closest('dialog')) {
            programmerMasquage(encart);
        }
    });
}

function programmerMasquage(encart) {
    const duree = parseInt(encart.dataset.masquerApres, 10);
    if (!duree || encart.dataset.programme) return;
    encart.dataset.programme = 'oui';

    let minuterie = setTimeout(function () { masquer(encart); }, duree);
    // Le délai est suspendu tant que la souris survole le message
    encart.addEventListener('mouseenter', function () { clearTimeout(minuterie); });
    encart.addEventListener('mouseleave', function () {
        minuterie = setTimeout(function () { masquer(encart); }, 2000);
    });
}

// Disparition en fondu, puis suppression de l'élément
function masquer(element) {
    element.style.overflow = 'hidden';
    element.style.maxHeight = element.scrollHeight + 'px';
    requestAnimationFrame(function () {
        element.classList.add('masque');
        element.style.maxHeight = '0px';
    });
    setTimeout(function () { element.remove(); }, 450);
}


/* ----- 2. Fenêtres modales ----- */
function initModales() {
    document.querySelectorAll('[data-modale]').forEach(function (declencheur) {
        declencheur.addEventListener('click', function (event) {
            const modale = document.getElementById(declencheur.dataset.modale);
            if (!modale || typeof modale.showModal !== 'function') return; // sinon le lien est suivi
            event.preventDefault();
            modale.showModal();
            modale.querySelectorAll('.encart[data-masquer-apres]').forEach(programmerMasquage);
        });
    });

    document.querySelectorAll('[data-fermer-modale]').forEach(function (bouton) {
        bouton.addEventListener('click', function () { bouton.closest('dialog').close(); });
    });

    // Un clic sur le fond assombri ferme la fenêtre
    document.querySelectorAll('dialog.modale').forEach(function (modale) {
        modale.addEventListener('click', function (event) {
            if (event.target === modale) modale.close();
        });
    });

    // Export JSON : le téléchargement ne recharge pas la page, on referme donc la fenêtre
    document.querySelectorAll('form[data-fermer-apres-envoi]').forEach(function (formulaire) {
        formulaire.addEventListener('submit', function () {
            setTimeout(function () { formulaire.closest('dialog').close(); }, 300);
        });
    });
}


/* ----- 3. Comptes à rebours ----- */
function initComptesARebours() {
    document.querySelectorAll('[data-compte-a-rebours]').forEach(function (compteur) {
        const fin = Date.now() + parseInt(compteur.dataset.compteARebours, 10) * 1000;
        const formulaire = compteur.closest('form');
        // Connexion : le bouton reste désactivé pendant l'attente imposée
        const boutonAttente = formulaire ? formulaire.querySelector('[data-attente]') : null;
        const messageAttente = compteur.closest('[data-attente-message]');
        if (boutonAttente) boutonAttente.disabled = true;

        let minuterie = null;

        function actualiser() {
            const restant = Math.max(0, Math.ceil((fin - Date.now()) / 1000));
            compteur.textContent = compteur.dataset.format === 'mmss'
                ? Math.floor(restant / 60) + ':' + String(restant % 60).padStart(2, '0')
                : String(restant);
            if (restant > 0) return;

            clearInterval(minuterie);
            if (boutonAttente) boutonAttente.disabled = false;
            if (messageAttente) masquer(messageAttente);

            // Code expiré : on empêche l'envoi du formulaire
            if (compteur.dataset.aExpiration) {
                document.querySelectorAll(compteur.dataset.aExpiration).forEach(function (element) {
                    element.disabled = true;
                });
                compteur.textContent = compteur.dataset.texteFin || '0:00';
                const conteneur = compteur.closest('.minuteur');
                if (conteneur) conteneur.classList.add('expire');
            }
        }

        minuterie = setInterval(actualiser, 250);
        actualiser();
    });
}


/* ----- 4. Règles du mot de passe (mêmes règles que securite.php) ----- */
const TESTS_MOT_DE_PASSE = {
    longueur: function (valeur) { return Array.from(valeur).length >= 20; },
    majuscule: function (valeur) { return /[A-Z]/.test(valeur); },
    minuscule: function (valeur) { return /[a-z]/.test(valeur); },
    chiffre: function (valeur) { return /[0-9]/.test(valeur); },
    special: function (valeur) { return /[^A-Za-z0-9]/.test(valeur); }
};

function initReglesMotDePasse() {
    document.querySelectorAll('[data-regles-pour]').forEach(function (liste) {
        const champ = document.getElementById(liste.dataset.reglesPour);
        const confirmation = document.getElementById(liste.dataset.confirmation);
        if (!champ || !confirmation) return;

        function verifier() {
            let reglesRespectees = true;
            liste.querySelectorAll('[data-regle]').forEach(function (regle) {
                const nom = regle.dataset.regle;
                const ok = nom === 'identique'
                    ? champ.value !== '' && champ.value === confirmation.value
                    : TESTS_MOT_DE_PASSE[nom](champ.value);
                regle.classList.toggle('valide', ok);
                if (!ok && nom !== 'identique') reglesRespectees = false;
            });
            // Le navigateur refuse d'envoyer le formulaire tant qu'une règle n'est pas respectée
            champ.setCustomValidity(reglesRespectees ? '' : 'Le mot de passe ne respecte pas toutes les règles affichées.');
            confirmation.setCustomValidity(confirmation.value === champ.value ? '' : 'Les deux mots de passe ne sont pas identiques.');
        }

        champ.addEventListener('input', verifier);
        confirmation.addEventListener('input', verifier);
        verifier();
    });
}


/* ----- 5. Afficher / masquer un mot de passe ----- */
function initAffichageMotDePasse() {
    document.querySelectorAll('[data-afficher-mdp]').forEach(function (bouton) {
        const champ = document.getElementById(bouton.dataset.afficherMdp);
        if (!champ) return;
        bouton.addEventListener('click', function () {
            const afficher = champ.type === 'password';
            champ.type = afficher ? 'text' : 'password';
            bouton.textContent = afficher ? 'Masquer' : 'Afficher';
            bouton.setAttribute('aria-pressed', String(afficher));
        });
    });
}


/* ----- 6. Contrôle des adresses e-mail (module Utilisateur) ----- */
function initControleEmails() {
    const nouvelle = document.querySelector('[data-different-de]');
    const confirmation = document.querySelector('[data-identique-a]');
    if (!nouvelle || !confirmation) return;

    function verifier() {
        const identiqueAActuelle = nouvelle.value.trim().toLowerCase() === nouvelle.dataset.differentDe.toLowerCase();
        nouvelle.setCustomValidity(identiqueAActuelle ? 'La nouvelle adresse doit être différente de l\'adresse actuelle.' : '');
        confirmation.setCustomValidity(confirmation.value.trim() === nouvelle.value.trim() ? '' : 'Les deux adresses ne sont pas identiques.');
    }

    nouvelle.addEventListener('input', verifier);
    confirmation.addEventListener('input', verifier);
    verifier();
}


/* ----- 7. Configuration SMTP : la sécurité suit le port choisi ----- */
function initPortSecurite() {
    const port = document.getElementById('smtp_port');
    const securite = document.getElementById('smtp_securite');
    if (!port || !securite) return;

    // 465 = SSL dès la connexion, 587 = TLS (STARTTLS)
    port.addEventListener('change', function () {
        securite.value = port.value === '465' ? 'ssl' : 'tls';
    });
    securite.addEventListener('change', function () {
        port.value = securite.value === 'ssl' ? '465' : '587';
    });
}
