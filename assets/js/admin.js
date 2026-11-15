const API = 'api/admin.php';
const apiFetch = (action, data = {}, method = 'POST') => epiFetch(API, action, data, method);

function setEl(id, val) { const el = document.getElementById(id); if (el) el.innerText = val; }
function formatDate(d)   { if (!d) return '—'; return new Date(d).toLocaleDateString('fr-FR', { day:'2-digit', month:'short', year:'numeric' }); }

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
        const matricule = document.getElementById('admin-id').value.trim();
        const password  = document.getElementById('password').value;
        try {
            const res = await apiFetch('login', { matricule, password });
            loginScreen.classList.remove('active');
            appScreen.classList.add('active');
            setEl('user-name-display', `${res.user.prenom} ${res.user.nom}`);
            loadDashboard();
            toast(`Bienvenue, ${res.user.prenom} !`, 'success');
        } catch (err) {
            toast(err.message, 'error');
        } finally { btn.disabled = false; }
    });

    logoutBtn.addEventListener('click', async () => {
        try { await apiFetch('logout'); } catch (_) {}
        appScreen.classList.remove('active');
        loginScreen.classList.add('active');
    });
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');
            pageTitle.innerText = item.innerText.trim();
            views.forEach(v => v.classList.remove('active'));
            const target = document.getElementById(item.getAttribute('data-target'));
            if (target) { target.classList.add('active'); dispatchView(item.getAttribute('data-target')); }
        });
    });

    function dispatchView(id) {
        const map = {
            'view-dashboard':    loadDashboard,
            'view-users':        loadUsers,
            'view-roles':        () => {},
            'view-notifications':loadNotifications,
            'view-stats':        loadStats,
            'view-logs':         loadLogs,
        };
        if (map[id]) map[id]();
    }
    async function loadDashboard() {
        try {
            const res = await apiFetch('get_dashboard', {}, 'GET');
            const d   = res.dashboard;
            setEl('kpi-etudiants',   d.total_etudiants   ?? '—');
            setEl('kpi-enseignants', d.total_enseignants  ?? '—');
            setEl('kpi-users',       d.total_users        ?? '—');
            setEl('kpi-alertes',     d.alertes_systeme    ?? '—');

            // Activité récente
            const listEl = document.getElementById('recent-activity-list');
            if (listEl && d.recent_logs?.length) {
                listEl.innerHTML = d.recent_logs.map(l => `
                    <div class="list-item">
                        <span class="log-badge log-${l.action}">${l.action}</span>
                        <h4>${escHtml(l.details)}</h4>
                        <p>${escHtml(l.user_nom ?? 'Système')} · ${formatDate(l.created_at)}</p>
                        <small>IP: ${l.ip_address}</small>
                    </div>`).join('');
            }
        } catch (err) { console.warn('Dashboard:', err.message); }
    }
    async function loadUsers(search = '', role = '', statut = '') {
        const tbody = document.querySelector('#users-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" class="text-muted">Chargement…</td></tr>';
        try {
            const params = new URLSearchParams({ action: 'get_users' });
            if (search) params.set('search', search);
            if (role)   params.set('role', role);
            if (statut) params.set('statut', statut);

            const res  = await fetch(`${API}?${params}`, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.success) throw new Error(json.error);
            const users = json.users || [];

            if (!users.length) { tbody.innerHTML = '<tr><td colspan="6">Aucun utilisateur.</td></tr>'; return; }

            tbody.innerHTML = users.map(u => {
                const avatar = u.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(u.prenom+'+'+u.nom)}&background=C9242E&color=fff&size=34`;
                const statusDot = u.statut === 'actif' ? 'active' : u.statut === 'suspendu' ? 'suspended' : 'inactive';
                const roleColors = { etudiant:'role-etudiant', enseignant:'role-enseignant', scolarite:'role-scolarite', admin:'role-admin' };
                return `<tr>
                    <td><div class="user-cell">
                        <img src="${avatar}" alt="" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(u.prenom+'+'+u.nom)}&background=999&color=fff&size=34'">
                        <div><strong>${escHtml(u.prenom)} ${escHtml(u.nom)}</strong><br><small>${escHtml(u.email)}</small></div>
                    </div></td>
                    <td><span class="role-badge ${roleColors[u.role]||''}">${u.role}</span></td>
                    <td>${escHtml(u.cin_matricule)}</td>
                    <td>${escHtml(u.email)}</td>
                    <td><span class="status-dot ${statusDot}"></span> ${escHtml(u.statut)}</td>
                    <td class="action-buttons">
                        <button class="btn-action edit"    onclick="openEditUser(${u.id})"    title="Modifier"><i class="fas fa-edit"></i></button>
                        <button class="btn-action suspend" onclick="toggleSuspend(${u.id},'${u.statut}')" title="${u.statut==='actif'?'Suspendre':'Réactiver'}">
                            <i class="fas fa-${u.statut==='actif'?'ban':'check-circle'}"></i></button>
                        <button class="btn-action delete"  onclick="confirmDelete(${u.id},'${u.prenom} ${u.nom}')" title="Supprimer">
                            <i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            }).join('');
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-danger">${escHtml(err.message)}</td></tr>`;
        }
    }
    const searchInput  = document.getElementById('search-user');
    const filterRole   = document.getElementById('filter-role');
    const filterStatut = document.getElementById('filter-status');

    function applyFilters() {
        loadUsers(searchInput?.value.trim() || '', filterRole?.value || '', filterStatut?.value || '');
    }
    if (searchInput)  searchInput.addEventListener('input',  debounce(applyFilters, 400));
    if (filterRole)   filterRole.addEventListener('change',  applyFilters);
    if (filterStatut) filterStatut.addEventListener('change',applyFilters);
    const formCreate = document.getElementById('form-create-user');
    if (formCreate) {
        // Peupler les selects classe / département
        formCreate.addEventListener('focusin', async () => {
            if (!formCreate.dataset.loaded) {
                try {
                    const [rc, rd] = await Promise.all([
                        apiFetch('get_classes',     {}, 'GET'),
                        apiFetch('get_departements',{}, 'GET'),
                    ]);
                    const selClasse = document.getElementById('create-classe');
                    const selDept   = document.getElementById('create-dept');
                    if (selClasse) selClasse.innerHTML = '<option value="">— Classe —</option>' +
                        (rc.classes||[]).map(c=>`<option value="${c.id}">${escHtml(c.nom)}</option>`).join('');
                    if (selDept) selDept.innerHTML = '<option value="">— Département —</option>' +
                        (rd.departements||[]).map(d=>`<option value="${d.id}">${escHtml(d.nom)}</option>`).join('');
                    formCreate.dataset.loaded = '1';
                } catch (_) {}
            }
        }, { once: false });
        const roleSelect = document.getElementById('create-role');
        if (roleSelect) {
            roleSelect.addEventListener('change', () => {
                document.getElementById('field-classe')?.classList.toggle('hidden', roleSelect.value !== 'etudiant');
                document.getElementById('field-dept')?.classList.toggle('hidden',   roleSelect.value !== 'enseignant');
                document.getElementById('field-grade')?.classList.toggle('hidden',  roleSelect.value !== 'enseignant');
                document.getElementById('field-poste')?.classList.toggle('hidden',  roleSelect.value !== 'scolarite');
            });
        }

        formCreate.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = formCreate.querySelector('button[type="submit"]');
            btn.disabled = true;
            try {
                const data = {
                    prenom:        document.getElementById('create-prenom').value.trim(),
                    nom:           document.getElementById('create-nom').value.trim(),
                    email:         document.getElementById('create-email').value.trim(),
                    cin_matricule: document.getElementById('create-cin').value.trim(),
                    role:          document.getElementById('create-role').value,
                    mot_de_passe:  document.getElementById('create-password').value,
                    classe_id:     document.getElementById('create-classe')?.value || null,
                    departement_id:document.getElementById('create-dept')?.value   || null,
                    grade:         document.getElementById('create-grade')?.value  || null,
                    poste:         document.getElementById('create-poste')?.value  || null,
                };
                await apiFetch('create_user', data);
                toast('Compte créé avec succès !', 'success');
                closeModal('modal-create-user');
                formCreate.reset();
                loadUsers();
            } catch (err) { toast(err.message, 'error'); }
            finally { btn.disabled = false; }
        });
    }
    window.openEditUser = async (id) => {
        try {
            const url = `${API}?action=get_user&id=${id}`;
            const res = await fetch(url, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.success) throw new Error(json.error);
            const u = json.user;

            document.getElementById('edit-user-id').value    = u.id;
            document.getElementById('edit-user-prenom').value= u.prenom;
            document.getElementById('edit-user-nom').value   = u.nom;
            document.getElementById('edit-user-email').value = u.email;
            document.getElementById('edit-user-statut').value= u.statut;
            document.getElementById('modal-edit-user').style.display = 'flex';
        } catch (err) { toast(err.message, 'error'); }
    };

    const formEdit = document.getElementById('form-edit-user');
    if (formEdit) {
        formEdit.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = formEdit.querySelector('button[type="submit"]');
            btn.disabled = true;
            try {
                const data = {
                    id:     parseInt(document.getElementById('edit-user-id').value),
                    prenom: document.getElementById('edit-user-prenom').value.trim(),
                    nom:    document.getElementById('edit-user-nom').value.trim(),
                    email:  document.getElementById('edit-user-email').value.trim(),
                    statut: document.getElementById('edit-user-statut').value,
                };
                const newPass = document.getElementById('edit-user-password')?.value;
                if (newPass) data.mot_de_passe = newPass;

                await apiFetch('update_user', data);
                toast('Utilisateur mis à jour.', 'success');
                closeModal('modal-edit-user');
                loadUsers();
            } catch (err) { toast(err.message, 'error'); }
            finally { btn.disabled = false; }
        });
    }
    window.toggleSuspend = async (id, statut) => {
        const action = statut === 'actif' ? 'suspendre' : 'réactiver';
        if (!confirm(`Voulez-vous ${action} ce compte ?`)) return;
        try {
            const res = await apiFetch('toggle_suspend', { id });
            toast(`Compte ${res.statut}.`, 'success');
            loadUsers();
        } catch (err) { toast(err.message, 'error'); }
    };
    window.confirmDelete = (id, name) => {
        document.getElementById('delete-user-name').innerText = name;
        document.getElementById('delete-user-id').value       = id;
        document.getElementById('modal-delete').style.display = 'flex';
    };

    const formDelete = document.getElementById('form-delete-user');
    if (formDelete) {
        formDelete.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id  = parseInt(document.getElementById('delete-user-id').value);
            const btn = formDelete.querySelector('button[type="submit"]');
            btn.disabled = true;
            try {
                await apiFetch('delete_user', { id });
                toast('Compte supprimé.', 'success');
                closeModal('modal-delete');
                loadUsers();
            } catch (err) { toast(err.message, 'error'); }
            finally { btn.disabled = false; }
        });
    }
    async function loadNotifications() {
        const listEl = document.getElementById('notif-history-list');
        if (!listEl) return;
        listEl.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement…</p>';
        try {
            const res  = await apiFetch('get_notifications', {}, 'GET');
            const list = res.notifications || [];
            if (!list.length) { listEl.innerHTML = '<p>Aucune notification.</p>'; return; }
            listEl.innerHTML = list.map(n => `
                <div class="list-item">
                    <div class="notif-header">
                        <span class="badge badge-${n.type==='alerte'?'danger':n.type==='urgence'?'danger':'info'}">${escHtml(n.type)}</span>
                        <small>${formatDate(n.envoye_le)}</small>
                    </div>
                    <h4>${escHtml(n.titre)}</h4>
                    <p>${escHtml(n.message)}</p>
                    <small><i class="fas fa-users"></i> ${escHtml(n.destinataires)} · Par: ${escHtml(n.auteur_nom)}</small>
                </div>`).join('');
        } catch (err) { listEl.innerHTML = `<p class="text-danger">${escHtml(err.message)}</p>`; }
    }

    const formNotif = document.getElementById('form-notification');
    if (formNotif) {
        formNotif.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = formNotif.querySelector('button[type="submit"]');
            btn.disabled = true;
            try {
                await apiFetch('send_notification', {
                    titre:        document.getElementById('notif-titre').value.trim(),
                    message:      document.getElementById('notif-message').value.trim(),
                    type:         document.getElementById('notif-type').value,
                    destinataires:document.getElementById('notif-dest').value,
                });
                toast('Notification envoyée !', 'success');
                formNotif.reset();
                loadNotifications();
            } catch (err) { toast(err.message, 'error'); }
            finally { btn.disabled = false; }
        });
    }
    async function loadStats() {
        try {
            const res = await apiFetch('get_stats', {}, 'GET');
            // Donut par rôle
            const rolesEl = document.getElementById('stats-roles');
            if (rolesEl && res.users_by_role) {
                const total = res.users_by_role.reduce((s, r) => s + parseInt(r.total), 0);
                rolesEl.innerHTML = res.users_by_role.map(r => `
                    <div class="legend-item">
                        <span class="dot" style="background:${roleColor(r.role)}"></span>
                        ${escHtml(r.role)} — ${escHtml(r.total)} (${total>0?Math.round(r.total/total*100):0}%)
                    </div>`).join('');
            }
        } catch (err) { console.warn('Stats:', err.message); }
    }

    function roleColor(r) {
        return { etudiant:'#C9242E', enseignant:'#1C3B70', scolarite:'#27ae60', admin:'#f39c12' }[r] || '#999';
    }

    async function loadLogs() {
        const tbody = document.querySelector('#logs-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Chargement…</td></tr>';
        try {
            const res  = await fetch(`${API}?action=get_logs&limit=30`, { credentials: 'same-origin' });
            const json = await res.json();
            const logs = json.logs || [];
            if (!logs.length) { tbody.innerHTML = '<tr><td colspan="5">Aucun log.</td></tr>'; return; }
            tbody.innerHTML = logs.map(l => `<tr>
                <td><small>${formatDate(l.created_at)}</small></td>
                <td>${escHtml(l.user_nom ?? 'Système')}</td>
                <td><span class="log-badge log-${l.action}">${l.action}</span></td>
                <td>${escHtml(l.details)}</td>
                <td>${l.ip_address}</td>
            </tr>`).join('');
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger">${escHtml(err.message)}</td></tr>`;
        }
    }
    window.loadPermissions = (role) => {
        const panel = document.getElementById('permissions-panel');
        if (!panel) return;
        if (!role) { panel.style.display = 'none'; return; }
        panel.style.display = 'block';
        const perms = {
            scolarite: { notes:true, emploi:true, users:false, annonces:true, stats:false, finances:false },
            enseignant: { notes:true, emploi:false, users:false, annonces:false, stats:false, finances:false },
            etudiant:   { notes:false, emploi:false, users:false, annonces:false, stats:false, finances:false },
        };
        const p = perms[role] || {};
        ['notes','emploi','users','annonces','stats','finances'].forEach(k => {
            const el = document.getElementById(`perm-${k}`);
            if (el) el.checked = !!p[k];
        });
    };
    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'none';
    }

    document.addEventListener('click', (e) => {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            if (e.target === m) m.style.display = 'none';
        });
    });
    function debounce(fn, ms) {
        let timer;
        return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
    }
    window.showView = (id) => {
        views.forEach(v => v.classList.remove('active'));
        navItems.forEach(n => n.classList.remove('active'));
        const t = document.getElementById(id);
        if (t) t.classList.add('active');
        const n = document.querySelector(`[data-target="${id}"]`);
        if (n) { n.classList.add('active'); pageTitle.innerText = n.innerText.trim(); }
        dispatchView(id);
    };

});
