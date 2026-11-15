<?php
/**
 * api/enseignant.php — API du portail Enseignant
 *
 * Règle d'or : un enseignant n'agit QUE sur les classes / matières de
 * son propre emploi du temps (contrôle d'appartenance sur chaque action).
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

/** L'enseignant donne-t-il cette matière à cette classe ? */
function teacherTeaches(int $eid, int $classeId, int $matiereId): bool
{
    $st = getDB()->prepare('SELECT 1 FROM emploi_temps WHERE enseignant_id=? AND classe_id=? AND matiere_id=? LIMIT 1');
    $st->execute([$eid, $classeId, $matiereId]);
    return (bool) $st->fetchColumn();
}

function teacherHasClass(int $eid, int $classeId): bool
{
    $st = getDB()->prepare('SELECT 1 FROM emploi_temps WHERE enseignant_id=? AND classe_id=? LIMIT 1');
    $st->execute([$eid, $classeId]);
    return (bool) $st->fetchColumn();
}

function teacherHasMatiere(int $eid, int $matiereId): bool
{
    $st = getDB()->prepare('SELECT 1 FROM emploi_temps WHERE enseignant_id=? AND matiere_id=? LIMIT 1');
    $st->execute([$eid, $matiereId]);
    return (bool) $st->fetchColumn();
}

$action = getAction();

