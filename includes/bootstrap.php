<?php
/**
 * bootstrap.php — noyau commun de My EPI App
 * ------------------------------------------------------------
 * Chargé par toutes les API (api/*.php) et par bin/setup.php.
 * Contient : configuration (.env), session sécurisée, en-têtes de
 * sécurité, CORS restreint, base de données, authentification,
 * protection CSRF, limitation des tentatives de connexion,
 * validation et utilitaires JSON.
 */
declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));
define('IS_CLI', PHP_SAPI === 'cli');

/* ─────────────────────────────────────────────
   1. CONFIGURATION (.env)
───────────────────────────────────────────── */
function loadEnv(string $file): void
{
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[0] === substr($v, -1)) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) {
            $_ENV[$k] = $v;
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    return isset($_ENV[$key]) && $_ENV[$key] !== '' ? $_ENV[$key] : $default;
}

loadEnv(ROOT_DIR . '/.env');

define('APP_ENV',       env('APP_ENV', 'production'));
define('APP_NAME',      'My EPI App');
define('DB_HOST',       env('DB_HOST', 'localhost'));
define('DB_PORT',       (int) env('DB_PORT', '3306'));
define('DB_NAME',       env('DB_NAME', 'myepi_db'));
define('DB_USER',       env('DB_USER', 'myepi'));
define('DB_PASS',       env('DB_PASS', ''));
define('DB_CHARSET',    'utf8mb4');
define('UPLOAD_DIR',    ROOT_DIR . '/uploads');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);           // 10 Mo
define('ACADEMIC_YEAR', env('ACADEMIC_YEAR', '2025/2026'));
define('CURRENT_SEMESTER', (int) env('CURRENT_SEMESTER', '2'));
define('ABSENCE_LIMIT_HOURS', (int) env('ABSENCE_LIMIT_HOURS', '30'));
define('SESSION_IDLE_MINUTES', (int) env('SESSION_IDLE_MINUTES', '30'));
define('LOGIN_MAX_FAILURES', 5);                      // échecs autorisés…
define('LOGIN_WINDOW_MINUTES', 15);                   // …sur cette fenêtre
define('ALLOWED_ORIGINS', array_filter(array_map('trim', explode(',', (string) env('ALLOWED_ORIGINS', '')))));

/* ─────────────────────────────────────────────
   2. ERREURS : jamais d'info technique au client
───────────────────────────────────────────── */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e): void {
    error_log('[MyEPI] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if ($e instanceof PDOException) {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 1062) jsonError('Cet enregistrement existe déjà.', 409);
        if ($code === 1451) jsonError('Suppression impossible : des données y sont rattachées.', 409);
        if ($code === 1452) jsonError('Référence invalide : élément inexistant.', 400);
    }
    $msg = 'Erreur interne du serveur.';
    if (APP_ENV === 'development') {
        $msg .= ' [' . $e->getMessage() . ']';
    }
    jsonError($msg, 500);
});

/* ─────────────────────────────────────────────
   3. SESSION + EN-TÊTES DE SÉCURITÉ
───────────────────────────────────────────── */
if (!IS_CLI) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('MYEPI_SID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000');
    }

    handleCors();
    enforceSameOrigin();
}

/**
 * CORS : désactivé par défaut (même origine uniquement).
 * Pour autoriser un front externe : ALLOWED_ORIGINS=https://exemple.tn dans .env
 */
function handleCors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
        header('Vary: Origin');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/** Refuse les requêtes modifiantes venant d'une autre origine (anti-CSRF, couche 1). */
function enforceSameOrigin(): void
{
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '' || in_array($origin, ALLOWED_ORIGINS, true)) {
        return;
    }
    $host = (string) parse_url($origin, PHP_URL_HOST);
    $port = parse_url($origin, PHP_URL_PORT);
    $originHost = $host . ($port ? ':' . $port : '');
    if (!hash_equals(strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')), strtolower($originHost))) {
        jsonError('Origine non autorisée.', 403);
    }
}

/* ─────────────────────────────────────────────
   4. RÉPONSES JSON
───────────────────────────────────────────── */
function jsonSuccess(array $data = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function getBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonError('Corps de requête JSON invalide.', 400);
    }
    return $data;
}

function getAction(): string
{
    return preg_replace('/[^a-z_]/', '', (string) ($_GET['action'] ?? ''));
}

/* ─────────────────────────────────────────────
   5. BASE DE DONNÉES
───────────────────────────────────────────── */
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('[MyEPI] Connexion BD impossible : ' . $e->getMessage());
            jsonError('Service temporairement indisponible.', 503);
        }
    }
    return $pdo;
}

function logActivity(int $userId, string $action, string $module, string $details = ''): void
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        getDB()->prepare('INSERT INTO activity_logs (user_id,action,module,details,ip_address) VALUES (?,?,?,?,?)')
               ->execute([$userId, $action, $module, mb_substr($details, 0, 500), $ip]);
    } catch (Throwable $e) {
        error_log('[MyEPI] logActivity: ' . $e->getMessage());
    }
}

