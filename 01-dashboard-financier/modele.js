/*
 * YEM'KAF : modèle financier des 6 premiers mois
 * Période : soft opening le 20/11/2026, puis 6 mois glissants (du 20 au 19) jusqu'au 19/05/2027.
 *
 * Conventions
 *  - Tous les montants sont HT, sauf les champs nommés *Ttc.
 *  - TVA : 10 % sur boissons, pâtisseries et snacking (consommation immédiate),
 *          5,5 % sur café en grains, coffrets et ventes web (denrées alimentaires).
 *  - Les prix et coûts unitaires sont ceux de la grille validée par les associés (brief).
 *  - Les « corrections » sont des propositions. Elles sont TOUTES désactivées par défaut :
 *    le modèle reproduit d'abord vos hypothèses, puis montre l'effet de chaque correction.
 *
 * Fichier autonome : utilisable dans le navigateur (window.YemkafModele) et dans Node (require).
 */
(function (root) {
  'use strict';

  var TVA_10 = 0.10;
  var TVA_55 = 0.055;

  // ---------------------------------------------------------------- calendrier
  var MOIS = [
    { id: 'M1', periode: '20 nov – 19 déc', joursOuverts: 30,
      reperes: ['Soft opening 20/11', 'E-shop en ligne ~27/11 (Black Friday)', 'Soirée VIP début décembre', 'Achats de Noël'] },
    { id: 'M2', periode: '20 déc – 19 janv', joursOuverts: 29,
      reperes: ['Noël : fermé le 25/12', 'Fermé le 1/01', 'Soldes d’hiver mi-janvier', 'Galette des rois'] },
    { id: 'M3', periode: '20 janv – 19 févr', joursOuverts: 31,
      reperes: ['Creux de janvier', 'Début du Ramadan ~7-8/02', 'Saint-Valentin 14/02'] },
    { id: 'M4', periode: '20 févr – 19 mars', joursOuverts: 28,
      reperes: ['Ramadan (soirées)', 'Aïd el-Fitr ~10/03 (coffrets)'] },
    { id: 'M5', periode: '20 mars – 19 avr', joursOuverts: 31,
      reperes: ['Pâques 28/03', 'Retour des touristes'] },
    { id: 'M6', periode: '20 avr – 19 mai', joursOuverts: 29,
      reperes: ['Paris Café Festival 24-26/04 (à confirmer)', '1er mai : fermé', 'Aïd al-Adha ~16/05'] }
  ];

  // ------------------------------------------------------- catalogue (brief §5)
  // mix = part des ventes du canal (hypothèse de travail à valider avec vos tickets SumUp)
  var CATALOGUE = {
    boissons: { tva: TVA_10, lignes: [
      { nom: 'Espresso Simple',      ttc: 3.00, cout: 0.76, mix: 0.14, g: 10 },
      { nom: 'Double Espresso',      ttc: 4.20, cout: 1.26, mix: 0.06, g: 18 },
      { nom: 'Cappuccino',           ttc: 5.00, cout: 1.53, mix: 0.14, g: 18 },
      { nom: 'Flat White',           ttc: 5.20, cout: 1.55, mix: 0.12, g: 18 },
      { nom: 'Latte ou Iced Latte',  ttc: 5.80, cout: 1.66, mix: 0.16, g: 18 },
      { nom: 'V60 Grand Cru',        ttc: 6.50, cout: 1.22, mix: 0.06, g: 15 },
      { nom: 'Rada’i Gingembre',     ttc: 4.70, cout: 1.21, mix: 0.04, g: 13 },
      { nom: 'Qishr Cascara Épicé',  ttc: 4.90, cout: 0.64, mix: 0.08, g: 0 },
      { nom: 'Shai Adeni Karak',     ttc: 5.10, cout: 0.74, mix: 0.08, g: 0 },
      { nom: 'Mufawar Cardamome',    ttc: 5.40, cout: 1.35, mix: 0.06, g: 15 },
      { nom: 'Qahwa Baydani',        ttc: 5.60, cout: 1.33, mix: 0.06, g: 14 }
    ]},
    // Suppléments par boisson moyenne : 10 % lait végétal (+0,50 TTC), 5 % micro-lot (+1,00 TTC)
    supplementsBoisson: { ttc: 0.10, cout: 0.03 },
    patisseries: { tva: TVA_10, lignes: [
      { nom: 'Pâtisserie Parisienne', ttc: 4.50, cout: 1.48, mix: 0.50 },
      { nom: 'Pâtisserie Yéménite',   ttc: 5.50, cout: 1.54, mix: 0.50 }
    ]},
    snacking: { tva: TVA_10, lignes: [
      { nom: 'Snacking Salé Yéménite', ttc: 9.50,  cout: 2.74, mix: 0.60 },
      { nom: 'Formule Midi Marais',    ttc: 16.50, cout: 5.45, mix: 0.40 }
    ]},
    sachets: { tva: TVA_55, lignes: [
      { nom: 'Sachet Boutique Classique', ttc: 25.00, cout: 13.15, mix: 0.65, g: 200 },
      { nom: 'Sachet Boutique Grand',     ttc: 30.00, cout: 16.32, mix: 0.35, g: 250 }
    ]},
    coffrets: { tva: TVA_55, lignes: [
      { nom: 'Coffret Duo Boutique',       ttc: 52.00, cout: 28.10, mix: 0.45, g: 400 },
      { nom: 'Coffret Prestige Écrin',     ttc: 79.00, cout: 44.50, mix: 0.35, g: 500 },
      { nom: 'Coffret Web Trois Terroirs', ttc: 69.00, cout: 42.20, mix: 0.20, g: 600 }
    ]},
    // Web : mix exprimé en part du chiffre d'affaires du panier
    web: { tva: TVA_55, lignes: [
      { nom: 'Coffret Web Trois Terroirs', ttc: 69.00, cout: 42.20, mix: 0.30, g: 600 },
      { nom: 'Abonnement Web Mensuel',     ttc: 54.00, cout: 34.65, mix: 0.25, g: 500 },
      { nom: 'Sachet Unitaire Web',        ttc: 31.00, cout: 17.70, mix: 0.35, g: 250 },
      { nom: 'Pack Rituel Qishr Web',      ttc: 22.00, cout: 6.70,  mix: 0.10, g: 0 }
    ]},
    b2bVert: [
      { nom: 'Vert Micro-Lot (sac 5 kg)', prix: 72.00, cout: 50.40, mix: 0.20 },
      { nom: 'Vert Signature (≥ 15 kg)',  prix: 66.00, cout: 50.20, mix: 0.50 },
      { nom: 'Vert Contrat (≥ 30 kg)',    prix: 61.50, cout: 50.00, mix: 0.30 }
    ],
    b2bTorrefie: [
      { nom: 'Torréfié Invité (3-9 kg)',    prix: 92.00, cout: 63.60, mix: 0.40 },
      { nom: 'Torréfié Palace (10-24 kg)',  prix: 86.50, cout: 63.40, mix: 0.40 },
      { nom: 'Torréfié Résident (≥ 25 kg)', prix: 81.00, cout: 63.10, mix: 0.20 }
    ]
  };

  // ------------------------------------------------------ charges fixes (brief §4)
  var CHARGES_FIXES_BRIEF = [
    { cle: 'salaires',  nom: 'Salaires chargés (4 salariés temps plein)', montant: 10200 },
    { cle: 'loyer',     nom: 'Loyer et charges (Marais)',                  montant: 6000 },
    { cle: 'energie',   nom: 'Électricité triphasée, eau filtrée, fibre',  montant: 720 },
    { cle: 'compta',    nom: 'Pennylane, Qonto, commissions TPE',          montant: 540 },
    { cle: 'mkgB2B',    nom: 'Marketing B2B et échantillons',              montant: 510 },
    { cle: 'rp',        nom: 'RP, soirée VIP, équipement snacking',        montant: 490 },
    { cle: 'assur',     nom: 'Assurances, SACEM, hygiène, maintenance',    montant: 430 },
    { cle: 'web',       nom: 'Hébergement web et logistique e-commerce',   montant: 360 }
  ];

  // Répartition saisonnière des 210 coffrets (Noël, Saint-Valentin, Aïd el-Fitr, Pâques)
  var REPARTITION_COFFRETS = [0.40, 0.22, 0.10, 0.12, 0.09, 0.07];

  // Votre plan tel qu'annoncé dans le brief (§11)
  var PLAN_DECLARE = { ca: 278383, margeBrute: 164623, resultat: 49123, franchiseLoyer: 18000 };

  // ------------------------------------------------------- leviers par défaut
  var LEVIERS_DEFAUT = {
    tassesJour: 135,          // objectif jour (brief §11)
    tauxPatisserie: 55 / 135, // 55 pâtisseries pour 135 tasses
    snackingJour: 28,
    debutSnacking: 2,         // Kubaneh et focaccias « dès le deuxième mois »
    sachetsJour: 6,
    coffrets6Mois: 210,
    colisMois: 120,
    panierWebTtc: 49.10,
    kgB2BMois: 60,
    partVertB2B: 0.50,
    coutBarista: 2550,        // 10 200 € / 4 salariés
    remunAssocies: 0,
    moisFranchise: 0,
    tresorerieDepart: 0,
    mensualitesEmprunt: 0,
    semainesStock: 0,         // stock de sécurité en café vert acheté en M1 (semaines de consommation en régime)
    formuleSumUp: 'standard'  // 'standard' (1,75 %) ou 'plus' (19 €/mois + 0,79 %)
  };

  var SUMUP = {
    standard: { nom: 'SumUp sans abonnement', taux: 0.0175, abonnement: 0 },
    plus:     { nom: 'SumUp Paiements Plus (ex-SumUp One)', taux: 0.0079, abonnement: 19 }
  };

  // ------------------------------------------------------- corrections proposées
  var CORRECTIONS = [
    { cle: 'rampe', titre: 'Montée en charge de la boutique',
      resume: 'Le brief vise 135 tasses par jour dès le 20 novembre. Une adresse neuve construit sa clientèle sur plusieurs mois, et le Centre Pompidou voisin est fermé jusqu’en 2030 : moins de touristes devant la porte.',
      detail: 'Boutique à 50 % de l’objectif en M1, puis 62, 72, 82, 90 et 96 %.',
      valeurs: [0.50, 0.62, 0.72, 0.82, 0.90, 0.96] },
    { cle: 'calendrier', titre: 'Saisonnalité et jours réellement ouverts',
      resume: 'Fermé le 25/12, le 1/01 et le 1/05. Le quartier se vide entre Noël et le Nouvel An et en janvier. Le Ramadan (février-mars) réduit une partie de la fréquentation de journée. Le printemps relance le passage.',
      detail: 'Jours ouverts 30, 29, 31, 28, 31, 29. Indice de fréquentation 1,06 · 0,94 · 0,90 · 0,95 · 1,04 · 1,06.',
      jours: [30, 29, 31, 28, 31, 29], saison: [1.06, 0.94, 0.90, 0.95, 1.04, 1.06] },
    { cle: 'rodage', titre: 'Rodage de l’e-shop et du cycle de vente B2B',
      resume: 'Le site ouvre vers le 27/11 sans historique ni référencement. Côté pro, un cupping du jeudi mène à un échantillon, puis à une commande test, puis à un contrat : comptez 6 à 10 semaines par client.',
      detail: 'Colis web à 50, 55, 45, 60, 75 et 90 % de l’objectif. B2B à 15, 30, 50, 70, 85 et 100 %.',
      web: [0.50, 0.55, 0.45, 0.60, 0.75, 0.90], b2b: [0.15, 0.30, 0.50, 0.70, 0.85, 1.00] },
    { cle: 'paiements', titre: 'Commissions SumUp et frais bancaires réels',
      resume: 'À 40 000 € TTC encaissés par mois, 1,75 % de SumUp coûtent environ 700 € à eux seuls, au-delà des 540 € budgétés pour la compta, Qonto et le TPE. Le paiement en ligne coûte en plus 2,5 % (boutique en ligne et liens SumUp).',
      detail: '92 % des ventes boutique par carte. Fixe : cabinet + Pennylane 300 €, paie 90 €, Qonto Essential ~59 €, plus l’abonnement SumUp selon la formule choisie.',
      partCarte: 0.92, tauxWeb: 0.025, fixeCompta: 450 },
    { cle: 'port', titre: 'Frais de port du e-commerce',
      resume: 'La livraison offerte sur le premier coffret et le seuil de gratuité restent à votre charge. Tarifs publics 2026 pour 1 kg : Colissimo 8,89 à 9,59 €, Mondial Relay 5,99 € (dès 3,89 € en compte pro).',
      detail: '5,00 € HT net par colis : transport moyen 6,20 €, moins 2,00 € de participation client en moyenne, plus 0,80 € de carton et calage.',
      parColis: 5.00 },
    { cle: 'pertes', titre: 'Invendus de vitrine',
      resume: 'Le brief ne compte aucune perte. En vitrine fraîche, 6 à 10 % des pièces ne se vendent pas. Too Good To Go ou un « goûter des riverains » à 18 h en récupère une partie.',
      detail: '8 % du coût matière des pâtisseries et du snacking.',
      taux: 0.08 },
    { cle: 'equipe', titre: 'Équipe réelle : 3 baristas salariés',
      resume: 'Le brief chiffre 4 salariés à temps plein, alors que vous êtes 3 baristas et 3 associés. Le planning de 20 séquences de 7 h demande alors environ 5 séquences par semaine tenues par les associés.',
      detail: 'Masse salariale : 3 × coût chargé moyen (levier ci-contre). La rémunération des associés reste un levier séparé.',
      salaries: 3 },
    { cle: 'social', titre: 'Frais sociaux annexes non budgétés',
      resume: 'Mutuelle (part employeur), médecine du travail, repas du personnel (obligation de la convention HCR) et petits frais RH n’apparaissent pas dans les 19 250 €.',
      detail: '+180 € par mois (estimation à confirmer avec votre cabinet). La CFE n’est pas due l’année civile de création.',
      montant: 180 }
  ];

  // ================================================================= calculs
  function round2(x) { return Math.round(x * 100) / 100; }

  function moyenneCanal(canal) {
    var lignes = canal.lignes, tva = canal.tva, ttc = 0, cout = 0, g = 0;
    for (var i = 0; i < lignes.length; i++) {
      ttc += lignes[i].ttc * lignes[i].mix;
      cout += lignes[i].cout * lignes[i].mix;
      g += (lignes[i].g || 0) * lignes[i].mix;
    }
    return { ttc: ttc, ht: ttc / (1 + tva), cout: cout, tva: tva, grammes: g };
  }

  // Web : le mix est une part du CA, on en déduit un taux de coût moyen et des grammes par € HT
  function tauxCoutWeb() {
    var l = CATALOGUE.web.lignes, t = 0;
    for (var i = 0; i < l.length; i++) {
      var ht = l[i].ttc / (1 + CATALOGUE.web.tva);
      t += l[i].mix * (l[i].cout / ht);
    }
    return t;
  }
  function grammesParEuroWeb() {
    var l = CATALOGUE.web.lignes, t = 0;
    for (var i = 0; i < l.length; i++) t += l[i].mix * ((l[i].g || 0) / (l[i].ttc / (1 + CATALOGUE.web.tva)));
    return t;
  }

  // Rendement validé : 1 kg de vert → 800 g nets vendables (freinte, rétention meules, purges)
  var RENDEMENT = 0.80;
  var PRIX_VERT_KG = 50;

  function moyenneB2B(lignes) {
    var prix = 0, cout = 0;
    for (var i = 0; i < lignes.length; i++) { prix += lignes[i].prix * lignes[i].mix; cout += lignes[i].cout * lignes[i].mix; }
    return { prix: prix, cout: cout };
  }

  function unitaires() {
    var b = moyenneCanal(CATALOGUE.boissons);
    var sup = CATALOGUE.supplementsBoisson;
    var boisson = { ttc: b.ttc + sup.ttc, ht: (b.ttc + sup.ttc) / (1 + TVA_10), cout: b.cout + sup.cout, tva: TVA_10, grammes: b.grammes };
    return {
      boisson: boisson,
      patisserie: moyenneCanal(CATALOGUE.patisseries),
      snacking: moyenneCanal(CATALOGUE.snacking),
      sachet: moyenneCanal(CATALOGUE.sachets),
      coffret: moyenneCanal(CATALOGUE.coffrets),
      tauxCoutWeb: tauxCoutWeb(),
      grammesParEuroWeb: grammesParEuroWeb(),
      b2bVert: moyenneB2B(CATALOGUE.b2bVert),
      b2bTorrefie: moyenneB2B(CATALOGUE.b2bTorrefie)
    };
  }

  function correction(cle) {
    for (var i = 0; i < CORRECTIONS.length; i++) if (CORRECTIONS[i].cle === cle) return CORRECTIONS[i];
    return null;
  }

  /**
   * simuler(leviers, actives) → résultat mensuel et totaux
   * leviers : objet au format LEVIERS_DEFAUT (les clés absentes prennent la valeur par défaut)
   * actives : objet { cleCorrection: true } ; tout ce qui manque est désactivé
   */
  function simuler(leviersPartiels, actives) {
    var p = {}, k;
    for (k in LEVIERS_DEFAUT) p[k] = LEVIERS_DEFAUT[k];
    for (k in (leviersPartiels || {})) p[k] = leviersPartiels[k];
    var on = actives || {};
    var u = unitaires();

    var cRampe = correction('rampe'), cCal = correction('calendrier'), cRod = correction('rodage');
    var cPai = correction('paiements'), cPort = correction('port'), cPertes = correction('pertes');
    var cEq = correction('equipe'), cSoc = correction('social');
    var sumup = SUMUP[p.formuleSumUp] || SUMUP.standard;

    var nbSalaries = on.equipe ? cEq.salaries : 4;
    var mois = [];

    for (var i = 0; i < MOIS.length; i++) {
      var jours = on.calendrier ? cCal.jours[i] : 30;
      var saison = on.calendrier ? cCal.saison[i] : 1;
      var rampe = on.rampe ? cRampe.valeurs[i] : 1;
      var fBoutique = jours * saison * rampe;

      var q = {
        tasses: p.tassesJour * fBoutique,
        patisseries: p.tassesJour * p.tauxPatisserie * fBoutique,
        snacking: (i + 1 >= p.debutSnacking) ? p.snackingJour * fBoutique : 0,
        sachets: p.sachetsJour * fBoutique,
        coffrets: p.coffrets6Mois * REPARTITION_COFFRETS[i],
        colis: p.colisMois * (on.rodage ? cRod.web[i] : 1),
        kgB2B: p.kgB2BMois * (on.rodage ? cRod.b2b[i] : 1)
      };

      var panierHt = p.panierWebTtc / (1 + TVA_55);
      var kgVert = q.kgB2B * p.partVertB2B, kgTorr = q.kgB2B - kgVert;

      var ca = {
        boissons: q.tasses * u.boisson.ht,
        patisseries: q.patisseries * u.patisserie.ht,
        snacking: q.snacking * u.snacking.ht,
        boutique: q.sachets * u.sachet.ht + q.coffrets * u.coffret.ht,
        web: q.colis * panierHt,
        b2b: kgVert * u.b2bVert.prix + kgTorr * u.b2bTorrefie.prix
      };
      var cout = {
        boissons: q.tasses * u.boisson.cout,
        patisseries: q.patisseries * u.patisserie.cout,
        snacking: q.snacking * u.snacking.cout,
        boutique: q.sachets * u.sachet.cout + q.coffrets * u.coffret.cout,
        web: ca.web * u.tauxCoutWeb,
        b2b: kgVert * u.b2bVert.cout + kgTorr * u.b2bTorrefie.cout
      };

      var caTotal = 0, coutTotal = 0, marge = {};
      for (k in ca) { marge[k] = ca[k] - cout[k]; caTotal += ca[k]; coutTotal += cout[k]; }
      var margeBrute = caTotal - coutTotal;

      // Charges variables additionnelles (corrections)
      var ttcBoutique = (ca.boissons + ca.patisseries + ca.snacking) * (1 + TVA_10) + ca.boutique * (1 + TVA_55);
      var fraisPaiement = on.paiements
        ? ttcBoutique * cPai.partCarte * sumup.taux + q.colis * p.panierWebTtc * cPai.tauxWeb
        : 0;
      var fraisPort = on.port ? q.colis * cPort.parColis : 0;
      var pertes = on.pertes ? (cout.patisseries + cout.snacking) * cPertes.taux : 0;

      // Charges fixes
      var fixes = {};
      for (var j = 0; j < CHARGES_FIXES_BRIEF.length; j++) fixes[CHARGES_FIXES_BRIEF[j].cle] = CHARGES_FIXES_BRIEF[j].montant;
      fixes.salaires = nbSalaries * p.coutBarista;
      fixes.loyer = (i < p.moisFranchise) ? 0 : 6000;
      if (on.paiements) fixes.compta = cPai.fixeCompta + sumup.abonnement;
      fixes.social = on.social ? cSoc.montant : 0;
      fixes.associes = p.remunAssocies;
      var totalFixes = 0;
      for (k in fixes) totalFixes += fixes[k];

      var resultat = margeBrute - fraisPaiement - fraisPort - pertes - totalFixes;

      // Besoin en café vert (kg) : ventes torréfiées / rendement + vert revendu tel quel
      var netKg = (q.tasses * u.boisson.grammes + q.sachets * u.sachet.grammes + q.coffrets * u.coffret.grammes
        + ca.web * u.grammesParEuroWeb) / 1000 + kgTorr;
      var vertKg = netKg / RENDEMENT + kgVert;

      mois.push({
        id: MOIS[i].id, periode: MOIS[i].periode, jours: jours,
        quantites: q, ca: ca, cout: cout, marge: marge,
        caTotal: caTotal, margeBrute: margeBrute,
        fraisPaiement: fraisPaiement, fraisPort: fraisPort, pertes: pertes,
        fixes: fixes, totalFixes: totalFixes,
        resultat: resultat,
        cafeVertKg: vertKg,
        flux: resultat - p.mensualitesEmprunt
      });
    }

    // Stock de sécurité : achat ponctuel en M1, hors compte de résultat (il sera consommé plus tard)
    var pm = pointMort(p, on, u);
    var achatStock = p.semainesStock * (pm.cafeVertKgMoisRegime * 12 / 52) * PRIX_VERT_KG;
    mois[0].achatStock = achatStock;
    mois[0].flux -= achatStock;

    // Totaux et indicateurs
    var tot = { caTotal: 0, margeBrute: 0, fraisPaiement: 0, fraisPort: 0, pertes: 0, totalFixes: 0, resultat: 0, flux: 0,
                ca: {}, marge: {}, quantites: {} };
    var cumul = p.tresorerieDepart, tresoMin = Infinity, moisTresoMin = null, premierMoisPositif = null;
    var cumuls = [];
    for (i = 0; i < mois.length; i++) {
      var m = mois[i];
      ['caTotal', 'margeBrute', 'fraisPaiement', 'fraisPort', 'pertes', 'totalFixes', 'resultat', 'flux'].forEach(function (c) { tot[c] += m[c]; });
      for (k in m.ca) { tot.ca[k] = (tot.ca[k] || 0) + m.ca[k]; tot.marge[k] = (tot.marge[k] || 0) + m.marge[k]; }
      for (k in m.quantites) tot.quantites[k] = (tot.quantites[k] || 0) + m.quantites[k];
      cumul += m.flux;
      m.tresorerieFin = cumul;
      cumuls.push(cumul);
      if (cumul < tresoMin) { tresoMin = cumul; moisTresoMin = m.id; }
      if (premierMoisPositif === null && m.resultat > 0) premierMoisPositif = m.id;
    }

    return {
      leviers: p, actives: on, unitaires: u, mois: mois, totaux: tot,
      tresorerieFin: cumul, tresorerieMin: tresoMin, moisTresorerieMin: moisTresoMin,
      premierMoisPositif: premierMoisPositif,
      achatStock: achatStock,
      pointMort: pm
    };
  }

  /**
   * Point mort en régime de croisière (mois de 30 jours, montée en charge terminée, sans saisonnalité).
   * La fréquentation boutique entraîne les pâtisseries, le snacking et les sachets dans les mêmes proportions
   * que vos objectifs (55, 28 et 6 pour 135 tasses). Web, B2B et coffrets restent au niveau de leurs leviers.
   * Résultat : nombre de tasses par jour qui couvre les charges fixes.
   */
  function pointMort(p, on, u) {
    var cPai = correction('paiements'), cPort = correction('port'), cPertes = correction('pertes');
    var sumup = SUMUP[p.formuleSumUp] || SUMUP.standard;
    var jours = 30;
    var carte = on.paiements ? cPai.partCarte * sumup.taux : 0;
    var tauxPertes = on.pertes ? cPertes.taux : 0;
    var t = Math.max(p.tassesJour, 1);

    function contribution(unit, tva) { return (unit.ht - unit.cout) - unit.ht * (1 + tva) * carte; }

    var parTasse = contribution(u.boisson, TVA_10)
      + p.tauxPatisserie * (contribution(u.patisserie, TVA_10) - u.patisserie.cout * tauxPertes)
      + (p.snackingJour / t) * (contribution(u.snacking, TVA_10) - u.snacking.cout * tauxPertes)
      + (p.sachetsJour / t) * contribution(u.sachet, TVA_55);

    var panierHt = p.panierWebTtc / (1 + TVA_55);
    var autres = (p.coffrets6Mois / 6) * contribution(u.coffret, TVA_55)
      + p.colisMois * (panierHt * (1 - u.tauxCoutWeb) - (on.paiements ? p.panierWebTtc * cPai.tauxWeb : 0) - (on.port ? cPort.parColis : 0))
      + p.kgB2BMois * (p.partVertB2B * (u.b2bVert.prix - u.b2bVert.cout) + (1 - p.partVertB2B) * (u.b2bTorrefie.prix - u.b2bTorrefie.cout));

    // Charges fixes en régime : loyer plein, pas de franchise
    var nb = on.equipe ? 3 : 4;
    var fixes = 0;
    for (var j = 0; j < CHARGES_FIXES_BRIEF.length; j++) fixes += CHARGES_FIXES_BRIEF[j].montant;
    fixes += nb * p.coutBarista - 10200;
    if (on.paiements) fixes += (cPai.fixeCompta + sumup.abonnement) - 540;
    if (on.social) fixes += correction('social').montant;
    fixes += p.remunAssocies;

    var tasses = parTasse > 0 ? (fixes - autres) / (jours * parTasse) : Infinity;

    // Café vert consommé par mois en régime (leviers à 100 %)
    var kgVert = p.kgB2BMois * p.partVertB2B, kgTorr = p.kgB2BMois - kgVert;
    var netKg = (jours * p.tassesJour * u.boisson.grammes + jours * p.sachetsJour * u.sachet.grammes
      + (p.coffrets6Mois / 6) * u.coffret.grammes + p.colisMois * panierHt * u.grammesParEuroWeb) / 1000 + kgTorr;

    return {
      tassesJour: Math.max(0, tasses),
      chargesFixesMois: fixes,
      contributionParTasse: parTasse,
      contributionAutresCanaux: autres,
      cafeVertKgMoisRegime: netKg / RENDEMENT + kgVert
    };
  }

  /** Vérifie la grille de marges du brief (§5) au centime près. */
  function verifierGrille() {
    var grille = [
      ['Espresso Simple', 3.00, 0.76, 1.97, 72.3, TVA_10], ['Double Espresso', 4.20, 1.26, 2.56, 67.1, TVA_10],
      ['Cappuccino', 5.00, 1.53, 3.02, 66.4, TVA_10], ['Flat White', 5.20, 1.55, 3.18, 67.2, TVA_10],
      ['Latte ou Iced Latte', 5.80, 1.66, 3.62, 68.6, TVA_10], ['V60 Grand Cru', 6.50, 1.22, 4.69, 79.3, TVA_10],
      ['Rada’i Gingembre', 4.70, 1.21, 3.06, 71.7, TVA_10], ['Qishr Cascara Épicé', 4.90, 0.64, 3.81, 85.5, TVA_10],
      ['Shai Adeni Karak', 5.10, 0.74, 3.89, 84.0, TVA_10], ['Mufawar Cardamome', 5.40, 1.35, 3.56, 72.5, TVA_10],
      ['Qahwa Baydani', 5.60, 1.33, 3.76, 73.9, TVA_10], ['Pâtisserie Parisienne', 4.50, 1.48, 2.61, 63.9, TVA_10],
      ['Pâtisserie Yéménite', 5.50, 1.54, 3.46, 69.3, TVA_10], ['Snacking Salé Yéménite', 9.50, 2.74, 5.90, 68.3, TVA_10],
      ['Formule Midi Marais', 16.50, 5.45, 9.55, 63.7, TVA_10], ['Sachet Boutique Classique', 25.00, 13.15, 10.55, 44.5, TVA_55],
      ['Sachet Boutique Grand', 30.00, 16.32, 12.11, 42.6, TVA_55], ['Coffret Duo Boutique', 52.00, 28.10, 21.19, 43.0, TVA_55],
      ['Coffret Prestige Écrin', 79.00, 44.50, 30.38, 40.6, TVA_55], ['Coffret Web Trois Terroirs', 69.00, 42.20, 23.20, 35.5, TVA_55],
      ['Abonnement Web Mensuel', 54.00, 34.65, 16.53, 32.3, TVA_55], ['Sachet Unitaire Web', 31.00, 17.70, 11.68, 39.8, TVA_55],
      ['Pack Rituel Qishr Web', 22.00, 6.70, 14.15, 67.9, TVA_55]
    ];
    return grille.map(function (g) {
      var ht = g[1] / (1 + g[5]);
      var marge = ht - g[2];
      var taux = marge / ht * 100;
      return {
        nom: g[0], ttc: g[1], tva: g[5], ht: round2(ht), cout: g[2],
        margeBrief: g[3], margeCalculee: round2(marge),
        tauxBrief: g[4], tauxCalcule: Math.round(taux * 10) / 10,
        ok: Math.abs(round2(marge) - g[3]) <= 0.011 && Math.abs(taux - g[4]) <= 0.15
      };
    });
  }

  var API = {
    MOIS: MOIS, CATALOGUE: CATALOGUE, CHARGES_FIXES_BRIEF: CHARGES_FIXES_BRIEF, CORRECTIONS: CORRECTIONS,
    LEVIERS_DEFAUT: LEVIERS_DEFAUT, SUMUP: SUMUP, PLAN_DECLARE: PLAN_DECLARE, REPARTITION_COFFRETS: REPARTITION_COFFRETS,
    RENDEMENT: RENDEMENT, PRIX_VERT_KG: PRIX_VERT_KG,
    simuler: simuler, unitaires: unitaires, verifierGrille: verifierGrille
  };
  if (typeof module !== 'undefined' && module.exports) module.exports = API;
  else root.YemkafModele = API;
})(this);
