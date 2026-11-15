#!/usr/bin/env python3
"""
Tests de bout en bout de My EPI App (aucune dépendance : bibliothèque standard Python).

Pré-requis : base importée + .env configuré + serveur lancé, par exemple :
    php -S 127.0.0.1:8000 -t .
Lancement :
    python3 tests/smoke_test.py http://127.0.0.1:8000

⚠ Ce script RÉINITIALISE les mots de passe (php bin/setup.php --demo) : à n'utiliser que sur une base de test.
"""
import http.cookiejar
import io
import json
import re
import subprocess
import sys
import urllib.error
import urllib.request
import uuid
from pathlib import Path

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8000").rstrip("/")
ROOT = Path(__file__).resolve().parent.parent
PASSED, FAILED = [], []


def check(name, cond, detail=""):
    (PASSED if cond else FAILED).append(name)
    print(("  ✔ " if cond else "  ✘ ") + name + ("" if cond else f"   <-- {detail}"))


class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.csrf = None
        self.last_headers = {}

    def raw(self, path, data=None, method=None, headers=None, csrf=True):
        h = dict(headers or {})
        if data is not None and "Content-Type" not in h:
            h["Content-Type"] = "application/json"
            data = json.dumps(data).encode()
        if csrf and self.csrf and (data is not None or method == "POST"):
            h.setdefault("X-CSRF-Token", self.csrf)
        req = urllib.request.Request(BASE + path, data=data, method=method or ("POST" if data is not None else "GET"), headers=h)
        try:
            r = self.opener.open(req)
        except urllib.error.HTTPError as e:
            r = e
        self.last_headers = dict(r.headers)
        return r.status if hasattr(r, "status") else r.code, r.read()

    def api(self, api, action, body=None, method=None, qs="", **kw):
        status, raw = self.raw(f"/api/{api}.php?action={action}{qs}", body, method, **kw)
        try:
            js = json.loads(raw)
        except Exception:
            js = {"success": False, "error": "non-JSON", "raw": raw[:200]}
        if js.get("csrf_token"):
            self.csrf = js["csrf_token"]
        return status, js

    def login(self, api, field, ident, pwd):
        return self.api(api, "login", {field: ident, "password": pwd})


