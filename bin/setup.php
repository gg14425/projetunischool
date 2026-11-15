<?php
/**
 * bin/setup.php — installation / maintenance (LIGNE DE COMMANDE UNIQUEMENT)
 *
 *   php bin/setup.php                       → (ré)initialise le mot de passe de l'admin (généré, affiché une fois)
 *   php bin/setup.php --admin-password=XXX  → fixe le mot de passe de l'admin
 *   php bin/setup.php --demo                → + génère des mots de passe aléatoires pour les comptes de démonstration
 *
 * Aucun mot de passe n'est stocké dans le dépôt : ils sont générés ici et affichés UNE fois.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/bootstrap.php';

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--')) {
        [$k, $v] = array_pad(explode('=', substr($a, 2), 2), 2, true);
        $opts[$k] = $v;
    }
}

$db = getDB();

// 1) Table anti brute-force (si migration non appliquée)
$db->exec(file_get_contents(__DIR__ . '/../database/migrations/001_hardening.sql'));
echo "✔ Base de données OK (table login_attempts présente)\n";

// 2) Dossiers d'upload
foreach (['cours', 'docs'] as $d) {
    if (!is_dir(UPLOAD_DIR . "/$d")) mkdir(UPLOAD_DIR . "/$d", 0755, true);
}

// 3) Administrateur
$st = $db->prepare('SELECT id, cin_matricule FROM utilisateurs WHERE role="admin" ORDER BY id LIMIT 1');
$st->execute();
$admin = $st->fetch();
if (!$admin) {
    fwrite(STDERR, "✘ Aucun compte admin dans la base : importez d'abord database/myepi_db.sql\n");
    exit(1);
}
$pwd = is_string($opts['admin-password'] ?? null) ? $opts['admin-password'] : generatePassword(14);
if (is_string($opts['admin-password'] ?? null)) {
    if (strlen($pwd) < 8 || !preg_match('/[A-Za-z]/', $pwd) || !preg_match('/\d/', $pwd)) {
        fwrite(STDERR, "✘ Mot de passe trop faible (8 caractères min., lettres + chiffres)\n");
        exit(1);
    }
}
$db->prepare('UPDATE utilisateurs SET mot_de_passe=?, statut="actif" WHERE id=?')->execute([hashPassword($pwd), $admin['id']]);
echo "\n=== ADMINISTRATEUR ===\n  Identifiant : {$admin['cin_matricule']}\n  Mot de passe: $pwd\n  (notez-le maintenant : il ne sera plus affiché)\n";

// 4) Comptes de démonstration (optionnel)
if (isset($opts['demo'])) {
    echo "\n=== COMPTES DE DÉMONSTRATION ===\n";
    $rows = $db->query('SELECT id, cin_matricule, role FROM utilisateurs WHERE role<>"admin" ORDER BY role, id')->fetchAll();
    $up = $db->prepare('UPDATE utilisateurs SET mot_de_passe=? WHERE id=?');
    foreach ($rows as $r) {
        $p = generatePassword(10);
        $up->execute([hashPassword($p), $r['id']]);
        printf("  %-11s %-12s %s\n", $r['role'], $r['cin_matricule'], $p);
    }
}
echo "\nTerminé.\n";
