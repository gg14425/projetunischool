/* =============================================================
   scriptsco.js — Portail Scolarité | My EPI App
   Corrections appliquées :
   ✔ setEl('user-nom') → setEl('user-name-display')
   ✔ showView() définie et exposée globalement (appelée depuis HTML)
   ✔ loadDashboard() injecte les KPIs dans les .kpi-value existants
     et met à jour les listes statiques du dashboard
   ✔ loadEtudiants() remplace le tbody de la table statique de view-etudiants
   ✔ loadEmploi() : select dynamique injecté + emploi-container créé
   ✔ loadNotes() : utilise id="notes-class" (existant) + tbody injecté
   ✔ loadAbsences() remplace le tbody de la table statique de view-absences
   ✔ loadAnnonces() remplace le list-group statique de view-annonces
   ✔ loadDocuments() remplace le list-group statique de view-documents
   ✔ Modals fiche étudiant + édition créées dynamiquement (absentes du HTML)
   ✔ Libellés statuts en français partout
   ✔ escHtml() sur toutes les données API (sécurité XSS)
   ✔ setLoading() sur tous les boutons submit
   ✔ debounce() sur la recherche étudiants
   ✔ Fermeture modals sur clic overlay
============================================================= */

'use strict';

const API = 'api/scolarite.php';
const apiFetch = (action, data = {}, method = 'POST') => epiFetch(API, action, data, method);

/* ─────────────────────────────────────────────
   FETCH API centralisé
───────────────────────────────────────────── */
/* ─────────────────────────────────────────────
   TOAST
───────────────────────────────────────────── *//* ─────────────────────────────────────────────
   UTILITAIRES
───────────────────────────────────────────── */
function setEl(id, val) {
    const el = document.getElementById(id);
    if (el) el.innerText = val ?? '—';
}

function formatDate(d) {
    if (!d) return '—';
    try {
        return new Date(d).toLocaleDateString('fr-FR', {
            day: '2-digit', month: 'short', year: 'numeric'
        });
    } catch { return d; }
}function setLoading(btn, loading) {
    if (!btn) return;
    btn.disabled = loading;
    if (loading) {
        btn.dataset.original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement…';
    } else {
        btn.innerHTML = btn.dataset.original || btn.innerHTML;
    }
}

function debounce(fn, ms) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
}

/* ─────────────────────────────────────────────
   COULEURS & LIBELLÉS STATUTS
───────────────────────────────────────────── */
function statColor(s) {
    if (['validee','payee','generee','actif'].includes(s)) return 'success';
    if (['insuffisant','rejetee','refusee','suspendu','exclu'].includes(s)) return 'danger';
    return 'warning';
}

function statLabel(s) {
    return {
        validee:     'Validée',     insuffisant: 'Insuffisant',
        en_attente:  'En attente',  rattrapage:  'Rattrapage',
        publie:      'Publiée',     brouillon:   'Brouillon',
        archive:     'Archivée',    generee:     'Générée',
        refusee:     'Refusée',     actif:       'Actif',
        suspendu:    'Suspendu',    diplome:     'Diplômé',
        exclu:       'Exclu',
    }[s] || s;
}

function justifieLabel(j) { return j ? 'Justifiée' : 'Non justifiée'; }
function justifieColor(j) { return j ? 'success' : 'danger'; }

