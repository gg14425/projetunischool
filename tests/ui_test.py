#!/usr/bin/env python3
"""
Test d'interface avec un vrai navigateur (Playwright + Chromium).
    pip install playwright && playwright install chromium
    python3 tests/ui_test.py http://127.0.0.1:8000
Réinitialise les mots de passe de démonstration (php bin/setup.php --demo) : base de TEST uniquement.
"""
import re
import subprocess
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8000").rstrip("/")
ROOT = Path(__file__).resolve().parent.parent
OK, KO = [], []


def check(name, cond, detail=""):
    (OK if cond else KO).append(name)
    print(("  ✔ " if cond else "  ✘ ") + name + ("" if cond else f"   <-- {detail}"))


setup = subprocess.run(["php", "bin/setup.php", "--demo"], cwd=ROOT, capture_output=True, text=True).stdout
admin_pwd = re.search(r"Mot de passe:\s+(\S+)", setup).group(1)
demo = {m.group(2): m.group(3) for m in re.finditer(r"^\s+(etudiant|enseignant|scolarite)\s+(\S+)\s+(\S+)$", setup, re.M)}

pdf = Path("/tmp/ui_upload.pdf")
subprocess.run(["php", "-r", "require 'includes/MiniPdf.php'; $p=new MiniPdf('t'); $p->text(50,50,'Support UI'); file_put_contents('/tmp/ui_upload.pdf',$p->output());"], cwd=ROOT)


def new_page(browser, errors, bad):
    ctx = browser.new_context(accept_downloads=True)
    page = ctx.new_page()
    page.on("pageerror", lambda e: errors.append(str(e)))
    page.on("console", lambda m: errors.append(m.text) if m.type == "error" and "favicon" not in m.text and "Failed to load resource" not in m.text else None)
    page.on("response", lambda r: bad.append(f"{r.status} {r.url}") if "/api/" in r.url and r.status >= 400 and "action=login" not in r.url else None)
    return page


def login(page, path, ident_id, ident, pwd):
    page.goto(BASE + path)
    page.fill(f"#{ident_id}", ident)
    page.fill("#password", pwd)
    page.click("#login-form button[type=submit]")
    page.wait_for_selector("#app-screen.active", timeout=8000)


def visit_all_views(page, name, errors, bad):
    items = page.locator(".nav-item")
    for i in range(items.count()):
        try:
            items.nth(i).click(timeout=3000)
            page.wait_for_timeout(350)
        except Exception as e:
            errors.append(f"clic nav {i}: {e}")
    check(f"{name} : toutes les vues se chargent sans erreur JS", not errors, errors[:3])
    check(f"{name} : aucune réponse API en erreur pendant la navigation", not bad, bad[:3])


