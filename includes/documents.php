<?php
/**
 * documents.php — génération des documents administratifs en PDF
 * (attestation de scolarité / de présence, relevé de notes,
 *  convention de stage, certificat de réussite).
 */
declare(strict_types=1);

require_once __DIR__ . '/MiniPdf.php';

const DOC_LABELS = [
    'attestation_scolarite' => 'ATTESTATION DE SCOLARITÉ',
    'attestation_presence'  => 'ATTESTATION DE PRÉSENCE',
    'releve_notes'          => 'RELEVÉ DE NOTES',
    'convention_stage'      => 'AUTORISATION DE STAGE',
    'certificat_reussite'   => 'CERTIFICAT DE RÉUSSITE',
];

/**
 * Rassemble toutes les données nécessaires à un document.
 * Retourne [contexte, null] ou [null, message d'erreur métier].
 */
function loadDocumentContext(PDO $db, int $demandeId): array
{
    $st = $db->prepare(
        'SELECT d.id, d.type_document, d.motif, d.created_at, d.statut,
                e.id AS etudiant_id, u.prenom, u.nom, u.cin_matricule, u.email,
                c.nom AS classe_nom, f.nom AS filiere_nom
         FROM demandes_documents d
         JOIN etudiants e    ON e.id = d.etudiant_id
         JOIN utilisateurs u ON u.id = e.utilisateur_id
         JOIN classes c      ON c.id = e.classe_id
         JOIN filieres f     ON f.id = c.filiere_id
         WHERE d.id = ?'
    );
    $st->execute([$demandeId]);
    $ctx = $st->fetch();
    if (!$ctx) {
        return [null, 'Demande introuvable.'];
    }

    $st = $db->prepare(
        'SELECT m.nom AS matiere, m.credits, n.cc, n.ds, n.examen, n.moyenne, n.statut, n.semestre
         FROM notes n JOIN matieres m ON m.id = n.matiere_id
         WHERE n.etudiant_id = ? AND n.annee_univ = ?
         ORDER BY n.semestre, m.nom'
    );
    $st->execute([$ctx['etudiant_id'], ACADEMIC_YEAR]);
    $ctx['notes'] = $st->fetchAll();

    $st = $db->prepare(
        'SELECT ' . SQL_WEIGHTED_AVG . ' FROM notes n JOIN matieres m ON m.id = n.matiere_id
         WHERE n.etudiant_id = ? AND n.annee_univ = ?'
    );
    $st->execute([$ctx['etudiant_id'], ACADEMIC_YEAR]);
    $ctx['moyenne_generale'] = $st->fetchColumn();

    $st = $db->prepare(
        'SELECT COALESCE(SUM(duree_heures),0) AS total,
                COALESCE(SUM(CASE WHEN justifie=1 THEN duree_heures END),0) AS justifiees
         FROM absences WHERE etudiant_id = ?'
    );
    $st->execute([$ctx['etudiant_id']]);
    $abs = $st->fetch();
    $ctx['abs_total']      = (float) $abs['total'];
    $ctx['abs_justifiees'] = (float) $abs['justifiees'];

    // Règle métier : un certificat de réussite exige des notes et aucune matière insuffisante.
    if ($ctx['type_document'] === 'certificat_reussite') {
        $graded = array_filter($ctx['notes'], fn($n) => $n['moyenne'] !== null);
        $failed = array_filter($graded, fn($n) => $n['statut'] === 'insuffisant');
        if (!$graded) {
            return [null, 'Aucune note disponible : impossible de délivrer un certificat de réussite.'];
        }
        if ($failed) {
            return [null, 'Des matières sont insuffisantes : certificat de réussite impossible. Refusez la demande.'];
        }
    }
    return [$ctx, null];
}

function documentReference(array $ctx): string
{
    return sprintf('EPI-%s-%05d', date('Y'), $ctx['id']);
}

function fmtNote(mixed $v): string
{
    return $v === null ? '-' : number_format((float) $v, 2, ',', '');
}

function frDate(?string $d = null): string
{
    return date('d/m/Y', $d ? strtotime($d) : time());
}