/* ─────────────────────────────────────────────
   CRÉATION DYNAMIQUE DES MODALS ABSENTES DU HTML
   (modal-fiche-etudiant + modal-edit-etudiant)
───────────────────────────────────────────── */
function injectModals() {
    if (document.getElementById('modal-fiche')) return; // déjà injectées

    document.body.insertAdjacentHTML('beforeend', `
    <!-- Modal fiche étudiant -->
    <div id="modal-fiche" class="modal-sco" style="display:none;">
        <div class="modal-sco-box">
            <div class="modal-sco-header">
                <h3><i class="fas fa-id-card text-primary"></i> Fiche étudiant</h3>
                <button class="modal-sco-close" onclick="closeScoModal('modal-fiche')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-sco-body">
                <div class="fiche-grid">
                    <div class="fiche-row"><span>Nom complet</span><strong id="fiche-nom">—</strong></div>
                    <div class="fiche-row"><span>CIN</span><strong id="fiche-cin">—</strong></div>
                    <div class="fiche-row"><span>Email</span><strong id="fiche-email">—</strong></div>
                    <div class="fiche-row"><span>Filière</span><strong id="fiche-filiere">—</strong></div>
                    <div class="fiche-row"><span>Classe</span><strong id="fiche-classe">—</strong></div>
                    <div class="fiche-row"><span>Statut</span><strong id="fiche-statut">—</strong></div>
                    <div class="fiche-row"><span>Moyenne générale</span>
                        <strong id="fiche-moy" style="color:var(--sco-light,#27ae60);">—</strong>
                    </div>
                    <div class="fiche-row"><span>Total absences</span>
                        <strong id="fiche-abs" style="color:#f39c12;">—</strong>
                    </div>
                </div>
            </div>
            <div class="modal-sco-footer">
                <button class="btn btn-outline btn-sm" onclick="closeScoModal('modal-fiche')">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Modal édition statut étudiant -->
    <div id="modal-edit-etudiant" class="modal-sco" style="display:none;">
        <div class="modal-sco-box" style="max-width:420px;">
            <div class="modal-sco-header">
                <h3><i class="fas fa-edit text-primary"></i> Modifier l'étudiant</h3>
                <button class="modal-sco-close" onclick="closeScoModal('modal-edit-etudiant')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="form-edit-etudiant">
                <div class="modal-sco-body">
                    <input type="hidden" id="edit-etudiant-id">
                    <p style="margin-bottom:12px;font-weight:600;" id="edit-etudiant-nom-label">—</p>
                    <div class="input-group-sco">
                        <label>Nouveau statut</label>
                        <select id="edit-etudiant-statut" class="sco-select">
                            <option value="actif">Actif</option>
                            <option value="suspendu">Suspendu</option>
                            <option value="diplome">Diplômé</option>
                            <option value="exclu">Exclu</option>
                        </select>
                    </div>
                </div>
                <div class="modal-sco-footer">
                    <button type="button" class="btn btn-outline btn-sm"
                            onclick="closeScoModal('modal-edit-etudiant')">Annuler</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Styles des modals injectées -->
    <style>
    .modal-sco {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.55);
        display: flex; align-items: center; justify-content: center;
        z-index: 9000;
        backdrop-filter: blur(3px);
        animation: fadeInOverlay 0.2s ease;
    }
    @keyframes fadeInOverlay { from { opacity:0; } to { opacity:1; } }

    .modal-sco-box {
        background: #fff;
        border-radius: 12px;
        width: 90%; max-width: 540px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        animation: modalSlideUp 0.25s cubic-bezier(0.34,1.4,0.64,1);
        overflow: hidden;
    }
    @keyframes modalSlideUp {
        from { opacity:0; transform:translateY(20px) scale(0.97); }
        to   { opacity:1; transform:translateY(0)    scale(1);    }
    }

    .modal-sco-header {
        display: flex; justify-content: space-between; align-items: center;
        padding: 18px 22px;
        border-bottom: 1px solid #e1e8ed;
        background: #f8f9fb;
    }
    .modal-sco-header h3 { font-size: 1rem; font-weight: 700; display:flex; align-items:center; gap:8px; }
    .modal-sco-close {
        background: none; border: none; font-size: 1.1rem;
        color: #7f8c8d; cursor: pointer; padding: 4px 8px; border-radius: 4px;
        transition: all 0.2s;
    }
    .modal-sco-close:hover { background: #f0f0f0; color: #C9242E; }

    .modal-sco-body  { padding: 22px; }
    .modal-sco-footer {
        padding: 14px 22px;
        border-top: 1px solid #e1e8ed;
        display: flex; justify-content: flex-end; gap: 10px;
        background: #f8f9fb;
    }

    .fiche-grid { display: flex; flex-direction: column; gap: 10px; }
    .fiche-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 10px 14px;
        background: #f4f7fa; border-radius: 8px;
        font-size: 0.9rem;
    }
    .fiche-row span { color: #7f8c8d; }
    .fiche-row strong { color: #2c3e50; }

    .input-group-sco { display: flex; flex-direction: column; gap: 6px; }
    .input-group-sco label { font-size: 0.85rem; font-weight: 600; color: #2c3e50; }
    .sco-select {
        padding: 10px 14px; border: 1.5px solid #e1e8ed; border-radius: 8px;
        font-size: 0.95rem; font-family: inherit; background: #fafbff;
        outline: none; transition: border-color 0.2s;
    }
    .sco-select:focus { border-color: #27ae60; }
    </style>
    `);
}

/* ─────────────────────────────────────────────
   FERMER UNE MODAL
───────────────────────────────────────────── */
window.closeScoModal = function(id) {
    const m = document.getElementById(id);
    if (m) m.style.display = 'none';
};

