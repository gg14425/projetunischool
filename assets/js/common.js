/* =============================================================
   common.js — utilitaires partagés par les 4 portails
   (escHtml, toast, appels API avec jeton CSRF)
============================================================= */
'use strict';

let CSRF_TOKEN = null;

/** Échappe une valeur avant insertion dans du HTML (anti-XSS). */
function escHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

/** Notification visuelle — le message est inséré en TEXTE (jamais en HTML). */
function toast(msg, type = 'info') {
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    const i = document.createElement('i');
    i.className = 'fas fa-' + (type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle');
    t.appendChild(i);
    t.appendChild(document.createTextNode(' ' + msg));
    document.body.appendChild(t);
    setTimeout(() => t.classList.add('show'), 50);
    setTimeout(() => {
        t.classList.remove('show');
        setTimeout(() => t.remove(), 400);
    }, 3500);
}

/**
 * Appel JSON vers une API. Mémorise le jeton CSRF reçu à la connexion et
 * l'envoie (en-tête X-CSRF-Token) sur toute requête modifiante.
 */
async function epiFetch(base, action, data = {}, method = 'POST') {
    const opts = { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } };
    if (method !== 'GET') {
        opts.body = JSON.stringify(data);
        if (CSRF_TOKEN) opts.headers['X-CSRF-Token'] = CSRF_TOKEN;
    }
    const res = await fetch(`${base}?action=${action}`, opts);
    let json;
    try { json = await res.json(); } catch (_) { throw new Error('Réponse serveur invalide.'); }
    if (json.csrf_token) CSRF_TOKEN = json.csrf_token;
    if (!json.success) {
        if (res.status === 401 && action !== 'login' && action !== 'logout') {
            toast('Session expirée, veuillez vous reconnecter.', 'error');
            setTimeout(() => location.reload(), 1500);
        }
        throw new Error(json.error || 'Erreur serveur');
    }
    return json;
}

/** Envoi de fichier (multipart) avec jeton CSRF. */
async function epiUpload(base, action, formData) {
    const res = await fetch(`${base}?action=${action}`, {
        method: 'POST', credentials: 'same-origin', body: formData,
        headers: CSRF_TOKEN ? { 'X-CSRF-Token': CSRF_TOKEN } : {},
    });
    let json;
    try { json = await res.json(); } catch (_) { throw new Error('Réponse serveur invalide.'); }
    if (!json.success) throw new Error(json.error || 'Erreur serveur');
    return json;
}
