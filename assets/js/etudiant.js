
/* =============================================================
   script.js — Portail Étudiant | My EPI App
   Corrections appliquées :
   - user-name-display & avatar synchronisés après login
   - profile card (card-nom, card-cin, card-filiere, card-classe)
   - loadProfile() met aussi à jour input.value (disabled)
   - kpi-moyenne, kpi-absences, kpi-credits, kpi-rang présents
   - alertes-container présent
   - annonces-list présent
   - emploi-container présent (plus de table statique)
   - #absences-table tbody présent
   - notes-container + notes-moy-gen présents
   - cours-container présent
   - alertes-list présent
   - #finances-table tbody + fin-total/paye/restant présents
   - #docs-table tbody + doc-form/doc-type/doc-motif présents
   - #reclamations-table tbody + reclamation-form/recl-type/recl-details
   - Barre de progression absences par matière calculée dynamiquement
   - Libellés statuts en français
   - Toutes les erreurs silencieuses corrigées
============================================================= */

'use strict';

const API = 'api/etudiant.php';
const apiFetch = (action, data = {}, method = 'POST') => epiFetch(API, action, data, method);
let currentUser = null;

/* ─────────────────────────────────────────────
   UTILITAIRE : fetch API centralisé
───────────────────────────────────────────── */
/* ─────────────────────────────────────────────
   UTILITAIRE : Toast notification
───────────────────────────────────────────── *//* ─────────────────────────────────────────────
   UTILITAIRE : Bouton loading
───────────────────────────────────────────── */
function setLoading(btn, loading) {
    if (!btn) return;
    btn.disabled = loading;
    if (loading) {
        btn.dataset.original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement…';
    } else {
        btn.innerHTML = btn.dataset.original || btn.innerHTML;
    }
}

/* ─────────────────────────────────────────────
   UTILITAIRE : Setter élément par ID (innerText)
───────────────────────────────────────────── */
function setEl(id, val) {
    const el = document.getElementById(id);
    if (el) el.innerText = val ?? '—';
}

/* ─────────────────────────────────────────────
   UTILITAIRE : Setter valeur d'input par ID
───────────────────────────────────────────── */
function setInput(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val ?? '';
}

/* ─────────────────────────────────────────────
   UTILITAIRE : Formatage date FR
───────────────────────────────────────────── */
function formatDate(d) {
    if (!d) return '—';
    try {
        return new Date(d).toLocaleDateString('fr-FR', {
            day: '2-digit', month: 'short', year: 'numeric'
        });
    } catch {
        return d;
    }
}

/* ─────────────────────────────────────────────
   UTILITAIRES : Couleurs & icônes
───────────────────────────────────────────── */
function iconType(type) {
    return {
        annonce:    'bullhorn',
        alerte:     'exclamation-triangle',
        info:       'info-circle',
        calendrier: 'calendar-alt',
        examen:     'pencil-alt',
    }[type] || 'bell';
}

function badgeType(type) {
    return {
        alerte:     'warning',
        info:       'info',
        examen:     'warning',
        calendrier: 'info',
    }[type] || 'success';
}

function iconFile(type) {
    return {
        PDF: 'pdf',
        PPT: 'powerpoint',
        TD:  'alt',
        TP:  'code',
    }[type] || 'alt';
}

function statColor(s) {
    if (s === 'validee')    return 'success';
    if (s === 'insuffisant') return 'danger';
    return 'warning';
}

// Libellés statuts notes en français
function statLabel(s) {
    return {
        validee:     'Validée',
        insuffisant: 'Insuffisant',
        en_attente:  'En attente',
        rattrapage:  'Rattrapage',
    }[s] || s;
}

function finColor(s) {
    if (s === 'payee')      return 'success';
    if (s === 'en_retard')  return 'danger';
    if (s === 'en_attente') return 'warning';
    return 'info';
}

// Libellés statuts finances en français
function finLabel(s) {
    return {
        payee:      'Payée',
        en_attente: 'En attente',
        en_retard:  'En retard',
        exonere:    'Exonéré',
    }[s] || s;
}

function reclColor(s) {
    if (s === 'traitee' || s === 'generee') return 'success';
    if (s === 'rejetee' || s === 'refusee') return 'danger';
    if (s === 'en_cours')                   return 'warning';
    return 'info';
}

