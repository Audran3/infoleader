# InfoLeader — Site vitrine + panneau d'administration (TP BTS SIO)

Site vitrine de l'entreprise fictive **InfoLeader**, spécialisée dans la vente de périphériques informatiques
(claviers, souris, écrans, casques, webcams, hubs), avec un **panneau d'administration** sécurisé.

Site 100 % local, exécuté par **WAMP** (Apache + PHP 8). Aucune base de données (les données sont dans des fichiers JSON),
aucun framework. Seule dépendance : **PHPMailer** (envoi des e-mails par un serveur SMTP).

## Pages publiques

| Page | Fichier | Contenu |
|------|---------|---------|
| Accueil | `index.php` | Bannière, slogan, bouton « Découvrir nos produits », présentation, points forts |
| Nos Produits | `products.php` | 6 produits lus dans `products.json`, grille responsive, filtre de recherche en temps réel |
| Contact | `contact.php` | Formulaire nom / e-mail / message, validation JavaScript + PHP, message enregistré pour le panel |

## Panneau d'administration (`/admin/`)

| Outil | Fichier | Rôle |
|-------|---------|------|
| Connexion | `admin/login.php` | Connexion + anti-brute force (3 s d'attente, puis 10 s, 30 s, 60 s, puis blocage de l'IP 4 h au 10e échec) |
| Mot de passe oublié | `admin/forgot-password.php` | Code à 8 chiffres envoyé par e-mail, valable 5 minutes, 5 essais maximum |
| Tableau de bord | `admin/dashboard.php` | Informations du compte connecté + accès aux 4 outils |
| Configuration des e-mails | `admin/email.php` | PHP mail (hébergeur web) ou SMTP (PHPMailer), bouton « Tester l'envoi », logs de débogage |
| Mot de passe | `admin/password.php` | Mot de passe actuel exigé, nouveau mot de passe de 20 caractères minimum respectant les règles |
| Utilisateur | `admin/user.php` | Changement de l'e-mail de connexion (mot de passe actuel, syntaxe, adresse différente, double saisie) |
| Maintenance | `admin/maintenance.php` | Système, Journaux (connexions + messages de contact), Sauvegarde JSON, Purger storage/tmp |

### Sécurité mise en œuvre

- Mots de passe et codes stockés uniquement sous forme de **hash** (`password_hash`, Argon2id).
- **Jeton CSRF** sur tous les formulaires, cookie de session `HttpOnly` + `SameSite=Strict`, nouvel identifiant de session à la connexion.
- Déconnexion après **30 minutes d'inactivité** ; les autres sessions sont fermées si le mot de passe ou l'e-mail change.
- Les dossiers `config/`, `includes/`, `storage/` et `vendor/` sont **interdits d'accès** depuis le navigateur (`.htaccess`).
- Toutes les données affichées sont échappées (`htmlspecialchars`) ; toutes les saisies sont revérifiées par PHP.

## Arborescence

```
infoleader/
├── index.php, products.php, contact.php   Pages publiques
├── style.css, script.js                   Style et interactions du site public
├── products.json                          Catalogue des 6 produits
├── assets/                                Images (bannière + produits)
├── admin/                                 Panneau d'administration (pages + admin.css + admin.js)
├── includes/                              Code PHP commun (sécurité, e-mails, stockage, journal…) — accès web interdit
│   └── creer-admin.php                    Outil en ligne de commande : crée / réinitialise le compte admin
├── config/                                admin.json, mail.json, securite.json — accès web interdit
├── storage/                               accès web interdit
│   ├── data/                              journal de connexion, messages de contact, limitation, demande de code
│   ├── logs/mail.log                      logs de débogage des e-mails
│   └── tmp/                               fichiers temporaires (copies des sauvegardes JSON)
├── vendor/phpmailer/phpmailer/            Bibliothèque PHPMailer 7.1.1 (src/ + LICENSE)
└── README.md
```

## Installation dans WAMP