with sync_playwright() as pw:
    browser = pw.chromium.launch()

    # ───────── Enseignant ─────────
    print("\n[Enseignant]")
    errors, bad = [], []
    page = new_page(browser, errors, bad)
    login(page, "/enseignant.html", "sco-id", "ENS-9087", demo["ENS-9087"])
    check("connexion enseignant", True)

    page.click('[data-target="view-attendance"]')
    page.wait_for_timeout(500)
    opts = page.locator("#class-select-appel option").count()
    check("appel : la liste des séances est chargée depuis l'API", opts > 1, opts)
    page.select_option("#class-select-appel", index=1)
    page.wait_for_selector("#appel-table tbody tr input.presence-toggle", state="attached", timeout=5000)
    n_rows = page.locator("#appel-table tbody tr").count()
    check("appel : la liste des étudiants s'affiche", n_rows >= 1, n_rows)
    page.locator("#appel-table .presence-toggle").first.evaluate("e => e.click()")
    page.click("#btn-save-appel")
    page.wait_for_selector(".toast-success", timeout=4000)
    check("appel : enregistrement confirmé (toast succès)", True)

    page.click('[data-target="view-grading"]')
    page.wait_for_timeout(500)
    page.select_option("#class-select-notes", index=1)
    page.wait_for_selector("#notes-table tbody tr input.grade-input", state="attached", timeout=5000)
    first = page.locator("#notes-table tbody tr").first
    first.locator('[data-field="cc"]').fill("12")
    first.locator('[data-field="ds"]').fill("13")
    first.locator('[data-field="examen"]').fill("14")
    page.click("#btn-save-notes")
    page.wait_for_selector(".toast-success", timeout=4000)
    check("notes : saisie + enregistrement OK", True)
    page.select_option("#class-select-notes", index=0)
    page.select_option("#class-select-notes", index=1)
    page.wait_for_timeout(700)
    check("notes : valeurs relues depuis la base (moyenne 13,3)", "13.30" in page.locator("#notes-table tbody tr").first.inner_text())

    page.click('[data-target="view-courses"]')
    page.wait_for_timeout(500)
    page.select_option("#up-matiere", index=1)
    page.fill("#up-titre", "Support <b>UI</b> & test")
    page.set_input_files("#up-fichier", str(pdf))
    page.click("#upload-form button[type=submit]")
    page.wait_for_selector("#cours-ens-container >> text=Support <b>UI</b> & test", timeout=5000)
    check("cours : upload via l'interface + affichage échappé (pas d'injection HTML)", page.locator("#cours-ens-container b").count() == 0)

    page.click('[data-target="view-messages"]')
    page.wait_for_timeout(400)
    page.fill("#msg-objet", "Rattrapage <img src=x onerror=alert(1)>")
    page.fill("#msg-contenu", "Bonjour à tous")
    page.click("#message-form button[type=submit]")
    page.wait_for_selector(".toast-success", timeout=4000)
    page.wait_for_timeout(500)
    check("messagerie : envoi OK, objet affiché en texte (pas d'<img> injecté)", page.locator("#messages-container img").count() == 0 and "Rattrapage" in page.locator("#messages-container").inner_text())
    visit_all_views(page, "enseignant", errors, bad)

    # ───────── Étudiant ─────────
    print("\n[Étudiant]")
    errors, bad = [], []
    page = new_page(browser, errors, bad)
    login(page, "/index.html", "cin", "12345678", demo["12345678"])
    check("connexion étudiant", True)
    visit_all_views(page, "étudiant", errors, bad)
    page.click('[data-target="view-profile"]')
    page.evaluate("showChangePwd()")
    page.fill("#current-pwd", "mauvais")
    page.fill("#new-pwd", "Nouveau2026x")
    page.fill("#confirm-pwd", "Nouveau2026x")
    page.evaluate("changePwd()")
    page.wait_for_selector(".toast-error", timeout=4000)
    check("changement de mot de passe : refusé si le mot de passe actuel est faux", True)
    page.evaluate("document.querySelectorAll('.toast').forEach(t => t.remove())")
    page.fill("#current-pwd", demo["12345678"])
    page.evaluate("changePwd()")
    page.wait_for_selector(".toast-success", timeout=4000)
    check("changement de mot de passe : accepté avec le bon mot de passe actuel (appel API réel)", True)

    # ───────── Scolarité ─────────
    print("\n[Scolarité]")
    errors, bad = [], []
    page = new_page(browser, errors, bad)
    login(page, "/scolarite.html", "sco-id", "SCO-0045", demo["SCO-0045"])
    check("connexion scolarité", True)
    visit_all_views(page, "scolarité", errors, bad)

    # ───────── Admin ─────────
    print("\n[Admin]")
    errors, bad = [], []
    page = new_page(browser, errors, bad)
    login(page, "/admin.html", "admin-id", "ADMIN-001", admin_pwd)
    check("connexion admin", True)
    visit_all_views(page, "admin", errors, bad)
    browser.close()

print(f"\n{'=' * 60}\nUI : {len(OK)} réussis, {len(KO)} échoués")
if KO:
    print(*KO, sep="\n  - ")
sys.exit(1 if KO else 0)