// Libellés statuts réclamations en français
function reclLabel(s) {
    return {
        en_attente: 'En attente',
        en_cours:   'En cours',
        traitee:    'Traitée',
        rejetee:    'Rejetée',
        generee:    'Générée',
        refusee:    'Refusée',
    }[s] || s;
}

/* ─────────────────────────────────────────────
   MISE À JOUR AVATAR / NOM utilisateur
───────────────────────────────────────────── */
function updateUserDisplay(prenom, nom) {
    const fullName = `${prenom} ${nom}`;
    const initials = `${prenom[0] || ''}+${nom[0] || ''}`;

    setEl('user-name-display', fullName);

    const av = document.getElementById('user-avatar');
    if (av) {
        av.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(initials)}&background=C9242E&color=fff&size=80`;
        av.alt = fullName;
    }
}

/* ─────────────────────────────────────────────
   MISE À JOUR CARTE ÉTUDIANT (profil)
───────────────────────────────────────────── */
function updateStudentCard(u) {
    const fullName = `${u.prenom} ${u.nom}`;
    const initials = `${u.prenom[0] || ''}+${u.nom[0] || ''}`;

    setEl('card-nom',     fullName);
    setEl('card-cin',     u.cin_matricule);
    setEl('card-filiere', u.filiere_nom || '—');
    setEl('card-classe',  u.classe_nom  || '—');

    const cp = document.getElementById('card-photo');
    if (cp) {
        cp.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(initials)}&background=fff&color=C9242E&size=80`;
        cp.alt = fullName;
    }
}