switch ($action) {

    /* ───────── Authentification ───────── */
    case 'login':
        $body = getBody();
        $u    = authenticate('enseignant', clean($body['matricule'] ?? ''), (string) ($body['password'] ?? ''));

        $st = getDB()->prepare(
            'SELECT ens.id AS enseignant_id, ens.grade, ens.specialite, d.nom AS dept_nom
             FROM enseignants ens JOIN departements d ON d.id=ens.departement_id
             WHERE ens.utilisateur_id=?'
        );
        $st->execute([$u['id']]);
        $p = $st->fetch();
        if (!$p) {
            jsonError('Profil enseignant incomplet. Contactez l\'administration.', 403);
        }

        $csrf = startUserSession([
            'id'            => (int) $u['id'],
            'enseignant_id' => (int) $p['enseignant_id'],
            'prenom'        => $u['prenom'],
            'nom'           => $u['nom'],
            'email'         => $u['email'],
            'matricule'     => $u['cin_matricule'],
            'grade'         => $p['grade'],
            'dept_nom'      => $p['dept_nom'],
            'role'          => 'enseignant',
        ]);
        logActivity((int) $u['id'], 'login', 'auth', 'Connexion enseignant');
        jsonSuccess(['user' => $_SESSION['user'], 'csrf_token' => $csrf]);

    case 'logout':
        doLogout('enseignant');

    case 'change_password':
        changeOwnPassword(requireAuth('enseignant'), getBody());

    /* ───────── Tableau de bord ───────── */
    case 'get_dashboard':
        $user = requireAuth('enseignant');
        $db   = getDB();
        $eid  = $user['enseignant_id'];

        $st = $db->prepare('SELECT COUNT(*) FROM emploi_temps WHERE enseignant_id=? AND semestre=?');
        $st->execute([$eid, CURRENT_SEMESTER]);
        $seances = $st->fetchColumn();

        $st = $db->prepare('SELECT COUNT(DISTINCT e.id) FROM etudiants e
                            JOIN emploi_temps et ON et.classe_id=e.classe_id
                            WHERE et.enseignant_id=? AND e.statut="actif"');
        $st->execute([$eid]);
        $etudiants = $st->fetchColumn();

        $st = $db->prepare('SELECT COUNT(*) FROM absences WHERE enseignant_id=?');
        $st->execute([$eid]);
        $absences = $st->fetchColumn();

        $st = $db->prepare('SELECT COUNT(*) FROM cours_supports WHERE enseignant_id=?');
        $st->execute([$eid]);
        $cours = $st->fetchColumn();

        jsonSuccess(['dashboard' => [
            'seances_semaine'  => $seances,
            'total_etudiants'  => $etudiants,
            'absences_saisies' => $absences,
            'cours_uploades'   => $cours,
        ]]);

    /* ───────── Emploi du temps ───────── */
    case 'get_emploi':
        $user = requireAuth('enseignant');
        $st = getDB()->prepare(
            'SELECT et.*, m.nom AS matiere_nom, c.nom AS classe_nom
             FROM emploi_temps et
             JOIN matieres m ON m.id=et.matiere_id
             JOIN classes c  ON c.id=et.classe_id
             WHERE et.enseignant_id=?
             ORDER BY FIELD(et.jour,"Lundi","Mardi","Mercredi","Jeudi","Vendredi","Samedi"), et.heure_debut'
        );
        $st->execute([$user['enseignant_id']]);
        jsonSuccess(['emploi' => $st->fetchAll()]);

    case 'get_seances':
        $user = requireAuth('enseignant');
        $st = getDB()->prepare(
            'SELECT et.id, et.classe_id, et.matiere_id, et.jour, et.heure_debut,
                    c.nom AS classe_nom, m.nom AS matiere_nom
             FROM emploi_temps et
             JOIN classes c  ON c.id=et.classe_id
             JOIN matieres m ON m.id=et.matiere_id
             WHERE et.enseignant_id=?
             ORDER BY FIELD(et.jour,"Lundi","Mardi","Mercredi","Jeudi","Vendredi","Samedi"), et.heure_debut'
        );
        $st->execute([$user['enseignant_id']]);
        jsonSuccess(['seances' => $st->fetchAll()]);

    case 'get_classes_matieres':
        $user = requireAuth('enseignant');
        $st = getDB()->prepare(
            'SELECT DISTINCT et.classe_id, et.matiere_id, c.nom AS classe_nom, m.nom AS matiere_nom
             FROM emploi_temps et
             JOIN classes c  ON c.id=et.classe_id
             JOIN matieres m ON m.id=et.matiere_id
             WHERE et.enseignant_id=?
             ORDER BY c.nom, m.nom'
        );
        $st->execute([$user['enseignant_id']]);
        jsonSuccess(['classes_matieres' => $st->fetchAll()]);

    /* ───────── Appel / absences ───────── */
    case 'get_liste_classe':
        $user      = requireAuth('enseignant');
        $classe_id = (int) (getBody()['classe_id'] ?? 0);
        if (!$classe_id) jsonError('classe_id requis.');
        if (!teacherHasClass($user['enseignant_id'], $classe_id)) {
            jsonError('Vous n\'enseignez pas dans cette classe.', 403);
        }
        $st = getDB()->prepare('SELECT u.prenom,u.nom,u.cin_matricule,e.id AS etudiant_id
                                FROM etudiants e JOIN utilisateurs u ON u.id=e.utilisateur_id
                                WHERE e.classe_id=? AND e.statut="actif"
                                ORDER BY u.nom,u.prenom');
        $st->execute([$classe_id]);
        jsonSuccess(['etudiants' => $st->fetchAll()]);

    case 'save_appel':
        $user       = requireAuth('enseignant');
        $body       = getBody();
        $classe_id  = (int) ($body['classe_id'] ?? 0);
        $matiere_id = (int) ($body['matiere_id'] ?? 0);
        $date       = requireDate(clean($body['date'] ?? date('Y-m-d')), 'Date de l\'appel');
        $presences  = $body['presences'] ?? [];
        $duree      = isset($body['duree_heures']) ? (float) $body['duree_heures'] : 1.5;

        if (!$classe_id || !$matiere_id || !is_array($presences) || !$presences) {
            jsonError('Données d\'appel incomplètes.');
        }
        if ($duree <= 0 || $duree > 8) jsonError('Durée invalide (0 à 8 heures).');
        // tolérance d'un jour (décalage horaire UTC côté navigateur)
        if (strtotime($date) > strtotime('+1 day')) jsonError('La date de l\'appel ne peut pas être dans le futur.');

        $eid = $user['enseignant_id'];
        if (!teacherTeaches($eid, $classe_id, $matiere_id)) {
            jsonError('Vous n\'enseignez pas cette matière à cette classe.', 403);
        }

        $db = getDB();
        $st = $db->prepare('SELECT id FROM etudiants WHERE classe_id=? AND statut="actif"');
        $st->execute([$classe_id]);
        $classIds = array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));

        // Validation complète AVANT toute écriture
        $absents = [];
        foreach ($presences as $p) {
            $sid = (int) ($p['etudiant_id'] ?? 0);
            if (!isset($classIds[$sid])) {
                jsonError("L'étudiant $sid n'appartient pas à cette classe.", 403);
            }
            if (array_key_exists('present', $p) && !$p['present']) {
                $absents[$sid] = mb_substr(clean($p['observation'] ?? ''), 0, 500);
            }
        }

        $db->beginTransaction();
        try {
            // Conserver les justifications déjà accordées par la scolarité
            $st = $db->prepare('SELECT a.etudiant_id, a.justifie FROM absences a
                                JOIN etudiants e ON e.id=a.etudiant_id
                                WHERE a.matiere_id=? AND a.enseignant_id=? AND a.date_absence=? AND e.classe_id=?');
            $st->execute([$matiere_id, $eid, $date, $classe_id]);
            $justified = [];
            foreach ($st->fetchAll() as $r) {
                $justified[(int) $r['etudiant_id']] = (int) $r['justifie'];
            }

            // Remplace l'appel du même jour, pour CETTE classe uniquement
            $db->prepare('DELETE a FROM absences a JOIN etudiants e ON e.id=a.etudiant_id
                          WHERE a.matiere_id=? AND a.enseignant_id=? AND a.date_absence=? AND e.classe_id=?')
               ->execute([$matiere_id, $eid, $date, $classe_id]);

            $ins = $db->prepare('INSERT INTO absences (etudiant_id,matiere_id,enseignant_id,date_absence,duree_heures,justifie,observation)
                                 VALUES (?,?,?,?,?,?,?)');
            foreach ($absents as $sid => $obs) {
                $ins->execute([$sid, $matiere_id, $eid, $date, $duree, $justified[$sid] ?? 0, $obs !== '' ? $obs : null]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $count = count($absents);

        logActivity($user['id'], 'create', 'absences', "Appel classe $classe_id matière $matiere_id le $date ($count absent(s))");
        jsonSuccess(['message' => 'Appel enregistré.', 'absents' => $count]);

    /* ───────── Notes ───────── */
    case 'get_notes_classe':
        $user       = requireAuth('enseignant');
        $body       = getBody();
        $classe_id  = (int) ($body['classe_id'] ?? 0);
        $matiere_id = (int) ($body['matiere_id'] ?? 0);
        if (!$classe_id || !$matiere_id) jsonError('classe_id et matiere_id requis.');
        if (!teacherTeaches($user['enseignant_id'], $classe_id, $matiere_id)) {
            jsonError('Vous n\'enseignez pas cette matière à cette classe.', 403);
        }
        // La jointure est limitée au semestre/année courants (évite les doublons)
        $st = getDB()->prepare(
            'SELECT u.prenom, u.nom, e.id AS etudiant_id,
                    n.id AS note_id, n.cc, n.ds, n.examen, n.moyenne, n.statut
             FROM etudiants e
             JOIN utilisateurs u ON u.id=e.utilisateur_id
             LEFT JOIN notes n   ON n.etudiant_id=e.id AND n.matiere_id=? AND n.semestre=? AND n.annee_univ=?
             WHERE e.classe_id=? AND e.statut="actif"
             ORDER BY u.nom, u.prenom'
        );
        $st->execute([$matiere_id, CURRENT_SEMESTER, ACADEMIC_YEAR, $classe_id]);
        jsonSuccess(['notes' => $st->fetchAll()]);

    case 'save_notes':
        $user  = requireAuth('enseignant');
        $notes = getBody()['notes'] ?? [];
        if (!is_array($notes) || !$notes) jsonError('Aucune note fournie.');
        if (count($notes) > 500)          jsonError('Trop de lignes envoyées (500 max).');

        $db  = getDB();
        $eid = $user['enseignant_id'];

        // Couples (étudiant, matière) que CET enseignant a le droit de noter
        $st = $db->prepare('SELECT DISTINCT e.id AS eid, et.matiere_id AS mid
                            FROM etudiants e JOIN emploi_temps et ON et.classe_id=e.classe_id
                            WHERE et.enseignant_id=? AND e.statut="actif"');
        $st->execute([$eid]);
        $allowed = [];
        foreach ($st->fetchAll() as $r) {
            $allowed[$r['eid'] . ':' . $r['mid']] = true;
        }

        // Validation complète AVANT toute écriture
        $clean = [];
        foreach ($notes as $i => $n) {
            $sid = (int) ($n['etudiant_id'] ?? 0);
            $mid = (int) ($n['matiere_id'] ?? 0);
            if (!isset($allowed["$sid:$mid"])) {
                jsonError('Vous n\'êtes pas autorisé à noter cet étudiant pour cette matière (ligne ' . ($i + 1) . ').', 403);
            }
            $clean[] = [
                $sid, $mid,
                gradeValue($n['cc'] ?? null, 'La note de CC'),
                gradeValue($n['ds'] ?? null, 'La note de DS'),
                gradeValue($n['examen'] ?? null, 'La note d\'examen'),
            ];
        }

        $db->beginTransaction();
        try {
            $upsert = $db->prepare('INSERT INTO notes (etudiant_id,matiere_id,cc,ds,examen,semestre,annee_univ) VALUES (?,?,?,?,?,?,?)
                                    ON DUPLICATE KEY UPDATE cc=VALUES(cc), ds=VALUES(ds), examen=VALUES(examen)');
            // Ne touche pas aux statuts décidés manuellement (rattrapage)
            $stat = $db->prepare('UPDATE notes SET statut = CASE
                                      WHEN moyenne IS NULL THEN "en_attente"
                                      WHEN moyenne >= 10   THEN "validee"
                                      ELSE "insuffisant" END
                                  WHERE etudiant_id=? AND matiere_id=? AND semestre=? AND annee_univ=?');
            foreach ($clean as [$sid, $mid, $cc, $ds, $ex]) {
                $upsert->execute([$sid, $mid, $cc, $ds, $ex, CURRENT_SEMESTER, ACADEMIC_YEAR]);
                $stat->execute([$sid, $mid, CURRENT_SEMESTER, ACADEMIC_YEAR]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        logActivity($user['id'], 'edit', 'notes', 'Notes enregistrées (lot de ' . count($clean) . ')');
        jsonSuccess(['message' => 'Notes sauvegardées.']);

    /* ───────── Supports de cours ───────── */
    case 'get_cours':
        $user = requireAuth('enseignant');
        $st = getDB()->prepare('SELECT cs.id, cs.titre, cs.type_fichier, cs.taille_kb, cs.date_upload,
                                       cs.matiere_id, m.nom AS matiere_nom, 1 AS disponible
                                FROM cours_supports cs JOIN matieres m ON m.id=cs.matiere_id
                                WHERE cs.enseignant_id=? ORDER BY cs.date_upload DESC');
        $st->execute([$user['enseignant_id']]);
        jsonSuccess(['cours' => $st->fetchAll()]);

    case 'download_support':
        $user = requireAuth('enseignant');
        $st = getDB()->prepare('SELECT fichier_url, titre FROM cours_supports WHERE id=? AND enseignant_id=?');
        $st->execute([(int) ($_GET['id'] ?? 0), $user['enseignant_id']]);
        $row = $st->fetch();
        if (!$row) jsonError('Support introuvable.', 404);
        $ext = strtolower(pathinfo($row['fichier_url'], PATHINFO_EXTENSION));
        streamUpload($row['fichier_url'], $row['titre'] . '.' . $ext, 'application/octet-stream');

    case 'delete_support':
        $user = requireAuth('enseignant');
        $id   = (int) (getBody()['id'] ?? 0);
        if (!$id) jsonError('ID requis.');

        $db = getDB();
        $st = $db->prepare('SELECT fichier_url FROM cours_supports WHERE id=? AND enseignant_id=?');
        $st->execute([$id, $user['enseignant_id']]);
        $row = $st->fetch();
        if (!$row) jsonError('Support introuvable.', 404);

        $base = realpath(UPLOAD_DIR);
        $full = realpath(ROOT_DIR . '/' . $row['fichier_url']);
        if ($base !== false && $full !== false && str_starts_with($full, $base . DIRECTORY_SEPARATOR) && is_file($full)) {
            @unlink($full);
        }
        $db->prepare('DELETE FROM cours_supports WHERE id=?')->execute([$id]);
        logActivity($user['id'], 'delete', 'cours_supports', "Support $id supprimé");
        jsonSuccess(['message' => 'Support supprimé.']);

    case 'upload_support':
        $user       = requireAuth('enseignant');
        $matiere_id = (int) ($_POST['matiere_id'] ?? 0);
        $titre      = clean($_POST['titre'] ?? '');
        $type       = clean($_POST['type_fichier'] ?? 'PDF');

        if (!$matiere_id || $titre === '') jsonError('matiere_id et titre requis.');
        if (mb_strlen($titre) > 200)       jsonError('Titre trop long (200 caractères max).');
        requireEnum($type, ['PDF', 'PPT', 'TD', 'TP', 'Video', 'Autre'], 'Type de fichier');
        if (!teacherHasMatiere($user['enseignant_id'], $matiere_id)) {
            jsonError('Vous n\'enseignez pas cette matière.', 403);
        }
        if (empty($_FILES['fichier'])) jsonError('Aucun fichier reçu.');

        $file = $_FILES['fichier'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            jsonError($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
                ? 'Fichier trop volumineux.' : 'Erreur lors de l\'envoi du fichier.');
        }
        if ($file['size'] > MAX_FILE_SIZE) jsonError('Fichier trop grand (max 10 Mo).');
        if (!is_uploaded_file($file['tmp_name'])) jsonError('Envoi invalide.');

        // 1) extension autorisée  2) contenu RÉEL cohérent avec l'extension (finfo, pas le type envoyé par le navigateur)
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = [
            'pdf'  => ['application/pdf'],
            'ppt'  => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'zip'  => ['application/zip'],
            'txt'  => ['text/plain'],
        ];
        if (!isset($allowed[$ext])) {
            jsonError('Extension non autorisée (pdf, ppt, pptx, doc, docx, zip, txt).');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $real  = $finfo->file($file['tmp_name']);
        if (!in_array($real, $allowed[$ext], true)) {
            jsonError('Le contenu du fichier ne correspond pas à son extension.');
        }

        $dir = UPLOAD_DIR . '/cours';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException('Impossible de créer le dossier uploads/cours');
        }
        $filename = 'cours_' . bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            jsonError('Échec de sauvegarde du fichier.', 500);
        }

        $db = getDB();
        $db->prepare('INSERT INTO cours_supports (titre,matiere_id,enseignant_id,type_fichier,fichier_url,taille_kb) VALUES (?,?,?,?,?,?)')
           ->execute([$titre, $matiere_id, $user['enseignant_id'], $type, 'uploads/cours/' . $filename, (int) ceil($file['size'] / 1024)]);
        $insertedId = (int) $db->lastInsertId();   // AVANT logActivity (qui fait lui-même un INSERT)
        logActivity($user['id'], 'create', 'cours_supports', "Upload: $titre");
        jsonSuccess(['id' => $insertedId], 201);

    /* ───────── Messages ───────── */
    case 'get_messages':
        $user = requireAuth('enseignant');
        $st = getDB()->prepare('SELECT m.*, CONCAT(u.prenom," ",u.nom) AS expediteur_nom
                                FROM messages m JOIN utilisateurs u ON u.id=m.expediteur_id
                                WHERE m.expediteur_id=? ORDER BY m.date_envoi DESC');
        $st->execute([$user['id']]);
        jsonSuccess(['messages' => $st->fetchAll()]);

    case 'send_message':
        $user    = requireAuth('enseignant');
        $body    = getBody();
        $objet   = clean($body['objet'] ?? '');
        $contenu = clean($body['contenu'] ?? '');
        $dest    = clean($body['destinataire_role'] ?? '');
        $cid     = !empty($body['classe_id']) ? (int) $body['classe_id'] : null;

        if ($objet === '' || $contenu === '' || $dest === '') jsonError('Objet, contenu et destinataire requis.');
        requireEnum($dest, ['etudiants', 'enseignants', 'scolarite', 'admin', 'tous'], 'Destinataire');
        if (mb_strlen($objet) > 200)    jsonError('Objet trop long (200 caractères max).');
        if (mb_strlen($contenu) > 5000) jsonError('Message trop long (5000 caractères max).');
        if ($cid !== null && !teacherHasClass($user['enseignant_id'], $cid)) {
            jsonError('Vous n\'enseignez pas dans cette classe.', 403);
        }

        $db = getDB();
        $db->prepare('INSERT INTO messages (expediteur_id,destinataire_role,classe_id,objet,contenu) VALUES (?,?,?,?,?)')
           ->execute([$user['id'], $dest, $cid, $objet, $contenu]);
        $insertedId = (int) $db->lastInsertId();   // AVANT logActivity (qui fait lui-même un INSERT)
        logActivity($user['id'], 'create', 'messages', "Message: $objet");
        jsonSuccess(['id' => $insertedId], 201);

    default:
        jsonError('Action inconnue.', 404);
}