/* ─────────────────────────────────────────────
   6. VALIDATION / NETTOYAGE
   NB : on stocke les données BRUTES (trim + caractères de contrôle
   retirés). L'échappement HTML se fait à l'AFFICHAGE (escHtml côté JS),
   ce qui évite les doubles échappements (&amp;amp;) en base.
───────────────────────────────────────────── */
function clean(mixed $v): string
{
    $s = trim((string) ($v ?? ''));
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    return $s ?? '';
}

function requireEnum(string $value, array $allowed, string $label): string
{
    if (!in_array($value, $allowed, true)) {
        jsonError("$label invalide.", 400);
    }
    return $value;
}

function requireDate(string $value, string $label = 'Date'): string
{
    $d = DateTime::createFromFormat('Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) {
        jsonError("$label invalide (format AAAA-MM-JJ).", 400);
    }
    return $value;
}

function requireTime(string $value, string $label = 'Heure'): string
{
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value)) {
        jsonError("$label invalide (format HH:MM).", 400);
    }
    return strlen($value) === 5 ? $value . ':00' : $value;
}

/** Note sur 20 : null si vide, erreur si hors [0 ; 20]. */
function gradeValue(mixed $v, string $label): ?float
{
    if ($v === null || $v === '') {
        return null;
    }
    if (!is_numeric($v)) {
        jsonError("$label doit être un nombre.", 400);
    }
    $f = (float) $v;
    if ($f < 0 || $f > 20) {
        jsonError("$label doit être comprise entre 0 et 20.", 400);
    }
    return round($f, 2);
}

function validatePasswordStrength(string $pwd): void
{
    if (strlen($pwd) < 8 || !preg_match('/[A-Za-z]/', $pwd) || !preg_match('/\d/', $pwd)) {
        jsonError('Le mot de passe doit contenir au moins 8 caractères, avec des lettres et des chiffres.', 400);
    }
}

function generatePassword(int $length = 12): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out . random_int(2, 9); // garantit au moins un chiffre
}

function hashPassword(string $plain): string
{
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword(string $plain, string $hash): bool
{
    return $hash !== '' && password_verify($plain, $hash);
}

/* ─────────────────────────────────────────────
   7. AUTHENTIFICATION
───────────────────────────────────────────── */

/**
 * Contrôle d'accès commun à toutes les actions protégées :
 *  - session valide + expiration par inactivité
 *  - rôle autorisé
 *  - compte toujours actif en base (une suspension agit immédiatement)
 *  - jeton CSRF obligatoire pour toute requête non-GET
 */
function requireAuth(string ...$roles): array
{
    if (empty($_SESSION['user'])) {
        jsonError('Non authentifié.', 401);
    }
    $last = (int) ($_SESSION['last_activity'] ?? 0);
    if ($last && (time() - $last) > SESSION_IDLE_MINUTES * 60) {
        destroySession();
        jsonError('Session expirée, veuillez vous reconnecter.', 401);
    }
    $user = $_SESSION['user'];
    if (!empty($roles) && !in_array($user['role'], $roles, true)) {
        jsonError('Accès refusé.', 403);
    }

    $st = getDB()->prepare('SELECT statut FROM utilisateurs WHERE id=?');
    $st->execute([$user['id']]);
    if ($st->fetchColumn() !== 'actif') {
        destroySession();
        jsonError('Compte suspendu ou supprimé.', 403);
    }

    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($sent === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $sent)) {
            jsonError('Jeton de sécurité invalide. Rechargez la page.', 403);
        }
    }

    $_SESSION['last_activity'] = time();
    return $user;
}

function destroySession(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/* ── Limitation des tentatives (anti brute-force) ── */
function throttleCheck(string $identifier): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $st = getDB()->prepare(
            'SELECT
                SUM(identifiant = ? AND ip_address = ?) AS par_compte,
                COUNT(*)                                AS par_ip
             FROM login_attempts
             WHERE succes = 0 AND created_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)
               AND (ip_address = ? OR identifiant = ?)'
        );
        $st->execute([$identifier, $ip, $ip, $identifier]);
        $r = $st->fetch();
        if ((int) $r['par_compte'] >= LOGIN_MAX_FAILURES || (int) $r['par_ip'] >= LOGIN_MAX_FAILURES * 4) {
            header('Retry-After: ' . LOGIN_WINDOW_MINUTES * 60);
            jsonError('Trop de tentatives. Réessayez dans ' . LOGIN_WINDOW_MINUTES . ' minutes.', 429);
        }
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) !== 1146) { // 1146 = table absente → migration non appliquée
            throw $e;
        }
        error_log('[MyEPI] Table login_attempts absente : appliquez database/migrations/001_hardening.sql');
    }
}

