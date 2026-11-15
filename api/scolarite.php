<?php
/**
 * api/scolarite.php — API du portail Scolarité
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/documents.php';

const ANNONCE_TYPES = ['annonce', 'alerte', 'info', 'calendrier', 'examen'];
const ANNONCE_DEST  = ['tous', 'etudiants', 'enseignants', 'scolarite', 'admin'];
const JOURS         = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

$action = getAction();

switch ($action) {

    /* ───────── Authentification ───────── */
    case 'login':
        $body = getBody();
        $u    = authenticate('scolarite', clean($body['matricule'] ?? ''), (string) ($body['password'] ?? ''));

        $st = getDB()->prepare('SELECT poste FROM scolarite_personnel WHERE utilisateur_id=?');
        $st->execute([$u['id']]);
        $poste = $st->fetchColumn();
        if ($poste === false) {
            jsonError('Profil scolarité incomplet. Contactez l\'administration.', 403);
        }

        $csrf = startUserSession([
            'id'     => (int) $u['id'],
            'prenom' => $u['prenom'],
            'nom'    => $u['nom'],
            'email'  => $u['email'],
            'poste'  => $poste,
            'role'   => 'scolarite',
        ]);
        logActivity((int) $u['id'], 'login', 'auth', 'Connexion scolarité');
        jsonSuccess(['user' => $_SESSION['user'], 'csrf_token' => $csrf]);

    case 'logout':
        doLogout('scolarité');

    case 'change_password':
        changeOwnPassword(requireAuth('scolarite'), getBody());

    /* ───────── Tableau de bord ───────── */
    case 'get_dashboard':
        requireAuth('scolarite');
        $db = getDB();
        $total_etudiants   = $db->query('SELECT COUNT(*) FROM etudiants WHERE statut="actif"')->fetchColumn();
        $total_enseignants = $db->query('SELECT COUNT(*) FROM enseignants')->fetchColumn();
        $absences_today    = $db->query('SELECT COUNT(*) FROM absences WHERE date_absence=CURDATE()')->fetchColumn();
        $annonces_publiees = $db->query('SELECT COUNT(*) FROM annonces WHERE statut="publie"')->fetchColumn();
        $docs_attente      = $db->query('SELECT COUNT(*) FROM demandes_documents WHERE statut="en_attente"')->fetchColumn();
        $notes_attente     = $db->query('SELECT COUNT(*) FROM notes WHERE statut="en_attente"')->fetchColumn();
        jsonSuccess(['dashboard' => compact('total_etudiants', 'total_enseignants', 'absences_today', 'annonces_publiees', 'docs_attente', 'notes_attente')]);

    /* ───────── Étudiants ───────── */
    case 'get_etudiants':
        requireAuth('scolarite');
        $st = getDB()->prepare(
            'SELECT u.id, u.prenom, u.nom, u.email, u.cin_matricule, u.statut, u.photo_url,
                    e.id AS etudiant_id, e.statut AS etudiant_statut,
                    c.nom AS classe_nom, f.nom AS filiere_nom
             FROM utilisateurs u
             JOIN etudiants e ON e.utilisateur_id=u.id
             JOIN classes c   ON c.id=e.classe_id
             JOIN filieres f  ON f.id=c.filiere_id
             WHERE u.role="etudiant"
             ORDER BY u.nom, u.prenom
             LIMIT 2000'
        );
        $st->execute();
        jsonSuccess(['etudiants' => $st->fetchAll()]);

    case 'get_fiche_etudiant':
        requireAuth('scolarite');
        $id = (int) (getBody()['id'] ?? 0);
        if (!$id) jsonError('ID requis.');

        $db = getDB();
        $st = $db->prepare(
            'SELECT u.id, u.prenom, u.nom, u.email, u.cin_matricule, u.photo_url, u.statut,
                    e.id AS etudiant_id, e.classe_id, e.date_naissance, e.adresse, e.telephone,
                    e.statut AS etudiant_statut, c.nom AS classe_nom, f.nom AS filiere_nom
             FROM utilisateurs u
             JOIN etudiants e ON e.utilisateur_id=u.id
             JOIN classes c   ON c.id=e.classe_id
             JOIN filieres f  ON f.id=c.filiere_id
             WHERE u.id=?'
        );
        $st->execute([$id]);
        $etudiant = $st->fetch();
        if (!$etudiant) jsonError('Étudiant introuvable.', 404);

        $st = $db->prepare('SELECT ' . SQL_WEIGHTED_AVG . ' FROM notes n JOIN matieres m ON m.id=n.matiere_id WHERE n.etudiant_id=?');
        $st->execute([$etudiant['etudiant_id']]);
        $etudiant['moyenne_generale'] = $st->fetchColumn();

        $st = $db->prepare('SELECT COALESCE(SUM(duree_heures),0) FROM absences WHERE etudiant_id=?');
        $st->execute([$etudiant['etudiant_id']]);
        $etudiant['total_absences'] = $st->fetchColumn();

        jsonSuccess(['etudiant' => $etudiant]);

    case 'update_etudiant':
        $user   = requireAuth('scolarite');
        $body   = getBody();
        $id     = (int) ($body['id'] ?? 0);
        $statut = requireEnum(clean($body['statut'] ?? ''), ['actif', 'suspendu', 'diplome', 'exclu'], 'Statut');
        if (!$id) jsonError('ID requis.');

        $db = getDB();
        $st = $db->prepare('SELECT 1 FROM etudiants WHERE utilisateur_id=?');
        $st->execute([$id]);
        if (!$st->fetchColumn()) jsonError('Étudiant introuvable.', 404);

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE etudiants SET statut=? WHERE utilisateur_id=?')->execute([$statut, $id]);
            $db->prepare('UPDATE utilisateurs SET statut=? WHERE id=? AND role="etudiant"')
               ->execute([$statut === 'actif' ? 'actif' : 'suspendu', $id]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        logActivity($user['id'], 'edit', 'etudiants', "Statut étudiant $id → $statut");
        jsonSuccess(['message' => 'Étudiant mis à jour.']);

    case 'get_classes':
        requireAuth('scolarite');
        $st = getDB()->prepare('SELECT c.*, f.nom AS filiere_nom FROM classes c JOIN filieres f ON f.id=c.filiere_id ORDER BY c.nom');
        $st->execute();
        jsonSuccess(['classes' => $st->fetchAll()]);

    /* ───────── Emploi du temps ───────── */
    case 'get_emploi':
        requireAuth('scolarite');
        $classe_id = (int) (getBody()['classe_id'] ?? 0);
        if (!$classe_id) jsonError('classe_id requis.');
        $st = getDB()->prepare(
            'SELECT et.*, m.nom AS matiere_nom, CONCAT(u.prenom," ",u.nom) AS enseignant
             FROM emploi_temps et
             JOIN matieres m      ON m.id=et.matiere_id
             JOIN enseignants ens ON ens.id=et.enseignant_id
             JOIN utilisateurs u  ON u.id=ens.utilisateur_id
             WHERE et.classe_id=?
             ORDER BY FIELD(et.jour,"Lundi","Mardi","Mercredi","Jeudi","Vendredi","Samedi"), et.heure_debut'
        );
        $st->execute([$classe_id]);
        jsonSuccess(['emploi' => $st->fetchAll()]);

    case 'add_creneau':
        $user = requireAuth('scolarite');
        $body = getBody();
        foreach (['classe_id', 'matiere_id', 'enseignant_id', 'jour', 'heure_debut', 'heure_fin', 'salle', 'type_seance'] as $f) {
            if (empty($body[$f])) jsonError("Champ $f requis.");
        }
        $classe  = (int) $body['classe_id'];
        $matiere = (int) $body['matiere_id'];
        $ens     = (int) $body['enseignant_id'];
        $jour    = requireEnum(clean($body['jour']), JOURS, 'Jour');
        $type    = requireEnum(clean($body['type_seance']), ['Cours', 'TD', 'TP'], 'Type de séance');
        $debut   = requireTime(clean($body['heure_debut']), 'Heure de début');
        $fin     = requireTime(clean($body['heure_fin']), 'Heure de fin');
        $salle   = clean($body['salle']);
        $sem     = (int) ($body['semestre'] ?? CURRENT_SEMESTER);
        if ($debut >= $fin)       jsonError('L\'heure de fin doit être postérieure à l\'heure de début.');
        if ($sem < 1 || $sem > 2) jsonError('Semestre invalide (1 ou 2).');
        if (mb_strlen($salle) > 50) jsonError('Nom de salle trop long.');

        // Détection des conflits : même classe, même enseignant ou même salle sur un créneau qui se chevauche
        $db = getDB();
        $st = $db->prepare(
            'SELECT classe_id, enseignant_id, salle FROM emploi_temps
             WHERE jour=? AND semestre=? AND heure_debut < ? AND heure_fin > ?
               AND (classe_id=? OR enseignant_id=? OR salle=?)'
        );
        $st->execute([$jour, $sem, $fin, $debut, $classe, $ens, $salle]);
        foreach ($st->fetchAll() as $c) {
            if ((int) $c['classe_id'] === $classe)      jsonError('Conflit : cette classe a déjà un cours sur ce créneau.', 409);
            if ((int) $c['enseignant_id'] === $ens)     jsonError('Conflit : cet enseignant a déjà un cours sur ce créneau.', 409);
            if ($c['salle'] === $salle)                 jsonError('Conflit : cette salle est déjà occupée sur ce créneau.', 409);
        }

        $db->prepare('INSERT INTO emploi_temps (classe_id,matiere_id,enseignant_id,jour,heure_debut,heure_fin,salle,type_seance,semestre)
                      VALUES (?,?,?,?,?,?,?,?,?)')
           ->execute([$classe, $matiere, $ens, $jour, $debut, $fin, $salle, $type, $sem]);
        $insertedId = (int) $db->lastInsertId();   // AVANT logActivity (qui fait lui-même un INSERT)
        logActivity($user['id'], 'create', 'emploi_temps', "Créneau ajouté: $jour $debut-$fin");
        jsonSuccess(['id' => $insertedId], 201);

    case 'delete_creneau':
        $user = requireAuth('scolarite');
        $id   = (int) (getBody()['id'] ?? 0);
        if (!$id) jsonError('ID requis.');
        $st = getDB()->prepare('DELETE FROM emploi_temps WHERE id=?');
        $st->execute([$id]);
        if ($st->rowCount() === 0) jsonError('Créneau introuvable.', 404);
        logActivity($user['id'], 'delete', 'emploi_temps', "Créneau $id supprimé");
        jsonSuccess(['message' => 'Créneau supprimé.']);

    /* ───────── Notes ───────── */
    case 'get_notes':
        requireAuth('scolarite');
        $classe_id = (int) (getBody()['classe_id'] ?? 0);
        if (!$classe_id) jsonError('classe_id requis.');
        $st = getDB()->prepare(
            'SELECT n.*, m.nom AS matiere_nom, CONCAT(u.prenom," ",u.nom) AS etudiant
             FROM notes n
             JOIN etudiants e    ON e.id=n.etudiant_id
             JOIN utilisateurs u ON u.id=e.utilisateur_id
             JOIN matieres m     ON m.id=n.matiere_id
             WHERE e.classe_id=?
             ORDER BY u.nom, m.nom'
        );
        $st->execute([$classe_id]);
        jsonSuccess(['notes' => $st->fetchAll()]);

    case 'update_note':
        $user = requireAuth('scolarite');
        $body = getBody();
        $id   = (int) ($body['id'] ?? 0);
        if (!$id) jsonError('ID requis.');
        $cc = gradeValue($body['cc'] ?? null, 'La note de CC');
        $ds = gradeValue($body['ds'] ?? null, 'La note de DS');
        $ex = gradeValue($body['examen'] ?? null, 'La note d\'examen');

        $db = getDB();
        $st = $db->prepare('SELECT 1 FROM notes WHERE id=?');
        $st->execute([$id]);
        if (!$st->fetchColumn()) jsonError('Note introuvable.', 404);

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE notes SET cc=?, ds=?, examen=? WHERE id=?')->execute([$cc, $ds, $ex, $id]);
            $db->prepare('UPDATE notes SET statut = CASE
                              WHEN moyenne IS NULL THEN "en_attente"
                              WHEN moyenne >= 10   THEN "validee"
                              ELSE "insuffisant" END
                          WHERE id=?')->execute([$id]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        logActivity($user['id'], 'edit', 'notes', "Note $id modifiée");
        jsonSuccess(['message' => 'Note mise à jour.']);

    /* ───────── Absences ───────── */
    case 'get_absences':
        requireAuth('scolarite');
        $st = getDB()->prepare(
            'SELECT a.*, m.nom AS matiere_nom,
                    CONCAT(ue.prenom," ",ue.nom)   AS etudiant,
                    CONCAT(uens.prenom," ",uens.nom) AS enseignant
             FROM absences a
             JOIN matieres m        ON m.id=a.matiere_id
             JOIN etudiants e       ON e.id=a.etudiant_id
             JOIN utilisateurs ue   ON ue.id=e.utilisateur_id
             JOIN enseignants ens   ON ens.id=a.enseignant_id
             JOIN utilisateurs uens ON uens.id=ens.utilisateur_id
             ORDER BY a.date_absence DESC
             LIMIT 2000'
        );
        $st->execute();
        jsonSuccess(['absences' => $st->fetchAll()]);

    case 'justify_absence':
        $user = requireAuth('scolarite');
        $id   = (int) (getBody()['id'] ?? 0);
        if (!$id) jsonError('ID requis.');
        $db = getDB();
        $st = $db->prepare('SELECT 1 FROM absences WHERE id=?');
        $st->execute([$id]);
        if (!$st->fetchColumn()) jsonError('Absence introuvable.', 404);
        $db->prepare('UPDATE absences SET justifie=1 WHERE id=?')->execute([$id]);
        logActivity($user['id'], 'edit', 'absences', "Absence $id justifiée");
        jsonSuccess(['message' => 'Absence justifiée.']);

    /* ───────── Annonces ───────── */
    case 'get_annonces':
        requireAuth('scolarite');
        $st = getDB()->prepare('SELECT a.*, CONCAT(u.prenom," ",u.nom) AS auteur_nom
                                FROM annonces a JOIN utilisateurs u ON u.id=a.auteur_id
                                ORDER BY a.created_at DESC');
        $st->execute();
        jsonSuccess(['annonces' => $st->fetchAll()]);

    case 'save_brouillon':
        $user = requireAuth('scolarite');
        $body = getBody();
        $titre   = clean($body['titre'] ?? '');
        $contenu = clean($body['contenu'] ?? '');
        $type    = requireEnum(clean($body['type'] ?? 'annonce'), ANNONCE_TYPES, 'Type d\'annonce');
        $dest    = requireEnum(clean($body['destinataires'] ?? 'tous'), ANNONCE_DEST, 'Destinataires');
        if ($titre === '' || $contenu === '') jsonError('Titre et contenu requis.');
        if (mb_strlen($titre) > 200)          jsonError('Titre trop long (200 caractères max).');

        $db = getDB();
        if (!empty($body['id'])) {
            $id = (int) $body['id'];
            $st = $db->prepare('SELECT 1 FROM annonces WHERE id=?');
            $st->execute([$id]);
            if (!$st->fetchColumn()) jsonError('Annonce introuvable.', 404);
            $db->prepare('UPDATE annonces SET titre=?,contenu=?,type=?,destinataires=? WHERE id=?')->execute([$titre, $contenu, $type, $dest, $id]);
        } else {
            $db->prepare('INSERT INTO annonces (titre,contenu,type,destinataires,auteur_id,statut) VALUES (?,?,?,?,?,"brouillon")')
               ->execute([$titre, $contenu, $type, $dest, $user['id']]);
            $id = (int) $db->lastInsertId();
        }
        logActivity($user['id'], 'create', 'annonces', "Brouillon: $titre");
        jsonSuccess(['id' => $id]);

    case 'publish_annonce':
        $user = requireAuth('scolarite');
        $body = getBody();
        $id   = (int) ($body['id'] ?? 0);
        $db   = getDB();

        if ($id) {
            $st = $db->prepare('SELECT 1 FROM annonces WHERE id=?');
            $st->execute([$id]);
            if (!$st->fetchColumn()) jsonError('Annonce introuvable.', 404);
            $db->prepare('UPDATE annonces SET statut="publie", date_publication=NOW() WHERE id=?')->execute([$id]);
        } else {
            $titre   = clean($body['titre'] ?? '');
            $contenu = clean($body['contenu'] ?? '');
            $type    = requireEnum(clean($body['type'] ?? 'annonce'), ANNONCE_TYPES, 'Type d\'annonce');
            $dest    = requireEnum(clean($body['destinataires'] ?? 'tous'), ANNONCE_DEST, 'Destinataires');
            if ($titre === '' || $contenu === '') jsonError('Titre et contenu requis.');
            if (mb_strlen($titre) > 200)          jsonError('Titre trop long (200 caractères max).');
            $db->prepare('INSERT INTO annonces (titre,contenu,type,destinataires,auteur_id,statut,date_publication) VALUES (?,?,?,?,?,"publie",NOW())')
               ->execute([$titre, $contenu, $type, $dest, $user['id']]);
            $id = (int) $db->lastInsertId();
        }
        logActivity($user['id'], 'publish', 'annonces', "Annonce $id publiée");
        jsonSuccess(['message' => 'Annonce publiée.', 'id' => $id]);

    case 'delete_annonce':
        $user = requireAuth('scolarite');
        $id   = (int) (getBody()['id'] ?? 0);
        if (!$id) jsonError('ID requis.');
        $st = getDB()->prepare('DELETE FROM annonces WHERE id=?');
        $st->execute([$id]);
        if ($st->rowCount() === 0) jsonError('Annonce introuvable.', 404);
        logActivity($user['id'], 'delete', 'annonces', "Annonce $id supprimée");
        jsonSuccess(['message' => 'Annonce supprimée.']);

    /* ───────── Demandes de documents ───────── */
    case 'get_demandes_docs':
        requireAuth('scolarite');
        $st = getDB()->prepare(
            'SELECT d.id, d.etudiant_id, d.type_document, d.motif, d.statut, d.created_at, d.updated_at,
                    (d.statut="generee" AND d.fichier_url IS NOT NULL) AS disponible,
                    CONCAT(u.prenom," ",u.nom) AS etudiant
             FROM demandes_documents d
             JOIN etudiants e    ON e.id=d.etudiant_id
             JOIN utilisateurs u ON u.id=e.utilisateur_id
             ORDER BY d.created_at DESC
             LIMIT 1000'
        );
        $st->execute();
        jsonSuccess(['demandes' => $st->fetchAll()]);

    case 'generer_document':
        $user = requireAuth('scolarite');
        $id   = (int) (getBody()['id'] ?? 0);
        if (!$id) jsonError('ID requis.');

        $db = getDB();
        [$ctx, $err] = loadDocumentContext($db, $id);
        if (!$ctx)                          jsonError($err, $err === 'Demande introuvable.' ? 404 : 422);
        if ($ctx['statut'] !== 'en_attente') jsonError('Cette demande a déjà été traitée.', 409);

        $pdf = buildDocumentPdf($ctx, $user['prenom'] . ' ' . $user['nom']);
        $dir = UPLOAD_DIR . '/docs';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException('Impossible de créer uploads/docs');
        }
        $filename = 'doc_' . $id . '_' . bin2hex(random_bytes(8)) . '.pdf';
        if (file_put_contents($dir . '/' . $filename, $pdf) === false) {
            throw new RuntimeException('Écriture du PDF impossible');
        }

        $db->prepare('UPDATE demandes_documents SET statut="generee", fichier_url=?, traitee_par=? WHERE id=?')
           ->execute(['uploads/docs/' . $filename, $user['id'], $id]);
        logActivity($user['id'], 'generate', 'demandes_documents', "Document $id généré (" . $ctx['type_document'] . ')');
        jsonSuccess(['message' => 'Document généré.', 'id' => $id, 'disponible' => true]);

    case 'refuser_document':
        $user = requireAuth('scolarite');
        $id   = (int) (getBody()['id'] ?? 0);
        if (!$id) jsonError('ID requis.');
        $st = getDB()->prepare('UPDATE demandes_documents SET statut="refusee", traitee_par=? WHERE id=? AND statut="en_attente"');
        $st->execute([$user['id'], $id]);
        if ($st->rowCount() === 0) jsonError('Demande introuvable ou déjà traitée.', 409);
        logActivity($user['id'], 'edit', 'demandes_documents', "Demande $id refusée");
        jsonSuccess(['message' => 'Demande refusée.']);

    case 'download_document':
        $user = requireAuth('scolarite');
        $id   = (int) ($_GET['id'] ?? 0);
        $st = getDB()->prepare('SELECT type_document, fichier_url FROM demandes_documents
                                WHERE id=? AND statut="generee" AND fichier_url IS NOT NULL');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) jsonError('Document introuvable.', 404);
        streamUpload($row['fichier_url'], $row['type_document'] . '_' . $id . '.pdf', 'application/pdf');

    default:
        jsonError('Action inconnue.', 404);
}
