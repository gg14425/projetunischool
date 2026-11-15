<?php
/**
 * api/etudiant.php — API du portail Étudiant
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$action = getAction();

switch ($action) {

    /* ───────── Authentification ───────── */
    case 'login':
        $body = getBody();
        $u    = authenticate('etudiant', clean($body['cin'] ?? ''), (string) ($body['password'] ?? ''));

        $st = getDB()->prepare(
            'SELECT e.id AS etudiant_id, e.classe_id, e.statut AS etudiant_statut,
                    c.nom AS classe_nom, f.nom AS filiere_nom
             FROM etudiants e
             JOIN classes c  ON c.id = e.classe_id
             JOIN filieres f ON f.id = c.filiere_id
             WHERE e.utilisateur_id = ?'
        );
        $st->execute([$u['id']]);
        $p = $st->fetch();
        if (!$p) {
            jsonError('Profil étudiant incomplet. Contactez la scolarité.', 403);
        }
        if ($p['etudiant_statut'] !== 'actif') {
            jsonError('Compte étudiant non actif (' . $p['etudiant_statut'] . '). Contactez la scolarité.', 403);
        }

        $csrf = startUserSession([
            'id'            => (int) $u['id'],
            'etudiant_id'   => (int) $p['etudiant_id'],
            'prenom'        => $u['prenom'],
            'nom'           => $u['nom'],
            'email'         => $u['email'],
            'cin_matricule' => $u['cin_matricule'],
            'role'          => 'etudiant',
            'classe_id'     => (int) $p['classe_id'],
            'classe_nom'    => $p['classe_nom'],
            'filiere_nom'   => $p['filiere_nom'],
        ]);
        logActivity((int) $u['id'], 'login', 'auth', 'Connexion étudiant');
        jsonSuccess(['user' => $_SESSION['user'], 'csrf_token' => $csrf]);

    case 'logout':
        doLogout('étudiant');

    case 'change_password':
        changeOwnPassword(requireAuth('etudiant'), getBody());

    /* ───────── Tableau de bord ───────── */
    case 'get_dashboard':
        $user = requireAuth('etudiant');
        $db   = getDB();
        $eid  = $user['etudiant_id'];

        $st = $db->prepare('SELECT ' . SQL_WEIGHTED_AVG . ' AS moy FROM notes n JOIN matieres m ON m.id=n.matiere_id WHERE n.etudiant_id=?');
        $st->execute([$eid]);
        $moy = $st->fetchColumn();

        $st = $db->prepare('SELECT COALESCE(SUM(duree_heures),0) FROM absences WHERE etudiant_id=?');
        $st->execute([$eid]);
        $absTotal = $st->fetchColumn();

        $st = $db->prepare('SELECT id,titre,contenu,type,date_publication FROM annonces
                            WHERE statut="publie" AND destinataires IN("tous","etudiants")
                            ORDER BY date_publication DESC LIMIT 3');
        $st->execute();
        $annonces = $st->fetchAll();

        $st = $db->prepare('SELECT * FROM alertes_pedagogiques
                            WHERE actif=1 AND (classe_id=? OR classe_id IS NULL)
                            ORDER BY created_at DESC LIMIT 5');
        $st->execute([$user['classe_id']]);
        $alertes = $st->fetchAll();

        jsonSuccess(['dashboard' => [
            'moyenne_generale'     => $moy ?? '--',
            'total_absences'       => $absTotal,
            'alertes_pedagogiques' => $alertes,
            'annonces_recentes'    => $annonces,
        ]]);

    case 'get_annonces':
        requireAuth('etudiant');
        $st = getDB()->prepare('SELECT a.id,a.titre,a.contenu,a.type,a.date_publication,
                                       CONCAT(u.prenom," ",u.nom) AS auteur
                                FROM annonces a
                                JOIN utilisateurs u ON u.id=a.auteur_id
                                WHERE a.statut="publie" AND a.destinataires IN("tous","etudiants")
                                ORDER BY a.date_publication DESC');
        $st->execute();
        jsonSuccess(['annonces' => $st->fetchAll()]);

    case 'get_profile':
        $user = requireAuth('etudiant');
        $st = getDB()->prepare(
            'SELECT u.id, u.prenom, u.nom, u.email, u.cin_matricule, u.photo_url, u.statut,
                    e.id AS etudiant_id, e.classe_id, e.date_naissance, e.adresse, e.telephone,
                    e.statut AS etudiant_statut, c.nom AS classe_nom, f.nom AS filiere_nom
             FROM utilisateurs u
             JOIN etudiants e ON e.utilisateur_id=u.id
             JOIN classes c   ON c.id=e.classe_id
             JOIN filieres f  ON f.id=c.filiere_id
             WHERE u.id=?'
        );
        $st->execute([$user['id']]);
        jsonSuccess(['user' => $st->fetch()]);

    /* ───────── Pédagogie ───────── */
    case 'get_emploi':
        $user = requireAuth('etudiant');
        $st = getDB()->prepare(
            'SELECT et.*, m.nom AS matiere_nom, CONCAT(u.prenom," ",u.nom) AS enseignant
             FROM emploi_temps et
             JOIN matieres m      ON m.id=et.matiere_id
             JOIN enseignants ens ON ens.id=et.enseignant_id
             JOIN utilisateurs u  ON u.id=ens.utilisateur_id
             WHERE et.classe_id=?
             ORDER BY FIELD(et.jour,"Lundi","Mardi","Mercredi","Jeudi","Vendredi","Samedi"), et.heure_debut'
        );
        $st->execute([$user['classe_id']]);
        jsonSuccess(['emploi' => $st->fetchAll()]);

    case 'get_absences':
        $user = requireAuth('etudiant');
        $db   = getDB();
        $eid  = $user['etudiant_id'];

        $st = $db->prepare(
            'SELECT a.*, m.nom AS matiere_nom, CONCAT(u.prenom," ",u.nom) AS enseignant
             FROM absences a
             JOIN matieres m      ON m.id=a.matiere_id
             JOIN enseignants ens ON ens.id=a.enseignant_id
             JOIN utilisateurs u  ON u.id=ens.utilisateur_id
             WHERE a.etudiant_id=?
             ORDER BY a.date_absence DESC'
        );
        $st->execute([$eid]);
        $absences = $st->fetchAll();

        $st = $db->prepare('SELECT COALESCE(SUM(duree_heures),0) AS total,
                                   COALESCE(SUM(IF(justifie=1,duree_heures,0)),0) AS justifiees
                            FROM absences WHERE etudiant_id=?');
        $st->execute([$eid]);
        $t = $st->fetch();

        jsonSuccess([
            'absences'          => $absences,
            'total_heures'      => $t['total'],
            'heures_justifiees' => $t['justifiees'],
            'plafond'           => ABSENCE_LIMIT_HOURS,
        ]);

    case 'get_notes':
        $user = requireAuth('etudiant');
        $db   = getDB();
        $eid  = $user['etudiant_id'];

        $st = $db->prepare('SELECT n.*, m.nom AS matiere_nom, m.credits
                            FROM notes n JOIN matieres m ON m.id=n.matiere_id
                            WHERE n.etudiant_id=?
                            ORDER BY n.semestre, m.nom');
        $st->execute([$eid]);
        $notes = $st->fetchAll();

        $st = $db->prepare('SELECT ' . SQL_WEIGHTED_AVG . ' FROM notes n JOIN matieres m ON m.id=n.matiere_id WHERE n.etudiant_id=?');
        $st->execute([$eid]);
        jsonSuccess(['notes' => $notes, 'moyenne_generale' => $st->fetchColumn() ?? '--']);

    case 'get_cours':
        $user = requireAuth('etudiant');
        // Uniquement les supports des matières de SA filière
        $st = getDB()->prepare(
            'SELECT cs.id, cs.titre, cs.type_fichier, cs.taille_kb, cs.date_upload,
                    m.nom AS matiere_nom, CONCAT(u.prenom," ",u.nom) AS enseignant, 1 AS disponible
             FROM cours_supports cs
             JOIN matieres m      ON m.id=cs.matiere_id
             JOIN classes c       ON c.filiere_id=m.filiere_id AND c.id=?
             JOIN enseignants ens ON ens.id=cs.enseignant_id
             JOIN utilisateurs u  ON u.id=ens.utilisateur_id
             ORDER BY cs.date_upload DESC'
        );
        $st->execute([$user['classe_id']]);
        jsonSuccess(['cours' => $st->fetchAll()]);

    case 'download_support':
        $user = requireAuth('etudiant');
        $id   = (int) ($_GET['id'] ?? 0);
        $st = getDB()->prepare(
            'SELECT cs.fichier_url, cs.titre
             FROM cours_supports cs
             JOIN matieres m ON m.id=cs.matiere_id
             JOIN classes c  ON c.filiere_id=m.filiere_id AND c.id=?
             WHERE cs.id=?'
        );
        $st->execute([$user['classe_id'], $id]);
        $row = $st->fetch();
        if (!$row) {
            jsonError('Support introuvable.', 404);
        }
        $ext = strtolower(pathinfo($row['fichier_url'], PATHINFO_EXTENSION));
        streamUpload($row['fichier_url'], $row['titre'] . '.' . $ext, 'application/octet-stream');

    case 'get_alertes':
        $user = requireAuth('etudiant');
        $st = getDB()->prepare('SELECT ap.*, m.nom AS matiere_nom
                                FROM alertes_pedagogiques ap
                                LEFT JOIN matieres m ON m.id=ap.matiere_id
                                WHERE ap.actif=1 AND (ap.classe_id=? OR ap.classe_id IS NULL)
                                ORDER BY ap.created_at DESC');
        $st->execute([$user['classe_id']]);
        jsonSuccess(['alertes' => $st->fetchAll()]);

    case 'get_messages':
        $user = requireAuth('etudiant');
        $st = getDB()->prepare(
            'SELECT m.id, m.objet, m.contenu, m.date_envoi, CONCAT(u.prenom," ",u.nom) AS expediteur_nom
             FROM messages m JOIN utilisateurs u ON u.id=m.expediteur_id
             WHERE m.destinataire_role IN ("etudiants","tous")
               AND (m.classe_id IS NULL OR m.classe_id=?)
             ORDER BY m.date_envoi DESC LIMIT 100'
        );
        $st->execute([$user['classe_id']]);
        jsonSuccess(['messages' => $st->fetchAll()]);

    /* ───────── Finances ───────── */
    case 'get_finances':
        $user = requireAuth('etudiant');
        $st = getDB()->prepare('SELECT * FROM finances WHERE etudiant_id=? ORDER BY tranche');
        $st->execute([$user['etudiant_id']]);
        $fins = $st->fetchAll();

        $total = 0.0;
        $paye  = 0.0;
        foreach ($fins as $f) {
            if ($f['statut'] === 'exonere') continue;      // une tranche exonérée n'est pas due
            $total += (float) $f['montant'];
            if ($f['statut'] === 'payee') $paye += (float) $f['montant'];
        }
        jsonSuccess([
            'finances'   => $fins,
            'total_du'   => number_format($total, 3, '.', ''),
            'total_paye' => number_format($paye, 3, '.', ''),
            'restant'    => number_format($total - $paye, 3, '.', ''),
        ]);

    /* ───────── Réclamations ───────── */
    case 'get_reclamations':
        $user = requireAuth('etudiant');
        $st = getDB()->prepare('SELECT * FROM reclamations WHERE auteur_id=? ORDER BY created_at DESC');
        $st->execute([$user['id']]);
        jsonSuccess(['reclamations' => $st->fetchAll()]);

    case 'add_reclamation':
        $user    = requireAuth('etudiant');
        $body    = getBody();
        $type    = clean($body['type'] ?? '');
        $details = clean($body['details'] ?? '');
        if ($type === '' || $details === '') jsonError('Type et détails requis.');
        if (mb_strlen($type) > 80)       jsonError('Type trop long (80 caractères max).');
        if (mb_strlen($details) > 2000)  jsonError('Détails trop longs (2000 caractères max).');

        $db = getDB();
        $db->prepare('INSERT INTO reclamations (auteur_id,type,details) VALUES (?,?,?)')
           ->execute([$user['id'], $type, $details]);
        $insertedId = (int) $db->lastInsertId();   // AVANT logActivity (qui fait lui-même un INSERT)
        logActivity($user['id'], 'create', 'reclamations', "Réclamation: $type");
        jsonSuccess(['id' => $insertedId], 201);

    /* ───────── Demandes de documents ───────── */
    case 'get_demandes_docs':
        $user = requireAuth('etudiant');
        $st = getDB()->prepare(
            'SELECT id, type_document, motif, statut, created_at, updated_at,
                    (statut="generee" AND fichier_url IS NOT NULL) AS disponible
             FROM demandes_documents WHERE etudiant_id=? ORDER BY created_at DESC'
        );
        $st->execute([$user['etudiant_id']]);
        jsonSuccess(['demandes' => $st->fetchAll()]);

    case 'add_demande_doc':
        $user  = requireAuth('etudiant');
        $body  = getBody();
        $type  = clean($body['type_document'] ?? '');
        $motif = clean($body['motif'] ?? '');
        if ($type === '' || $motif === '') jsonError('Type de document et motif requis.');
        requireEnum($type, ['attestation_presence', 'releve_notes', 'attestation_scolarite', 'convention_stage', 'certificat_reussite'], 'Type de document');
        if (mb_strlen($motif) > 200) jsonError('Motif trop long (200 caractères max).');

        $db = getDB();
        $st = $db->prepare('SELECT COUNT(*) FROM demandes_documents WHERE etudiant_id=? AND type_document=? AND statut="en_attente"');
        $st->execute([$user['etudiant_id'], $type]);
        if ((int) $st->fetchColumn() > 0) {
            jsonError('Une demande de ce document est déjà en attente de traitement.', 409);
        }
        $db->prepare('INSERT INTO demandes_documents (etudiant_id,type_document,motif) VALUES (?,?,?)')
           ->execute([$user['etudiant_id'], $type, $motif]);
        $insertedId = (int) $db->lastInsertId();   // AVANT logActivity (qui fait lui-même un INSERT)
        logActivity($user['id'], 'create', 'demandes_documents', "Demande: $type");
        jsonSuccess(['id' => $insertedId], 201);

    case 'download_document':
        $user = requireAuth('etudiant');
        $id   = (int) ($_GET['id'] ?? 0);
        $st = getDB()->prepare('SELECT type_document, fichier_url FROM demandes_documents
                                WHERE id=? AND etudiant_id=? AND statut="generee" AND fichier_url IS NOT NULL');
        $st->execute([$id, $user['etudiant_id']]);
        $row = $st->fetch();
        if (!$row) {
            jsonError('Document introuvable ou non encore généré.', 404);
        }
        logActivity($user['id'], 'view', 'demandes_documents', "Téléchargement document $id");
        streamUpload($row['fichier_url'], $row['type_document'] . '_' . $id . '.pdf', 'application/pdf');

    default:
        jsonError('Action inconnue.', 404);
}