function throttleRecord(string $identifier, bool $success): void
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $db = getDB();
        if ($success) {
            $db->prepare('DELETE FROM login_attempts WHERE identifiant=? AND ip_address=?')->execute([$identifier, $ip]);
            return;
        }
        $db->prepare('INSERT INTO login_attempts (identifiant, ip_address, succes) VALUES (?,?,0)')
           ->execute([mb_substr($identifier, 0, 60), $ip]);
    } catch (PDOException $e) {
        error_log('[MyEPI] throttleRecord: ' . $e->getMessage());
    }
}

/**
 * Connexion commune aux 4 portails.
 * Retourne la ligne `utilisateurs` si les identifiants sont valides,
 * sinon répond directement en JSON (401 / 403 / 429).
 */
function authenticate(string $role, string $identifier, string $password): array
{
    if ($identifier === '' || $password === '') {
        jsonError('Identifiant et mot de passe requis.', 400);
    }
    if (strlen($identifier) > 30 || strlen($password) > 200) {
        jsonError('Identifiants incorrects.', 401);
    }
    throttleCheck($identifier);

    $st = getDB()->prepare('SELECT * FROM utilisateurs WHERE cin_matricule=? AND role=?');
    $st->execute([$identifier, $role]);
    $user = $st->fetch();

    if (!$user) {
        // égalise le temps de réponse pour ne pas révéler l'existence du compte
        static $dummy = null;
        $dummy ??= password_hash('dummy-password', PASSWORD_BCRYPT, ['cost' => 12]);
        password_verify($password, $dummy);
        throttleRecord($identifier, false);
        jsonError('Identifiants incorrects.', 401);
    }
    if (!verifyPassword($password, $user['mot_de_passe'])) {
        throttleRecord($identifier, false);
        jsonError('Identifiants incorrects.', 401);
    }
    if ($user['statut'] !== 'actif') {
        jsonError('Compte suspendu ou inactif. Contactez l\'administration.', 403);
    }

    throttleRecord($identifier, true);
    if (password_needs_rehash($user['mot_de_passe'], PASSWORD_BCRYPT, ['cost' => 12])) {
        getDB()->prepare('UPDATE utilisateurs SET mot_de_passe=? WHERE id=?')
               ->execute([hashPassword($password), $user['id']]);
    }
    return $user;
}

/** Ouvre la session après authentification (régénère l'ID : anti session-fixation). */
function startUserSession(array $sessionUser): string
{
    session_regenerate_id(true);
    $_SESSION['user']          = $sessionUser;
    $_SESSION['csrf']          = bin2hex(random_bytes(32));
    $_SESSION['last_activity'] = time();
    return $_SESSION['csrf'];
}

/** Changement de mot de passe (mot de passe actuel obligatoire). */
function changeOwnPassword(array $user, array $body): never
{
    $current = (string) ($body['current_password'] ?? '');
    $new     = (string) ($body['new_password'] ?? '');
    if ($current === '' || $new === '') {
        jsonError('Mot de passe actuel et nouveau mot de passe requis.');
    }
    $st = getDB()->prepare('SELECT mot_de_passe FROM utilisateurs WHERE id=?');
    $st->execute([$user['id']]);
    if (!verifyPassword($current, (string) $st->fetchColumn())) {
        jsonError('Mot de passe actuel incorrect.', 403);
    }
    if (hash_equals($current, $new)) {
        jsonError('Le nouveau mot de passe doit être différent de l\'ancien.');
    }
    validatePasswordStrength($new);
    getDB()->prepare('UPDATE utilisateurs SET mot_de_passe=? WHERE id=?')->execute([hashPassword($new), $user['id']]);
    session_regenerate_id(true);
    logActivity((int) $user['id'], 'edit', 'auth', 'Changement de mot de passe');
    jsonSuccess(['message' => 'Mot de passe modifié avec succès.']);
}

function doLogout(string $label): never
{
    $uid = $_SESSION['user']['id'] ?? null;
    if ($uid) {
        logActivity((int) $uid, 'logout', 'auth', "Déconnexion $label");
    }
    destroySession();
    jsonSuccess(['message' => 'Déconnecté.']);
}

/* ─────────────────────────────────────────────
   8. FICHIERS
───────────────────────────────────────────── */

/** Envoie un fichier du dossier uploads/ après vérification du chemin (anti path-traversal). */
function streamUpload(string $relativePath, string $downloadName, string $mime): never
{
    $base = realpath(UPLOAD_DIR);
    $full = realpath(ROOT_DIR . '/' . ltrim($relativePath, '/'));
    if ($base === false || $full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR) || !is_file($full)) {
        jsonError('Fichier introuvable.', 404);
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($full));
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) . '"');
    header('Cache-Control: private, no-store');
    readfile($full);
    exit;
}

/* ─────────────────────────────────────────────
   9. SQL RÉUTILISABLE
───────────────────────────────────────────── */

/** Moyenne générale pondérée par les crédits (coefficients). */
const SQL_WEIGHTED_AVG = 'ROUND(SUM(n.moyenne * m.credits) / NULLIF(SUM(CASE WHEN n.moyenne IS NOT NULL THEN m.credits END), 0), 2)';