/* =============================================================
   INITIALISATION
============================================================= */
document.addEventListener('DOMContentLoaded', () => {

    // Injecter les modals manquantes dès le chargement
    injectModals();

    const loginForm   = document.getElementById('login-form');
    const loginScreen = document.getElementById('login-screen');
    const appScreen   = document.getElementById('app-screen');
    const logoutBtn   = document.getElementById('logout-btn');
    const navItems    = document.querySelectorAll('.nav-item');
    const views       = document.querySelectorAll('.view');
    const pageTitle   = document.getElementById('current-page-title');

    /* ─────────────────────────────────────────
       CONNEXION
    ───────────────────────────────────────── */
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = loginForm.querySelector('button[type="submit"]');
        setLoading(btn, true);

        const matricule = document.getElementById('sco-id').value.trim();
        const password  = document.getElementById('password').value;

        try {
            const res = await apiFetch('login', { matricule, password });
            const u   = res.user;

            loginScreen.classList.remove('active');
            appScreen.classList.add('active');

            // ✔ FIX : ID correct dans le HTML
            setEl('user-name-display', `${u.prenom} ${u.nom}`);

            // Mettre à jour l'avatar topbar si présent
            const av = document.querySelector('.topbar .user-info img.avatar');
            if (av) {
                const initials = `${u.prenom[0] || ''}+${u.nom[0] || ''}`;
                av.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(initials)}&background=27ae60&color=fff`;
                av.alt = `${u.prenom} ${u.nom}`;
            }

            loadDashboard();
            toast(`Bienvenue, ${u.prenom} !`, 'success');
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            setLoading(btn, false);
        }
    });

    /* ─────────────────────────────────────────
       DÉCONNEXION
    ───────────────────────────────────────── */
    logoutBtn.addEventListener('click', async () => {
        try { await apiFetch('logout'); } catch (_) {}
        appScreen.classList.remove('active');
        loginScreen.classList.add('active');
        setEl('user-name-display', 'Scolarité');
        toast('Déconnecté.', 'info');
    });

    /* ─────────────────────────────────────────
       NAVIGATION
    ───────────────────────────────────────── */
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');
            if (pageTitle) pageTitle.innerText = item.innerText.trim();

            views.forEach(v => v.classList.remove('active'));
            const targetId = item.getAttribute('data-target');
            const target   = document.getElementById(targetId);
            if (target) {
                target.classList.add('active');
                dispatchView(targetId);
            }
        });
    });

    /* ─────────────────────────────────────────
       DISPATCH VUE → CHARGEMENT DONNÉES
    ───────────────────────────────────────── */
    function dispatchView(id) {
        const map = {
            'view-dashboard': loadDashboard,
            'view-etudiants': loadEtudiants,
            'view-emploi':    loadEmploi,
            'view-notes':     loadNotes,
            'view-absences':  loadAbsences,
            'view-annonces':  loadAnnonces,
            'view-documents': loadDocuments,
        };
        if (map[id]) map[id]();
    }

    /* ─────────────────────────────────────────
       showView() — appelée depuis les boutons du HTML dashboard
       ✔ FIX : définie ici, exposée globalement
    ───────────────────────────────────────── */
    window.showView = function(targetId) {
        views.forEach(v => v.classList.remove('active'));
        navItems.forEach(n => n.classList.remove('active'));

        const target = document.getElementById(targetId);
        if (target) target.classList.add('active');

        const navItem = document.querySelector(`[data-target="${targetId}"]`);
        if (navItem) {
            navItem.classList.add('active');
            if (pageTitle) pageTitle.innerText = navItem.innerText.trim();
        }

        dispatchView(targetId);
    };

    /* ─────────────────────────────────────────
       Fermer modals sur clic overlay
    ───────────────────────────────────────── */
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.modal-sco').forEach(m => {
            if (e.target === m) m.style.display = 'none';
        });
    });

    /* =============================================================
       VUE : DASHBOARD
       ✔ FIX : KPIs injectés dans les .kpi-value existants (pas d'IDs)
                via position dans le .kpi-grid
              + listes dynamiques pour tâches urgentes et annonces
    ============================================================= */
    async function loadDashboard() {
        try {
            const res = await apiFetch('get_dashboard', {}, 'GET');
            const d   = res.dashboard;

            // Les 4 .kpi-value dans l'ordre du HTML : étudiants, classes, notes, documents
            const kpiValues = document.querySelectorAll('#view-dashboard .kpi-value');
            if (kpiValues[0]) kpiValues[0].textContent = d.total_etudiants    ?? '—';
            if (kpiValues[1]) kpiValues[1].textContent = '—'; // classes (pas dans API dashboard)
            if (kpiValues[2]) kpiValues[2].textContent = d.notes_attente       ?? '—';
            if (kpiValues[3]) kpiValues[3].textContent = d.docs_attente        ?? '—';

            // Panneau "Dernières annonces publiées" dans le dashboard
            loadDashboardAnnonces();

        } catch (err) {
            console.warn('Dashboard:', err.message);
        }
    }

    async function loadDashboardAnnonces() {
        // Le 2ème .list-group du dashboard (annonces récentes)
        const cards = document.querySelectorAll('#view-dashboard .card');
        // 2ème card = annonces
        const annonceCard = cards[1];
        if (!annonceCard) return;
        const listGroup = annonceCard.querySelector('.list-group');
        if (!listGroup) return;

        try {
            const res  = await apiFetch('get_annonces', {}, 'GET');
            const list = (res.annonces || []).slice(0, 4); // max 4 dans le dashboard
            if (!list.length) return;

            listGroup.innerHTML = list.map(a => {
                const isPublie  = a.statut === 'publie';
                const badgeCls  = isPublie ? 'badge-success' : 'badge-warning';
                const badgeLbl  = statLabel(a.statut);
                return `
                <div class="list-item">
                    <div class="notif-header">
                        <span class="badge ${badgeCls}">${badgeLbl}</span>
                        <small>${formatDate(a.date_publication || a.created_at)}</small>
                    </div>
                    <h4>${escHtml(a.titre)}</h4>
                    <p>${isPublie
                        ? `Destinataires : ${escHtml(a.destinataires)}. Auteur : ${escHtml(a.auteur_nom || '—')}.`
                        : 'En attente de validation.'
                    }</p>
                    <div style="margin-top:8px;display:flex;gap:8px;">
                        ${isPublie
                            ? ''
                            : `<button class="btn btn-primary btn-sm" onclick="publierAnnonce(${a.id})">
                                   <i class="fas fa-paper-plane"></i> Publier
                               </button>`
                        }
                        <button class="btn btn-outline btn-sm" style="color:#C9242E;border-color:#C9242E;"
                                onclick="supprimerAnnonce(${a.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>`;
            }).join('');
        } catch (err) {
            console.warn('Dashboard annonces:', err.message);
        }
    }

    /* =============================================================
       VUE : ÉTUDIANTS (view-etudiants)
       ✔ FIX : remplace le tbody de la table statique (sans ID)
    ============================================================= */
    async function loadEtudiants(searchVal = '') {
        // Cibler le tbody de la table dans view-etudiants
        const tbody = document.querySelector('#view-etudiants .data-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8" class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</td></tr>';

        try {
            const res  = await apiFetch('get_etudiants', {}, 'GET');
            let list   = res.etudiants || [];

            // Filtrage côté client si recherche active
            if (searchVal) {
                const q = searchVal.toLowerCase();
                list = list.filter(e =>
                    `${e.prenom} ${e.nom} ${e.cin_matricule} ${e.email} ${e.classe_nom} ${e.filiere_nom}`
                        .toLowerCase().includes(q)
                );
            }

            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-muted">Aucun étudiant trouvé.</td></tr>';
                return;
            }

            tbody.innerHTML = list.map(e => {
                const avatarUrl = `https://ui-avatars.com/api/?name=${
                    encodeURIComponent(`${e.prenom}+${e.nom}`)}&background=C9242E&color=fff&size=32`;
                return `
                <tr>
                    <td>
                        <div class="user-cell">
                            <img src="${escHtml(e.photo_url || avatarUrl)}" alt=""
                                 style="width:32px;height:32px;border-radius:50%;object-fit:cover;"
                                 onerror="this.src='${avatarUrl}'">
                            <span><strong>${escHtml(e.prenom)} ${escHtml(e.nom)}</strong></span>
                        </div>
                    </td>
                    <td>${escHtml(e.cin_matricule)}</td>
                    <td>${escHtml(e.filiere_nom)} / ${escHtml(e.classe_nom)}</td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                    <td>
                        <span class="status-badge ${statColor(e.statut)}">
                            ${statLabel(e.statut)}
                        </span>
                    </td>
                    <td class="action-buttons">
                        <button class="btn-action view" onclick="voirFiche(${e.id})" title="Voir fiche">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-action edit" onclick="editerEtudiant(${e.id},'${escHtml(e.statut)}','${escHtml(e.prenom+' '+e.nom)}')" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');

        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-danger">${escHtml(err.message)}</td></tr>`;
        }
    }

    // Recherche en temps réel sur le champ .search-input de view-etudiants
    const searchInput = document.querySelector('#view-etudiants .search-input');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            loadEtudiants(searchInput.value.trim());
        }, 350));
    }

    /* ─────────────────────────────────────────
       VOIR FICHE ÉTUDIANT (modal)
    ───────────────────────────────────────── */
    window.voirFiche = async (id) => {
        try {
            const res = await apiFetch('get_fiche_etudiant', { id }, 'POST');
            const e   = res.etudiant;

            setEl('fiche-nom',     `${e.prenom} ${e.nom}`);
            setEl('fiche-cin',     e.cin_matricule || '—');
            setEl('fiche-email',   e.email || '—');
            setEl('fiche-filiere', e.filiere_nom || '—');
            setEl('fiche-classe',  e.classe_nom  || '—');
            setEl('fiche-statut',  statLabel(e.etudiant_statut || e.statut));
            setEl('fiche-moy',     e.moyenne_generale ?? '—');
            setEl('fiche-abs',     `${e.total_absences ?? 0} h`);

            const modal = document.getElementById('modal-fiche');
            if (modal) modal.style.display = 'flex';
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    /* ─────────────────────────────────────────
       ÉDITER STATUT ÉTUDIANT (modal)
    ───────────────────────────────────────── */
    window.editerEtudiant = (id, statut, nomLabel = '') => {
        const modal = document.getElementById('modal-edit-etudiant');
        if (!modal) return;

        document.getElementById('edit-etudiant-id').value      = id;
        document.getElementById('edit-etudiant-statut').value  = statut;

        const lbl = document.getElementById('edit-etudiant-nom-label');
        if (lbl) lbl.textContent = nomLabel || `Étudiant #${id}`;

        modal.style.display = 'flex';
    };

    // Formulaire édition étudiant
    const editForm = document.getElementById('form-edit-etudiant');
    if (editForm) {
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn    = editForm.querySelector('button[type="submit"]');
            const id     = document.getElementById('edit-etudiant-id').value;
            const statut = document.getElementById('edit-etudiant-statut').value;
            setLoading(btn, true);
            try {
                await apiFetch('update_etudiant', { id, statut });
                toast('Étudiant mis à jour.', 'success');
                closeScoModal('modal-edit-etudiant');
                loadEtudiants();
            } catch (err) {
                toast(err.message, 'error');
            } finally {
                setLoading(btn, false);
            }
        });
    }

    /* =============================================================
       VUE : EMPLOI DU TEMPS (view-emploi)
       ✔ FIX : select dynamique injecté + emploi-grid affiché dans card
    ============================================================= */
    async function loadEmploi() {
        const section = document.getElementById('view-emploi');
        if (!section) return;

        // Trouver ou créer le select de classe
        let selClasse = document.getElementById('emploi-classe-select');
        if (!selClasse) {
            // Injecter le select et le container dans le haut de la card
            const card = section.querySelector('.card');
            if (!card) return;
            const flexBar = card.querySelector('.flex-between > div');
            if (flexBar) {
                // Remplacer le select statique par un select dynamique
                selClasse = document.createElement('select');
                selClasse.id = 'emploi-classe-select';
                selClasse.className = 'custom-select';
                selClasse.innerHTML = '<option value="">— Choisir une classe —</option>';
                flexBar.prepend(selClasse);
            }

            // Créer le container d'affichage de l'emploi du temps
            let container = document.getElementById('emploi-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'emploi-container';
                // Remplacer la table statique par le container dynamique
                const staticTable = card.querySelector('table');
                if (staticTable) staticTable.replaceWith(container);
                else card.appendChild(container);
            }
        }

        // Peupler le select avec les classes
        if (!selClasse) return;
        try {
            const res = await apiFetch('get_classes', {}, 'GET');
            const cls = res.classes || [];
            selClasse.innerHTML = '<option value="">— Choisir une classe —</option>' +
                cls.map(c => `<option value="${c.id}">${escHtml(c.nom)} (${escHtml(c.filiere_nom)})</option>`).join('');

            // Écouter les changements (une seule fois)
            if (!selClasse.dataset.bound) {
                selClasse.dataset.bound = '1';
                selClasse.addEventListener('change', () => {
                    if (selClasse.value) renderEmploi(selClasse.value);
                    else {
                        const c = document.getElementById('emploi-container');
                        if (c) c.innerHTML = '';
                    }
                });
            }
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function renderEmploi(classe_id) {
        const c = document.getElementById('emploi-container');
        if (!c) return;
        c.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</p>';
        try {
            const res     = await apiFetch('get_emploi', { classe_id }, 'POST');
            const jours   = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
            const seances = res.emploi || [];

            if (!seances.length) {
                c.innerHTML = '<p class="text-muted">Aucune séance pour cette classe.</p>';
                return;
            }

            const grid = jours.map(j => {
                const ss = seances.filter(x => x.jour === j);
                return `
                <div class="emploi-col">
                    <div class="emploi-day-header">${j}</div>
                    ${ss.length
                        ? ss.map(x => `
                            <div class="emploi-seance">
                                <strong>${escHtml(x.matiere_nom)}</strong><br>
                                <small>${x.heure_debut}–${x.heure_fin} · ${escHtml(x.salle)}</small><br>
                                <small>${escHtml(x.enseignant)}</small>
                                <span class="badge badge-info" style="display:inline-block;margin-top:4px;">
                                    ${escHtml(x.type_seance)}
                                </span>
                                <button class="btn-action" title="Supprimer"
                                    style="background:rgba(201,36,46,0.1);color:#C9242E;margin-top:4px;width:26px;height:26px;"
                                    onclick="deleteCreneau(${x.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>`).join('')
                        : '<p style="font-size:0.8rem;padding:8px;color:#999;">Libre</p>'
                    }
                </div>`;
            }).join('');

            c.innerHTML = `<div class="emploi-grid">${grid}</div>`;

        } catch (err) {
            c.innerHTML = `<p class="text-danger">${escHtml(err.message)}</p>`;
        }
    }

    window.deleteCreneau = async (id) => {
        if (!confirm('Supprimer ce créneau ?')) return;
        try {
            await apiFetch('delete_creneau', { id });
            toast('Créneau supprimé.', 'success');
            const sel = document.getElementById('emploi-classe-select');
            if (sel?.value) renderEmploi(sel.value);
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    /* =============================================================
       VUE : NOTES (view-notes)
       ✔ FIX : utilise id="notes-class" (existant dans le HTML)
                + remplace le tbody de la table statique
    ============================================================= */
    async function loadNotes() {
        // ✔ FIX : le HTML a id="notes-class" (pas "notes-classe-select")
        const selC = document.getElementById('notes-class');
        if (!selC) return;

        // Peupler le select avec les vraies classes depuis l'API
        try {
            const res = await apiFetch('get_classes', {}, 'GET');
            const cls = res.classes || [];

            // Garder l'option vide, remplacer les options statiques hardcodées
            selC.innerHTML = '<option value="">-- Choisir une classe --</option>' +
                cls.map(c => `<option value="${c.id}">${escHtml(c.nom)}</option>`).join('');

            if (!selC.dataset.bound) {
                selC.dataset.bound = '1';
                selC.addEventListener('change', () => {
                    if (selC.value) renderNotes(selC.value);
                    else {
                        // Réinitialiser le tbody
                        const tb = document.querySelector('#view-notes .data-table tbody');
                        if (tb) tb.innerHTML = '<tr><td colspan="8" class="text-muted">Choisissez une classe.</td></tr>';
                    }
                });
            }
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function renderNotes(classe_id) {
        // ✔ FIX : cibler le tbody de la table statique existante (sans ID)
        const tbody = document.querySelector('#view-notes .data-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8"><i class="fas fa-spinner fa-spin"></i> Chargement…</td></tr>';

        try {
            const res  = await apiFetch('get_notes', { classe_id }, 'POST');
            const list = res.notes || [];

            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-muted">Aucune note pour cette classe.</td></tr>';
                return;
            }

            tbody.innerHTML = list.map(n => {
                const moy   = parseFloat(n.moyenne);
                const color = isNaN(moy) ? '#f39c12' : moy >= 10 ? '#27ae60' : '#C9242E';
                return `
                <tr>
                    <td>${escHtml(n.etudiant)}</td>
                    <td>${escHtml(n.matiere_nom)}</td>
                    <td>
                        <input class="grade-input" type="number"
                               value="${n.cc ?? ''}"
                               data-id="${n.id}" data-field="cc"
                               min="0" max="20" step="0.25"
                               style="width:65px;padding:6px;border:1.5px solid #e1e8ed;border-radius:6px;text-align:center;">
                    </td>
                    <td>
                        <input class="grade-input" type="number"
                               value="${n.ds ?? ''}"
                               data-id="${n.id}" data-field="ds"
                               min="0" max="20" step="0.25"
                               style="width:65px;padding:6px;border:1.5px solid #e1e8ed;border-radius:6px;text-align:center;">
                    </td>
                    <td>
                        <input class="grade-input" type="number"
                               value="${n.examen ?? ''}"
                               data-id="${n.id}" data-field="examen"
                               min="0" max="20" step="0.25"
                               style="width:65px;padding:6px;border:1.5px solid #e1e8ed;border-radius:6px;text-align:center;">
                    </td>
                    <td>
                        <strong class="moy-display" style="color:${color};">
                            ${n.moyenne ?? '—'}
                        </strong>
                    </td>
                    <td>
                        <span class="status-badge ${statColor(n.statut)}">
                            ${statLabel(n.statut)}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="saveOneNote(${n.id}, this)">
                            <i class="fas fa-save"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');

            // Calcul de la moyenne en temps réel à la saisie
            tbody.querySelectorAll('.grade-input').forEach(inp => {
                inp.addEventListener('input', () => {
                    const row  = inp.closest('tr');
                    const cc   = parseFloat(row.querySelector('[data-field="cc"]').value)   || 0;
                    const ds   = parseFloat(row.querySelector('[data-field="ds"]').value)   || 0;
                    const exam = parseFloat(row.querySelector('[data-field="examen"]').value) || 0;

                    if (cc || ds || exam) {
                        const moy = (cc * 0.2 + ds * 0.3 + exam * 0.5).toFixed(2);
                        const el  = row.querySelector('.moy-display');
                        if (el) {
                            el.textContent = moy;
                            el.style.color = moy >= 10 ? '#27ae60' : '#C9242E';
                        }
                    }
                });
            });

        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-danger">${escHtml(err.message)}</td></tr>`;
        }
    }

    window.saveOneNote = async (noteId, btn) => {
        const row  = btn.closest('tr');
        const cc   = row.querySelector('[data-field="cc"]').value;
        const ds   = row.querySelector('[data-field="ds"]').value;
        const exam = row.querySelector('[data-field="examen"]').value;
        setLoading(btn, true);
        try {
            await apiFetch('update_note', { id: noteId, cc, ds, examen: exam });
            toast('Note sauvegardée !', 'success');
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            setLoading(btn, false);
        }
    };

    /* =============================================================
       VUE : ABSENCES (view-absences)
       ✔ FIX : remplace le tbody de la table statique existante
    ============================================================= */
    async function loadAbsences() {
        // Cibler le tbody de la table statique dans view-absences
        const tbody = document.querySelector('#view-absences .data-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</td></tr>';

        try {
            const res = await apiFetch('get_absences', {}, 'GET');
            const lst = res.absences || [];

            if (!lst.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-muted">Aucune absence enregistrée.</td></tr>';
                return;
            }

            tbody.innerHTML = lst.map(a => `
                <tr>
                    <td><strong>${escHtml(a.etudiant)}</strong></td>
                    <td>${escHtml(a.matiere_nom)}</td>
                    <td>${formatDate(a.date_absence)}</td>
                    <td>${a.duree_heures} h</td>
                    <td>—</td>
                    <td>
                        <span class="status-badge ${justifieColor(a.justifie)}">
                            ${justifieLabel(a.justifie)}
                        </span>
                    </td>
                    <td>
                        ${!a.justifie
                            ? `<button class="btn btn-outline btn-sm" onclick="justifierAbs(${a.id})">
                                   <i class="fas fa-check"></i> Justifier
                               </button>`
                            : '<span class="text-muted">—</span>'
                        }
                    </td>
                </tr>`).join('');

        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-danger">${escHtml(err.message)}</td></tr>`;
        }
    }

    window.justifierAbs = async (id) => {
        if (!confirm('Marquer cette absence comme justifiée ?')) return;
        try {
            await apiFetch('justify_absence', { id });
            toast('Absence justifiée.', 'success');
            loadAbsences();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    /* =============================================================
       VUE : ANNONCES (view-annonces)
       ✔ FIX : remplace le list-group statique de la 2ème card
                + connecte les boutons du formulaire de la 1ère card
    ============================================================= */
    async function loadAnnonces() {
        // 2ème card de view-annonces = liste des annonces publiées
        const cards = document.querySelectorAll('#view-annonces .card');
        const listeCard = cards[1];
        if (!listeCard) return;

        const listGroup = listeCard.querySelector('.list-group');
        if (!listGroup) return;

        listGroup.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</p>';

        try {
            const res  = await apiFetch('get_annonces', {}, 'GET');
            const list = res.annonces || [];

            if (!list.length) {
                listGroup.innerHTML = '<p class="text-muted">Aucune annonce.</p>';
                return;
            }

            listGroup.innerHTML = list.map(a => {
                const isPublie  = a.statut === 'publie';
                const badgeCls  = isPublie ? 'badge-success' : a.statut === 'brouillon' ? 'badge-warning' : 'badge-info';
                return `
                <div class="list-item">
                    <div class="notif-header">
                        <span class="badge ${badgeCls}">${statLabel(a.statut)}</span>
                        <small>${formatDate(a.date_publication || a.created_at)}</small>
                    </div>
                    <h4>${escHtml(a.titre)}</h4>
                    <p>
                        ${isPublie
                            ? `Destinataires : ${escHtml(a.destinataires)}. Vues : ${a.nb_lectures ?? 0}.`
                            : 'En attente de publication.'
                        }
                    </p>
                    <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                        ${!isPublie
                            ? `<button class="btn btn-primary btn-sm" onclick="publierAnnonce(${a.id})">
                                   <i class="fas fa-paper-plane"></i> Publier
                               </button>`
                            : `<button class="btn btn-outline btn-sm">
                                   <i class="fas fa-eye"></i> Voir
                               </button>`
                        }
                        <button class="btn btn-outline btn-sm" style="color:#C9242E;border-color:#C9242E;"
                                onclick="supprimerAnnonce(${a.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>`;
            }).join('');

        } catch (err) {
            listGroup.innerHTML = `<p class="text-danger">${escHtml(err.message)}</p>`;
        }

        // Connecter les boutons Sauvegarder / Publier de la 1ère card
        bindAnnonceButtons();
    }

    function bindAnnonceButtons() {
        // Bouton "Sauvegarder" (brouillon)
        const btnSave = document.querySelector('#view-annonces .btn-outline:not([onclick])');
        if (btnSave && !btnSave.dataset.bound) {
            btnSave.dataset.bound = '1';
            btnSave.addEventListener('click', () => publierOuSauvegarder(false));
        }
        // Bouton "Publier maintenant"
        const btnPublish = document.querySelector('#view-annonces .btn-primary:not([onclick])');
        if (btnPublish && !btnPublish.dataset.bound) {
            btnPublish.dataset.bound = '1';
            btnPublish.addEventListener('click', () => publierOuSauvegarder(true));
        }
    }

    async function publierOuSauvegarder(publish) {
        const section = document.getElementById('view-annonces');
        if (!section) return;
        const firstCard = section.querySelector('.card');
        if (!firstCard) return;

        const inputs  = firstCard.querySelectorAll('input[type="text"], textarea');
        const selects = firstCard.querySelectorAll('select');

        const titre        = inputs[0]?.value.trim()  || '';
        const contenu      = inputs[1]?.value.trim()  || '';
        const destinataires = selects[0]?.value || 'tous';
        const type         = selects[1]?.value || 'annonce';

        if (!titre || !contenu) {
            toast('Titre et contenu sont requis.', 'error');
            return;
        }

        const destMap = {
            'Tous les étudiants':   'etudiants',
            'Tous les enseignants': 'enseignants',
            'Classe spécifique':    'etudiants',
            'Tous les utilisateurs': 'tous',
        };
        const typeMap = {
            '📅 Calendrier académique': 'calendrier',
            '📋 Examens & résultats':   'examen',
            '📝 Réinscriptions':        'annonce',
            '🔔 Alerte administrative': 'alerte',
            'ℹ️ Information générale':  'info',
        };

        try {
            await apiFetch(
                publish ? 'publish_annonce' : 'save_brouillon',
                {
                    titre,
                    contenu,
                    type: typeMap[type] || 'annonce',
                    destinataires: destMap[destinataires] || 'tous',
                }
            );
            toast(publish ? 'Annonce publiée !' : 'Brouillon sauvegardé.', 'success');
            // Réinitialiser le formulaire
            if (inputs[0]) inputs[0].value = '';
            if (inputs[1]) inputs[1].value = '';
            loadAnnonces();
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    window.publierAnnonce = async (id) => {
        try {
            await apiFetch('publish_annonce', { id });
            toast('Annonce publiée !', 'success');
            loadAnnonces();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    window.supprimerAnnonce = async (id) => {
        if (!confirm('Supprimer définitivement cette annonce ?')) return;
        try {
            await apiFetch('delete_annonce', { id });
            toast('Annonce supprimée.', 'success');
            loadAnnonces();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    /* =============================================================
       VUE : DOCUMENTS (view-documents)
       ✔ FIX : remplace le list-group statique de la 1ère card
    ============================================================= */
    async function loadDocuments() {
        // 1ère card de view-documents = demandes en attente
        const firstCard = document.querySelector('#view-documents .card');
        if (!firstCard) return;

        const listGroup = firstCard.querySelector('.list-group');
        if (!listGroup) return;

        listGroup.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</p>';

        try {
            const res  = await apiFetch('get_demandes_docs', {}, 'GET');
            const list = res.demandes || [];

            if (!list.length) {
                listGroup.innerHTML = '<p class="text-muted">Aucune demande en cours.</p>';
                return;
            }

            listGroup.innerHTML = list.map(d => {
                const badgeCls = d.statut === 'en_attente' ? 'badge-warning'
                               : d.statut === 'generee'    ? 'badge-success'
                               : 'badge-danger';
                return `
                <div class="list-item">
                    <div class="notif-header">
                        <span class="badge ${badgeCls}">${statLabel(d.statut)}</span>
                        <small>${formatDate(d.created_at)}</small>
                    </div>
                    <h4>${escHtml(d.type_document.replaceAll('_', ' '))}</h4>
                    <p>
                        Demandée par : <strong>${escHtml(d.etudiant)}</strong>
                        — Pour : ${escHtml(d.motif)}
                    </p>
                    <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                        ${d.statut === 'en_attente' ? `
                            <button class="btn btn-primary btn-sm" onclick="genererDoc(${d.id})">
                                <i class="fas fa-check"></i> Générer
                            </button>
                            <button class="btn btn-outline btn-sm" onclick="refuserDoc(${d.id})">
                                Refuser
                            </button>
                        ` : d.statut === 'generee' && Number(d.disponible) ? `
                            <a href="${API}?action=download_document&id=${Number(d.id)}" target="_blank" rel="noopener"
                               class="btn btn-outline btn-sm">
                                <i class="fas fa-download"></i> Télécharger
                            </a>
                        ` : `<span class="text-muted" style="font-size:0.85rem;">${statLabel(d.statut)}</span>`}
                    </div>
                </div>`;
            }).join('');

        } catch (err) {
            listGroup.innerHTML = `<p class="text-danger">${escHtml(err.message)}</p>`;
        }
    }

    window.genererDoc = async (id) => {
        if (!confirm('Générer ce document ?')) return;
        try {
            await apiFetch('generer_document', { id });
            toast('Document généré avec succès !', 'success');
            loadDocuments();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

    window.refuserDoc = async (id) => {
        if (!confirm('Refuser cette demande ?')) return;
        try {
            await apiFetch('refuser_document', { id });
            toast('Demande refusée.', 'info');
            loadDocuments();
        } catch (err) {
            toast(err.message, 'error');
        }
    };

}); // fin DOMContentLoaded