/** Construit le PDF et retourne ses octets. */
function buildDocumentPdf(array $ctx, string $agentName): string
{
    $title = DOC_LABELS[$ctx['type_document']] ?? 'DOCUMENT';
    $pdf   = new MiniPdf($title);
    $red   = [201, 36, 46];
    $grey  = [90, 90, 90];
    $left  = 60;
    $width = MiniPdf::W - 2 * $left;
    $ref   = documentReference($ctx);
    $fullName = mb_strtoupper($ctx['nom']) . ' ' . $ctx['prenom'];

    $header = function () use ($pdf, $red, $grey, $left, $width, $ref): void {
        $pdf->rect(0, 0, MiniPdf::W, 12, $red);
        $pdf->text($left, 62, 'EPI', 30, true, $red);
        $pdf->text($left, 80, 'Service de la Scolarité', 11, false, $grey);
        $pdf->textRight(MiniPdf::W - $left, 56, 'Réf. : ' . $ref, 9, false);
        $pdf->textRight(MiniPdf::W - $left, 70, 'Le ' . frDate(), 9, false);
        $pdf->line($left, 92, $left + $width, 92, 1.2, $red);
    };
    $footer = function () use ($pdf, $grey, $left, $width, $ref): void {
        $pdf->line($left, 790, $left + $width, 790, 0.5, $grey);
        $pdf->textCentered(806, "Document généré électroniquement par My EPI App - $ref", 8, false, $grey);
    };

    $header();
    $pdf->textCentered(140, $title, 18, true);
    $pdf->line(MiniPdf::W / 2 - 70, 150, MiniPdf::W / 2 + 70, 150, 1.5, $red);

    $identity = "{$fullName}, titulaire du CIN / matricule n° {$ctx['cin_matricule']},";
    $year     = ACADEMIC_YEAR;

    switch ($ctx['type_document']) {
        case 'attestation_scolarite':
            $y = $pdf->paragraph($left, 200, $width,
                "Le Service de la Scolarité de l'EPI atteste que :\n\n" .
                "$identity est régulièrement inscrit(e) en {$ctx['classe_nom']} (filière : {$ctx['filiere_nom']}) " .
                "au titre de l'année universitaire $year.\n\n" .
                "La présente attestation est délivrée à l'intéressé(e), sur sa demande (motif : {$ctx['motif']}), " .
                "pour servir et valoir ce que de droit.", 12);
            break;

        case 'attestation_presence':
            $y = $pdf->paragraph($left, 200, $width,
                "Le Service de la Scolarité de l'EPI atteste que :\n\n" .
                "$identity inscrit(e) en {$ctx['classe_nom']} (filière : {$ctx['filiere_nom']}), suit les enseignements " .
                "de l'année universitaire $year.\n\n" .
                'À la date de ce jour, le total des absences enregistrées est de ' .
                rtrim(rtrim(number_format($ctx['abs_total'], 1, ',', ''), '0'), ',') . ' heure(s), dont ' .
                rtrim(rtrim(number_format($ctx['abs_justifiees'], 1, ',', ''), '0'), ',') . " heure(s) justifiée(s) " .
                "(plafond réglementaire : " . ABSENCE_LIMIT_HOURS . " heures).\n\n" .
                "Document délivré sur demande (motif : {$ctx['motif']}).", 12);
            break;

        case 'convention_stage':
            $y = $pdf->paragraph($left, 200, $width,
                "Le Service de la Scolarité de l'EPI atteste que :\n\n" .
                "$identity inscrit(e) en {$ctx['classe_nom']} (filière : {$ctx['filiere_nom']}), " .
                "année universitaire $year, est autorisé(e) à effectuer un stage en entreprise.\n\n" .
                "Objet de la demande : {$ctx['motif']}.\n\n" .
                "La convention de stage détaillée (organisme d'accueil, dates, encadrant) sera établie et signée " .
                "par les trois parties : l'établissement, l'organisme d'accueil et l'étudiant(e).", 12);
            break;

        case 'certificat_reussite':
            $valid  = count(array_filter($ctx['notes'], fn($n) => $n['statut'] === 'validee'));
            $graded = count(array_filter($ctx['notes'], fn($n) => $n['moyenne'] !== null));
            $y = $pdf->paragraph($left, 200, $width,
                "Le Service de la Scolarité de l'EPI certifie que :\n\n" .
                "$identity inscrit(e) en {$ctx['classe_nom']} (filière : {$ctx['filiere_nom']}), a validé $valid matière(s) " .
                "sur $graded évaluée(s) au titre de l'année universitaire $year, " .
                'avec une moyenne générale de ' . fmtNote($ctx['moyenne_generale']) . " / 20.\n\n" .
                "Ce certificat est délivré pour servir et valoir ce que de droit.", 12);
            break;

        case 'releve_notes':
        default:
            $y = $pdf->paragraph($left, 190, $width,
                "Étudiant(e) : $fullName   |   CIN / matricule : {$ctx['cin_matricule']}\n" .
                "Classe : {$ctx['classe_nom']}   |   Filière : {$ctx['filiere_nom']}   |   Année : $year", 11);
            $y += 6;
            // En-tête du tableau
            $cols = [['Matière', 0, 'l'], ['Cr.', 195, 'r'], ['CC', 235, 'r'], ['DS', 280, 'r'], ['Examen', 340, 'r'], ['Moy.', 395, 'r'], ['Résultat', 410, 'l']];
            $drawHead = function (float $yy) use ($pdf, $left, $width, $cols, $red): float {
                $pdf->rect($left, $yy - 14, $width, 22, $red);
                foreach ($cols as [$label, $dx, $al]) {
                    $pdf->text($left + 6 + ($al === 'r' ? $dx - 28 : $dx), $yy, $label, 10, true, [255, 255, 255]);
                }
                return $yy + 24;
            };
            $y = $drawHead($y + 10);
            $i = 0;
            foreach ($ctx['notes'] as $n) {
                if ($y > 740) {
                    $footer();
                    $pdf->addPage();
                    $header();
                    $y = $drawHead(130);
                }
                if ($i++ % 2 === 0) {
                    $pdf->rect($left, $y - 12, $width, 20, [245, 245, 245]);
                }
                $statut = ['validee' => 'Validée', 'insuffisant' => 'Insuffisant', 'rattrapage' => 'Rattrapage', 'en_attente' => 'En attente'][$n['statut']] ?? $n['statut'];
                $pdf->text($left + 6, $y, $pdf->fit($n['matiere'] . ' (S' . $n['semestre'] . ')', 165, 10), 10);
                $pdf->textRight($left + 6 + 195, $y, (string) $n['credits'], 10);
                $pdf->textRight($left + 6 + 235, $y, fmtNote($n['cc']), 10);
                $pdf->textRight($left + 6 + 280, $y, fmtNote($n['ds']), 10);
                $pdf->textRight($left + 6 + 340, $y, fmtNote($n['examen']), 10);
                $pdf->textRight($left + 6 + 395, $y, fmtNote($n['moyenne']), 10, true);
                $pdf->text($left + 6 + 410, $y, $statut, 10);
                $y += 20;
            }
            if (!$ctx['notes']) {
                $pdf->text($left + 6, $y, 'Aucune note enregistrée pour cette année universitaire.', 10, false, $grey);
                $y += 20;
            }
            $pdf->line($left, $y - 6, $left + $width, $y - 6, 0.8, $grey);
            $y += 12;
            $pdf->text($left + 6, $y, 'Moyenne générale pondérée (crédits) : ' . fmtNote($ctx['moyenne_generale']) . ' / 20', 12, true);
            $y += 24;
            $pdf->text($left + 6, $y, 'Pondération : CC 20 % - DS 30 % - Examen 50 %.', 9, false, $grey);
            break;
    }

    if ($y > 690) {
        $footer();
        $pdf->addPage();
        $header();
        $y = 150;
    }
    $pdf->text($left + $width - 170, max($y + 40, 640), 'Le Service de la Scolarité', 11, true);
    $pdf->text($left + $width - 170, max($y + 56, 656), $agentName, 10, false, $grey);
    $footer();

    return $pdf->output();
}
