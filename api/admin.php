<?php
/**
 * api/admin.php — API du portail Administrateur
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

const USER_ROLES    = ['etudiant', 'enseignant', 'scolarite', 'admin'];
const USER_STATUTS  = ['actif', 'suspendu', 'inactif'];

/** Aligne etudiants.statut quand le compte utilisateur est (dés)activé. */
function syncStudentStatus(PDO $db, int $userId, string $userStatut): void
{
    $new = $userStatut === 'actif' ? 'actif' : 'suspendu';
    $db->prepare('UPDATE etudiants SET statut=? WHERE utilisateur_id=? AND statut IN ("actif","suspendu")')
       ->execute([$new, $userId]);
}

function countActiveAdmins(PDO $db): int
{
    return (int) $db->query('SELECT COUNT(*) FROM utilisateurs WHERE role="admin" AND statut="actif"')->fetchColumn();
}

$action = getAction();

switch ($action) {

    /* ───────── Authentification ───────── */
    case 'login':
        $body = getBody();
        $u    = authenticate('admin', clean($body['matricule'] ?? ''), (string) ($body['password'] ?? ''));
        $csrf = startUserSession([
            'id'     => (int) $u['id'],
            'prenom' => $u['prenom'],
            'nom'    => $u['nom'],
            'email'  => $u['email'],
            'role'   => 'admin',
        ]);
        logActivity((int) $u['id'], 'login', 'auth', 'Connexion admin');
        jsonSuccess(['user' => $_SESSION['user'], 'csrf_token' => $csrf]);

    case 'logout':
        doLogout('admin');

    case 'change_password':
        changeOwnPassword(requireAuth('admin'), getBody());

    /* ───────── Tableau de bord ───────── */
    case 'get_dashboard':
        requireAuth('admin');
        $db = getDB();
        $total_users       = $db->query('SELECT COUNT(*) FROM utilisateurs WHERE statut="actif"')->fetchColumn();
        $total_etudiants   = $db->query('SELECT COUNT(*) FROM utilisateurs WHERE role="etudiant" AND statut="actif"')->fetchColumn();
        $total_enseignants = $db->query('SELECT COUNT(*) FROM utilisateurs WHERE role="enseignant" AND statut="actif"')->fetchColumn();
        $alertes_systeme   = $db->query('SELECT COUNT(*) FROM reclamations WHERE statut="en_attente"')->fetchColumn();
        $recent_logs = $db->query('SELECT l.*, CONCAT(u.prenom," ",u.nom) AS user_nom
                                   FROM activity_logs l LEFT JOIN utilisateurs u ON u.id=l.user_id
                                   ORDER BY l.created_at DESC, l.id DESC LIMIT 5')->fetchAll();
        jsonSuccess(['dashboard' => compact('total_users', 'total_etudiants', 'total_enseignants', 'alertes_systeme', 'recent_logs')]);

    /* ───────── Utilisateurs ───────── */
    case 'get_users':
        requireAuth('admin');
        $where  = '1=1';
        $params = [];
        if (!empty($_GET['role']) && in_array($_GET['role'], USER_ROLES, true)) {
            $where .= ' AND u.role=?';
            $params[] = $_GET['role'];
        }
        if (!empty($_GET['statut']) && in_array($_GET['statut'], USER_STATUTS, true)) {
            $where .= ' AND u.statut=?';
            $params[] = $_GET['statut'];
        }
        if (!empty($_GET['search'])) {
            $s = '%' . addcslashes(mb_substr((string) $_GET['search'], 0, 80), '%_\\') . '%';
            $where .= ' AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.cin_matricule LIKE ?)';
            array_push($params, $s, $s, $s, $s);
        }
        $limit  = max(1, min((int) ($_GET['limit'] ?? 500), 1000));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        $st = getDB()->prepare("SELECT u.id,u.prenom,u.nom,u.email,u.cin_matricule,u.role,u.statut,u.photo_url,u.created_at
                                FROM utilisateurs u WHERE $where
                                ORDER BY u.role,u.nom,u.prenom LIMIT $limit OFFSET $offset");
        $st->execute($params);
        jsonSuccess(['users' => $st->fetchAll(), 'limit' => $limit, 'offset' => $offset]);

    case 'get_user':
        requireAuth('admin');
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) jsonError('ID requis.');
        $st = getDB()->prepare(
            'SELECT u.id,u.prenom,u.nom,u.email,u.cin_matricule,u.role,u.statut,u.photo_url,u.created_at,
                    e.id AS etudiant_id, e.classe_id, e.statut AS etudiant_statut,
                    c.nom AS classe_nom, f.nom AS filiere_nom,
                    ens.id AS enseignant_id, ens.grade, ens.specialite, d.nom AS dept_nom
             FROM utilisateurs u
             LEFT JOIN etudiants e     ON e.utilisateur_id=u.id
             LEFT JOIN classes c       ON c.id=e.classe_id
             LEFT JOIN filieres f      ON f.id=c.filiere_id
             LEFT JOIN enseignants ens ON ens.utilisateur_id=u.id
             LEFT JOIN departements d  ON d.id=ens.departement_id
             WHERE u.id=?'
        );
        $st->execute([$id]);
        $user = $st->fetch();
        if (!$user) jsonError('Utilisateur introuvable.', 404);
        jsonSuccess(['user' => $user]);

    case 'create_user':
        $admin = requireAuth('admin');
        $body  = getBody();

        $prenom = clean($body['prenom'] ?? '');
        $nom    = clean($body['nom'] ?? '');
        $email  = filter_var(clean($body['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $cin    = clean($body['cin_matricule'] ?? '');
        $role   = requireEnum(clean($body['role'] ?? ''), USER_ROLES, 'Rôle');
        $statut = requireEnum(clean($body['statut'] ?? 'actif'), USER_STATUTS, 'Statut');

        if ($prenom === '' || $nom === '' || !$email || $cin === '') {
            jsonError('Prénom, nom, e-mail valide et identifiant sont obligatoires.');
        }
        if (mb_strlen($prenom) > 80 || mb_strlen($nom) > 80 || mb_strlen($email) > 150) jsonError('Un des champs est trop long.');
        if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $cin)) {
            jsonError('Identifiant invalide (3 à 30 caractères : lettres, chiffres, . _ -).');
        }

        $generated = false;
        $password  = (string) ($body['mot_de_passe'] ?? '');
        if ($password === '') {
            $password  = generatePassword();
            $generated = true;
        } else {
            validatePasswordStrength($password);
        }

        $db = getDB();
        $st = $db->prepare('SELECT COUNT(*) FROM utilisateurs WHERE email=? OR cin_matricule=?');
        $st->execute([$email, $cin]);
        if ((int) $st->fetchColumn() > 0) jsonError('E-mail ou identifiant déjà utilisé.', 409);

        // Données propres au rôle (validées AVANT d'écrire quoi que ce soit)
        $classe_id = $dept_id = null;
        if ($role === 'etudiant') {
            $classe_id = (int) ($body['classe_id'] ?? 0);
            $st = $db->prepare('SELECT 1 FROM classes WHERE id=?');
            $st->execute([$classe_id]);
            if (!$classe_id || !$st->fetchColumn()) jsonError('Une classe valide est requise pour un étudiant.');
        } elseif ($role === 'enseignant') {
            $dept_id = (int) ($body['departement_id'] ?? 0);
            $st = $db->prepare('SELECT 1 FROM departements WHERE id=?');
            $st->execute([$dept_id]);
            if (!$dept_id || !$st->fetchColumn()) jsonError('Un département valide est requis pour un enseignant.');
        }

        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO utilisateurs (prenom,nom,email,cin_matricule,role,mot_de_passe,statut) VALUES (?,?,?,?,?,?,?)')
               ->execute([$prenom, $nom, $email, $cin, $role, hashPassword($password), $statut]);
            $newId = (int) $db->lastInsertId();

            if ($role === 'etudiant') {
                $db->prepare('INSERT INTO etudiants (utilisateur_id,classe_id,statut) VALUES (?,?,?)')
                   ->execute([$newId, $classe_id, $statut === 'actif' ? 'actif' : 'suspendu']);
            } elseif ($role === 'enseignant') {
                $db->prepare('INSERT INTO enseignants (utilisateur_id,departement_id,grade) VALUES (?,?,?)')
                   ->execute([$newId, $dept_id, mb_substr(clean($body['grade'] ?? '') ?: 'Enseignant', 0, 80)]);
            } elseif ($role === 'scolarite') {
                $db->prepare('INSERT INTO scolarite_personnel (utilisateur_id,poste) VALUES (?,?)')
                   ->execute([$newId, mb_substr(clean($body['poste'] ?? '') ?: 'Agent de scolarité', 0, 100)]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        logActivity($admin['id'], 'create', 'utilisateurs', "Compte créé: $prenom $nom ($role)");
        $resp = ['id' => $newId, 'message' => 'Compte créé avec succès.'];
        if ($generated) {
            $resp['mot_de_passe_temporaire'] = $password;   // affiché UNE seule fois
            $resp['message'] .= ' Mot de passe temporaire : ' . $password;
        }
        jsonSuccess($resp, 201);

    case 'update_user':
        $admin = requireAuth('admin');
        $body  = getBody();
        $id    = (int) ($body['id'] ?? 0);
        if (!$id) jsonError('ID requis.');

        $db = getDB();
        $st = $db->prepare('SELECT role, statut FROM utilisateurs WHERE id=?');
        $st->execute([$id]);
        $target = $st->fetch();
        if (!$target) jsonError('Utilisateur introuvable.', 404);

        $fields = [];
        $vals   = [];
        if (!empty($body['prenom'])) { $fields[] = 'prenom=?'; $vals[] = mb_substr(clean($body['prenom']), 0, 80); }
        if (!empty($body['nom']))    { $fields[] = 'nom=?';    $vals[] = mb_substr(clean($body['nom']), 0, 80); }
        if (!empty($body['email'])) {
            $email = filter_var(clean($body['email']), FILTER_VALIDATE_EMAIL);
            if (!$email) jsonError('Adresse e-mail invalide.');
            $st = $db->prepare('SELECT 1 FROM utilisateurs WHERE email=? AND id<>?');
            $st->execute([$email, $id]);
            if ($st->fetchColumn()) jsonError('Cet e-mail est déjà utilisé.', 409);
            $fields[] = 'email=?';
            $vals[]   = $email;
        }
        $newStatut = null;
        if (!empty($body['statut'])) {
            $newStatut = requireEnum(clean($body['statut']), USER_STATUTS, 'Statut');
            if ($id === $admin['id'] && $newStatut !== 'actif') jsonError('Vous ne pouvez pas désactiver votre propre compte.');
            if ($target['role'] === 'admin' && $target['statut'] === 'actif' && $newStatut !== 'actif' && countActiveAdmins($db) <= 1) {
                jsonError('Impossible de désactiver le dernier administrateur actif.');
            }
            $fields[] = 'statut=?';
            $vals[]   = $newStatut;
        }
        if (!empty($body['mot_de_passe'])) {
            validatePasswordStrength((string) $body['mot_de_passe']);
            $fields[] = 'mot_de_passe=?';
            $vals[]   = hashPassword((string) $body['mot_de_passe']);
        }

        $db->beginTransaction();
        try {
            if ($fields) {
                $vals[] = $id;
                $db->prepare('UPDATE utilisateurs SET ' . implode(',', $fields) . ' WHERE id=?')->execute($vals);
            }
            if ($newStatut !== null && $target['role'] === 'etudiant') {
                syncStudentStatus($db, $id, $newStatut);
            }
            if (!empty($body['classe_id']) && $target['role'] === 'etudiant') {
                $db->prepare('UPDATE etudiants SET classe_id=? WHERE utilisateur_id=?')->execute([(int) $body['classe_id'], $id]);
            }
            if (!empty($body['grade']) && $target['role'] === 'enseignant') {
                $db->prepare('UPDATE enseignants SET grade=? WHERE utilisateur_id=?')->execute([mb_substr(clean($body['grade']), 0, 80), $id]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        logActivity($admin['id'], 'edit', 'utilisateurs', "Compte $id modifié");
        jsonSuccess(['message' => 'Utilisateur mis à jour.']);

    case 'delete_user':
        $admin = requireAuth('admin');
        $id    = (int) (getBody()['id'] ?? 0);
        if (!$id) jsonError('ID requis.');
        if ($id === $admin['id']) jsonError('Vous ne pouvez pas supprimer votre propre compte.');

        $db = getDB();
        $st = $db->prepare('SELECT prenom,nom,role,statut FROM utilisateurs WHERE id=?');
        $st->execute([$id]);
        $target = $st->fetch();
        if (!$target) jsonError('Utilisateur introuvable.', 404);
        if ($target['role'] === 'admin' && $target['statut'] === 'actif' && countActiveAdmins($db) <= 1) {
            jsonError('Impossible de supprimer le dernier administrateur actif.');
        }

        $db->prepare('DELETE FROM utilisateurs WHERE id=?')->execute([$id]);
        logActivity($admin['id'], 'delete', 'utilisateurs', "Compte supprimé: {$target['prenom']} {$target['nom']} ({$target['role']})");
        jsonSuccess(['message' => 'Compte supprimé.']);

    case 'toggle_suspend':
        $admin = requireAuth('admin');
        $id    = (int) (getBody()['id'] ?? 0);
        if (!$id) jsonError('ID requis.');
        if ($id === $admin['id']) jsonError('Impossible de se suspendre soi-même.');

        $db = getDB();
        $st = $db->prepare('SELECT role, statut FROM utilisateurs WHERE id=?');
        $st->execute([$id]);
        $cur = $st->fetch();
        if (!$cur) jsonError('Utilisateur introuvable.', 404);

        $new = $cur['statut'] === 'actif' ? 'suspendu' : 'actif';
        if ($cur['role'] === 'admin' && $new !== 'actif' && countActiveAdmins($db) <= 1) {
            jsonError('Impossible de suspendre le dernier administrateur actif.');
        }
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE utilisateurs SET statut=? WHERE id=?')->execute([$new, $id]);
            if ($cur['role'] === 'etudiant') syncStudentStatus($db, $id, $new);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        logActivity($admin['id'], 'edit', 'utilisateurs', "Compte $id → $new");
        jsonSuccess(['message' => "Compte $new.", 'statut' => $new]);

    case 'reset_password':
        $admin = requireAuth('admin');
        $body  = getBody();
        $id    = (int) ($body['id'] ?? 0);
        $pass  = (string) ($body['new_password'] ?? '');
        if (!$id) jsonError('ID requis.');

        $generated = false;
        if ($pass === '') {
            $pass      = generatePassword();
            $generated = true;
        } else {
            validatePasswordStrength($pass);
        }
        $st = getDB()->prepare('UPDATE utilisateurs SET mot_de_passe=? WHERE id=?');
        $st->execute([hashPassword($pass), $id]);
        $chk = getDB()->prepare('SELECT 1 FROM utilisateurs WHERE id=?');
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) jsonError('Utilisateur introuvable.', 404);

        logActivity($admin['id'], 'edit', 'utilisateurs', "Mot de passe réinitialisé pour #$id");
        $resp = ['message' => 'Mot de passe réinitialisé.'];
        if ($generated) $resp['mot_de_passe_temporaire'] = $pass;
        jsonSuccess($resp);

    /* ───────── Classes / référentiels ───────── */
    case 'get_classes':
        requireAuth('admin');
        $st = getDB()->prepare('SELECT c.*, f.nom AS filiere_nom FROM classes c JOIN filieres f ON f.id=c.filiere_id ORDER BY c.nom');
        $st->execute();
        jsonSuccess(['classes' => $st->fetchAll()]);

    case 'create_classe':
        $admin = requireAuth('admin');
        $body  = getBody();
        $nom   = clean($body['nom'] ?? '');
        $fid   = (int) ($body['filiere_id'] ?? 0);
        $annee = (int) ($body['annee'] ?? 1);
        if ($nom === '' || !$fid)   jsonError('Nom et filière requis.');
        if (mb_strlen($nom) > 80)   jsonError('Nom trop long (80 caractères max).');
        if ($annee < 1 || $annee > 5) jsonError('Année invalide (1 à 5).');
        $db = getDB();
        $db->prepare('INSERT INTO classes (nom,filiere_id,annee) VALUES (?,?,?)')->execute([$nom, $fid, $annee]);
        $insertedId = (int) $db->lastInsertId();   // AVANT logActivity (qui fait lui-même un INSERT)
        logActivity($admin['id'], 'create', 'classes', "Classe: $nom");
        jsonSuccess(['id' => $insertedId], 201);

    case 'delete_classe':
        $admin = requireAuth('admin');
        $id    = (int) (getBody()['id'] ?? 0);
        if (!$id) jsonError('ID requis.');
        $db = getDB();
        $st = $db->prepare('SELECT COUNT(*) FROM etudiants WHERE classe_id=?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) jsonError('Suppression impossible : des étudiants sont inscrits dans cette classe.', 409);
        $st = $db->prepare('DELETE FROM classes WHERE id=?');
        $st->execute([$id]);
        if ($st->rowCount() === 0) jsonError('Classe introuvable.', 404);
        logActivity($admin['id'], 'delete', 'classes', "Classe $id supprimée");
        jsonSuccess(['message' => 'Classe supprimée.']);

    case 'get_filieres':
        requireAuth('admin');
        jsonSuccess(['filieres' => getDB()->query('SELECT * FROM filieres ORDER BY nom')->fetchAll()]);

    case 'get_departements':
        requireAuth('admin');
        jsonSuccess(['departements' => getDB()->query('SELECT * FROM departements ORDER BY nom')->fetchAll()]);

    /* ───────── Statistiques & journaux ───────── */
    case 'get_stats':
        requireAuth('admin');
        $db = getDB();
        $users_by_role = $db->query('SELECT role, COUNT(*) AS total FROM utilisateurs GROUP BY role')->fetchAll();
        $notes_stats   = $db->query('SELECT statut, COUNT(*) AS total FROM notes GROUP BY statut')->fetchAll();
        $abs_month     = $db->query('SELECT COUNT(*) FROM absences WHERE MONTH(date_absence)=MONTH(NOW()) AND YEAR(date_absence)=YEAR(NOW())')->fetchColumn();

        // Activité RÉELLE par module sur les 30 derniers jours (journal d'activité)
        $mods  = $db->query('SELECT module, COUNT(*) AS total FROM activity_logs
                             WHERE created_at > (NOW() - INTERVAL 30 DAY) AND module <> "auth"
                             GROUP BY module ORDER BY total DESC LIMIT 8')->fetchAll();
        $max   = $mods ? max(array_map(fn($m) => (int) $m['total'], $mods)) : 0;
        $modules_usage = array_map(fn($m) => [
            'module' => $m['module'],
            'total'  => (int) $m['total'],
            'pct'    => $max ? (int) round(100 * $m['total'] / $max) : 0,
        ], $mods);

        jsonSuccess(compact('users_by_role', 'modules_usage', 'notes_stats', 'abs_month'));

    case 'get_logs':
        requireAuth('admin');
        $limit  = max(1, min((int) ($_GET['limit'] ?? 50), 200));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        $where  = '1=1';
        $params = [];
        if (!empty($_GET['action'])) { $where .= ' AND l.action=?'; $params[] = (string) $_GET['action']; }
        if (!empty($_GET['module'])) { $where .= ' AND l.module=?'; $params[] = (string) $_GET['module']; }
        if (!empty($_GET['date']))   { $where .= ' AND DATE(l.created_at)=?'; $params[] = requireDate((string) $_GET['date']); }

        $st = getDB()->prepare("SELECT l.*, CONCAT(u.prenom,' ',u.nom) AS user_nom
                                FROM activity_logs l LEFT JOIN utilisateurs u ON u.id=l.user_id
                                WHERE $where ORDER BY l.created_at DESC, l.id DESC
                                LIMIT $limit OFFSET $offset");
        $st->execute($params);
        jsonSuccess(['logs' => $st->fetchAll()]);

    /* ───────── Notifications ───────── */
    case 'get_notifications':
        requireAuth('admin');
        $st = getDB()->prepare('SELECT n.*, CONCAT(u.prenom," ",u.nom) AS auteur_nom
                                FROM notifications n JOIN utilisateurs u ON u.id=n.auteur_id
                                ORDER BY n.envoye_le DESC LIMIT 20');
        $st->execute();
        jsonSuccess(['notifications' => $st->fetchAll()]);

    case 'send_notification':
        $admin = requireAuth('admin');
        $body  = getBody();
        $titre = clean($body['titre'] ?? '');
        $msg   = clean($body['message'] ?? '');
        $type  = requireEnum(clean($body['type'] ?? 'info'), ['alerte', 'info', 'annonce', 'urgence'], 'Type');
        $dest  = requireEnum(clean($body['destinataires'] ?? 'tous'), ['tous', 'etudiants', 'enseignants', 'scolarite'], 'Destinataires');
        if ($titre === '' || $msg === '') jsonError('Titre et message requis.');
        if (mb_strlen($titre) > 200)      jsonError('Titre trop long (200 caractères max).');

        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO notifications (titre,message,type,destinataires,auteur_id) VALUES (?,?,?,?,?)')
               ->execute([$titre, $msg, $type, $dest, $admin['id']]);
            // Publie aussi une annonce visible dans les portails
            $db->prepare('INSERT INTO annonces (titre,contenu,type,destinataires,auteur_id,statut,date_publication) VALUES (?,?,?,?,?,"publie",NOW())')
               ->execute([$titre, $msg, 'alerte', $dest, $admin['id']]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        logActivity($admin['id'], 'create', 'notifications', "Notification: $titre → $dest");
        jsonSuccess(['message' => 'Notification envoyée.'], 201);

    /* ───────── Réclamations ───────── */
    case 'get_reclamations':
        requireAuth('admin');
        $st = getDB()->prepare('SELECT r.*, CONCAT(u.prenom," ",u.nom) AS auteur_nom, u.role AS auteur_role
                                FROM reclamations r JOIN utilisateurs u ON u.id=r.auteur_id
                                ORDER BY r.created_at DESC LIMIT 500');
        $st->execute();
        jsonSuccess(['reclamations' => $st->fetchAll()]);

    case 'update_reclamation':
        $admin  = requireAuth('admin');
        $body   = getBody();
        $id     = (int) ($body['id'] ?? 0);
        $statut = requireEnum(clean($body['statut'] ?? ''), ['en_attente', 'en_cours', 'traitee', 'rejetee'], 'Statut');
        $rep    = mb_substr(clean($body['reponse'] ?? ''), 0, 2000);
        if (!$id) jsonError('ID requis.');
        $db = getDB();
        $chk = $db->prepare('SELECT 1 FROM reclamations WHERE id=?');
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) jsonError('Réclamation introuvable.', 404);
        $db->prepare('UPDATE reclamations SET statut=?, reponse=? WHERE id=?')->execute([$statut, $rep !== '' ? $rep : null, $id]);
        logActivity($admin['id'], 'edit', 'reclamations', "Réclamation $id → $statut");
        jsonSuccess(['message' => 'Réclamation mise à jour.']);

    default:
        jsonError('Action inconnue.', 404);
}
