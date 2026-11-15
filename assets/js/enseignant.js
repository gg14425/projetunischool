const API = 'api/enseignant.php';
const apiFetch = (action, data = {}, method = 'POST') => epiFetch(API, action, data, method);
let currentEns = null;

function setEl(id, val) {
    const el = document.getElementById(id);
    if (el) el.innerText = val;
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

document.addEventListener('DOMContentLoaded', () => {

    const loginForm   = document.getElementById('login-form');
    const loginScreen = document.getElementById('login-screen');
    const appScreen   = document.getElementById('app-screen');
    const logoutBtn   = document.getElementById('logout-btn');
    const navItems    = document.querySelectorAll('.nav-item');
    const views       = document.querySelectorAll('.view');
    const pageTitle   = document.getElementById('current-page-title');
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = loginForm.querySelector('button[type="submit"]');
        btn.disabled = true;
        const matricule = document.getElementById('sco-id').value.trim(); // champ utilisé pour enseignant aussi
        const password  = document.getElementById('password').value;
        try {
            const res  = await apiFetch('login', { matricule, password });
            currentEns = res.user;
            loginScreen.classList.remove('active');
            appScreen.classList.add('active');
            populateUserInfo();
            loadDashboard();
            toast(`Bienvenue, ${currentEns.prenom} ${currentEns.nom} !`, 'success');
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false;
        }
    });

    logoutBtn.addEventListener('click', async () => {
        try { await apiFetch('logout'); } catch (_) {}
        currentEns = null;
        appScreen.classList.remove('active');
        loginScreen.classList.add('active');
        toast('Déconnecté.', 'info');
    });

    navItems.forEach(item => {
        item.addEventListener('click', () => {
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');
            pageTitle.innerText = item.innerText.trim();
            views.forEach(v => v.classList.remove('active'));
            const target = document.getElementById(item.getAttribute('data-target'));
            if (target) {
                target.classList.add('active');
                dispatchView(item.getAttribute('data-target'));
            }
        });
    });

    function populateUserInfo() {
        setEl('user-nom', `${currentEns?.prenom||''} ${currentEns?.nom||''}`);
        setEl('user-grade', currentEns?.grade || 'Enseignant');
    }

    function dispatchView(id) {
        const map = {
            'view-dashboard':  loadDashboard,
            'view-emploi':     loadEmploi,
            'view-attendance': loadSeances,
            'view-grading':    loadClassesMatieres,
            'view-courses':    loadCours,
            'view-messages':   loadMessages,
        };
        if (map[id]) map[id]();
    }
    async function loadDashboard() {
        try {
            const res = await apiFetch('get_dashboard', {}, 'GET');
            const d   = res.dashboard;
            setEl('kpi-seances',   d.seances_semaine ?? '0');
            setEl('kpi-etudiants', d.total_etudiants ?? '0');
            setEl('kpi-absences',  d.absences_saisies ?? '0');
            setEl('kpi-cours',     d.cours_uploades ?? '0');
        } catch (err) {
            console.warn('Dashboard:', err.message);
        }
        loadEmploi();
    }
    async function loadEmploi() {
        const c = document.getElementById('emploi-ens-container');
        if (!c) return;
        c.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</p>';
        try {
            const res    = await apiFetch('get_emploi', {}, 'GET');
            const jours  = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
            const seances = res.emploi || [];

            const grid = jours.map(j => {
                const s = seances.filter(x => x.jour === j);
                return `<div class="emploi-col">
                    <div class="emploi-day-header">${j}</div>
                    ${s.length ? s.map(x => `
                        <div class="emploi-seance emploi-${(x.type_seance||'cours').toLowerCase()}">
                            <strong>${escHtml(x.matiere_nom)}</strong><br>
                            <small>${escHtml(x.heure_debut)}–${escHtml(x.heure_fin)}</small><br>
                            <small><i class="fas fa-users"></i> ${escHtml(x.classe_nom)}</small><br>
                            <small><i class="fas fa-map-marker-alt"></i> ${escHtml(x.salle)}</small>
                            <span class="badge badge-info">${x.type_seance}</span>
                        </div>`).join('') : '<p class="text-muted" style="font-size:0.8rem;padding:8px">Libre</p>'}
                </div>`;
            }).join('');

            c.innerHTML = `<div class="emploi-grid">${grid}</div>`;
        } catch (err) {
            c.innerHTML = `<p class="text-danger">${escHtml(err.message)}</p>`;
        }
    }
    async function loadSeances() {
        const sel = document.getElementById('class-select-appel');
        if (!sel) return;
        try {
            const res = await apiFetch('get_seances', {}, 'GET');
            const seances = res.seances || [];
            sel.innerHTML = '<option value="">-- Choisir une séance --</option>' +
                seances.map(s => `<option value="${s.classe_id}:${s.matiere_id}">${escHtml(s.classe_nom)} — ${escHtml(s.matiere_nom)} (${escHtml(s.jour)} ${escHtml(s.heure_debut)})</option>`).join('');

            sel.onchange = async (e) => {
                if (!e.target.value) return;
                const [classe_id, matiere_id] = e.target.value.split(':');
                const list = document.getElementById('attendance-list');
                if (list) {
                    list.style.display = 'block';
                    await loadListeClasse(classe_id, matiere_id);
                }
            };
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function loadListeClasse(classe_id, matiere_id) {
        const tbody = document.querySelector('#appel-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="3">Chargement…</td></tr>';
        try {
            const res  = await apiFetch('get_liste_classe', { classe_id }, 'POST');
            const lst  = res.etudiants || [];
            tbody.innerHTML = lst.map(e => `<tr>
                <td>${escHtml(e.prenom)} ${escHtml(e.nom)}</td>
                <td>${escHtml(e.cin_matricule)}</td>
                <td>
                    <label class="toggle-switch">
                        <input type="checkbox" class="presence-toggle" data-etudiant="${e.etudiant_id}" checked>
                        <span class="slider">Présent</span>
                    </label>
                </td>
            </tr>`).join('');
            const btnAppel = document.getElementById('btn-save-appel');
            if (btnAppel) {
                btnAppel.onclick = async () => {
                    const presences = [];
                    document.querySelectorAll('.presence-toggle').forEach(t => {
                        presences.push({ etudiant_id: t.dataset.etudiant, present: t.checked });
                    });
                    try {
                        await apiFetch('save_appel', { classe_id, matiere_id, presences, date: new Date().toISOString().split('T')[0] });
                        toast('Appel enregistré avec succès !', 'success');
                    } catch (err) {
                        toast(err.message, 'error');
                    }
                };
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="3" class="text-danger">${escHtml(err.message)}</td></tr>`;
        }
    }
    async function loadClassesMatieres() {
        const selC = document.getElementById('class-select-notes');
        if (!selC) return;
        try {
            const res = await apiFetch('get_classes_matieres', {}, 'GET');
            const cms = res.classes_matieres || [];
            selC.innerHTML = '<option value="">-- Choisir classe/matière --</option>' +
                cms.map(c => `<option value="${c.classe_id}:${c.matiere_id}">${escHtml(c.classe_nom)} — ${escHtml(c.matiere_nom)}</option>`).join('');

            selC.onchange = async (e) => {
                if (!e.target.value) return;
                const [classe_id, matiere_id] = e.target.value.split(':');
                const list = document.getElementById('grading-list');
                if (list) {
                    list.style.display = 'block';
                    await loadNotesClasse(classe_id, matiere_id);
                }
            };
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function loadNotesClasse(classe_id, matiere_id) {
        const tbody = document.querySelector('#notes-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6">Chargement…</td></tr>';
        try {
            const res  = await apiFetch('get_notes_classe', { classe_id, matiere_id }, 'POST');
            const etuds = res.notes || [];
            tbody.innerHTML = etuds.map(e => {
                const moy = parseFloat(e.moyenne) || 0;
                return `<tr>
                    <td>${escHtml(e.prenom)} ${escHtml(e.nom)}</td>
                    <td><input type="number" class="grade-input" data-field="cc" data-id="${e.etudiant_id}" value="${e.cc ?? ''}" min="0" max="20" step="0.25"></td>
                    <td><input type="number" class="grade-input" data-field="ds" data-id="${e.etudiant_id}" value="${e.ds ?? ''}" min="0" max="20" step="0.25"></td>
                    <td><input type="number" class="grade-input" data-field="examen" data-id="${e.etudiant_id}" value="${e.examen ?? ''}" min="0" max="20" step="0.25"></td>
                    <td><strong class="moy-cell" style="color:${moy>=10?'#27ae60':'#C9242E'}">${e.moyenne ?? '—'}</strong></td>
                    <td><span class="status-badge ${moy>=10?'success':'danger'}">${e.statut||'en_attente'}</span></td>
                </tr>`;
            }).join('');
            document.querySelectorAll('.grade-input').forEach(input => {
                input.addEventListener('input', () => {
                    const row   = input.closest('tr');
                    const cc    = parseFloat(row.querySelector('[data-field="cc"]').value) || 0;
                    const ds    = parseFloat(row.querySelector('[data-field="ds"]').value) || 0;
                    const exam  = parseFloat(row.querySelector('[data-field="examen"]').value) || 0;
                    if (cc || ds || exam) {
                        const moy = (cc*0.2 + ds*0.3 + exam*0.5).toFixed(2);
                        const el  = row.querySelector('.moy-cell');
                        if (el) { el.innerText = moy; el.style.color = moy>=10?'#27ae60':'#C9242E'; }
                    }
                });
            });
            const btn = document.getElementById('btn-save-notes');
            if (btn) {
                btn.onclick = async () => {
                    const notes = [];
                    document.querySelectorAll('#notes-table tbody tr').forEach(row => {
                        const id   = row.querySelector('.grade-input')?.dataset.id;
                        const cc   = row.querySelector('[data-field="cc"]')?.value || null;
                        const ds   = row.querySelector('[data-field="ds"]')?.value || null;
                        const exam = row.querySelector('[data-field="examen"]')?.value || null;
                        if (id) notes.push({ etudiant_id: id, matiere_id, cc, ds, examen: exam });
                    });
                    try {
                        await apiFetch('save_notes', { notes });
                        toast('Notes sauvegardées !', 'success');
                    } catch (err) {
                        toast(err.message, 'error');
                    }
                };
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-danger">${escHtml(err.message)}</td></tr>`;
        }
    }
    async function loadCours() {
        const c = document.getElementById('cours-ens-container');
        if (!c) return;
        c.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</p>';
        try {
            const selM = document.getElementById('up-matiere');
            if (selM) {
                const cm = (await apiFetch('get_classes_matieres', {}, 'GET')).classes_matieres || [];
                const seen = new Map(cm.map(x => [x.matiere_id, x.matiere_nom]));
                selM.innerHTML = '<option value="">— Matière —</option>' +
                    [...seen].map(([id, nom]) => `<option value="${Number(id)}">${escHtml(nom)}</option>`).join('');
            }
            const res   = await apiFetch('get_cours', {}, 'GET');
            const cours = res.cours || [];
            if (!cours.length) { c.innerHTML = '<p>Aucun support uploadé.</p>'; return; }
            c.innerHTML = cours.map(s => `
                <div class="list-item">
                    <div class="notif-header">
                        <h4><i class="fas fa-file-pdf"></i> ${escHtml(s.titre)}</h4>
                        <button class="btn btn-outline btn-sm" onclick="deleteSupport(${s.id})">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </div>
                    <p>${escHtml(s.matiere_nom)} · <span class="badge badge-info">${escHtml(s.type_fichier)}</span></p>
                    <small>${formatDate(s.date_upload)}</small>
                    ${s.disponible ? `<a href="${API}?action=download_support&id=${Number(s.id)}" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-download"></i></a>` : ''}
                </div>`).join('');
        } catch (err) {
            c.innerHTML = `<p class="text-danger">${escHtml(err.message)}</p>`;
        }
    }

    window.deleteSupport = async (id) => {
        if (!confirm('Supprimer ce support ?')) return;
        try {
            await apiFetch('delete_support', { id });
            toast('Support supprimé.', 'success');
            loadCours();
        } catch (err) {
            toast(err.message, 'error');
        }
    };
    async function loadMessages() {
        const c = document.getElementById('messages-container');
        if (!c) return;
        c.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</p>';
        try {
            const selC = document.getElementById('msg-classe');
            if (selC && selC.options.length <= 1) {
                const cm = (await apiFetch('get_classes_matieres', {}, 'GET')).classes_matieres || [];
                const seen = new Map(cm.map(x => [x.classe_id, x.classe_nom]));
                selC.innerHTML = '<option value="">Toutes mes classes</option>' +
                    [...seen].map(([id, nom]) => `<option value="${Number(id)}">${escHtml(nom)}</option>`).join('');
            }
            const res  = await apiFetch('get_messages', {}, 'GET');
            const msgs = res.messages || [];
            if (!msgs.length) { c.innerHTML = '<p>Aucun message.</p>'; return; }
            c.innerHTML = msgs.map(m => `
                <div class="list-item">
                    <div class="notif-header">
                        <h4>${escHtml(m.objet)}</h4>
                        <small>${formatDate(m.date_envoi)}</small>
                    </div>
                    <p>${escHtml(m.contenu)}</p>
                    <small><i class="fas fa-users"></i> À: ${escHtml(m.destinataire_role)}</small>
                </div>`).join('');
        } catch (err) {
            c.innerHTML = `<p class="text-danger">${escHtml(err.message)}</p>`;
        }
    }
    const msgForm = document.getElementById('message-form');
    if (msgForm) {
        msgForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const objet            = document.getElementById('msg-objet').value;
                const contenu          = document.getElementById('msg-contenu').value;
                const destinataire_role = document.getElementById('msg-dest').value;
                const classe_id = document.getElementById('msg-classe')?.value || null;
                await apiFetch('send_message', { objet, contenu, destinataire_role, classe_id });
                toast('Message envoyé.', 'success');
                msgForm.reset();
                loadMessages();
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    }


    const upForm = document.getElementById('upload-form');
    if (upForm) {
        upForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = upForm.querySelector('button[type="submit"]');
            const file = document.getElementById('up-fichier').files[0];
            if (!file) { toast('Choisissez un fichier.', 'error'); return; }
            const fd = new FormData();
            fd.append('matiere_id', document.getElementById('up-matiere').value);
            fd.append('titre', document.getElementById('up-titre').value);
            fd.append('type_fichier', document.getElementById('up-type').value);
            fd.append('fichier', file);
            btn.disabled = true;
            try {
                await epiUpload(API, 'upload_support', fd);
                toast('Support ajouté.', 'success');
                upForm.reset();
                loadCours();
            } catch (err) {
                toast(err.message, 'error');
            } finally {
                btn.disabled = false;
            }
        });
    }

}); 