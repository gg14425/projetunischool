# My EPI App — Plateforme de gestion universitaire

Application web de gestion de la vie universitaire de l'EPI, avec **4 espaces** :

| Espace | Page | API | Fonctions principales |
|---|---|---|---|
| Étudiant | `index.html` | `api/etudiant.php` | notes, absences, emploi du temps, supports de cours, finances, réclamations, demandes de documents (PDF) |
| Enseignant | `enseignant.html` | `api/enseignant.php` | appel, saisie des notes, dépôt de supports, messagerie |
| Scolarité | `scolarite.html` | `api/scolarite.php` | dossiers étudiants, emplois du temps, notes, absences, annonces, **génération des documents PDF** |
| Administrateur | `admin.html` | `api/admin.php` | comptes, classes, statistiques, journal d'activité, notifications, réclamations |

Technologies : PHP 8.1+ (PDO), MySQL/MariaDB, HTML/CSS/JavaScript natif. Aucune dépendance Composer/npm.

## Structure

```
myepi/
├─ index.html, enseignant.html, scolarite.html, admin.html   ← les 4 portails
├─ api/            ← 4 API (une par rôle)
├─ includes/       ← bootstrap.php (sécurité commune), MiniPdf.php, documents.php
├─ assets/         ← css/, js/ (common.js = code partagé), img/
├─ database/       ← myepi_db.sql + migrations/
├─ bin/setup.php   ← installation (mots de passe), ligne de commande uniquement
├─ uploads/        ← fichiers déposés (jamais accessibles directement)
├─ tests/          ← smoke_test.py (API, 84 contrôles) + ui_test.py (navigateur, 21 contrôles)
├─ .env.example    ← modèle de configuration
└─ docs/           ← rapport des corrections
```

## Installation (XAMPP / WAMP / Linux)

1. **Base de données** — créez la base et un utilisateur dédié (pas `root`) :
   ```sql
   CREATE DATABASE myepi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'myepi'@'localhost' IDENTIFIED BY 'un-mot-de-passe-solide';
   GRANT ALL ON myepi_db.* TO 'myepi'@'localhost';
   ```
   puis importez `database/myepi_db.sql`.
   *(Base déjà installée ? appliquez seulement `database/migrations/001_hardening.sql`.)*
2. **Configuration** — copiez `.env.example` en `.env` et renseignez `DB_USER`, `DB_PASS`, l'année et le semestre en cours.
3. **Comptes** — en ligne de commande, depuis le dossier du projet :
   ```bash
   php bin/setup.php --demo
   ```
   Le script génère des mots de passe **aléatoires** (admin + comptes de démonstration) et les affiche **une seule fois**. Pour la production, n'utilisez pas `--demo` et supprimez les comptes de démonstration.
4. **Lancement** — placez le dossier dans `htdocs/` (Apache) ou lancez `php -S localhost:8000` puis ouvrez `http://localhost:8000/`.

## Tests

```bash
php -S 127.0.0.1:8000 &                       # serveur de test
python3 tests/smoke_test.py http://127.0.0.1:8000   # API : sécurité, droits, validation, PDF, uploads
python3 tests/ui_test.py    http://127.0.0.1:8000   # navigateur (pip install playwright && playwright install chromium)
```
⚠ Les tests réinitialisent les mots de passe de démonstration : **base de test uniquement**.

## Sécurité — ce qui est en place

- Mots de passe **bcrypt** ; politique : 8 caractères min., lettres + chiffres ; changement avec mot de passe actuel.
- **Anti brute-force** : 5 échecs / 15 min par compte+IP (HTTP 429).
- Session : cookie `HttpOnly` + `SameSite=Strict` (+ `Secure` en HTTPS), ID régénéré à la connexion, expiration par inactivité (30 min), compte re-vérifié à chaque requête (une suspension agit immédiatement).
- **CSRF** : jeton obligatoire (en-tête `X-CSRF-Token`) + contrôle de l'origine sur toute requête modifiante.
- **CORS** fermé par défaut (liste blanche dans `.env`).
- **Contrôle d'accès par ressource** : un enseignant ne peut agir que sur ses classes/matières ; un étudiant ne télécharge que ses documents et les supports de sa filière.
- Requêtes SQL préparées ; données stockées brutes, **échappées à l'affichage** ; erreurs internes jamais exposées au client.
- Uploads : extension en liste blanche + contrôle du **contenu réel** (finfo), nom aléatoire, dossier `uploads/` fermé (`.htaccess`) — téléchargement uniquement via l'API.

## Limites connues / pistes d'évolution

- Les PDF utilisent les polices standard (alphabet latin) : un nom en caractères arabes serait remplacé par « ? ». Pour l'arabe, intégrer une police TTF (ex. via TCPDF/Dompdf).
- Pondération des notes fixe : CC 20 % · DS 30 % · Examen 50 % (définie dans la base).
- Les messages envoyés par les enseignants sont lisibles via l'API (`get_messages` côté étudiant) mais l'interface étudiant n'a pas encore d'écran « Messagerie ».
- Un seul compte connecté par navigateur à la fois (session partagée entre les 4 portails).
- En production : HTTPS obligatoire, sauvegardes de la base, `APP_ENV=production`.
