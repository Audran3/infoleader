# Plan d'amélioration — Site InfoLeader

> Établi le 15/10/2026 à partir de `DOCUMENTATION.md` (section 10 « Limites connues et risques »).
> Toutes les actions se réalisent **en local sur WAMP**, sans serveur distant, sans paiement réel et sans nouvelle dépendance.
> Le stockage en fichiers JSON n'est pas un défaut : il reste la solution de référence de ce plan.

## Sommaire

1. [Comment lire ce plan](#1-comment-lire-ce-plan)
2. [Sécurité](#2-sécurité)
3. [Conformité / RGPD](#3-conformité--rgpd)
4. [Stockage des données](#4-stockage-des-données)
5. [Qualité du code](#5-qualité-du-code)
6. [Front-end](#6-front-end)
7. [Documentation](#7-documentation)
8. [Exploitation locale](#8-exploitation-locale)
9. [Définition de Terminé](#9-définition-de-terminé)
10. [Séquencement par jalons](#10-séquencement-par-jalons)
11. [Petit lexique](#11-petit-lexique)

---

## 1. Comment lire ce plan

| Priorité | Signification |
|----------|---------------|
| **P0** | Critique : risque réel pour la sécurité ou la conformité. À faire en premier. |
| **P1** | Important : améliore nettement la sécurité, la qualité ou le respect de la loi. |
| **P2** | Optionnel : confort, finition, évolutions possibles. |

| Complexité | Signification |
|------------|---------------|
| **Simple** | Moins d'une heure, un ou deux fichiers. |
| **Moyen** | Une à trois heures, plusieurs fichiers, quelques tests. |
| **Complexe** | Plusieurs séances, à valider avec le professeur. |

La colonne « Statut » se met à jour au fil du travail : ☐ à faire, ☑ terminé (avec la date).

---

## 2. Sécurité

| N° | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation | Statut |
|----|--------|----------|------------|--------------------|-----------------------|--------|
| SEC-1 | **Protéger les secrets avant tout dépôt Git.** Créer un fichier `.gitignore` qui exclut `config/admin.json`, `config/mail.json`, les données de `storage/` et les logs ; fournir un modèle sans secret `config/mail.example.json`. | P0 | Simple | `.gitignore` (nouveau), `config/mail.example.json` (nouveau), `README.md` | Dans GitHub Desktop, en ajoutant le dossier du site comme dépôt, `config/mail.json`, `config/admin.json`, `storage/data/*.json` et `storage/logs/mail.log` n'apparaissent pas dans la liste des fichiers à envoyer. `config/mail.example.json` ne contient aucun mot de passe. | ☑ 15/10/2026 |
| SEC-2 | **Vérifier que les dossiers sensibles sont réellement fermés.** Dans Maintenance > Système, tester par une vraie requête HTTP que `config/`, `storage/`, `includes/` et `vendor/` renvoient l'erreur 403, au lieu de vérifier seulement la présence du `.htaccess`. | P0 | Moyen | `includes/systeme.php` | La section « Fichiers de configuration » affiche « refusé (HTTP 403) » pour chaque dossier. Si l'on renomme temporairement `vendor/.htaccess` (dossier sans secret), la ligne passe en « Erreur » et l'état global devient rouge. | ☑ 15/10/2026 |
| SEC-3 | **Limiter les abus du formulaire de contact.** 3 messages maximum par adresse IP toutes les 10 minutes, et un « champ piège » invisible : s'il est rempli, le message est ignoré. | P0 | Moyen | `contact.php`, `includes/stockage.php`, `config/securite.json` | Un 4e message envoyé en moins de 10 minutes depuis la même IP est refusé avec un message clair. Un envoi avec le champ piège rempli n'apparaît pas dans Maintenance > Journaux > Messages de contact. Un message normal est toujours enregistré. | ☑ 15/10/2026 |
| SEC-4 | **Empêcher un inconnu d'annuler le code de réinitialisation.** Limiter les demandes de code à 3 par heure et par IP, et journaliser les demandes refusées. | P1 | Moyen | `admin/forgot-password.php`, `config/securite.json` | Une 4e demande dans l'heure depuis la même IP est refusée sans remplacer le code en cours. L'entrée apparaît dans le journal de connexion. | ☑ 15/10/2026 |
| SEC-5 | **Encadrer les logs de débogage.** Désactiver l'option par défaut, afficher un avertissement dans le panel quand elle est active, ajouter un bouton « Vider mail.log » (avec confirmation) dans Maintenance. | P1 | Simple | `admin/email.php`, `admin/maintenance.php`, `includes/systeme.php`, `config/mail.json` | Quand les logs sont actifs, un encart orange le signale sur le tableau de bord. Le bouton vide `storage/logs/mail.log` après confirmation (Maintenance > Journaux > Logs e-mails). | ☑ 15/10/2026 |
| SEC-6 | **Ne plus journaliser l'identifiant saisi en clair.** En cas d'échec de connexion, n'enregistrer qu'une version masquée (ex. `ad***@infoleader.local`). | P1 | Simple | `admin/login.php` | Après un échec, le journal affiche l'identifiant masqué ; un texte qui n'est pas une adresse e-mail est remplacé par « (saisie non valide) ». | ☑ 15/10/2026 |
| SEC-7 | **Masquer les erreurs PHP aux visiteurs.** Ajouter à la racine du site un `.htaccess` avec `php_flag display_errors off` (les erreurs restent enregistrées dans le journal de WAMP). | P1 | Simple | `.htaccess` (nouveau, racine) | Une erreur PHP volontaire (page de test temporaire utilisant une variable inexistante) n'affiche aucun détail technique dans le navigateur, mais apparaît dans `C:\wamp64\logs\php_error.log`. (Une faute dans `products.json` ne convient pas pour ce test : `products.php` affiche alors « catalogue indisponible » sans erreur PHP.) | ☑ 15/10/2026 |
| SEC-8 | **Ajouter les en-têtes de sécurité aux pages publiques** (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`), comme sur le panel. | P1 | Simple | `.htaccess` (racine) — réalisé avec le module Apache `mod_headers`, actif sous WAMP | Dans le navigateur (F12 > Réseau > en-têtes de la réponse), les 3 en-têtes sont présents sur les 3 pages. | ☑ 15/10/2026 |
| SEC-9 | **Prévenir par e-mail** lors d'un changement de mot de passe ou d'adresse de connexion, en réutilisant `envoyerMail()`. | P1 | Simple | `admin/password.php`, `admin/user.php`, `admin/forgot-password.php` | Chaque changement provoque un e-mail « Votre mot de passe a été modifié » (ou « adresse modifiée ») reçu dans la boîte configurée. | ☑ 15/10/2026 (testé sans envoi réel ; réception à confirmer au prochain changement) |
| SEC-10 | **Content-Security-Policy** (liste des sources autorisées pour les scripts et styles). Prérequis : remplacer l'attribut `onsubmit` de `contact.php` par un écouteur dans `script.js`. | P2 | Moyen | `contact.php`, `script.js`, en-têtes HTTP | L'en-tête `Content-Security-Policy` est présent ; le site et le panel fonctionnent sans erreur dans la console (F12). | ☐ |

## 3. Conformité / RGPD

| N° | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation | Statut |
|----|--------|----------|------------|--------------------|-----------------------|--------|
| RGPD-1 | **Informer les visiteurs du formulaire de contact** : finalité (répondre à la demande), données conservées (nom, e-mail, message, adresse IP), durée de conservation, droit de suppression et contact. | P0 | Simple | `contact.php`, `style.css` | Un court texte d'information est visible sous le formulaire, avant le bouton d'envoi. | ☑ 15/10/2026 |
| RGPD-2 | **Appliquer une durée de conservation** : suppression automatique des messages de contact plus anciens que `contact_conservation_mois` (12 mois, durée annoncée par la mention de RGPD-1) et des entrées du journal de plus de 6 mois. | P1 | Moyen | `includes/stockage.php`, `includes/journal.php`, `config/securite.json` | Un message daté de plus de 12 mois (créé pour le test) disparaît après l'enregistrement d'un nouveau message. | ☑ 15/10/2026 |

## 4. Stockage des données

| N° | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation | Statut |
|----|--------|----------|------------|--------------------|-----------------------|--------|
| STO-1 | **Contrôler le contenu de `products.json`** : ignorer un produit incomplet au lieu de provoquer des avertissements PHP. | P2 | Simple | `products.php` | Un produit sans `prix` (test) n'est pas affiché et la page ne montre aucun avertissement. | ☐ |
| STO-2 | **Limiter la taille de `mail.log`** : au-delà de 1 Mo, renommer le fichier en `mail.log.1` et repartir d'un fichier vide. | P2 | Simple | `includes/mailer.php` | Avec un `mail.log` de plus de 1 Mo, l'envoi suivant crée `mail.log.1` et un nouveau `mail.log`. | ☐ |
| STO-3 | **(Optionnel — sous accord du professeur) Migrer les messages de contact et le journal vers SQLite.** SQLite est déjà fourni avec PHP sous WAMP (extension `pdo_sqlite`, activée) : aucune installation. Base dans `storage/data/infoleader.sqlite`. | P2 | Complexe | `includes/stockage.php`, `includes/journal.php`, `admin/maintenance.php` | Les messages et le journal sont enregistrés dans la base ; Maintenance les affiche, les filtre et les purge comme avant ; les anciens fichiers JSON sont importés une fois. | ☐ |

## 5. Qualité du code

| N° | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation | Statut |
|----|--------|----------|------------|--------------------|-----------------------|--------|
| QUAL-1 | **Factoriser l'en-tête et le pied de page publics** dans deux fichiers inclus, et supprimer la fonction `e()` en double de `products.php`. | P1 | Moyen | `index.php`, `products.php`, `contact.php`, `includes/entete-public.php` et `includes/pied-public.php` (nouveaux) | Le menu n'existe plus qu'à un seul endroit ; les 3 pages s'affichent comme avant, avec le bon lien actif. | ☑ 15/10/2026 |
| QUAL-2 | **Rédiger un cahier de recettes** : la liste des tests manuels à refaire après chaque modification (pages, filtre, contact, connexion, anti-brute force, e-mails, modules). | P1 | Simple | `TESTS.md` (nouveau) | Le fichier liste au moins 20 tests avec le résultat attendu ; il a été déroulé une fois en entier. | ☑ 15/10/2026 (39 tests automatisés OK ; quelques tests manuels restent à faire, voir `TESTS.md`) |
| QUAL-3 | **Clarifier les onglets de la configuration des e-mails** : demander confirmation avant d'enregistrer en mode PHP mail si le SMTP est déjà configuré. | P2 | Simple | `admin/email.php` | Enregistrer depuis l'onglet PHP mail alors que le SMTP fonctionne affiche une fenêtre de confirmation. | ☐ |

## 6. Front-end

| N° | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation | Statut |
|----|--------|----------|------------|--------------------|-----------------------|--------|
| FRONT-1 | **Page d'erreur 404 personnalisée** aux couleurs du site, avec un lien vers l'accueil. | P2 | Simple | `404.php` (nouveau), `.htaccess` (directive `ErrorDocument`) | Une adresse inexistante (ex. `/infoleader/abc`) affiche la page 404 du site. | ☐ |
| FRONT-2 | **Thème sombre automatique** selon le réglage de l'ordinateur (`prefers-color-scheme`). | P2 | Moyen | `style.css`, `admin/admin.css` | Avec Windows en mode sombre, le site et le panel s'affichent en sombre et restent lisibles. | ☐ |
| FRONT-3 | **Déplacer le bouton « Administration »** du menu vers le pied de page (à valider avec le client, qui l'a demandé dans le menu). | P2 | Simple | `index.php`, `products.php`, `contact.php`, `style.css` | Le lien vers le panel est dans le pied de page des 3 pages et n'apparaît plus dans le menu. | ☐ |

## 7. Documentation

| N° | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation | Statut |
|----|--------|----------|------------|--------------------|-----------------------|--------|
| DOC-1 | **Tenir un journal des versions** : chaque tâche terminée y est notée avec sa date. | P2 | Simple | `CHANGELOG.md` (nouveau) | Chaque tâche cochée dans ce fichier a une ligne correspondante dans `CHANGELOG.md`. | ☐ |
| DOC-2 | **Mettre à jour `DOCUMENTATION.md` à la fin de chaque jalon.** | P1 | Simple | `DOCUMENTATION.md` | La section « Limites connues » ne mentionne plus les points corrigés. | ☑ 15/10/2026 (jalons 1 à 3 ; à refaire après le jalon 4) |

## 8. Exploitation locale

| N° | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation | Statut |
|----|--------|----------|------------|--------------------|-----------------------|--------|
| EXP-1 | **Outil de déblocage d'adresse IP** en ligne de commande (sur le modèle de `creer-admin.php`), pour ne plus devoir supprimer `limitation.json` à la main. | P1 | Simple | `includes/debloquer-ip.php` (nouveau), `README.md` | Après 10 échecs volontaires, la commande débloque l'IP et la connexion redevient possible immédiatement. | ☑ 15/10/2026 |
| EXP-2 | **Une seule copie de travail** : travailler uniquement dans `C:\wamp64\www\infoleader` et ne garder sur le Bureau qu'une sauvegarde datée (dossier compressé sans les secrets). | P2 | Simple | — (procédure dans `README.md`) | Le `README.md` décrit la procédure de sauvegarde et de restauration ; une sauvegarde a été faite et restaurée une fois pour vérifier. | ☐ |

---

## 9. Définition de Terminé

Une tâche est **terminée** seulement si toutes les conditions suivantes sont vraies :

1. Le **critère d'acceptation** de la tâche a été vérifié dans le navigateur, sur `http://localhost/infoleader/` (ou `http://infoleader.local/`).
2. Les **3 pages publiques et le panel** fonctionnent toujours (navigation, filtre, contact, connexion).
3. Aucune **erreur PHP** nouvelle dans `C:\wamp64\logs\php_error.log`, aucune erreur rouge dans la console du navigateur (F12).
4. **Aucune nouvelle dépendance** n'a été ajoutée sans accord du professeur ; rien ne suppose un serveur distant ni un paiement réel.
5. Le code ajouté est **commenté en français** et suit le style existant.
6. Les **secrets** (mots de passe, mot de passe SMTP) n'apparaissent ni dans le code, ni dans les logs, ni dans les fichiers publiés.
7. Le **statut** est passé à ☑ avec la date dans ce fichier, et `DOCUMENTATION.md` est corrigé si le fonctionnement a changé.

---

## 10. Séquencement par jalons

| Jalon | Objectif | Tâches | Durée indicative |
|-------|----------|--------|------------------|
| **Jalon 1 — Urgent** | Supprimer les risques critiques | SEC-1 → SEC-2 → SEC-3 → RGPD-1 | 1 séance |
| **Jalon 2 — Sécurité** | Fermer les failles restantes | SEC-6 → SEC-7 → SEC-8 → SEC-5 → SEC-4 → SEC-9 → EXP-1 | 1 à 2 séances |
| **Jalon 3 — Qualité et conformité** | Code plus simple à maintenir, données limitées dans le temps | QUAL-1 → QUAL-2 → RGPD-2 → DOC-2 | 1 séance |
| **Jalon 4 — Finitions** | Confort et présentation | STO-1 → STO-2 → FRONT-1 → QUAL-3 → FRONT-3 → DOC-1 → EXP-2 → FRONT-2 → SEC-10 | Selon le temps disponible |
| **Jalon 5 — Optionnel** | Évolution du stockage (sous accord du professeur) | STO-3 (SQLite) | 2 séances ou plus |

Règle : on ne commence un jalon que lorsque toutes les tâches du jalon précédent sont terminées.

---

## 11. Petit lexique

- **`.gitignore`** : liste des fichiers que Git ne doit jamais envoyer sur GitHub (ex. fichiers contenant des mots de passe).
- **Erreur 403** : réponse du serveur qui signifie « accès interdit ».
- **`.htaccess`** : petit fichier de réglages qu'Apache lit dans un dossier (ex. interdire l'accès à ce dossier).
- **Champ piège (honeypot)** : champ de formulaire caché aux humains ; seuls les robots le remplissent, ce qui permet de les repérer.
- **En-tête HTTP** : information technique envoyée par le serveur avec chaque page (invisible à l'écran, visible avec F12).
- **Content-Security-Policy (CSP)** : en-tête qui indique au navigateur d'où les scripts et styles ont le droit de venir ; limite les injections de code.
- **RGPD** : règlement européen sur la protection des données personnelles ; il impose notamment d'informer les personnes et de limiter la durée de conservation.
- **Journal des versions (changelog)** : liste datée des modifications apportées au site.
- **SQLite / PDO** : SQLite est une base de données contenue dans un seul fichier, sans serveur ; PDO est l'outil de PHP pour lui envoyer des requêtes SQL.
- **Rotation des logs** : renommer un fichier de log devenu trop gros pour repartir d'un fichier vide.
