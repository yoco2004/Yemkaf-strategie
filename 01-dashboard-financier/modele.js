/*
 * YEM'KAF : modèle financier des 6 premiers mois (version 2 : données modifiables)
 * Période : soft opening le 20/11/2026, puis 6 mois glissants (du 20 au 19) jusqu'au 19/05/2027.
 *
 * Trois couches :
 *  1. DONNEES (modifiables et enregistrées) : charges fixes, dépenses ponctuelles, catalogue prix/coûts/mix.
 *  2. LEVIERS (curseurs d'exploration, non enregistrés) : volumes, panier web, B2B, trésorerie.
 *  3. CORRECTIONS (propositions de Claude, toutes désactivées par défaut) : montée en charge, saisonnalité,
 *     rodage web/B2B, commissions de paiement, frais de port, invendus.
 *
 * Conventions : montants HT sauf champs *Ttc ; TVA 10 % (boissons, pâtisseries, snacking),
 * 5,5 % (café en grains, coffrets, web). Fichier autonome : navigateur (window.YemkafModele) et Node (require).
 */
(function (root) {
  'use strict';

  var TVA_10 = 0.10, TVA_55 = 0.055;
  var RENDEMENT = 0.80;        // 1 kg de vert → 800 g vendables (freinte, rétention, purges)
  var PRIX_VERT_KG = 50;       // café vert yéménite, € HT/kg brut

  // ---------------------------------------------------------------- calendrier
  var MOIS = [
    { id: 'M1', periode: '20 nov – 19 déc', joursOuverts: 30,
      reperes: ['Soft opening 20/11', 'E-shop ~27/11 (Black Friday)', 'Soirée VIP samedi 12/12', 'Achats de Noël'] },
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

  var CANAUX = [
    { cle: 'boissons', nom: 'Boissons', tva: TVA_10 },
    { cle: 'patisseries', nom: 'Pâtisseries', tva: TVA_10 },
    { cle: 'snacking', nom: 'Snacking du midi', tva: TVA_10 },
    { cle: 'sachets', nom: 'Sachets boutique', tva: TVA_55 },
    { cle: 'coffrets', nom: 'Coffrets cadeaux', tva: TVA_55 },
    { cle: 'web', nom: 'E-commerce', tva: TVA_55 }
  ];

  // ------------------------------------------------- données modifiables par défaut
  // g = grammes de café net par unité (sert au calcul du café vert à acheter)
  // Boissons : grille « Premium Yémen » validée le 6/11/2026 (le brief reste dans verifierGrille)
  var DONNEES_DEFAUT = {
    version: 2,
    chargesFixes: [
      { id: 'barista-chef', nom: 'Chef barista (salaire chargé)', categorie: 'Équipe', montant: 2550, debut: 1, fin: 6 },
      { id: 'barista-senior', nom: 'Barista senior (salaire chargé)', categorie: 'Équipe', montant: 2550, debut: 1, fin: 6 },
      { id: 'barista-web', nom: 'Barista vente et web (salaire chargé)', categorie: 'Équipe', montant: 2550, debut: 1, fin: 6 },
      { id: 'associes', nom: 'Rémunération des associés', categorie: 'Équipe', montant: 0, debut: 1, fin: 6 },
      { id: 'loyer', nom: 'Loyer et charges, rue du Renard', categorie: 'Local', montant: 6000, debut: 1, fin: 6 },
      { id: 'stockage', nom: 'Loyer du bureau de stockage', categorie: 'Local', montant: 630, debut: 1, fin: 6 },
      { id: 'energie', nom: 'Électricité triphasée, eau filtrée, fibre', categorie: 'Local', montant: 720, debut: 1, fin: 6 },
      { id: 'compta', nom: 'Pennylane, Qonto, frais bancaires', categorie: 'Gestion', montant: 540, debut: 1, fin: 6 },
      { id: 'mkt-b2b', nom: 'Marketing B2B et échantillons', categorie: 'Commercial', montant: 510, debut: 1, fin: 6 },
      { id: 'rp', nom: 'RP, soirée VIP, équipement snacking', categorie: 'Commercial', montant: 490, debut: 1, fin: 6 },
      { id: 'assurances', nom: 'Assurances, SACEM, hygiène, maintenance', categorie: 'Gestion', montant: 430, debut: 1, fin: 6 },
      { id: 'web', nom: 'Hébergement WordPress et logistique e-commerce', categorie: 'Commercial', montant: 360, debut: 1, fin: 6 }
    ],
    depensesPonctuelles: [],
    catalogue: {
      boissons: [
        { id: 'espresso', nom: 'Espresso Simple', ttc: 3.50, cout: 0.76, mix: 14, g: 10 },
        { id: 'double', nom: 'Double Espresso', ttc: 4.80, cout: 1.26, mix: 6, g: 18 },
        { id: 'cappuccino', nom: 'Cappuccino', ttc: 5.50, cout: 1.53, mix: 14, g: 18 },
        { id: 'flat-white', nom: 'Flat White', ttc: 6.00, cout: 1.55, mix: 12, g: 18 },
        { id: 'latte', nom: 'Latte ou Iced Latte', ttc: 6.20, cout: 1.66, mix: 16, g: 18 },
        { id: 'v60', nom: 'V60 Grand Cru', ttc: 8.00, cout: 1.22, mix: 6, g: 15 },
        { id: 'radai', nom: 'Rada’i Gingembre', ttc: 5.20, cout: 1.21, mix: 4, g: 13 },
        { id: 'qishr', nom: 'Qishr Cascara Épicé', ttc: 5.50, cout: 0.64, mix: 8, g: 0 },
        { id: 'shai', nom: 'Shai Adeni Karak', ttc: 5.80, cout: 0.74, mix: 8, g: 0 },
        { id: 'mufawar', nom: 'Mufawar Cardamome', ttc: 6.00, cout: 1.35, mix: 6, g: 15 },
        { id: 'baydani', nom: 'Qahwa Baydani', ttc: 6.20, cout: 1.33, mix: 6, g: 14 },
        { id: 'supplements', nom: 'Suppléments (lait végétal, micro-lot), par boisson', ttc: 0.10, cout: 0.03, mix: 0, g: 0, parBoisson: true }
      ],
      patisseries: [
        { id: 'pat-paris', nom: 'Pâtisserie Parisienne', ttc: 4.50, cout: 1.48, mix: 50 },
        { id: 'pat-yemen', nom: 'Pâtisserie Yéménite (Sabaya, Maamoul)', ttc: 5.50, cout: 1.54, mix: 50 }
      ],
      snacking: [
        { id: 'snack', nom: 'Snacking Salé Yéménite (Kubaneh, focaccia)', ttc: 9.50, cout: 2.74, mix: 60 },
        { id: 'formule', nom: 'Formule Midi Marais', ttc: 16.50, cout: 5.45, mix: 40 }
      ],
      sachets: [
        { id: 'sachet-150', nom: 'Sachet 150 g (prix proposé)', ttc: 19.00, cout: 10.00, mix: 40, g: 150 },
        { id: 'sachet-200', nom: 'Sachet 200 g (brief : Classique)', ttc: 25.00, cout: 13.15, mix: 0, g: 200 },
        { id: 'sachet-250', nom: 'Sachet 250 g (brief : Grand)', ttc: 30.00, cout: 16.32, mix: 45, g: 250 },
        { id: 'sachet-melange', nom: 'Mélange épicé 200 g (≈ 85 % café yéménite, 15 % épices)', ttc: 24.00, cout: 12.36, mix: 15, g: 170 }
      ],
      coffrets: [
        { id: 'duo', nom: 'Coffret Duo Boutique', ttc: 52.00, cout: 28.10, mix: 45, g: 400 },
        { id: 'prestige', nom: 'Coffret Prestige Écrin', ttc: 79.00, cout: 44.50, mix: 35, g: 500 },
        { id: 'trois-terroirs', nom: 'Coffret Trois Terroirs', ttc: 69.00, cout: 42.20, mix: 20, g: 600 }
      ],
      // Web : le mix est une part du chiffre d'affaires du panier
      web: [
        { id: 'web-coffret', nom: 'Coffret Web Trois Terroirs', ttc: 69.00, cout: 42.20, mix: 30, g: 600 },
        { id: 'web-abo', nom: 'Abonnement Web Mensuel', ttc: 54.00, cout: 34.65, mix: 25, g: 500 },
        { id: 'web-sachet', nom: 'Sachet Unitaire Web 250 g', ttc: 27.90, cout: 17.70, mix: 35, g: 250 },
        { id: 'web-qishr', nom: 'Pack Rituel Qishr Web', ttc: 22.00, cout: 6.70, mix: 10, g: 0 }
      ]
    }
  };

  // Suggestions de charges souvent oubliées (ajout en un clic, montants estimés à confirmer)
  var SUGGESTIONS = [
    { type: 'fixe', nom: 'Mutuelle, médecine du travail, repas du personnel (HCR)', categorie: 'Équipe', montant: 180, note: 'Estimation pour 3 salariés, à confirmer avec le cabinet.' },
    { type: 'fixe', nom: 'Licence WooCommerce Subscriptions (abonnement mensuel)', categorie: 'Commercial', montant: 22, note: 'Extension payante, environ 279 $ par an.' },
    { type: 'fixe', nom: 'Brevo (e-mails et SMS automatiques)', categorie: 'Commercial', montant: 40, note: 'Formule Standard environ 15 € + SMS à environ 0,045 € l’unité.' },
    { type: 'fixe', nom: 'Entretien La Marzocco et cartouches de filtration', categorie: 'Local', montant: 60, note: 'Si non compris dans les 430 € du brief.' },
    { type: 'fixe', nom: 'Logiciel de planning et paie', categorie: 'Gestion', montant: 40, note: 'Optionnel.' },
    { type: 'ponctuelle', nom: 'Vaisselle réemployable pour 25 places (loi AGEC)', montant: 800, mois: 1, note: 'Tasses, verres, assiettes, couverts.' },
    { type: 'ponctuelle', nom: 'Four ou grill pour le snacking (absent du plan du bar)', montant: 1200, mois: 2, note: 'Kubaneh et focaccias toastées dès M2.' },
    { type: 'ponctuelle', nom: 'Formation hygiène HACCP 14 h', montant: 350, mois: 1, note: 'Souvent prise en charge par l’OPCO AKTO.' },
    { type: 'ponctuelle', nom: 'Stand au Paris Café Festival', montant: 1500, mois: 6, note: 'Tarif exposant à demander.' }
  ];

  // Répartition saisonnière des coffrets (Noël, Saint-Valentin, Aïd el-Fitr, Pâques)
  var REPARTITION_COFFRETS = [0.40, 0.22, 0.10, 0.12, 0.09, 0.07];

  // Plan annoncé dans le brief (§11), calculé avec 4 salariés
  var PLAN_DECLARE = { ca: 278383, margeBrute: 164623, resultat: 49123, franchiseLoyer: 18000 };

  // ------------------------------------------------------- leviers par défaut
  var LEVIERS_DEFAUT = {
    tassesJour: 135,
    tauxPatisserie: 55 / 135,
    snackingJour: 28,
    debutSnacking: 2,
    sachetsJour: 6,
    coffrets6Mois: 210,
    colisMois: 120,
    panierWebTtc: 49.10,
    kgB2BMois: 60,
    partVertB2B: 0.50,
    moisFranchise: 0,
    tresorerieDepart: 0,
    mensualitesEmprunt: 0,
    semainesStock: 0,
    formuleSumUp: 'standard',     // 'standard' (1,75 %) ou 'plus' (19 €/mois + 0,79 %)
    offreMollie: 'lancement'      // 'lancement' (0 % jusqu'au 31/12/2026) ou 'standard'
  };

  var SUMUP = {
    standard: { nom: 'SumUp sans abonnement', taux: 0.0175, abonnement: 0 },
    plus: { nom: 'SumUp Paiements Plus', taux: 0.0079, abonnement: 19 }
  };
  // Mollie (carte CB de particulier) : 1,4 % + 0,25 € selon Moneyvox, à vérifier dans l'espace Mollie
  var MOLLIE = { taux: 0.014, fixe: 0.25, partSansFraisLancement: [1, 0.4, 0, 0, 0, 0] };

  var B2B = {
    vert: [
      { nom: 'Vert Micro-Lot (sac 5 kg)', prix: 72.00, cout: 50.40, mix: 0.20 },
      { nom: 'Vert Signature (≥ 15 kg)', prix: 66.00, cout: 50.20, mix: 0.50 },
      { nom: 'Vert Contrat (≥ 30 kg)', prix: 61.50, cout: 50.00, mix: 0.30 }
    ],
    torrefie: [
      { nom: 'Torréfié Invité (3-9 kg)', prix: 92.00, cout: 63.60, mix: 0.40 },
      { nom: 'Torréfié Palace (10-24 kg)', prix: 86.50, cout: 63.40, mix: 0.40 },
      { nom: 'Torréfié Résident (≥ 25 kg)', prix: 81.00, cout: 63.10, mix: 0.20 }
    ]
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
      resume: 'Le site WordPress ouvre vers le 27/11 sans historique ni référencement. Côté pro, un cupping du jeudi mène à un échantillon, puis à une commande test, puis à un contrat : comptez 6 à 10 semaines par client.',
      detail: 'Colis web à 50, 55, 45, 60, 75 et 90 % de l’objectif. B2B à 15, 30, 50, 70, 85 et 100 %.',
      web: [0.50, 0.55, 0.45, 0.60, 0.75, 0.90], b2b: [0.15, 0.30, 0.50, 0.70, 0.85, 1.00] },
    { cle: 'paiements', titre: 'Commissions SumUp et Mollie',
      resume: 'Chaque paiement carte coûte : 1,75 % chez SumUp sans abonnement (0,79 % avec Paiements Plus à 19 € par mois), et environ 1,4 % + 0,25 € par commande web chez Mollie. Mollie annonce 0 % de frais pour les nouveaux clients WooCommerce jusqu’au 31/12/2026 (dans la limite de 50 000 €).',
      detail: '92 % des ventes boutique par carte. Formule SumUp et offre Mollie réglables dans les leviers. La ligne « Pennylane, Qonto, frais bancaires » reste pour la compta et la banque.',
      partCarte: 0.92 },
    { cle: 'port', titre: 'Frais de port du e-commerce',
      resume: 'La livraison offerte sur le premier coffret et le seuil de gratuité restent à votre charge. Tarifs publics 2026 pour 1 kg : Colissimo 8,89 à 9,59 €, Mondial Relay 5,99 € (dès 3,89 € en compte pro).',
      detail: '5,00 € HT net par colis : transport moyen 6,20 €, moins 2,00 € de participation client en moyenne, plus 0,80 € de carton et calage.',
      parColis: 5.00 },
    { cle: 'pertes', titre: 'Invendus de vitrine',
      resume: 'Le brief ne compte aucune perte. En vitrine fraîche, 6 à 10 % des pièces ne se vendent pas. Too Good To Go ou un « goûter des riverains » à 18 h en récupère une partie.',
      detail: '8 % du coût matière des pâtisseries et du snacking.',
      taux: 0.08 }
  ];

  // ================================================================= outils
  function round2(x) { return Math.round(x * 100) / 100; }
  function num(x, d) { var n = Number(x); return isFinite(n) ? n : (d || 0); }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function correction(cle) {
    for (var i = 0; i < CORRECTIONS.length; i++) if (CORRECTIONS[i].cle === cle) return CORRECTIONS[i];
    return null;
  }

  /** Remet des données saisies dans une forme sûre (types, bornes, champs manquants). */
  function normaliserDonnees(d) {
    var out = clone(DONNEES_DEFAUT);
    if (!d || typeof d !== 'object') return out;
    if (Array.isArray(d.chargesFixes)) {
      out.chargesFixes = d.chargesFixes.map(function (c, i) {
        var debut = Math.min(6, Math.max(1, Math.round(num(c.debut, 1))));
        var fin = Math.min(6, Math.max(debut, Math.round(num(c.fin, 6))));
        return { id: String(c.id || ('ligne-' + i)), nom: String(c.nom || 'Charge'), categorie: String(c.categorie || 'Autre'),
          montant: Math.max(0, num(c.montant)), debut: debut, fin: fin };
      });
    }
    if (Array.isArray(d.depensesPonctuelles)) {
      out.depensesPonctuelles = d.depensesPonctuelles.map(function (c, i) {
        return { id: String(c.id || ('ponct-' + i)), nom: String(c.nom || 'Dépense'), montant: Math.max(0, num(c.montant)),
          mois: Math.min(6, Math.max(1, Math.round(num(c.mois, 1)))) };
      });
    }
    if (d.catalogue && typeof d.catalogue === 'object') {
      Object.keys(out.catalogue).forEach(function (k) {
        if (!Array.isArray(d.catalogue[k])) return;
        out.catalogue[k] = d.catalogue[k].map(function (l, i) {
          var r = { id: String(l.id || (k + '-' + i)), nom: String(l.nom || 'Produit'), ttc: Math.max(0, num(l.ttc)),
            cout: Math.max(0, num(l.cout)), mix: Math.max(0, num(l.mix)), g: Math.max(0, num(l.g)) };
          if (l.parBoisson) r.parBoisson = true;
          return r;
        });
      });
    }
    return out;
  }

  // Moyenne pondérée d'un canal (mix exprimé en % ; normalisé si la somme n'est pas 100)
  function moyenneCanal(lignes, tva) {
    var somme = 0, i;
    var vendables = lignes.filter(function (l) { return !l.parBoisson; });
    for (i = 0; i < vendables.length; i++) somme += vendables[i].mix;
    var ttc = 0, cout = 0, g = 0;
    if (somme > 0) {
      for (i = 0; i < vendables.length; i++) {
        var w = vendables[i].mix / somme;
        ttc += vendables[i].ttc * w; cout += vendables[i].cout * w; g += (vendables[i].g || 0) * w;
      }
    }
    lignes.forEach(function (l) { if (l.parBoisson) { ttc += l.ttc; cout += l.cout; } });
    return { ttc: ttc, ht: ttc / (1 + tva), cout: cout, tva: tva, grammes: g, sommeMix: somme };
  }

  // Web : mix = part du CA. Taux de coût moyen et grammes de café par euro HT.
  function moyenneWeb(lignes) {
    var somme = 0, t = 0, gpe = 0;
    lignes.forEach(function (l) { somme += l.mix; });
    if (somme <= 0) return { tauxCout: 0, grammesParEuro: 0, sommeMix: 0 };
    lignes.forEach(function (l) {
      var ht = l.ttc / (1 + TVA_55);
      if (ht <= 0) return;
      t += (l.mix / somme) * (l.cout / ht);
      gpe += (l.mix / somme) * ((l.g || 0) / ht);
    });
    return { tauxCout: t, grammesParEuro: gpe, sommeMix: somme };
  }

  function moyenneB2B(lignes) {
    var prix = 0, cout = 0;
    lignes.forEach(function (l) { prix += l.prix * l.mix; cout += l.cout * l.mix; });
    return { prix: prix, cout: cout };
  }

  function unitaires(donnees) {
    var c = donnees.catalogue;
    return {
      boisson: moyenneCanal(c.boissons, TVA_10),
      patisserie: moyenneCanal(c.patisseries, TVA_10),
      snacking: moyenneCanal(c.snacking, TVA_10),
      sachet: moyenneCanal(c.sachets, TVA_55),
      coffret: moyenneCanal(c.coffrets, TVA_55),
      web: moyenneWeb(c.web),
      b2bVert: moyenneB2B(B2B.vert),
      b2bTorrefie: moyenneB2B(B2B.torrefie)
    };
  }

  /** Charges fixes du mois i (0 à 5), ligne par ligne, avec la franchise de loyer. */
  function fixesDuMois(donnees, i, moisFranchise) {
    var lignes = [], total = 0;
    donnees.chargesFixes.forEach(function (c) {
      var actif = (i + 1) >= c.debut && (i + 1) <= c.fin;
      var montant = actif ? c.montant : 0;
      if (c.id === 'loyer' && i < moisFranchise) montant = 0;
      lignes.push({ id: c.id, nom: c.nom, categorie: c.categorie, montant: montant });
      total += montant;
    });
    return { lignes: lignes, total: total };
  }

  /**
   * simuler(leviers, actives, donnees) → résultat mensuel et totaux.
   * leviers : partiel accepté ; actives : { cleCorrection: true } ; donnees : DONNEES (normalisées ici).
   */
  function simuler(leviersPartiels, actives, donneesBrutes) {
    var p = {}, k;
    for (k in LEVIERS_DEFAUT) p[k] = LEVIERS_DEFAUT[k];
    for (k in (leviersPartiels || {})) p[k] = leviersPartiels[k];
    var on = actives || {};
    var d = normaliserDonnees(donneesBrutes || DONNEES_DEFAUT);
    var u = unitaires(d);

    var cRampe = correction('rampe'), cCal = correction('calendrier'), cRod = correction('rodage');
    var cPai = correction('paiements'), cPort = correction('port'), cPertes = correction('pertes');
    var sumup = SUMUP[p.formuleSumUp] || SUMUP.standard;
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
        web: ca.web * u.web.tauxCout,
        b2b: kgVert * u.b2bVert.cout + kgTorr * u.b2bTorrefie.cout
      };
      var caTotal = 0, coutTotal = 0, marge = {};
      for (k in ca) { marge[k] = ca[k] - cout[k]; caTotal += ca[k]; coutTotal += cout[k]; }
      var margeBrute = caTotal - coutTotal;

      // Variables issues des corrections
      var ttcBoutique = (ca.boissons + ca.patisseries + ca.snacking) * (1 + TVA_10) + ca.boutique * (1 + TVA_55);
      var fraisSumUp = 0, fraisMollie = 0;
      if (on.paiements) {
        fraisSumUp = ttcBoutique * cPai.partCarte * sumup.taux + sumup.abonnement;
        var horsPromo = p.offreMollie === 'lancement' ? 1 - MOLLIE.partSansFraisLancement[i] : 1;
        fraisMollie = q.colis * (p.panierWebTtc * MOLLIE.taux + MOLLIE.fixe) * horsPromo;
      }
      var fraisPaiement = fraisSumUp + fraisMollie;
      var fraisPort = on.port ? q.colis * cPort.parColis : 0;
      var pertes = on.pertes ? (cout.patisseries + cout.snacking) * cPertes.taux : 0;

      var fx = fixesDuMois(d, i, p.moisFranchise);
      var ponctuelles = 0;
      d.depensesPonctuelles.forEach(function (dp) { if (dp.mois === i + 1) ponctuelles += dp.montant; });

      var resultat = margeBrute - fraisPaiement - fraisPort - pertes - fx.total - ponctuelles;

      var netKg = (q.tasses * u.boisson.grammes + q.sachets * u.sachet.grammes + q.coffrets * u.coffret.grammes
        + ca.web * u.web.grammesParEuro) / 1000 + kgTorr;
      var vertKg = netKg / RENDEMENT + kgVert;

      mois.push({
        id: MOIS[i].id, periode: MOIS[i].periode, jours: jours,
        quantites: q, ca: ca, cout: cout, marge: marge, caTotal: caTotal, margeBrute: margeBrute,
        fraisPaiement: fraisPaiement, fraisSumUp: fraisSumUp, fraisMollie: fraisMollie, fraisPort: fraisPort, pertes: pertes,
        fixes: fx.lignes, totalFixes: fx.total, ponctuelles: ponctuelles,
        resultat: resultat, cafeVertKg: vertKg, flux: resultat - p.mensualitesEmprunt
      });
    }

    var pm = pointMort(p, on, u, d);
    var achatStock = p.semainesStock * (pm.cafeVertKgMoisRegime * 12 / 52) * PRIX_VERT_KG;
    mois[0].achatStock = achatStock;
    mois[0].flux -= achatStock;

    var tot = { caTotal: 0, margeBrute: 0, fraisPaiement: 0, fraisPort: 0, pertes: 0, totalFixes: 0, ponctuelles: 0, resultat: 0, flux: 0,
      ca: {}, marge: {}, quantites: {} };
    var cumul = p.tresorerieDepart, tresoMin = Infinity, moisTresoMin = null, premierMoisPositif = null;
    mois.forEach(function (m) {
      ['caTotal', 'margeBrute', 'fraisPaiement', 'fraisPort', 'pertes', 'totalFixes', 'ponctuelles', 'resultat', 'flux'].forEach(function (c) { tot[c] += m[c]; });
      for (var kk in m.ca) { tot.ca[kk] = (tot.ca[kk] || 0) + m.ca[kk]; tot.marge[kk] = (tot.marge[kk] || 0) + m.marge[kk]; }
      for (kk in m.quantites) tot.quantites[kk] = (tot.quantites[kk] || 0) + m.quantites[kk];
      cumul += m.flux; m.tresorerieFin = cumul;
      if (cumul < tresoMin) { tresoMin = cumul; moisTresoMin = m.id; }
      if (premierMoisPositif === null && m.resultat > 0) premierMoisPositif = m.id;
    });

    return { leviers: p, actives: on, donnees: d, unitaires: u, mois: mois, totaux: tot,
      tresorerieFin: cumul, tresorerieMin: tresoMin, moisTresorerieMin: moisTresoMin,
      premierMoisPositif: premierMoisPositif, achatStock: achatStock, pointMort: pm };
  }

  /**
   * Point mort en régime de croisière (mois de 30 jours, montée en charge terminée, sans saisonnalité).
   * Pâtisseries, snacking et sachets suivent la fréquentation dans les proportions de vos leviers.
   * Web, B2B et coffrets restent au niveau de leurs leviers. Charges fixes : celles du mois M6.
   */
  function pointMort(p, on, u, d) {
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
      + p.colisMois * (panierHt * (1 - u.web.tauxCout) - (on.paiements ? p.panierWebTtc * MOLLIE.taux + MOLLIE.fixe : 0) - (on.port ? cPort.parColis : 0))
      + p.kgB2BMois * (p.partVertB2B * (u.b2bVert.prix - u.b2bVert.cout) + (1 - p.partVertB2B) * (u.b2bTorrefie.prix - u.b2bTorrefie.cout));

    var fixes = fixesDuMois(d, 5, 0).total + (on.paiements ? sumup.abonnement : 0);
    var tasses = parTasse > 0 ? (fixes - autres) / (jours * parTasse) : Infinity;

    var kgVert = p.kgB2BMois * p.partVertB2B, kgTorr = p.kgB2BMois - kgVert;
    var netKg = (jours * p.tassesJour * u.boisson.grammes + jours * p.sachetsJour * u.sachet.grammes
      + (p.coffrets6Mois / 6) * u.coffret.grammes + p.colisMois * panierHt * u.web.grammesParEuro) / 1000 + kgTorr;

    return { tassesJour: Math.max(0, tasses), chargesFixesMois: fixes, contributionParTasse: parTasse,
      contributionAutresCanaux: autres, cafeVertKgMoisRegime: netKg / RENDEMENT + kgVert };
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
      var ht = g[1] / (1 + g[5]), marge = ht - g[2], taux = marge / ht * 100;
      return { nom: g[0], ttc: g[1], tva: g[5], ht: round2(ht), cout: g[2], margeBrief: g[3], margeCalculee: round2(marge),
        tauxBrief: g[4], tauxCalcule: Math.round(taux * 10) / 10 };
    });
  }

  var API = {
    MOIS: MOIS, CANAUX: CANAUX, DONNEES_DEFAUT: DONNEES_DEFAUT, SUGGESTIONS: SUGGESTIONS, CORRECTIONS: CORRECTIONS,
    LEVIERS_DEFAUT: LEVIERS_DEFAUT, SUMUP: SUMUP, MOLLIE: MOLLIE, B2B: B2B, PLAN_DECLARE: PLAN_DECLARE,
    REPARTITION_COFFRETS: REPARTITION_COFFRETS, RENDEMENT: RENDEMENT, PRIX_VERT_KG: PRIX_VERT_KG, TVA_10: TVA_10, TVA_55: TVA_55,
    normaliserDonnees: normaliserDonnees, simuler: simuler, unitaires: unitaires, verifierGrille: verifierGrille, fixesDuMois: fixesDuMois
  };
  if (typeof module !== 'undefined' && module.exports) module.exports = API;
  else root.YemkafModele = API;
})(this);
