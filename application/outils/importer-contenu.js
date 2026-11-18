#!/usr/bin/env node
/*
 * Importe le contenu de la stratégie (dépôt Yemkaf-strategie) dans l'application :
 *   - les modules et les deux simulateurs → app/contenu/*.html (liens réécrits pour l'application) ;
 *   - la feuille de route, les objectifs mensuels et le planning de départ → app/seed/*.json.
 * Usage : node outils/importer-contenu.js <chemin du dépôt Yemkaf-strategie>
 * Les liens internes utilisent %%BASE%%, remplacé par l'application au moment de servir la page.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const SRC = path.resolve(process.argv[2] || path.join(__dirname, '..', '..'));
const APP = path.resolve(__dirname, '..');
const lire = (p) => fs.readFileSync(path.join(SRC, p), 'utf8');
const ecrire = (p, t) => { fs.mkdirSync(path.dirname(path.join(APP, p)), { recursive: true }); fs.writeFileSync(path.join(APP, p), t); console.log('ok :', p); };

const LIENS = [
  ['href="../index.html"', 'href="%%BASE%%/"'],
  ['href="../01-dashboard-financier/index.html"', 'href="%%BASE%%/simulateur"'],
  ['href="../07-simulateur-prix/index.html"', 'href="%%BASE%%/prix"'],
  ['href="../00-marque/index.html"', 'href="%%BASE%%/module/marque"'],
  ['href="../02-onboarding-baristas/index.html"', 'href="%%BASE%%/module/manuel"'],
  ['href="../06-regles-maison/index.html"', 'href="%%BASE%%/module/regles"'],
  ['href="contrat-cafe-vert.html"', 'href="%%BASE%%/module/contrat-vert"'],
  ['href="contrat-cafe-torrefie.html"', 'href="%%BASE%%/module/contrat-torrefie"'],
  ['href="reglement-interieur.html"', 'href="%%BASE%%/module/reglement"'],
  ['href="charte-associes.html"', 'href="%%BASE%%/module/charte"'],
  ['../assets/', '%%BASE%%/assets/'],
];
function reecrire(html, retourIndex) {
  for (const [a, b] of LIENS) html = html.split(a).join(b);
  if (retourIndex) html = html.split('href="index.html"').join('href="%%BASE%%/module/' + retourIndex + '"');
  return html;
}

// Modules
const MODULES = [
  ['00-marque/index.html', '00-marque.html'], ['02-onboarding-baristas/index.html', '02-manuel.html'],
  ['03-marketing-lancement/index.html', '03-lancement.html'], ['04-ecommerce/index.html', '04-eshop.html'],
  ['05-b2b-juridique/index.html', '05-b2b.html'], ['05-b2b-juridique/contrat-cafe-vert.html', '05-contrat-vert.html', 'b2b'],
  ['05-b2b-juridique/contrat-cafe-torrefie.html', '05-contrat-torrefie.html', 'b2b'], ['06-regles-maison/index.html', '06-regles.html'],
  ['06-regles-maison/reglement-interieur.html', '06-reglement.html', 'regles'], ['06-regles-maison/charte-associes.html', '06-charte.html', 'regles'],
];
for (const [src, dst, retour] of MODULES) ecrire('app/contenu/' + dst, reecrire(lire(src), retour));

// Simulateurs (le modèle de calcul est intégré dans la page du simulateur financier)
let simu = lire('01-dashboard-financier/index.html');
simu = simu.replace('<script src="modele.js"></script>', () => '<script>\n' + lire('01-dashboard-financier/modele.js') + '\n</script>');
ecrire('app/contenu/simulateur-financier.html', reecrire(simu));
ecrire('app/contenu/simulateur-prix.html', reecrire(lire('07-simulateur-prix/index.html')));

// Feuille de route et objectifs, lus dans la salle de pilotage
const hub = lire('index.html');
const extraire = (nom) => {
  const i = hub.indexOf('var ' + nom + ' = ');
  if (i < 0) throw new Error(nom + ' introuvable');
  let j = hub.indexOf('{', i) < hub.indexOf('[', i) && hub.indexOf('{', i) > 0 ? hub.indexOf('{', i) : hub.indexOf('[', i);
  const ouvre = hub[j], ferme = ouvre === '{' ? '}' : ']';
  let n = 0, k = j;
  for (; k < hub.length; k++) { if (hub[k] === ouvre) n++; else if (hub[k] === ferme) { n--; if (n === 0) break; } }
  return vm.runInNewContext('(' + hub.slice(j, k + 1) + ')');
};
const PHASES = extraire('PHASES');
const MOIS = { 'nov.': 11, 'déc.': 12, 'janv.': 1, 'févr.': 2, 'mars': 3, 'avr.': 4, 'mai': 5 };
function dateDe(lib) {
  const m = [...lib.matchAll(/(\d{1,2})(?:er)? (nov\.|déc\.|janv\.|févr\.|mars|avr\.|mai)/g)].pop();
  if (!m) return null;
  const mois = MOIS[m[2]], an = mois >= 11 ? 2026 : 2027;
  return an + '-' + String(mois).padStart(2, '0') + '-' + String(m[1]).padStart(2, '0');
}
const taches = [];
for (const ph of PHASES) for (const t of ph.tasks) {
  const d = dateDe(t[1]);
  taches.push({ cle: t[0], echeance: d, libelle: d && !/→/.test(t[1]) ? '' : t[1], titre: t[2], detail: t[3], module: t[4] === 'barista' ? 'manuel' : (t[4] === 'simulateur' ? 'simulateur' : t[4]), periode: ph.t + ' · ' + ph.d });
}
ecrire('app/seed/taches.json', JSON.stringify(taches, null, 1));

const PAL = extraire('PAL');
const tasses = (PAL.lignes || PAL.rows || Object.values(PAL).find(Array.isArray) || []).find((l) => l[0] === 'Tasses par jour');
if (!tasses) throw new Error('Objectifs de tasses introuvables');
const debuts = ['2026-11-20', '2026-12-20', '2027-01-20', '2027-02-20', '2027-03-20', '2027-04-20'];
const fins = ['2026-12-19', '2027-01-19', '2027-02-19', '2027-03-19', '2027-04-19', '2027-05-19'];
ecrire('app/seed/objectifs.json', JSON.stringify(tasses[1].map((v, i) => ({ mois: 'M' + (i + 1), du: debuts[i], au: fins[i], tasses: v })), null, 1));

// Planning de départ (Manuel barista)
const P = (o, m, f, r) => ({ ouverture: o, midi: m, fermeture: f, renfort: r });
ecrire('app/seed/planning.json', JSON.stringify({
  lundi: P('chef', 'vente', 'khaled', 'amer'), mardi: P('chef', 'vente', 'khaled', 'amer'),
  mercredi: P('amer', 'vente', 'senior', 'yousif'), jeudi: P('amer', 'vente', 'senior', 'yousif'),
  vendredi: P('chef', 'vente', 'senior', 'khaled'), samedi: P('chef', 'yousif', 'senior', ''), dimanche: P('chef', 'yousif', 'senior', ''),
  note: 'Planning de départ du Manuel barista : renforts de 8 h à 10 h 30 pendant les quatre premières semaines.'
}, null, 1));