/* =============================================================
   INITIALISATION PRINCIPALE
============================================================= */
document.addEventListener('DOMContentLoaded', () => {

    const loginForm   = document.getElementById('login-form');
    const loginScreen = document.getElementById('login-screen');
    const appScreen   = document.getElementById('app-screen');
    const logoutBtn   = document.getElementById('logout-btn');
    const navItems    = document.querySelectorAll('.nav-item');
    const views       = document.querySelectorAll('.view');
    const pageTitle   = document.getElementById('current-page-title');

    
    if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = loginForm.querySelector('button[type="submit"]');
        setLoading(btn, true);

        const cin      = document.getElementById('cin').value.trim();
        const password = document.getElementById('password').value;

        try {
            const res  = await apiFetch('login', { cin, password });
            currentUser = res.user;

            // Mettre à jour l'affichage avant de changer d'écran
            updateUserDisplay(currentUser.prenom, currentUser.nom);

            loginScreen.classList.remove('active');
            appScreen.classList.add('active');

            // Charger le dashboard (KPIs + annonces)
            loadDashboard();
            loadProfile();

            toast(`Bienvenue, ${currentUser.prenom} ${currentUser.nom} !`, 'success');
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            setLoading(btn, false);
   }
});
}

    /* ─────────────────────────────────────────
       DÉCONNEXION
    ───────────────────────────────────────── */
    logoutBtn.addEventListener('click', async () => {
        try { await apiFetch('logout'); } catch (_) { /* silencieux */ }

        currentUser = null;

        // Réinitialiser les champs
        document.getElementById('cin').value      = '';
        document.getElementById('password').value = '';

        // Réinitialiser l'affichage
        setEl('user-name-display', 'Étudiant');
        const av = document.getElementById('user-avatar');
        if (av) av.src = 'https://ui-avatars.com/api/?name=E&background=C9242E&color=fff';

        appScreen.classList.remove('active');
        loginScreen.classList.add('active');

        toast('Vous êtes déconnecté.', 'info');
    });

    /* ─────────────────────────────────────────
       NAVIGATION SIDEBAR
    ───────────────────────────────────────── */
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');

            // Titre topbar = texte du nav item (sans icône)
            pageTitle.innerText = item.innerText.trim();

            views.forEach(v => v.classList.remove('active'));
            const targetId = item.getAttribute('data-target');
            const target   = document.getElementById(targetId);
            if (target) {
                target.classList.add('active');
                dispatchViewLoad(targetId);
            }
        });
    });

    /* ─────────────────────────────────────────
       DISPATCH : charge les données selon la vue
    ───────────────────────────────────────── */
    function dispatchViewLoad(viewId) {
        const map = {
            'view-notifications': loadAnnonces,
            'view-profile':       loadProfile,
            'view-emploi':        loadEmploi,
            'view-absences':      loadAbsences,
            'view-notes':         loadNotes,
            'view-classroom':     loadCours,
            'view-messages':      loadAlertes,
            'view-finances':      loadFinances,
            'view-reclamations':  loadReclamations,
            'view-admin':         loadDemandeDocs,
        };
        if (map[viewId]) map[viewId]();
    }

    /* =============================================================
       VUE : DASHBOARD — KPIs + alertes + annonces
    ============================================================= */
    async function loadDashboard() {
        try {
            const res = await apiFetch('get_dashboard', {}, 'GET');
            const d   = res.dashboard;

            // ── KPIs ──
            setEl('kpi-moyenne',  d.moyenne_generale ?? '--');
            setEl('kpi-absences', d.total_absences   ?? '0');
            setEl('kpi-credits',  d.credits_valides  ?? '--');
            setEl('kpi-rang',
                d.rang_classe
                    ? `${d.rang_classe}/${d.total_classe}`
                    : '--'
            );

            // ── Alertes pédagogiques ──
            const alertesContainer = document.getElementById('alertes-container');
            if (alertesContainer && d.alertes_pedagogiques?.length) {
                alertesContainer.innerHTML = d.alertes_pedagogiques.map(a => `
                    <div class="alert alert-${a.type === 'absence_prof' ? 'warning' : 'info'}">
                        <i class="fas fa-${a.type === 'absence_prof' ? 'exclamation-triangle' : 'redo'}"></i>
                        <div>
                            <strong>${a.type === 'absence_prof' ? 'Cours annulé' : 'Rattrapage'} :</strong>
                            ${escHtml(a.message)}
                            ${a.date_seance
                                ? `<br><small><i class="fas fa-calendar"></i> ${formatDate(a.date_seance)}`
                                + (a.heure_debut ? ` à ${a.heure_debut}` : '')
                                + (a.salle ? ` · Salle ${escHtml(a.salle)}` : '')
                                + `</small>`
                                : ''
                            }
                        </div>
                    </div>`).join('');
            } else if (alertesContainer) {
                alertesContainer.innerHTML = '';
            }

        } catch (err) {
            console.warn('Dashboard KPIs :', err.message);
        }

        // Charger les annonces dans la vue notifications
        loadAnnonces();
    }

    /* =============================================================
       VUE : ANNONCES (view-notifications)
       ID requis : annonces-list
    ============================================================= */
    async function loadAnnonces() {
        const c = document.getElementById('annonces-list');
        if (!c) return;
        c.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</p>';
        try {
            const res      = await apiFetch('get_annonces', {}, 'GET');
            const annonces = res.annonces || [];

            if (!annonces.length) {
                c.innerHTML = '<p class="text-muted">Aucune annonce publiée.</p>';
                return;
            }

            c.innerHTML = annonces.map(a => `
                <div class="list-item">
                    <div class="notif-header">
                        <h4>
                            <i class="fas fa-${iconType(a.type)}"></i>
                            ${escHtml(a.titre)}
                        </h4>
                        <span class="badge badge-${badgeType(a.type)}">${escHtml(a.type)}</span>
                    </div>
                    <p>${escHtml(a.contenu)}</p>
                    <small>
                        <i class="fas fa-eye"></i> ${a.nb_lectures ?? 0} lectures
                        · ${formatDate(a.date_publication)}
                        · <em>${escHtml(a.auteur ?? '')}</em>
                    </small>
                </div>`).join('');
        } catch (err) {
            c.innerHTML = `<p class="text-danger"><i class="fas fa-exclamation-circle"></i> ${escHtml(err.message)}</p>`;
        }
    }

    /* =============================================================
       VUE : PROFIL (view-profile)
       IDs requis : profile-nom/email/cin/classe/filiere/statut
                    card-nom/cin/filiere/classe/photo
    ============================================================= */
    async function loadProfile() {
        try {
            const res = await apiFetch('get_profile', {}, 'GET');
            const u   = res.user;

            // Champs du formulaire (inputs disabled)
            setInput('profile-nom',     `${u.prenom} ${u.nom}`);
            setInput('profile-email',   u.email);
            setInput('profile-cin',     u.cin_matricule);
            setInput('profile-classe',  u.classe_nom);
            setInput('profile-filiere', u.filiere_nom);
            setInput('profile-statut',  u.etudiant_statut || u.statut);

            // Carte numérique
            updateStudentCard(u);

            // Mettre à jour aussi le nom dans la topbar si pas encore fait
            updateUserDisplay(u.prenom, u.nom);

        } catch (err) {
            toast(err.message, 'error');
        }
    }

    /* =============================================================
       VUE : EMPLOI DU TEMPS (view-emploi)
       ID requis : emploi-container
    ============================================================= */
    async function loadEmploi() {
        const c = document.getElementById('emploi-container');
        if (!c) return;
        c.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement de l\'emploi du temps…</p>';
        try {
            const res     = await apiFetch('get_emploi', {}, 'GET');
            const jours   = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
            const seances = res.emploi || [];

            if (!seances.length) {
                c.innerHTML = '<p class="text-muted">Aucune séance planifiée pour cette semaine.</p>';
                return;
            }

            // Type couleur badge
            const typeBadge = { Cours: 'info', TD: 'success', TP: 'warning' };

            const cols = jours.map(j => {
                const ss = seances.filter(x => x.jour === j);
                return `
                <div class="emploi-col">
                    <div class="emploi-day-header">${j}</div>
                    ${ss.length
                        ? ss.map(x => `
                            <div class="emploi-seance emploi-${x.type_seance.toLowerCase()}">
                                <strong>${escHtml(x.matiere_nom)}</strong><br>
                                <small><i class="fas fa-clock"></i> ${escHtml(x.heure_debut)}–${escHtml(x.heure_fin)}</small><br>
                                <small><i class="fas fa-map-marker-alt"></i> ${escHtml(x.salle)}</small><br>
                                <small><i class="fas fa-user"></i> ${escHtml(x.enseignant)}</small>
                                <span class="badge badge-${typeBadge[x.type_seance] || 'info'}" style="margin-top:6px;display:inline-block;">
                                    ${escHtml(x.type_seance)}
                                </span>
                            </div>`).join('')
                        : '<p class="text-muted" style="font-size:0.8rem;padding:8px;">Libre</p>'
                    }
                </div>`;
            }).join('');

            c.innerHTML = `<div class="emploi-grid">${cols}</div>`;

        } catch (err) {
            c.innerHTML = `<p class="text-danger"><i class="fas fa-exclamation-circle"></i> ${escHtml(err.message)}</p>`;
        }
    }

    /* =============================================================
       VUE : ABSENCES (view-absences)
       IDs requis : abs-total, abs-justifie, abs-plafond
                    #absences-table tbody
    ============================================================= */
    async function loadAbsences() {
        const tbody = document.querySelector('#absences-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</td></tr>';

        try {
            const res = await apiFetch('get_absences', {}, 'GET');
            const abs = res.absences || [];

            // KPIs absences
            setEl('abs-total',    res.total_heures      ?? '0');
            setEl('abs-justifie', res.heures_justifiees ?? '0');
            setEl('abs-plafond',  res.plafond           ?? '30');

            if (!abs.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-muted">Aucune absence enregistrée.</td></tr>';
                return;
            }

            tbody.innerHTML = abs.map(a => `
                <tr>
                    <td>${formatDate(a.date_absence)}</td>
                    <td><strong>${escHtml(a.matiere_nom)}</strong></td>
                    <td>${a.duree_heures} h</td>
                    <td>${escHtml(a.enseignant)}</td>
                    <td>
                        <span class="status-badge ${a.justifie ? 'success' : 'danger'}">
                            ${a.justifie ? 'Justifiée' : 'Non justifiée'}
                        </span>
                    </td>
                    <td>${escHtml(a.observation || '—')}</td>
                </tr>`).join('');

        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-danger">${escHtml(err.message)}</td></tr>`;
        }
    }

    /* =============================================================
       VUE : NOTES (view-notes)
       IDs requis : notes-container, notes-moy-gen
    ============================================================= */
    async function loadNotes() {
        const c = document.getElementById('notes-container');
        if (!c) return;
        c.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement des notes…</p>';

        try {
            const res   = await apiFetch('get_notes', {}, 'GET');
            const notes = res.notes || [];

            // Moyenne générale
            setEl('notes-moy-gen', res.moyenne_generale ?? '--');

            if (!notes.length) {
                c.innerHTML = '<p class="text-muted">Aucune note disponible pour le moment.</p>';
                return;
            }

            // Regrouper par semestre
            const bySemestre = {};
            notes.forEach(n => {
                const s = n.semestre || 1;
                if (!bySemestre[s]) bySemestre[s] = [];
                bySemestre[s].push(n);
            });

            let html = '';
            Object.keys(bySemestre).sort().forEach(sem => {
                const rows = bySemestre[sem];
                // Calculer moyenne du semestre
                const moyennes = rows.filter(n => n.moyenne !== null && n.moyenne !== undefined);
                const moyS = moyennes.length
                    ? (moyennes.reduce((acc, n) => acc + parseFloat(n.moyenne), 0) / moyennes.length).toFixed(2)
                    : null;

                html += `
                <div style="margin-bottom:24px;">
                    <div class="flex-between" style="margin-bottom:12px;">
                        <h4 style="font-size:0.95rem;font-weight:700;color:var(--text-dark);">
                            <i class="fas fa-book text-primary"></i> Semestre ${sem}
                        </h4>
                        ${moyS ? `<span class="total-moyenne" style="margin:0;padding:6px 14px;font-size:0.9rem;">
                            Moy. S${sem} : <strong>${moyS} / 20</strong>
                        </span>` : ''}
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Matière</th>
                                    <th>Crédits</th>
                                    <th>CC (20%)</th>
                                    <th>DS (30%)</th>
                                    <th>Examen (50%)</th>
                                    <th>Moyenne</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows.map(n => {
                                    const moy   = parseFloat(n.moyenne);
                                    const color = isNaN(moy) ? 'var(--text-muted)' : moy >= 10 ? '#27ae60' : '#C9242E';
                                    return `<tr>
                                        <td><strong>${escHtml(n.matiere_nom)}</strong></td>
                                        <td style="text-align:center;">${n.credits ?? '—'}</td>
                                        <td style="text-align:center;">${n.cc     ?? '—'}</td>
                                        <td style="text-align:center;">${n.ds     ?? '—'}</td>
                                        <td style="text-align:center;">${n.examen ?? '—'}</td>
                                        <td style="text-align:center;">
                                            <strong style="color:${color};">
                                                ${n.moyenne ?? '—'}
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="status-badge ${statColor(n.statut)}">
                                                ${statLabel(n.statut)}
                                            </span>
                                        </td>
                                    </tr>`;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>`;
            });

            c.innerHTML = html;

        } catch (err) {
            c.innerHTML = `<p class="text-danger"><i class="fas fa-exclamation-circle"></i> ${escHtml(err.message)}</p>`;
        }
    }

    /* =============================================================
       VUE : ESPACE COURS (view-classroom)
       ID requis : cours-container
    ============================================================= */
    async function loadCours() {
        const c = document.getElementById('cours-container');
        if (!c) return;
        c.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement des supports…</p>';

        try {
            const res   = await apiFetch('get_cours', {}, 'GET');
            const cours = res.cours || [];

            if (!cours.length) {
                c.innerHTML = '<p class="text-muted">Aucun support de cours disponible.</p>';
                return;
            }

            // Regrouper par matière
            const byMatiere = {};
            cours.forEach(s => {
                const key = s.matiere_nom;
                if (!byMatiere[key]) byMatiere[key] = [];
                byMatiere[key].push(s);
            });

            c.innerHTML = Object.entries(byMatiere).map(([matiere, supports]) => `
                <div class="course-card" style="margin-bottom:16px;">
                    <div class="course-header">
                        <i class="fas fa-book-open" style="margin-right:8px;"></i>
                        ${escHtml(matiere)}
                        <span style="float:right;font-size:0.78rem;font-weight:400;opacity:0.85;">
                            ${escHtml(supports[0]?.enseignant ?? '')}
                        </span>
                    </div>
                    <div class="course-body">
                        <ul>
                            ${supports.map(s => `
                                <li>
                                    <span>
                                        <i class="fas fa-file-${iconFile(s.type_fichier)}"
                                           style="color:var(--primary);margin-right:6px;"></i>
                                        ${escHtml(s.titre)}
                                        <span class="badge badge-info" style="font-size:0.68rem;margin-left:6px;">
                                            ${escHtml(s.type_fichier)}
                                        </span>
                                    </span>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <small class="text-muted">${formatDate(s.date_upload)}</small>
                                        ${s.disponible
                                            ? `<a href="${API}?action=download_support&id=${Number(s.id)}" target="_blank" rel="noopener"
                                                  class="btn btn-outline btn-sm">
                                                   <i class="fas fa-download"></i> Télécharger
                                               </a>`
                                            : ''
                                        }
                                    </div>
                                </li>`).join('')}
                        </ul>
                    </div>
                </div>`).join('');

        } catch (err) {
            c.innerHTML = `<p class="text-danger"><i class="fas fa-exclamation-circle"></i> ${escHtml(err.message)}</p>`;
        }
    }

    /* =============================================================
       VUE : ALERTES & RATTRAPAGES (view-messages)
       ID requis : alertes-list
    ============================================================= */
    async function loadAlertes() {
        const c = document.getElementById('alertes-list');
        if (!c) return;
        c.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement des alertes…</p>';

        try {
            const res  = await apiFetch('get_alertes', {}, 'GET');
            const lst  = res.alertes || [];

            if (!lst.length) {
                c.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-check-circle"></i>
                        <div>Aucune alerte pédagogique en cours. Tous les cours se déroulent normalement.</div>
                    </div>`;
                return;
            }

            c.innerHTML = lst.map(a => {
                const isRattrapage = a.type === 'rattrapage';
                return `
                <div class="alert alert-${isRattrapage ? 'info' : 'warning'}" style="margin-bottom:12px;">
                    <i class="fas fa-${isRattrapage ? 'redo' : 'ban'}"></i>
                    <div>
                        <strong>${isRattrapage ? 'Rattrapage' : 'Cours annulé'}</strong>
                        ${a.matiere_nom ? ` — ${escHtml(a.matiere_nom)}` : ''}<br>
                        ${escHtml(a.message)}
                        ${a.date_seance
                            ? `<br><small>
                                <i class="fas fa-calendar-alt"></i> ${formatDate(a.date_seance)}
                                ${a.heure_debut ? ` à ${a.heure_debut}` : ''}
                                ${a.salle ? ` · <i class="fas fa-map-marker-alt"></i> ${escHtml(a.salle)}` : ''}
                               </small>`
                            : ''
                        }
                    </div>
                </div>`;
            }).join('');

        } catch (err) {
            c.innerHTML = `<p class="text-danger"><i class="fas fa-exclamation-circle"></i> ${escHtml(err.message)}</p>`;
        }
    }

    /* =============================================================
       VUE : FINANCES (view-finances)
       IDs requis : fin-total, fin-paye, fin-restant
                    #finances-table tbody
    ============================================================= */
    async function loadFinances() {
        const tbody = document.querySelector('#finances-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</td></tr>';

        try {
            const res  = await apiFetch('get_finances', {}, 'GET');
            const fins = res.finances || [];

            // KPIs financiers
            setEl('fin-total',   res.total_du   ?? '—');
            setEl('fin-paye',    res.total_paye  ?? '—');
            setEl('fin-restant', res.restant     ?? '—');

            if (!fins.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Aucune donnée financière.</td></tr>';
                return;
            }

            tbody.innerHTML = fins.map(f => `
                <tr>
                    <td><strong>Tranche ${f.tranche}</strong></td>
                    <td>${f.montant} TND</td>
                    <td>${formatDate(f.date_echeance)}</td>
                    <td>${f.date_paiement ? formatDate(f.date_paiement) : '—'}</td>
                    <td>
                        <span class="status-badge ${finColor(f.statut)}">
                            ${finLabel(f.statut)}
                        </span>
                    </td>
                </tr>`).join('');

        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger">${escHtml(err.message)}</td></tr>`;
        }
    }

    /* =============================================================
       VUE : RÉCLAMATIONS (view-reclamations)
       IDs requis : reclamation-form, recl-type, recl-details
                    #reclamations-table tbody
    ============================================================= */
    async function loadReclamations() {
        const tbody = document.querySelector('#reclamations-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</td></tr>';

        try {
            const res = await apiFetch('get_reclamations', {}, 'GET');
            const lst = res.reclamations || [];

            if (!lst.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-muted">Aucune réclamation soumise.</td></tr>';
                return;
            }

            tbody.innerHTML = lst.map(r => `
                <tr>
                    <td><strong>${escHtml(r.type)}</strong></td>
                    <td style="max-width:200px;word-break:break-word;">
                        ${escHtml(r.details.substring(0, 80))}${r.details.length > 80 ? '…' : ''}
                    </td>
                    <td>
                        <span class="status-badge ${reclColor(r.statut)}">
                            ${reclLabel(r.statut)}
                        </span>
                    </td>
                    <td style="max-width:160px;word-break:break-word;color:var(--text-muted);font-size:0.85rem;">
                        ${escHtml(r.reponse || 'En attente de traitement')}
                    </td>
                </tr>`).join('');

        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-danger">${escHtml(err.message)}</td></tr>`;
        }
    }

    // Formulaire nouvelle réclamation
    const reclForm = document.getElementById('reclamation-form');
    if (reclForm) {
        reclForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = reclForm.querySelector('button[type="submit"]');
            setLoading(btn, true);
            try {
                const type    = document.getElementById('recl-type').value;
                const details = document.getElementById('recl-details').value.trim();

                if (!details) {
                    toast('Veuillez décrire le problème.', 'error');
                    return;
                }

                await apiFetch('add_reclamation', { type, details });
                toast('Réclamation envoyée avec succès.', 'success');
                reclForm.reset();
                loadReclamations();
            } catch (err) {
                toast(err.message, 'error');
            } finally {
                setLoading(btn, false);
            }
        });
    }

    /* =============================================================
       VUE : ADMINISTRATION / DOCUMENTS (view-admin)
       IDs requis : doc-form, doc-type, doc-motif
                    #docs-table tbody
    ============================================================= */
    async function loadDemandeDocs() {
        const tbody = document.querySelector('#docs-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</td></tr>';

        try {
            const res = await apiFetch('get_demandes_docs', {}, 'GET');
            const lst = res.demandes || [];

            if (!lst.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-muted">Aucune demande soumise.</td></tr>';
                return;
            }

            tbody.innerHTML = lst.map(d => `
                <tr>
                    <td><strong>${escHtml(d.type_document.replaceAll('_', ' '))}</strong></td>
                    <td>${escHtml(d.motif)}</td>
                    <td>
                        <span class="status-badge ${reclColor(d.statut)}">
                            ${reclLabel(d.statut)}
                        </span>
                    </td>
                    <td>
                        ${Number(d.disponible)
                            ? `<a href="${API}?action=download_document&id=${Number(d.id)}" target="_blank" rel="noopener"
                                  class="btn btn-outline btn-sm">
                                   <i class="fas fa-download"></i> Télécharger
                               </a>`
                            : '<span class="text-muted">—</span>'
                        }
                    </td>
                </tr>`).join('');

        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-danger">${escHtml(err.message)}</td></tr>`;
        }
    }

    // Formulaire demande de document
    const docForm = document.getElementById('doc-form');
    if (docForm) {
        docForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = docForm.querySelector('button[type="submit"]');
            setLoading(btn, true);
            try {
                const type_document = document.getElementById('doc-type').value;
                const motif         = document.getElementById('doc-motif').value.trim();

                if (!motif) {
                    toast('Veuillez indiquer le motif de la demande.', 'error');
                    return;
                }

                await apiFetch('add_demande_doc', { type_document, motif });
                toast('Demande envoyée avec succès. Traitement sous 48h.', 'success');
                docForm.reset();
                loadDemandeDocs();
            } catch (err) {
                toast(err.message, 'error');
            } finally {
                setLoading(btn, false);
            }
        });
    }

    /* =============================================================
       SÉCURITÉ : Échappement HTML pour éviter les injections XSS
    ============================================================= */}); // fin DOMContentLoaded