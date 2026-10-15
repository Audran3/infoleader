# Documentation technique — Site InfoLeader

> Analyse réalisée le 15/10/2026 à partir du code présent dans `C:\wamp64\www\infoleader`.
> Chaque affirmation renvoie au fichier qui la justifie. Lorsqu'une information est absente du code, c'est indiqué.

## Table des matières

1. [Présentation générale](#1-présentation-générale)
2. [Stack technique](#2-stack-technique)
3. [Arborescence du projet](#3-arborescence-du-projet)
4. [Points d'entrée et routage](#4-points-dentrée-et-routage)
5. [Stockage des données](#5-stockage-des-données)
6. [Authentification, sessions et sécurité](#6-authentification-sessions-et-sécurité)
7. [Front-end](#7-front-end)
8. [Intégrations externes](#8-intégrations-externes)
9. [Configuration](#9-configuration)
10. [Limites connues et risques](#10-limites-connues-et-risques)
11. [Points forts](#11-points-forts)

---

## 1. Présentation générale

InfoLeader est le site vitrine d'une entreprise fictive qui vend des périphériques informatiques (claviers, souris, écrans, casques, webcams, hubs). Il comporte deux parties.

**Site public** (3 pages) :

| Page | Fichier | Fonctionnalités |
|------|---------|-----------------|
| Accueil | `index.php` | Bannière, slogan, bouton « Découvrir nos produits », présentation, 3 points forts |
| Nos Produits | `products.php` | 6 produits lus dans `products.json`, grille responsive, filtre de recherche en temps réel |
| Contact | `contact.php` | Formulaire nom / e-mail / message, validation JavaScript et PHP, CAPTCHA (calcul en image), enregistrement du message et envoi par e-mail à l'équipe, anti-abus, mention d'information RGPD |

Un bouton « Administration » dans le menu des 3 pages mène au panneau d'administration (`admin/`).

**Panneau d'administration** (`admin/`) :

- connexion protégée par un anti-brute force (`admin/login.php`) ;
- mot de passe oublié par code à 8 chiffres envoyé par e-mail (`admin/forgot-password.php`) ;
- tableau de bord avec les informations du compte et 4 outils (`admin/dashboard.php`) ;
- configuration des e-mails : PHP mail ou SMTP (`admin/email.php`) ;
- changement du mot de passe (`admin/password.php`) et de l'adresse e-mail de connexion (`admin/user.php`) ;
- maintenance : état du système, journaux, sauvegarde JSON, purge des fichiers temporaires (`admin/maintenance.php`).

Le site n'a qu'un seul compte, le compte administrateur. Il n'y a ni inscription, ni compte client, ni panier, ni paiement.

## 2. Stack technique

| Élément | Valeur | Source |
|---------|--------|--------|
| Environnement | WAMP sous Windows, exécution locale | `C:\wamp64\` |
| Serveur web | Apache 2.4.65 | `C:\wamp64\bin\apache\apache2.4.65` |
| Langage serveur | PHP 8.3.28 (module Apache) | `httpd.conf` : `LoadModule php_module ".../php8.3.28/..."` |
| Extensions PHP utilisées | `openssl` (chiffrement SMTP), `mbstring` (`mb_strlen`, `mb_substr`), `json` | `includes/systeme.php`, `includes/securite.php` |
| Hachage des mots de passe | Argon2id si disponible, sinon bcrypt | `includes/securite.php` → `hacherMotDePasse()` |
| Langages front-end | HTML5, CSS3, JavaScript natif (ES6) | `*.php`, `style.css`, `script.js`, `admin/admin.css`, `admin/admin.js` |
| Bibliothèque externe | PHPMailer 7.1.1, installée à la main (sans Composer) | `vendor/phpmailer/phpmailer/src/` |
| Stockage | Fichiers JSON (aucune base de données) | `config/`, `storage/data/`, `products.json` |
| Framework | Aucun (ni Bootstrap, ni Tailwind, ni jQuery) | — |

MySQL est installé avec WAMP mais **n'est pas utilisé** par le site : aucun appel à `mysqli` ni à `PDO` n'existe dans le code.

## 3. Arborescence du projet

```
infoleader/
├── index.php              Accueil
├── products.php           Nos Produits (lit products.json)
├── contact.php            Contact (formulaire, CAPTCHA, enregistrement et envoi par e-mail du message)
├── captcha.php            Image du CAPTCHA (nouveau calcul à chaque affichage)
├── style.css              Styles du site public (576 lignes)
├── script.js              JavaScript du site public (154 lignes)
├── products.json          Catalogue des 6 produits
├── README.md              Installation, vhost, configuration SMTP, dépannage
├── DOCUMENTATION.md       Ce document
├── TODO_LIST.md           Plan d'amélioration
├── TESTS.md               Cahier de recettes (tests à refaire après chaque modification)
├── .gitignore             Fichiers à ne jamais envoyer sur GitHub (secrets, données, logs)
├── .htaccess              Réglages Apache du site : erreurs PHP masquées aux visiteurs
├── assets/
│   ├── banner.jpg         Image de fond de la bannière
│   └── products/          6 images produits (casque, clavier, ecran, hub, souris, webcam)
├── admin/                 Panneau d'administration
│   ├── index.php          Redirection vers dashboard.php
│   ├── login.php          Connexion
│   ├── forgot-password.php Mot de passe oublié (2 étapes)
│   ├── logout.php         Déconnexion (POST + jeton CSRF)
│   ├── dashboard.php      Tableau de bord
│   ├── email.php          Configuration des e-mails
│   ├── password.php       Module Mot de passe
│   ├── user.php           Module Utilisateur
│   ├── maintenance.php    Module Maintenance (4 sections)
│   ├── admin.css          Styles du panel
│   └── admin.js           JavaScript du panel
├── includes/              Code PHP commun — accès web interdit (.htaccess)
│   ├── bootstrap.php      Constantes de chemins, fuseau horaire, e(), ipClient(), dateFr()
│   ├── stockage.php       Lecture / écriture JSON, configMail(), configSecurite(), messages de contact
│   ├── journal.php        Journal de connexion
│   ├── securite.php       Session, CSRF, authentification, anti-brute force, règles des mots de passe
│   ├── mailer.php         Envoi des e-mails (PHP mail ou SMTP via PHPMailer)
│   ├── systeme.php        Diagnostic, fichiers temporaires, sauvegarde JSON
│   ├── layout.php         Gabarit HTML du panel (en-tête, messages, modales)
│   ├── captcha.php        CAPTCHA : session publique, calcul, vérification, dessin de l'image
│   ├── entete-public.php  Début commun des pages publiques (head, en-tête, menu)
│   ├── pied-public.php    Pied de page commun des pages publiques
│   ├── creer-admin.php    Outil en ligne de commande : crée / réinitialise le compte admin
│   └── debloquer-ip.php   Outil en ligne de commande : liste et lève les blocages anti-brute force
├── config/                Configuration — accès web interdit (.htaccess)
│   ├── admin.json         Compte administrateur
│   ├── mail.json          Réglages d'envoi des e-mails
│   ├── mail.example.json  Modèle de mail.json sans aucun secret (publiable)
│   └── securite.json      Réglages de l'anti-brute force et des sessions
├── storage/               Données produites par le site — accès web interdit (.htaccess)
│   ├── data/              Fichiers JSON créés à la demande
│   ├── logs/mail.log      Logs de débogage des e-mails
│   └── tmp/               Fichiers temporaires (copies des sauvegardes JSON)
└── vendor/phpmailer/      PHPMailer 7.1.1 + licence — accès web interdit (.htaccess)
```

## 4. Points d'entrée et routage

- Il n'y a **pas de routeur central** : chaque fichier `.php` est un point d'entrée. Apache associe directement l'URL au fichier (ex. `http://localhost/infoleader/products.php` → `products.php`).
- `admin/index.php` redirige vers `admin/dashboard.php`, qui redirige vers `admin/login.php` si personne n'est connecté (`exigerConnexion()` dans `includes/securite.php`).
- Les variantes d'une page passent par des paramètres GET :

| Page | Paramètre | Valeurs |
|------|-----------|---------|
| `admin/email.php` | `onglet` | `phpmail`, `smtp` (l'ancien nom `brevo` est redirigé vers `smtp`) |
| `admin/maintenance.php` | `section` | `systeme`, `journaux`, `sauvegarde`, `purge` |
| `admin/maintenance.php` | `journal`, `filtre` | `connexion` / `contact` ; `succes`, `echecs`, `evenements` |
| `admin/forgot-password.php` | `etape` | `code` (étape 2) |

- Les formulaires du panel utilisent le schéma **POST → redirection → GET** (fonction `rediriger()`) : actualiser la page ne renvoie pas le formulaire. Exceptions : les erreurs de `email.php` et `user.php` réaffichent la page directement pour conserver les valeurs saisies.
- Accès au site : `http://localhost/infoleader/` ou `http://infoleader.local/` (virtual host déclaré dans `httpd-vhosts.conf`, nécessite une ligne dans le fichier `hosts` de Windows).

## 5. Stockage des données

Le site n'utilise **pas de base de données**. Toutes les données sont dans des fichiers JSON, lus avec `lireJson()` et écrits avec `ecrireJson()` (`includes/stockage.php`). L'écriture utilise `LOCK_EX` pour éviter que deux écritures simultanées se mélangent.

| Fichier | Contenu | Écrit par | Limite |
|---------|---------|-----------|--------|
| `products.json` | 6 produits : `id`, `nom`, `categorie`, `prix`, `description`, `image` | Édition manuelle | — |
| `config/admin.json` | `email`, `password_hash`, `cree_le`, `mot_de_passe_modifie_le`, `email_modifie_le`, `derniere_connexion` (date, ip) | `login.php`, `password.php`, `user.php`, `forgot-password.php`, `creer-admin.php` | 1 compte |
| `config/mail.json` | `mode` (`phpmail`/`smtp`), expéditeur, destinataire, serveur, port, sécurité, identifiant, **mot de passe SMTP en clair**, `logs_debogage` | `admin/email.php` | — |
| `config/securite.json` | Paliers d'attente, seuil de blocage, durées (session, code, blocage), limite du formulaire de contact | Édition manuelle | — |
| `storage/data/limitation.json` | Par adresse IP : `echecs`, `dernier_echec`, `prochain_essai`, `bloque_jusqua` | `includes/securite.php` | — |
| `storage/data/reinitialisation.json` | Demande de code en cours : `hash` du code, `cree`, `expire`, `essais`, `ip` | `forgot-password.php` | 1 demande |
| `storage/data/demandes_code.json` | Par adresse IP : horodatages des demandes de code de la dernière heure | `forgot-password.php` via `enregistrerDemandeCode()` | 1 heure |
| `storage/data/journal_connexion.json` | Entrées `date`, `type` (`succes`/`echec`/`blocage`/`evenement`), `ip`, `message` | `includes/journal.php` | 1 000 entrées (`JOURNAL_TAILLE_MAX`) et 6 mois |
| `storage/data/messages_contact.json` | `date`, `nom`, `email`, `message`, `ip` | `contact.php` via `enregistrerMessageContact()` | 500 messages et 12 mois |
| `storage/logs/mail.log` | Texte : chaque e-mail envoyé (destinataire, sujet, contenu, résultat), dialogue SMTP masqué en cas d'échec | `includes/mailer.php` | Aucune limite |
| `storage/tmp/*.json` | Copies des sauvegardes exportées | `admin/maintenance.php` | Purge manuelle |

Les fichiers de `storage/data/` sont créés à la première écriture. Les valeurs par défaut sont définies dans `configMail()` et `configSecurite()` (`includes/stockage.php`).

## 6. Authentification, sessions et sécurité

**Authentification** (`admin/login.php`, `includes/securite.php`)

- Un seul compte. Le mot de passe est stocké sous forme de hash (`password_hash`, Argon2id) et vérifié avec `password_verify`.
- `password_verify` est toujours exécuté, même si l'e-mail est faux : la durée de réponse ne révèle pas si l'e-mail existe.
- Règles des mots de passe : 20 à 128 caractères, une majuscule, une minuscule, un chiffre, un caractère spécial, différent de l'actuel (`erreursMotDePasse()`). Contrôle en direct en JavaScript (`admin/admin.js`) et contrôle obligatoire en PHP.

**Anti-brute force** (`config/securite.json`, `enregistrerEchec()`)

- Par adresse IP (`REMOTE_ADDR`). Attente de 3 s après les échecs 1 à 3, 10 s (4-5), 30 s (6-7), 60 s (8-9).
- Au 10e échec, l'IP est bloquée 4 heures (connexion et mot de passe oublié).
- Un essai pendant l'attente compte comme un échec. Les échecs sont oubliés après 60 minutes sans erreur.

**Mot de passe oublié** (`admin/forgot-password.php`)

- Code de 8 chiffres généré avec `random_int`, stocké sous forme de hash, valable 5 minutes, 5 essais maximum, renvoi possible après 60 secondes.
- 3 demandes de code au plus par adresse IP et par heure ; au-delà, refus journalisé sans remplacer le code en cours (SEC-4).
- Le code est envoyé au destinataire défini dans `config/mail.json`.

**Alertes et journal**

- Alerte e-mail après tout changement de mot de passe, réinitialisation ou changement d'adresse de connexion (SEC-9).
- En cas d'échec de connexion, l'identifiant saisi est masqué dans le journal (SEC-6).
- Durées de conservation appliquées automatiquement : 12 mois pour les messages de contact, 6 mois pour le journal (RGPD-2).

**Sessions** (`demarrerSession()`)

- Nom `INFOLEADER_ADMIN`, cookie `HttpOnly`, `SameSite=Strict`, `Secure` si HTTPS, `session.use_strict_mode` activé.
- `session_regenerate_id(true)` à la connexion et après un changement de mot de passe ou d'e-mail.
- Déconnexion après 30 minutes d'inactivité.
- Une « empreinte » du compte (hash du mot de passe haché + e-mail) est gardée en session : si le mot de passe ou l'e-mail change, les autres sessions sont fermées.
- Après 5 erreurs de mot de passe actuel dans les modules Mot de passe ou Utilisateur, la session est fermée (`verifierMotDePasseActuel()`).

**Autres protections**

- Jeton CSRF sur tous les formulaires du panel, y compris la déconnexion (`champCsrf()`, `csrfValide()`).
- Échappement de toutes les données affichées avec `htmlspecialchars` (fonction `e()`).
- En-têtes HTTP `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin` sur tout le site (`.htaccess` racine, SEC-8) ; le panel ajoute `Cache-Control: no-store`.
- Erreurs PHP jamais affichées aux visiteurs, seulement journalisées (`.htaccess` racine, SEC-7).
- Outil `includes/debloquer-ip.php` (ligne de commande uniquement) pour lever un blocage anti-brute force (EXP-1).
- Dossiers `config/`, `includes/`, `storage/`, `vendor/` protégés par un `.htaccess` contenant `Require all denied`. La section Système vérifie que la protection est réellement appliquée (réponse HTTP 403).
- Fichier `.gitignore` : les secrets et les données personnelles ne sont jamais envoyés sur GitHub.
- Formulaire de contact : limite de 3 messages par IP en 10 minutes et champ piège anti-robots ; les refus sont inscrits au journal (type `blocage`).
- CAPTCHA du formulaire de contact (`includes/captcha.php`, image servie par `captcha.php`) : un calcul (ex. « 7 + 4 = ? ») dessiné dans une image brouillée par l'extension GD de PHP, sans service externe. La réponse est gardée dans une session publique distincte de celle du panel (`INFOLEADER_CONTACT`), ne sert qu'une fois et expire après 15 minutes. Un formulaire envoyé sans avoir affiché l'image est refusé.
- `includes/creer-admin.php` refuse de s'exécuter hors ligne de commande (`PHP_SAPI !== 'cli'`).
- Actions irréversibles du panel (purges, export) confirmées par une fenêtre modale.

## 7. Front-end

**Site public** (`style.css`, `script.js`)

- HTML5 sémantique : `header`, `nav`, `main`, `section`, `article`, `footer`.
- CSS : variables de couleurs (bleu `#0056b3`, gris `#2d2d2d`, blanc, vert `#4caf50`), police système (Segoe UI, Roboto, Arial), approche mobile d'abord avec media queries à 600, 768 et 960 px, en-tête collant (`position: sticky`).
- JavaScript : menu mobile (hamburger), défilement fluide vers les ancres, apparition en fondu, filtre des produits (attente de 150 ms, insensible aux accents, plusieurs mots), validation du formulaire de contact (`validateForm()`, appelée par `onsubmit`).
- `script.js` est chargé dans le `<head>` sans `defer` : il ajoute la classe `js` immédiatement pour éviter un clignotement des animations.
- Les animations respectent le réglage « réduire les animations » (`prefers-reduced-motion`).

**Panel** (`admin/admin.css`, `admin/admin.js`)

- Messages qui disparaissent après 6 à 9 secondes (pause au survol), fenêtres modales `<dialog>`, comptes à rebours (attente de connexion, validité du code), cases des règles du mot de passe cochées en direct, bouton « Afficher » des mots de passe, synchronisation port / sécurité SMTP.

**Thème clair / sombre** : absent. Le site n'a qu'un thème clair.

**Images** : 7 fichiers JPG générés comme illustrations provisoires (`assets/`).

## 8. Intégrations externes

| Intégration | Détail | Fichier |
|-------------|--------|---------|
| Envoi d'e-mails par SMTP | PHPMailer 7.1.1. Configuration actuelle : `mail.olivier-dutoit.fr`, port 465, SSL. Utilisé pour le test d'envoi, le code « mot de passe oublié », les alertes de sécurité et chaque message du formulaire de contact (envoyé au destinataire configuré, avec l'adresse du visiteur en « Répondre à ») | `includes/mailer.php`, `config/mail.json`, `contact.php` |
| Bibliothèque GD de PHP | Dessin de l'image du CAPTCHA ; police `C:/Windows/Fonts/arialbd.ttf` si elle existe, sinon police intégrée à GD | `includes/captcha.php` |
| PHP mail | Fonction `mail()`. Ne fonctionne pas sous WAMP (aucun serveur de messagerie local) | `includes/mailer.php` |

Aucune API externe, aucun CDN, aucune police en ligne, aucun outil de statistiques, aucun paiement.

## 9. Configuration

- **Constantes de chemins** : `RACINE`, `DOSSIER_CONFIG`, `DOSSIER_DATA`, `DOSSIER_LOGS`, `DOSSIER_TMP`, `FICHIER_*` (`includes/bootstrap.php`), `DOSSIER_PHPMAILER` (`includes/mailer.php`).
- **Constantes métier** : `MDP_LONGUEUR_MIN = 20`, `MDP_LONGUEUR_MAX = 128` (`includes/securite.php`), `JOURNAL_TAILLE_MAX = 1000` (`includes/journal.php`).
- **Fuseau horaire** : `Europe/Paris` (`includes/bootstrap.php`).
- **Fichiers JSON** : `config/admin.json`, `config/mail.json`, `config/securite.json` (voir section 5).
- **Apache** : virtual host `infoleader.local` dans `C:\wamp64\bin\apache\apache2.4.65\conf\extra\httpd-vhosts.conf` ; une sauvegarde de l'original existe (`httpd-vhosts.conf.bak-avant-infoleader`).
- **PHP** : le `php.ini` utilisé par Apache est `C:\wamp64\bin\apache\apache2.4.65\bin\php.ini` ; `openssl` et `mbstring` y sont activés ; `display_errors` y vaut `1`, mais le `.htaccess` racine le force à `Off` pour le site.
- **`.htaccess` racine** : masque les erreurs PHP aux visiteurs et garde leur journalisation (SEC-7) ; ajoute les en-têtes de sécurité à toutes les réponses (SEC-8).
- Pas de fichier `.env` ni de variables d'environnement.

## 10. Limites connues et risques

Classés du plus urgent au moins urgent. Mise à jour du 15/10/2026 après les jalons 1 à 3 de `TODO_LIST.md` :
*Corrigé* = le risque n'existe plus ; *Atténué / Encadré / Contrôlé* = le risque est réduit, ce qui reste est indiqué.

**Sécurité et confidentialité**

1. **Mot de passe SMTP en clair** dans `config/mail.json`. PHP en a besoin pour se connecter au serveur. Le fichier n'est protégé que par `config/.htaccess`. *Atténué (SEC-1)* : le fichier `.gitignore` empêche de l'envoyer sur GitHub, et `config/mail.example.json` sert de modèle sans secret. Une copie manuelle du dossier (clé USB, archive) l'emporte toujours.
2. **Protection dépendante du `.htaccess`.** Si Apache ignore les `.htaccess` (`AllowOverride None`) ou si le site est servi autrement, `config/`, `storage/` et `vendor/` deviennent lisibles. *Contrôlé (SEC-2)* : la section Système demande à Apache un fichier de chaque dossier par une vraie requête HTTP ; toute réponse autre que 403 est signalée, et un dossier ouvert fait passer l'état global en rouge (`codeHttpFichier()`, `verifierAccesWeb()` dans `includes/systeme.php`).
3. **Formulaire de contact exposé aux robots** (`contact.php`). *Atténué (SEC-3)* : 3 messages au plus par adresse IP et par tranche de 10 minutes (`messagesRecentsDeIp()`, réglages `contact_max_messages` et `contact_fenetre_minutes` de `config/securite.json`), et un champ piège invisible (`site_web`) qui fait ignorer les messages des robots. Reste : pas de jeton CSRF, et un robot qui change d'adresse IP n'est pas freiné.
4. **Données personnelles du formulaire de contact** : nom, e-mail, message et adresse IP sont enregistrés. *Atténué (RGPD-1)* : une mention d'information sous le formulaire indique la finalité, les données conservées, l'absence de transmission, la durée de conservation (`contact_conservation_mois` = 12 dans `config/securite.json`), les droits et la CNIL. *Durée appliquée (RGPD-2)* : les messages de plus de 12 mois et les entrées du journal de plus de 6 mois (`journal_conservation_mois`) sont supprimés automatiquement à chaque nouvelle écriture et à l'ouverture du module Maintenance (`filtrerParAnciennete()`, `appliquerDureesConservation()` dans `includes/stockage.php`).
5. **Annulation du code de réinitialisation.** *Corrigé (SEC-4)* : 3 demandes de code au plus par adresse IP et par heure (`demandes_code_max`, `demandes_code_fenetre_minutes` dans `config/securite.json`, horodatages dans `storage/data/demandes_code.json`). Au-delà, la demande est refusée et journalisée sans remplacer le code en cours. Reste : un attaquant qui dispose de nombreuses adresses IP n'est pas freiné.
6. **Logs de débogage sensibles** : `storage/logs/mail.log` contient le contenu des e-mails, dont les codes de réinitialisation. *Encadré (SEC-5)* : option désactivée par défaut, avertissement permanent sur le tableau de bord et point d'attention dans Système quand elle est active, bouton « Vider mail.log » (avec confirmation) dans Maintenance > Journaux > Logs e-mails. Reste : le fichier grossit sans limite tant que l'option est active (tâche STO-2).
7. ~~Identifiant saisi journalisé en clair.~~ *Corrigé (SEC-6)* : lors d'un échec de connexion, le journal ne garde qu'une version masquée de l'identifiant (`ad***@infoleader.local`) ; un texte qui n'est pas une adresse e-mail devient « (saisie non valide) » (`masquerIdentifiant()` dans `includes/journal.php`, appelée par `admin/login.php`).
8. ~~Affichage des erreurs PHP aux visiteurs.~~ *Corrigé (SEC-7)* : le `php.ini` d'Apache contient toujours `display_errors = 1`, mais le fichier `.htaccess` à la racine du site le remplace par `Off` pour tout le site (`php_flag`, dans un bloc `<IfModule php_module>`). Les erreurs restent enregistrées dans `C:\wamp64\logs\php_error.log`.
9. **En-têtes de sécurité.** *Corrigé en partie (SEC-8)* : `X-Content-Type-Options`, `X-Frame-Options` et `Referrer-Policy` sont envoyés pour tout le site par le `.htaccess` racine (`mod_headers`), avec les mêmes valeurs que le panel. Reste : aucune politique `Content-Security-Policy` (tâche SEC-10).
10. **Blocage de l'administrateur lui-même** : en local, toutes les connexions viennent de `::1`. Dix erreurs bloquent l'administrateur 4 heures. *Atténué (EXP-1)* : l'outil en ligne de commande `includes/debloquer-ip.php` liste les adresses suivies et en débloque une (ou toutes) ; l'action est journalisée.
11. ~~Aucune notification lors d'un changement sensible.~~ *Corrigé (SEC-9)* : `notifierChangementSecurite()` (`includes/mailer.php`) envoie une alerte au destinataire configuré après un changement de mot de passe (`password.php`), une réinitialisation (`forgot-password.php`) ou un changement d'adresse de connexion (`user.php`), et inscrit l'envoi au journal. L'alerte part vers le destinataire des e-mails du site, pas vers l'ancienne adresse de connexion.

**Qualité du code**

12. ~~En-tête et pied de page dupliqués.~~ *Corrigé (QUAL-1)* : ils sont dans `includes/entete-public.php` et `includes/pied-public.php`, inclus par les 3 pages publiques. Chaque page définit `$titrePage`, `$descriptionPage` et `$pageActive` avant l'inclusion.
13. ~~Fonction `e()` en double.~~ *Corrigé (QUAL-1)* : les 3 pages publiques incluent `includes/bootstrap.php`, seule définition de `e()`.
14. **`products.php` ne vérifie pas le contenu de `products.json`** : un produit sans champ `nom` ou `prix` provoque des avertissements PHP.
15. **Lecture-modification-écriture non atomique** des fichiers JSON (journal, limitation) : deux requêtes simultanées peuvent perdre une entrée. Sans conséquence en usage local.
16. **Tests.** *Atténué (QUAL-2)* : le cahier de recettes `TESTS.md` liste 38 tests avec le résultat attendu ; une recette automatisée a donné 39 / 39 OK le 15/10/2026. Reste : aucun script de test automatisé n'est livré dans le projet ; quelques tests manuels (navigateur, vraie boîte mail) restent à dérouler.
17. **Confusion possible entre les onglets** de `admin/email.php` : enregistrer depuis l'onglet PHP mail rebascule le mode actif sur PHP mail.

**Front-end et ergonomie**

- **CAPTCHA et accessibilité** : une personne aveugle ne peut pas lire l'image du calcul. Le texte d'aide propose l'e-mail et le téléphone de l'entreprise comme solution de remplacement. Un robot capable de lire les images (OCR) pourrait aussi résoudre le calcul ; la limite par IP et le champ piège restent en place derrière.

18. Pas de page d'erreur 404 personnalisée.
19. Pas de thème sombre.
20. Le bouton « Administration » est visible par tous les visiteurs dans le menu public (demande du client), ce qui signale l'adresse du panel.

**Documentation et exploitation**

21. Pas d'historique des versions (pas de dépôt Git, pas de `CHANGELOG`).
22. PHPMailer est copié à la main dans `vendor/` : sa mise à jour est manuelle.
23. Les copies du site (dossier WAMP et copie sur le Bureau) doivent être synchronisées à la main.

## 11. Points forts

- Mots de passe et codes jamais stockés en clair (hash Argon2id).
- Anti-brute force progressif complet : attente, blocage, journalisation.
- Protection CSRF et échappement systématique des données affichées.
- Sessions durcies (cookie HttpOnly / SameSite, régénération de l'identifiant, expiration, invalidation des autres sessions).
- Double validation des formulaires : JavaScript pour le confort, PHP pour la sécurité.
- Secrets retirés de la sauvegarde JSON ; identifiants SMTP masqués dans les logs.
- Dossiers sensibles fermés au navigateur par `.htaccess`.
- Aucune dépendance inutile : une seule bibliothèque (PHPMailer), pas de framework.
- Code commenté en français, fonctions courtes et nommées clairement.
- Site responsive, accessible au clavier, compatible avec « réduire les animations ».
- `README.md` complet (installation, vhost, SMTP, dépannage).