1. Lancer WAMP et attendre que l'icône soit **verte**.
2. Le dossier `infoleader` doit se trouver dans `C:\wamp64\www\`.
3. Ouvrir **http://localhost/infoleader** (site) et **http://localhost/infoleader/admin/** (panel).
4. Les identifiants initiaux du panel sont dans le fichier `IDENTIFIANTS_ADMIN.txt` (hors du dossier du site).
   Changez le mot de passe dès la première connexion, puis supprimez ce fichier.

## Virtual host `infoleader.local`

1. Bloc-notes **en administrateur** → `C:\Windows\System32\drivers\etc\hosts`, ajouter :
   ```
   127.0.0.1       infoleader.local
   ::1             infoleader.local
   ```
2. Dans `C:\wamp64\bin\apache\apache2.4.65\conf\extra\httpd-vhosts.conf` (déjà fait) :
   ```apache
   <VirtualHost *:80>
       ServerName infoleader.local
       DocumentRoot "C:/wamp64/www/infoleader"
       <Directory "C:/wamp64/www/infoleader">
           Options Indexes FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
3. Icône WAMP → **Redémarrer les services**, puis ouvrir **http://infoleader.local**.

## Configurer l'envoi des e-mails (SMTP)

Le site envoie ses e-mails par le serveur SMTP d'une messagerie (ici `mail.olivier-dutoit.fr`), via PHPMailer.

1. Pré-requis WAMP (déjà vérifiés) : extension `openssl` active dans `php.ini`, PHPMailer installé dans `vendor/`,
   port **465** joignable en sortie (le 587 est bloqué sur ce réseau).
2. Dans le panel : **E-mails** → onglet **SMTP**. Déjà rempli : serveur `mail.olivier-dutoit.fr`, port 465, SSL,
   identifiant et expéditeur = l'adresse du compte. L'expéditeur doit toujours être l'adresse du compte SMTP.
3. Saisir le **mot de passe** du compte, puis cliquer sur **Tester l'envoi**.
4. Vérifier la réception (et le dossier Spam), puis tester « Mot de passe oublié ».

En cas d'échec : message d'erreur affiché dans le panel, `storage/logs/mail.log` et `C:\wamp64\logs\php_error.log`.

## Tester le site

Après chaque modification, refaire les tests du cahier de recettes `TESTS.md` (site public, connexion,
modules du panel, mot de passe oublié, tests dans le navigateur et avec une vraie boîte mail).

## Publier le code sur GitHub sans les secrets

Le fichier `.gitignore` empêche Git d'envoyer :

- `config/admin.json` (compte administrateur) et `config/mail.json` (mot de passe SMTP en clair) ;
- les données et logs de `storage/` (messages de contact, journal, codes de réinitialisation).

Après avoir récupéré le code sur un autre poste :

1. Créer le compte administrateur : `php includes/creer-admin.php <e-mail> <fichier d'identifiants>`.
2. Copier `config/mail.example.json` en `config/mail.json` (facultatif : sans ce fichier, les valeurs par défaut sont utilisées),
   puis saisir le mot de passe SMTP dans le panel (E-mails → SMTP).

## Dépannage

- **IP bloquée pendant les tests** : dans un terminal ouvert dans `includes/`, lister les adresses suivies avec
  `C:\wamp64\bin\php\php8.3.28\php.exe debloquer-ip.php`, puis débloquer la vôtre (en local `::1` ou `127.0.0.1`)
  avec `... debloquer-ip.php ::1` (ou `--toutes`).
- **Mot de passe perdu sans envoi d'e-mail possible** : avec les logs de débogage activés, le code de
  « Mot de passe oublié » figure dans `storage/logs/mail.log`. En dernier recours, dans un terminal ouvert dans `includes/` :
  `C:\wamp64\bin\php\php8.3.28\php.exe creer-admin.php admin@infoleader.local C:\chemin\identifiants.txt`

## Charte graphique

| Rôle | Couleur |
|------|---------|
| Bleu tech (principal) | `#0056b3` |
| Gris foncé (textes, en-têtes) | `#2d2d2d` |
| Blanc | `#ffffff` |
| Vert (accent) | `#4caf50` |

Polices sans-serif : Segoe UI, Roboto, Arial.

## Hors périmètre

- Base de données (SQLite, MySQL) : les données sont stockées dans des fichiers JSON.
- Framework CSS / JS, AJAX, API externe.
- Envoi d'e-mail via PHP mail en local (ne fonctionne que chez un hébergeur web).
