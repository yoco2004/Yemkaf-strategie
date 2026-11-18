// Test de bout en bout (base vide, serveur local : php -S 127.0.0.1:8099 -t public outils/routeur-dev.php ; e-mails en journal_seulement).
// Lancer : node tests/e2e.js
const { chromium } = require('playwright'); const fs = require('fs'); const path = require('path');
const BASE = 'http://127.0.0.1:8099'; const MAILS = path.join(__dirname, '..', 'storage', 'logs', 'mails.log');
const SP = process.env.SP || path.join(__dirname, 'captures'); fs.mkdirSync(SP, { recursive: true }); const res = []; const ok = (c, m) => { res.push((c ? 'OK   ' : 'ÉCHEC ') + m); };
function dernierCode(email) { const t = (fs.existsSync(MAILS) ? fs.readFileSync(MAILS, 'utf8') : '').split('==== ').filter(b => b.includes('→ ' + email)).pop() || ''; const c = t.match(/connexion à Yem'kaf Outils : (\d{6})/); const l = t.match(/(http:\/\/\S+\/connexion\/lien\?t=[0-9a-f]+)/); return { code: c && c[1], lien: l && l[1] }; }
async function nouveau(b, w = 1280) { const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message)); p.on('console', m => { if (m.type() === 'error' && !/fonts|ERR_FAILED|cdn.jsdelivr|status of 403|\/installer/.test(m.text())) p.errs.push(m.text() + ' ' + (m.location() || {}).url); }); await p.route(/fonts\.(googleapis|gstatic)\.com|cdn\.jsdelivr\.net/, r => r.abort()); return p; }
async function connecter(p, email) {
  await p.goto(BASE + '/connexion'); await p.fill('input[name=email]', email); await p.click('button[type=submit]');
  await p.waitForSelector('input[name=code]'); const { code } = dernierCode(email); await p.fill('input[name=code]', code); await p.click('button[type=submit]'); await p.waitForLoadState();
}
(async () => {
  const b = await chromium.launch();
  const a = await nouveau(b);
  // Installation
  await a.goto(BASE + '/'); ok(a.url().endsWith('/installer'), 'redirection vers l’installateur');
  await a.fill('input[name=prenom]', 'Admin'); await a.selectOption('select[name=personne]', 'yousif'); await a.fill('input[name=cle_installation]', 'mauvaise-phrase-1');
  await a.click('button[type=submit]'); ok(await a.isVisible('text=Phrase d’installation incorrecte'), 'phrase d’installation refusée');
  await a.fill('input[name=prenom]', 'Admin'); await a.fill('input[name=cle_installation]', 'phrase-de-test-123'); await a.click('button[type=submit]');
  ok(await a.isVisible('text=Installation terminée'), 'installation terminée');
  await a.goto(BASE + '/installer'); ok(await a.isVisible('text=Déjà installé'), 'installateur verrouillé ensuite'); a.errs.length = 0;
  // Connexion : adresse inconnue
  await a.goto(BASE + '/connexion'); await a.fill('input[name=email]', 'inconnu@test.fr'); await a.click('button[type=submit]');
  ok(await a.isVisible('text=Vérifiez vos e-mails') && !(fs.existsSync(MAILS) ? fs.readFileSync(MAILS, 'utf8') : '').includes('inconnu@test.fr'), 'adresse inconnue : même message, aucun e-mail');
  await a.fill('input[name=code]', '123456'); await a.click('button[type=submit]'); ok(await a.isVisible('.flash.bad'), 'code au hasard refusé');
  // Connexion admin
  await connecter(a, 'admin@test.fr'); ok(await a.isVisible('h1:has-text("Admin")'), 'admin connecté, accueil');
  ok(await a.isVisible('.app-nav >> text=Simulateurs'), 'menu complet pour l’admin');
  // Équipe
  await a.goto(BASE + '/equipe');
  for (const [prenom, email, role, cle] of [['Khaled', 'khaled@test.fr', 'associe', 'khaled'], ['Lina', 'chef@test.fr', 'barista', 'chef']]) {
    await a.fill('form[action="/equipe"] input[name=prenom]', prenom); await a.fill('form[action="/equipe"] input[name=email]', email);
    await a.selectOption('form[action="/equipe"] select[name=role]', role); await a.selectOption('form[action="/equipe"] select[name=cle]', cle);
    await a.click('form[action="/equipe"] button'); ok(await a.isVisible('text=Personne ajoutée'), 'ajout de ' + prenom);
  }
  ok((fs.existsSync(MAILS) ? fs.readFileSync(MAILS, 'utf8') : '').includes('Votre accès à Yem'), 'e-mail d’accueil envoyé');
  // Tâches
  await a.goto(BASE + '/taches?vue=tout&statut=ouvert'); const n0 = await a.locator('.task-line').count(); ok(n0 > 50, 'feuille de route importée (' + n0 + ' tâches)');
  await a.locator('.task-line button.check').first().click(); await a.waitForLoadState(); ok(await a.isVisible('text=Statut mis à jour'), 'tâche cochée');
  ok(await a.locator('.task-line').count() === n0 - 1, 'tâche faite retirée de la vue « à faire »');
  await a.fill('#ajouter input[name=titre]', 'Tester la machine à glaçons'); await a.selectOption('#ajouter select[name=cle]', 'chef'); await a.fill('#ajouter input[name=echeance]', '2026-11-19');
  await a.click('#ajouter button'); ok(await a.isVisible('text=Ajouté'), 'tâche ajoutée');
  await a.click('text=Tester la machine à glaçons'); await a.fill('textarea[name=texte]', 'Prévoir 30 minutes avant l’ouverture.'); await a.click('text=Publier');
  ok(await a.isVisible('text=Prévoir 30 minutes'), 'commentaire publié');
  // Journal
  await a.goto(BASE + '/journal'); await a.fill('input[name=ca_ttc]', '812,50'); await a.fill('input[name=tasses]', '78'); await a.fill('input[name=sachets]', '4'); await a.fill('input[name=invendus]', '3');
  await a.fill('textarea[name=remarque]', 'Le Qishr a beaucoup plu.'); await a.click('text=Enregistrer le journal'); ok(await a.isVisible('text=C’est enregistré') && await a.isVisible('svg.chart'), 'journal du soir enregistré, graphique affiché');
  // Planning
  await a.goto(BASE + '/planning'); await a.selectOption('select[name="p[lundi][renfort]"]', 'yousif'); await a.click('text=Enregistrer le planning');
  ok(await a.isVisible('text=C’est enregistré') && (await a.inputValue('select[name="p[lundi][renfort]"]')) === 'yousif', 'planning modifié');
  // Commandes
  await a.goto(BASE + '/commandes'); await a.fill('input[name=produit]', 'Lait entier'); await a.fill('input[name=quantite]', '12 bouteilles'); await a.fill('input[name=fournisseur]', 'Laiterie'); await a.check('input[name=urgent]');
  await a.click('form.form-grid button'); ok(await a.isVisible('.order.urgent'), 'commande urgente ajoutée');
  await a.click('text=Marquer commandé'); await a.click('text=Marquer reçu'); ok(await a.isVisible('text=Reçu ces 30 derniers jours'), 'commande commandée puis reçue');
  // Documents
  await a.goto(BASE + '/documents'); await a.setInputFiles('#deposer input[type=file]', path.join(__dirname, '..', 'public', 'assets', 'marque') + '/sachet-vert.jpg');
  await a.fill('#deposer input[name=titre]', 'Maquette sachet vert'); await a.selectOption('#deposer select[name=categorie]', 'graphisme'); await a.selectOption('#deposer select[name=visibilite]', 'associes');
  await a.click('#deposer button'); ok(await a.isVisible('text=Fichier enregistré'), 'image déposée'); await a.waitForTimeout(600);
  const imgOk = await a.evaluate(() => { const i = document.querySelector('.doc-thumb img'); return i && i.complete && i.naturalWidth > 0; }); ok(imgOk, 'aperçu de l’image affiché');
  await a.setInputFiles('input[type=file]', path.join(__dirname, '..', 'public', 'assets', 'marque') + '/sachet-bordeaux.jpg'); await a.fill('input[name=note]', 'Couleur bordeaux'); await a.click('text=Déposer la version');
  ok(await a.isVisible('text=version 2'), 'nouvelle version du document');
  await a.goto(BASE + '/documents'); await a.setInputFiles('#deposer input[type=file]', path.join(__dirname, '..', 'README.md'));
  await a.fill('#deposer input[name=titre]', 'Stratégie de lancement'); await a.selectOption('#deposer select[name=categorie]', 'strategie'); await a.click('#deposer button'); ok(await a.isVisible('text=Fichier enregistré'), 'document de stratégie déposé (visible par tous)');
  // Simulateurs
  await a.goto(BASE + '/simulateur'); await a.waitForTimeout(1500); ok(a.errs.length === 0, 'simulateur financier sans erreur ' + JSON.stringify(a.errs));
  ok(await a.isVisible('.yk-retour'), 'barre de retour dans le simulateur');
  const v0 = await a.evaluate(() => fetch('/api/data/simulateur-financier', { headers: { Accept: 'application/json' } }).then(r => r.json()));
  ok(v0.data === null, 'pas encore de chiffres partagés');
  await a.evaluate(() => { const i = document.querySelector('#donnees input[type=number]'); });
  const champ = a.locator('#ed-fixes input[type=number]').first(); await a.evaluate(() => document.getElementById('donnees').scrollIntoView());
  if (await champ.count()) { await champ.fill('3100'); await champ.dispatchEvent('input'); await a.waitForTimeout(2500); }
  const v1 = await a.evaluate(() => fetch('/api/data/simulateur-financier', { headers: { Accept: 'application/json' } }).then(r => r.json()));
  ok(v1.data && v1.version >= 1, 'chiffres du simulateur enregistrés pour l’équipe (version ' + v1.version + ')');
  await a.goto(BASE + '/prix'); await a.waitForTimeout(800); await a.fill('#pa-prixVertKg', '52'); await a.waitForTimeout(2200);
  const p1 = await a.evaluate(() => fetch('/api/data/simulateur-prix', { headers: { Accept: 'application/json' } }).then(r => r.json()));
  ok(p1.data && p1.data.params.prixVertKg === 52, 'simulateur de prix enregistré');
  // Versions
  await a.goto(BASE + '/versions?cle=simulateur-prix'); await a.fill('input[name=nom]', 'Prix avec vert à 52 €'); await a.click('text=Enregistrer les chiffres actuels');
  ok(await a.isVisible('text=Prix avec vert à 52 €'), 'version enregistrée');
  await a.goto(BASE + '/prix'); await a.waitForTimeout(800); await a.fill('#pa-prixVertKg', '60'); await a.waitForTimeout(2200);
  await a.goto(BASE + '/versions?cle=simulateur-prix'); a.once('dialog', d => d.accept());
  await a.locator('tr:has-text("Prix avec vert à 52") button:has-text("Restaurer")').click(); await a.waitForLoadState();
  const p2 = await a.evaluate(() => fetch('/api/data/simulateur-prix', { headers: { Accept: 'application/json' } }).then(r => r.json()));
  ok(p2.data.params.prixVertKg === 52 && await a.isVisible('text=Avant la restauration'), 'version restaurée, ancienne gardée');
  // Modules
  for (const m of ['marque', 'manuel', 'b2b', 'contrat-vert', 'regles', 'reglement', 'charte']) { const r = await a.goto(BASE + '/module/' + m); ok(r.status() === 200 && await a.isVisible('.yk-retour'), 'module ' + m); }
  const imgsMod = await (async () => { await a.goto(BASE + '/module/marque'); await a.waitForTimeout(300); return a.evaluate(() => [...document.images].filter(i => i.complete && i.naturalWidth === 0).map(i => i.src)); })();
  ok(imgsMod.length === 0, 'images du module charte affichées ' + JSON.stringify(imgsMod));
  // Paramètres et activité
  await a.goto(BASE + '/parametres'); await a.selectOption('select[name="d[barista][planning]"]', '2'); await a.click('text=Enregistrer les paramètres'); ok(await a.isVisible('text=C’est enregistré'), 'paramètres enregistrés (barista peut modifier le planning)');
  await a.selectOption('select[name="d[barista][planning]"]', '1'); await a.click('text=Enregistrer les paramètres');
  await a.goto(BASE + '/activite'); ok(await a.locator('tbody tr').count() > 10, 'journal d’activité rempli');

  // Barista : lien ouvert dans un autre navigateur
  const c1 = await nouveau(b, 390); await c1.goto(BASE + '/connexion'); await c1.fill('input[name=email]', 'chef@test.fr'); await c1.click('button[type=submit]');
  const { lien } = dernierCode('chef@test.fr'); const autre = await nouveau(b, 390); await autre.goto(lien);
  ok(await autre.isVisible('text=Ce lien ne fonctionne que dans le navigateur'), 'lien transféré à un autre navigateur : refusé');
  await c1.goto(lien); ok(await c1.isVisible('h1:has-text("Lina")'), 'lien ouvert dans le bon navigateur : connecté');
  ok(!(await c1.isVisible('.app-nav >> text=Simulateurs')), 'barista : pas de simulateurs dans le menu');
  ok((await c1.goto(BASE + '/simulateur')).status() === 403, 'barista : simulateur refusé');
  ok((await c1.goto(BASE + '/module/b2b')).status() === 403, 'barista : module pros refusé');
  ok((await c1.goto(BASE + '/equipe')).status() === 403, 'barista : équipe refusée');
  const putRefuse = await c1.evaluate(() => fetch('/api/data/simulateur-prix', { method: 'PUT', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: '{}' }).then(r => r.status));
  ok(putRefuse === 403, 'barista : écriture du simulateur refusée par l’API');
  await c1.goto(BASE + '/documents'); ok(!(await c1.isVisible('text=Maquette sachet vert')) && await c1.isVisible('text=Stratégie de lancement'), 'barista : documents « associés » cachés');
  await c1.goto(BASE + '/planning'); ok((await c1.locator('table.planning select').count()) === 0, 'barista : planning en lecture');
  await c1.goto(BASE + '/journal?jour=2026-11-01'); ok(await c1.isVisible('text=Lecture seule pour ce jour'), 'barista : journal d’un ancien jour en lecture');
  await c1.goto(BASE + '/taches?vue=moi'); ok(await c1.isVisible('text=Tester la machine à glaçons'), 'barista : voit la tâche qui lui est attribuée');
  // Limite d'appareils (2 pour un barista)
  const c2 = await nouveau(b, 390); await connecter(c2, 'chef@test.fr'); const c3 = await nouveau(b, 390); await connecter(c3, 'chef@test.fr');
  await c1.goto(BASE + '/'); ok(c1.url().includes('/connexion'), 'troisième appareil : le plus ancien est déconnecté');
  ok((fs.existsSync(MAILS) ? fs.readFileSync(MAILS, 'utf8') : '').includes('Nouvelle connexion à Yem'), 'alerte « nouvel appareil » envoyée');
  await c3.goto(BASE + '/mes-appareils'); ok(await c3.locator('tbody tr').count() === 2, 'mes appareils : 2 connectés');
  // Admin désactive le barista : déconnexion immédiate
  await a.goto(BASE + '/equipe'); const det = a.locator('details.person:has-text("chef@test.fr")'); await det.locator('summary').click();
  await det.locator('select[name=actif]').selectOption('0'); await det.locator('button:has-text("Enregistrer")').click();
  await c3.goto(BASE + '/'); ok(c3.url().includes('/connexion'), 'compte désactivé : déconnecté partout');
  // Captures
  await a.goto(BASE + '/'); await a.screenshot({ path: SP + '/app-accueil.png', fullPage: false });
  const m = await nouveau(b, 390); await connecter(m, 'khaled@test.fr'); await m.goto(BASE + '/taches'); await m.screenshot({ path: SP + '/app-taches-phone.png' });
  await m.goto(BASE + '/'); const ov = await m.evaluate(() => document.documentElement.scrollWidth - innerWidth); ok(ov <= 0, 'téléphone : pas de débordement (' + ov + ')');
  for (const p of ['/journal', '/planning', '/commandes', '/documents', '/simulateurs']) { await m.goto(BASE + p); const o = await m.evaluate(() => document.documentElement.scrollWidth - innerWidth); ok(o <= 0, 'téléphone ' + p + ' sans débordement (' + o + ')'); }
  await a.goto(BASE + '/journal'); await a.screenshot({ path: SP + '/app-journal.png', fullPage: true });
  ok([a, c1, c2, c3, m].every(p => p.errs.length === 0), 'aucune erreur JavaScript ' + JSON.stringify([a, c1, c2, c3, m].map(p => p.errs)));
  console.log(res.join('\n')); console.log(res.filter(r => r.startsWith('ÉCHEC')).length + ' échec(s) sur ' + res.length);
  await b.close();
})().catch(e => { console.log(res.join('\n')); console.error('ARRÊT :', e.message); process.exit(1); });