def multipart(fields, files):
    b = "----" + uuid.uuid4().hex
    out = io.BytesIO()
    for k, v in fields.items():
        out.write(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
    for k, (fn, content, ctype) in files.items():
        out.write(f'--{b}\r\nContent-Disposition: form-data; name="{k}"; filename="{fn}"\r\nContent-Type: {ctype}\r\n\r\n'.encode())
        out.write(content + b"\r\n")
    out.write(f"--{b}--\r\n".encode())
    return out.getvalue(), {"Content-Type": f"multipart/form-data; boundary={b}"}


# ───────────────────────── préparation : mots de passe frais ─────────────────────────
print("Préparation : bin/setup.php --demo")
setup = subprocess.run(["php", "bin/setup.php", "--demo"], cwd=ROOT, capture_output=True, text=True).stdout
admin_pwd = re.search(r"Mot de passe:\s+(\S+)", setup).group(1)
demo = {m.group(2): m.group(3) for m in re.finditer(r"^\s+(etudiant|enseignant|scolarite)\s+(\S+)\s+(\S+)$", setup, re.M)}
assert admin_pwd and len(demo) >= 8, "setup.php n'a pas produit les comptes attendus"

E1, E2, E3 = "12345678", "87654321", "11223344"   # E1,E2 : classe 1 (filière 1) ; E3 : classe 3 (filière 2)
T1, T2 = "ENS-9087", "ENS-0045"
S1 = "SCO-0045"

print("\n[1] Authentification & session")
c = Client()
st, js = c.login("etudiant", "cin", E1, "mauvais-mdp")
check("mauvais mot de passe → 401", st == 401, st)
st, js = c.login("etudiant", "cin", "00000000", "x")
check("compte inexistant → même message 401", st == 401 and js.get("error") == "Identifiants incorrects.", js)
for _ in range(5):
    c.login("etudiant", "cin", "99999999", "x")
st, js = c.login("etudiant", "cin", "99999999", "x")
check("limitation des tentatives → 429 après 5 échecs", st == 429, st)

st, js = c.login("etudiant", "cin", E1, demo[E1])
check("connexion étudiant OK + jeton CSRF", st == 200 and js.get("csrf_token"), js)
probe = Client()
probe.login("etudiant", "cin", E1, demo[E1])
setcookie = probe.last_headers.get("Set-Cookie", "")
check("cookie de session HttpOnly + SameSite=Strict", "HttpOnly" in setcookie and "SameSite=Strict" in setcookie, setcookie)
check("pas de CORS « * »", probe.last_headers.get("Access-Control-Allow-Origin") is None, probe.last_headers)
check("en-têtes de sécurité présents", probe.last_headers.get("X-Content-Type-Options") == "nosniff" and probe.last_headers.get("X-Frame-Options") == "DENY")

st, js = c.api("etudiant", "get_dashboard", method="GET")
check("dashboard étudiant accessible", st == 200 and js.get("success"), js)
saved = c.csrf
c.csrf = None
st, js = c.api("etudiant", "add_reclamation", {"type": "Test", "details": "sans csrf"})
check("POST sans jeton CSRF → 403", st == 403, (st, js))
c.csrf = saved
st, js = c.api("etudiant", "add_reclamation", {"type": "Test", "details": "origine"}, headers={"Origin": "http://evil.example"})
check("POST depuis une autre origine → 403", st == 403, (st, js))
st, js = c.api("admin", "get_users", method="GET")
check("un étudiant ne peut pas appeler l'API admin → 403", st == 403, (st, js))
anon = Client()
st, js = anon.api("etudiant", "get_notes", method="GET")
check("accès sans session → 401", st == 401, st)

print("\n[2] Mots de passe")
st, js = c.api("etudiant", "change_password", {"current_password": "faux", "new_password": "Nouveau123"})
check("changement avec mauvais mot de passe actuel → 403", st == 403, js)
st, js = c.api("etudiant", "change_password", {"current_password": demo[E1], "new_password": "abc"})
check("nouveau mot de passe faible refusé → 400", st == 400, js)
st, js = c.api("etudiant", "change_password", {"current_password": demo[E1], "new_password": "Nouveau123"})
check("changement de mot de passe réel → 200", st == 200, js)
c2 = Client()
st, js = c2.login("etudiant", "cin", E1, demo[E1])
check("ancien mot de passe ne fonctionne plus", st == 401, st)
st, js = c2.login("etudiant", "cin", E1, "Nouveau123")
check("nouveau mot de passe fonctionne", st == 200, js)
demo[E1] = "Nouveau123"

print("\n[3] Enseignant : droits & validation des notes")
t1 = Client(); t1.login("enseignant", "matricule", T1, demo[T1])
t2 = Client(); t2.login("enseignant", "matricule", T2, demo[T2])
cm1 = {(x["classe_id"], x["matiere_id"]) for x in t1.api("enseignant", "get_classes_matieres", method="GET")[1]["classes_matieres"]}
cm2 = {(x["classe_id"], x["matiere_id"]) for x in t2.api("enseignant", "get_classes_matieres", method="GET")[1]["classes_matieres"]}
foreign = sorted(cm2 - cm1)
check("jeu de données : T2 a des classes/matières que T1 n'a pas", bool(foreign))
cl, ma = foreign[0]
st, js = t1.api("enseignant", "get_notes_classe", {"classe_id": cl, "matiere_id": ma})
check("lecture des notes d'une classe non enseignée → 403", st == 403, (st, js))
classes_t1 = {x[0] for x in cm1}
foreign_class = next(i for i in range(1, 20) if i not in classes_t1)
st, js = t1.api("enseignant", "get_liste_classe", {"classe_id": foreign_class})
check("liste d'une classe non enseignée → 403", st == 403, (st, js))

mine_cl, mine_ma = sorted(cm1)[0]
st, js = t1.api("enseignant", "get_notes_classe", {"classe_id": mine_cl, "matiere_id": mine_ma})
rows = js["notes"]
check("lecture des notes de SA classe → 200, sans doublon", st == 200 and len({r["etudiant_id"] for r in rows}) == len(rows), js)
sid = rows[0]["etudiant_id"]
st, js = t1.api("enseignant", "save_notes", {"notes": [{"etudiant_id": sid, "matiere_id": mine_ma, "cc": 25, "ds": 10, "examen": 10}]})
check("note > 20 refusée → 400", st == 400, js)
st, js = t1.api("enseignant", "save_notes", {"notes": [{"etudiant_id": sid, "matiere_id": mine_ma, "cc": -1, "ds": 10, "examen": 10}]})
check("note négative refusée → 400", st == 400, js)
st, js = t1.api("enseignant", "save_notes", {"notes": [{"etudiant_id": sid, "matiere_id": ma if ma != mine_ma else 999, "cc": 10, "ds": 10, "examen": 10}]})
check("notation d'une matière non enseignée → 403", st == 403, js)
other_student = 3  # étudiant d'une autre classe (classe 3)
st, js = t1.api("enseignant", "save_notes", {"notes": [{"etudiant_id": other_student, "matiere_id": mine_ma, "cc": 10, "ds": 10, "examen": 10}]})
check("notation d'un étudiant hors de sa classe → 403", st == 403, js)
st, js = t1.api("enseignant", "save_notes", {"notes": [{"etudiant_id": sid, "matiere_id": mine_ma, "cc": 0, "ds": 12.5, "examen": 14}]})
check("saisie valide (y compris note 0) → 200", st == 200, js)
st, js = t1.api("enseignant", "get_notes_classe", {"classe_id": mine_cl, "matiere_id": mine_ma})
r = next(x for x in js["notes"] if x["etudiant_id"] == sid)
check("moyenne recalculée par la base (0×0,2 + 12,5×0,3 + 14×0,5 = 10,75)", float(r["moyenne"]) == 10.75 and r["statut"] == "validee", r)

print("\n[4] Appel / absences")
st, js = t1.api("enseignant", "get_liste_classe", {"classe_id": mine_cl})
students = [e["etudiant_id"] for e in js["etudiants"]]
pres = [{"etudiant_id": s, "present": i != 0} for i, s in enumerate(students)]
st, js = t1.api("enseignant", "save_appel", {"classe_id": mine_cl, "matiere_id": mine_ma, "presences": pres, "date": "2026-01-12"})
check("appel enregistré", st == 200 and js.get("absents") == 1, js)
st, js = t1.api("enseignant", "save_appel", {"classe_id": mine_cl, "matiere_id": mine_ma, "presences": [{"etudiant_id": 3, "present": False}], "date": "2026-01-12"})
check("appel avec un étudiant d'une autre classe → 403", st == 403, js)
st, js = t1.api("enseignant", "save_appel", {"classe_id": mine_cl, "matiere_id": mine_ma, "presences": pres, "date": "2999-01-01"})
check("appel à une date future refusé → 400", st == 400, js)
st, js = t1.api("enseignant", "save_appel", {"classe_id": cl, "matiere_id": ma, "presences": pres, "date": "2026-01-12"})
check("appel pour une classe non enseignée → 403", st == 403, js)

print("\n[5] Scolarité : emploi du temps, notes, documents PDF")
s1 = Client(); s1.login("scolarite", "matricule", S1, demo[S1])
st, js = s1.api("scolarite", "add_creneau", {"classe_id": 1, "matiere_id": 2, "enseignant_id": 2, "jour": "Mardi", "heure_debut": "09:00", "heure_fin": "10:30", "salle": "NOUVELLE", "type_seance": "Cours"})
check("conflit classe/enseignant détecté → 409", st == 409, (st, js))
st, js = s1.api("scolarite", "add_creneau", {"classe_id": 1, "matiere_id": 2, "enseignant_id": 2, "jour": "Mardi", "heure_debut": "10:00", "heure_fin": "09:00", "salle": "X1", "type_seance": "Cours"})
check("heure de fin avant début → 400", st == 400, js)
st, js = s1.api("scolarite", "add_creneau", {"classe_id": 1, "matiere_id": 2, "enseignant_id": 2, "jour": "Dimanche", "heure_debut": "09:00", "heure_fin": "10:00", "salle": "X1", "type_seance": "Cours"})
check("jour invalide → 400", st == 400, js)
st, js = s1.api("scolarite", "add_creneau", {"classe_id": 1, "matiere_id": 2, "enseignant_id": 2, "jour": "Samedi", "heure_debut": "14:00", "heure_fin": "15:30", "salle": "SALLE-TEST", "type_seance": "TD"})
check("créneau valide créé", st == 201, js)
if st == 201:
    s1.api("scolarite", "delete_creneau", {"id": js["id"]})
st, js = s1.api("scolarite", "update_note", {"id": 1, "cc": 30, "ds": 10, "examen": 10})
check("update_note > 20 refusé → 400", st == 400, js)

e1 = Client(); e1.login("etudiant", "cin", E1, demo[E1])
e3 = Client(); e3.login("etudiant", "cin", E3, demo[E3])
for dtype in ("attestation_scolarite", "releve_notes", "attestation_presence", "convention_stage", "certificat_reussite"):
    st, js = e1.api("etudiant", "add_demande_doc", {"type_document": dtype, "motif": "Test automatique"})
    # (la base de démo contient déjà une demande « attestation_presence » en attente → 409 attendu dans ce cas)
    check(f"demande {dtype} créée (ou déjà en attente)", st in (201, 409), js)
    if st == 201:
        last = max(d["id"] for d in e1.api("etudiant", "get_demandes_docs", method="GET")[1]["demandes"])
        check(f"  id retourné = id réel de la demande ({dtype})", js["id"] == last, (js, last))
st, js = e1.api("etudiant", "add_demande_doc", {"type_document": "releve_notes", "motif": "doublon"})
check("doublon de demande en attente → 409", st == 409, js)
st, js = e1.api("etudiant", "add_demande_doc", {"type_document": "inexistant", "motif": "x"})
check("type de document invalide → 400", st == 400, js)

pending = [d for d in e1.api("etudiant", "get_demandes_docs", method="GET")[1]["demandes"] if d["statut"] == "en_attente"]
by_type = {d["type_document"]: d["id"] for d in pending}
for dtype in ("attestation_scolarite", "releve_notes", "attestation_presence", "convention_stage"):
    st, js = s1.api("scolarite", "generer_document", {"id": by_type[dtype]})
    check(f"génération PDF {dtype}", st == 200 and js.get("disponible"), js)
    st, raw = e1.raw(f"/api/etudiant.php?action=download_document&id={by_type[dtype]}")
    ct = e1.last_headers.get("Content-Type", "")
    check(f"  téléchargement {dtype} = vrai PDF", st == 200 and raw[:5] == b"%PDF-" and raw.rstrip().endswith(b"%%EOF") and "pdf" in ct, (st, ct, raw[:20]))
    if dtype == "releve_notes":
        pdf_path = Path("/tmp/releve_test.pdf"); pdf_path.write_bytes(raw)
st, raw = e3.raw(f"/api/etudiant.php?action=download_document&id={by_type['attestation_scolarite']}")
check("un autre étudiant ne peut PAS télécharger ce document → 404", st == 404, st)
st, js = s1.api("scolarite", "generer_document", {"id": by_type["attestation_scolarite"]})
check("régénération d'une demande déjà traitée → 409", st == 409, js)
st, js = s1.api("scolarite", "update_note", {"id": 1, "cc": 5, "ds": 5, "examen": 5})   # note 1 = étudiant 1 → insuffisant
check("la note modifiée par la scolarité passe en « insuffisant »", st == 200, js)
st, js = s1.api("scolarite", "generer_document", {"id": by_type["certificat_reussite"]})
check("certificat de réussite refusé si matières insuffisantes → 422", st == 422, (st, js))
st, js = s1.api("scolarite", "refuser_document", {"id": by_type["certificat_reussite"]})
check("refus de demande", st == 200, js)

print("\n[6] Upload de supports de cours")
def upload(client, fields, files):
    body, h = multipart(fields, files)
    status, raw = client.raw("/api/enseignant.php?action=upload_support", body, "POST", headers=h)
    return status, json.loads(raw)
st, js = upload(t1, {"matiere_id": mine_ma, "titre": "shell", "type_fichier": "PDF"}, {"fichier": ("shell.php", b"<?php system($_GET['c']); ?>", "application/x-php")})
check("upload d'un .php refusé → 400", st == 400, js)
st, js = upload(t1, {"matiere_id": mine_ma, "titre": "faux pdf", "type_fichier": "PDF"}, {"fichier": ("faux.pdf", b"<?php echo 'pwn'; ?>", "application/pdf")})
check("faux PDF (contenu ≠ extension) refusé → 400", st == 400, js)
real_pdf = Path("/tmp/releve_test.pdf").read_bytes()
st, js = upload(t1, {"matiere_id": ma if ma != mine_ma else 999, "titre": "hors matière", "type_fichier": "PDF"}, {"fichier": ("a.pdf", real_pdf, "application/pdf")})
check("upload pour une matière non enseignée → 403", st == 403, js)
st, js = upload(t1, {"matiere_id": mine_ma, "titre": "Cours test", "type_fichier": "PDF"}, {"fichier": ("cours.pdf", real_pdf, "application/pdf")})
check("upload PDF valide → 201", st == 201, js)
sup_id = js.get("id")
st, raw = e1.raw(f"/api/etudiant.php?action=download_support&id={sup_id}")
check("étudiant de la même filière télécharge le support", st == 200 and raw[:5] == b"%PDF-", st)
st, raw = e3.raw(f"/api/etudiant.php?action=download_support&id={sup_id}")
check("étudiant d'une autre filière → 404", st == 404, st)
st, raw = anon.raw("/uploads/cours/")
check("(serveur PHP intégré) liste de répertoire non exposée", st in (403, 404), st)
st, js = t1.api("enseignant", "delete_support", {"id": sup_id})
check("suppression du support", st == 200, js)

print("\n[7] Administration")
a = Client(); a.login("admin", "matricule", "ADMIN-001", admin_pwd)
st, js = a.api("admin", "create_user", {"prenom": "Test", "nom": "Faible", "email": "t.faible@epi.tn", "cin_matricule": "TEST0001", "role": "scolarite", "mot_de_passe": "abc"})
check("création avec mot de passe faible → 400", st == 400, js)
st, js = a.api("admin", "create_user", {"prenom": "Test", "nom": "Etud", "email": "t.etud@epi.tn", "cin_matricule": "TEST0002", "role": "etudiant"})
check("étudiant sans classe → 400", st == 400, js)
st, js = a.api("admin", "create_user", {"prenom": "Test", "nom": "Gen", "email": "t.gen@epi.tn", "cin_matricule": "TEST0003", "role": "scolarite"})
check("création sans mot de passe → mot de passe aléatoire fourni", st == 201 and len(js.get("mot_de_passe_temporaire", "")) >= 12, js)
new_id = js.get("id")
st, js = a.api("admin", "create_user", {"prenom": "Test", "nom": "Gen", "email": "t.gen@epi.tn", "cin_matricule": "TEST0004", "role": "scolarite", "mot_de_passe": "Valide1234"})
check("e-mail en doublon → 409", st == 409, js)
st, js = a.api("admin", "update_user", {"id": new_id, "email": "pas-un-email"})
check("update_user : e-mail invalide → 400", st == 400, js)
st, js = a.api("admin", "update_user", {"id": new_id, "statut": "nimportequoi"})
check("update_user : statut invalide → 400", st == 400, js)
admin_id = a.api("admin", "get_users", method="GET", qs="&role=admin")[1]["users"][0]["id"]
st, js = a.api("admin", "toggle_suspend", {"id": admin_id})
check("un admin ne peut pas se suspendre", st == 400, js)
st, js = a.api("admin", "delete_user", {"id": admin_id})
check("un admin ne peut pas se supprimer", st == 400, js)
st, js = a.api("admin", "send_notification", {"titre": "T", "message": "M", "type": "bidon", "destinataires": "tous"})
check("notification : type invalide → 400", st == 400, js)
st, js = a.api("admin", "get_stats", method="GET")
check("statistiques : activité RÉELLE par module", st == 200 and all("total" in m for m in js["modules_usage"]) and len(js["modules_usage"]) > 0, js)

print("\n[8] Suspension immédiate d'un compte & données brutes")
uid3 = a.api("admin", "get_users", method="GET", qs="&search=" + E3)[1]["users"][0]["id"]
st, js = e3.api("etudiant", "get_notes", method="GET")
check("étudiant actif accède à ses notes", st == 200, js)
a.api("admin", "toggle_suspend", {"id": uid3})
st, js = e3.api("etudiant", "get_notes", method="GET")
check("après suspension, la session est coupée immédiatement → 403", st == 403, (st, js))
a.api("admin", "toggle_suspend", {"id": uid3})
st, js = e1.api("etudiant", "add_reclamation", {"type": "L'été & <b>test</b>", "details": "O'Brien <script>alert(1)</script>"})
recl = e1.api("etudiant", "get_reclamations", method="GET")[1]["reclamations"][0]
check("données stockées BRUTES (pas de &amp; en base)", recl["type"] == "L'été & <b>test</b>" and "<script>" in recl["details"], recl)

print("\n[9] Divers")
st, js = anon.api("etudiant", "action_inexistante", method="GET")
check("action inconnue → 404 générique (sans écho de l'entrée)", st == 404 and "inexistante" not in json.dumps(js), js)
st, raw = anon.raw("/api/etudiant.php?action=login", b"{pas du json", "POST", headers={"Content-Type": "application/json"})
check("JSON invalide → 400 propre", st == 400 and b"success" in raw, (st, raw[:80]))
e1.api("etudiant", "logout", {})
st, js = e1.api("etudiant", "get_notes", method="GET")
check("après déconnexion → 401", st == 401, st)

print(f"\n{'='*60}\nRésultat : {len(PASSED)} réussis, {len(FAILED)} échoués")
if FAILED:
    print("Échecs :", *FAILED, sep="\n  - ")
sys.exit(1 if FAILED else 0)
