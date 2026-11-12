/*
 * YEM'KAF : modèle financier des 6 premiers mois (version 3 : tout est paramétrable)
 * Période : soft opening le 20/11/2026, puis 6 mois glissants (du 20 au 19) jusqu'au 19/05/2027.
 *
 * Trois couches :
 *  1. DONNEES (modifiables, enregistrées, exportables) : charges fixes, dépenses ponctuelles, catalogue
 *     (prix, coût hors café, grammes de café, part des ventes), grille B2B, paramètres (SumUp, Mollie,
 *     prix du café vert, trésorerie).
 *  2. LEVIERS (curseurs d'exploration, non enregistrés) : volumes, panier web, B2B, franchise, stock.
 *  3. HYPOTHÈSES PRUDENTES (désactivées dans le « Plan », toutes actives dans le « Scénario prudent ») :
 *     montée en charge, saisonnalité, rodage web/B2B, frais de port, invendus.
 *
 * Coût d'un produit = coût hors café (gobelet, lait, épices, emballage…) + grammes de café × prix du vert ÷ rendement.
 * Montants HT sauf champs *Ttc ; TVA 10 % (boissons, pâtisseries, snacking), 5,5 % (café, coffrets, web).
 * Fichier autonome : navigateur (window.YemkafModele) et Node (require).
 */
(function (root) {
  'use strict';

  var TVA_10 = 0.10, TVA_55 = 0.055;
  var RENDEMENT = 0.80;          // 1 kg de vert → 800 g vendables (freinte, rétention, purges)
  var VERT_REFERENCE = 50;       // prix du vert inclus dans les anciens coûts (conversion des données enregistrées en v2)

  // ---------------------------------------------------------------- calendrier
  var MOIS = [
    { id: 'M1', periode: '20 nov – 19 déc', joursOuverts: 30,
      reperes: ['Soft opening 20/11', 'E-shop ~27/11 (Black Friday)', 'Soirée VIP samedi 12/12', 'Achats de Noël'] },
    { id: 'M2', periode: '20 déc – 19 janv', joursOuverts: 29,
      reperes: ['Noël : fermé le 25/12', 'Fermé le 1/01', 'Soldes d’hiver mi-janvier', 'Galette des rois'] },
    { id: 'M3', periode: '20 janv – 19 févr', joursOuverts: 31,
      reperes: ['Creux de janvier', 'Début du Ramadan ~7-8/02', 'Saint-Valentin 14/02'] },
    { id: 'M4', periode: '20 févr – 19 mars', joursOuverts: 28,
      reperes: ['Ramadan (box ftour à emporter)', 'Aïd el-Fitr ~10/03 (coffrets)'] },
    { id: 'M5', periode: '20 mars – 19 avr', joursOuverts: 31,
      reperes: ['Pâques 28/03', 'Retour des touristes'] },
    { id: 'M6', periode: '20 avr – 19 mai', joursOuverts: 29,
      reperes: ['Paris Café Festival 24-26/04 (à confirmer)', '1er mai : fermé', 'Aïd al-Adha ~16/05'] }
  ];

  // ------------------------------------------------- données modifiables par défaut
  // cout = coût HT hors café ; g = grammes de café net par unité
  var DONNEES_DEFAUT = {
    version: 3,
    chargesFixes: [
      { id: 'barista-chef', nom: 'Chef barista (coût employeur)', categorie: 'Équipe', montant: 2800, debut: 1, fin: 6 },
      { id: 'barista-senior', nom: 'Barista senior (coût employeur)', categorie: 'Équipe', montant: 2800, debut: 1, fin: 6 },
      { id: 'barista-web', nom: 'Barista vente et web (coût employeur)', categorie: 'Équipe', montant: 2800, debut: 1, fin: 6 },
      { id: 'associes', nom: 'Rémunération des associés', categorie: 'Équipe', montant: 0, debut: 1, fin: 6 },
      { id: 'loyer', nom: 'Loyer et charges, rue du Renard', categorie: 'Local', montant: 6000, debut: 1, fin: 6 },
      { id: 'stockage', nom: 'Loyer du bureau de stockage', categorie: 'Local', montant: 630, debut: 1, fin: 6 },
      { id: 'energie', nom: 'Électricité triphasée, eau filtrée, fibre', categorie: 'Local', montant: 720, debut: 1, fin: 6 },
      { id: 'compta', nom: 'Pennylane, Qonto, frais bancaires', categorie: 'Gestion', montant: 540, debut: 1, fin: 6 },
      { id: 'mkt-b2b', nom: 'Marketing B2B et échantillons', categorie: 'Commercial', montant: 510, debut: 1, fin: 6 },
      { id: 'rp', nom: 'RP, presse et équipement snacking', categorie: 'Commercial', montant: 490, debut: 1, fin: 6 },
      { id: 'assurances', nom: 'Assurances, SACEM, hygiène, maintenance', categorie: 'Gestion', montant: 430, debut: 1, fin: 6 },
      { id: 'web', nom: 'Hébergement WordPress et logistique e-commerce', categorie: 'Commercial', montant: 360, debut: 1, fin: 6 }
    ],
    depensesPonctuelles: [
      { id: 'soiree-vip', nom: 'Soirée VIP du samedi 12 décembre (photographe, échantillons, impression)', montant: 800, mois: 1 }
    ],
    catalogue: {
      boissons: [
        { id: 'espresso', nom: 'Espresso', ttc: 3.50, cout: 0.135, mix: 14, g: 10 },
        { id: 'double', nom: 'Double Espresso', ttc: 4.80, cout: 0.135, mix: 6, g: 18 },
        { id: 'cappuccino', nom: 'Cappuccino', ttc: 5.50, cout: 0.405, mix: 14, g: 18 },
        { id: 'flat-white', nom: 'Flat White', ttc: 6.00, cout: 0.425, mix: 12, g: 18 },
        { id: 'latte', nom: 'Latte ou Iced Latte', ttc: 6.20, cout: 0.535, mix: 16, g: 18 },
        { id: 'v60', nom: 'V60 Grand Cru', ttc: 8.00, cout: 0.2825, mix: 6, g: 15 },
        { id: 'radai', nom: 'Rada’i Gingembre', ttc: 5.20, cout: 0.3975, mix: 4, g: 13 },
        { id: 'qishr', nom: 'Qishr Cascara Épicé', ttc: 5.50, cout: 0.64, mix: 8, g: 0 },
        { id: 'shai', nom: 'Shai Adeni Karak', ttc: 5.80, cout: 0.74, mix: 8, g: 0 },
        { id: 'mufawar', nom: 'Mufawar Cardamome', ttc: 6.00, cout: 0.4125, mix: 6, g: 15 },
        { id: 'baydani', nom: 'Qahwa Baydani', ttc: 6.20, cout: 0.455, mix: 6, g: 14 },
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
        { id: 'sachet-150', nom: 'Sachet 150 g', ttc: 19.00, cout: 0.625, mix: 40, g: 150 },
        { id: 'sachet-200', nom: 'Sachet 200 g', ttc: 25.00, cout: 0.65, mix: 0, g: 200 },
        { id: 'sachet-250', nom: 'Sachet 250 g', ttc: 30.00, cout: 0.695, mix: 45, g: 250 },
        { id: 'sachet-melange', nom: 'Mélange épicé 200 g (≈ 85 % café, 15 % épices)', ttc: 24.00, cout: 1.735, mix: 15, g: 170 }
      ],
      coffrets: [
        { id: 'duo', nom: 'Coffret Duo Boutique', ttc: 52.00, cout: 3.10, mix: 45, g: 400 },
        { id: 'prestige', nom: 'Coffret Prestige Écrin', ttc: 79.00, cout: 13.25, mix: 35, g: 500 },
        { id: 'trois-terroirs', nom: 'Coffret Trois Terroirs', ttc: 69.00, cout: 4.70, mix: 20, g: 600 }
      ],
      // Web : le mix est une part du chiffre d'affaires du panier
      web: [
        { id: 'web-coffret', nom: 'Coffret Web Trois Terroirs', ttc: 69.00, cout: 4.70, mix: 30, g: 600 },
        { id: 'web-abo', nom: 'Abonnement Web Mensuel (500 g)', ttc: 54.00, cout: 3.40, mix: 25, g: 500 },
        { id: 'web-sachet', nom: 'Sachet Web 250 g', ttc: 27.90, cout: 2.075, mix: 35, g: 250 },
        { id: 'web-qishr', nom: 'Pack Rituel Qishr Web', ttc: 22.00, cout: 6.70, mix: 10, g: 0 }
      ]
    },
    // Grille professionnelle ajustée le 12/11/2026 ; extra = frais hors café par kg
    b2b: {
      vert: [
        { id: 'vert-micro', nom: 'Vert Micro-Lot (sac de 5 kg)', prix: 80.00, extra: 0.40, mix: 20 },
        { id: 'vert-signature', nom: 'Vert Signature (dès 15 kg)', prix: 75.00, extra: 0.20, mix: 50 },
        { id: 'vert-contrat', nom: 'Vert Contrat (dès 30 kg)', prix: 70.00, extra: 0.00, mix: 30 }
      ],
      torrefie: [
        { id: 'torr-invite', nom: 'Torréfié Invité (3 à 9 kg/mois)', prix: 99.00, extra: 1.10, mix: 40 },
        { id: 'torr-palace', nom: 'Torréfié Palace (10 à 24 kg/mois)', prix: 94.00, extra: 0.90, mix: 40 },
        { id: 'torr-resident', nom: 'Torréfié Résident (dès 25 kg/mois)', prix: 89.00, extra: 0.60, mix: 20 }
      ]
    },
    parametres: {
      prixVertKg: 55,            // café vert tout compris, 50 à 60 € selon les lots
      sumupAbonnement: 90,       // contrat SumUp : 90 € par mois
      sumupTaux: 0.0049,         // et 0,49 % par paiement
      partCarte: 0.92,           // part des ventes boutique payées par carte
      mollieTaux: 0.014,         // Mollie CB : 1,4 % + 0,25 € (à vérifier)
      mollieFixe: 0.25,
      mollieOffre: true,         // 0 % pour les nouveaux comptes jusqu'au 31/12/2026
      tresorerieDepart: 15000,   // solde Qonto au début de la période
      mensualitesEmprunt: 0      // aucun crédit en cours
    }
  };

  var SUGGESTIONS = [
    { type: 'fixe', nom: 'Mutuelle, médecine du travail, repas du personnel (HCR)', categorie: 'Équipe', montant: 180, note: 'Si non compris dans les 2 800 € par salarié.' },
    { type: 'fixe', nom: 'Licence WooCommerce Subscriptions (abonnement mensuel)', categorie: 'Commercial', montant: 22, note: 'Extension payante, environ 279 $ par an.' },
    { type: 'fixe', nom: 'Brevo (e-mails et SMS automatiques)', categorie: 'Commercial', montant: 40, note: 'Formule Standard environ 15 € + SMS à environ 0,045 € l’unité.' },
    { type: 'fixe', nom: 'Entretien La Marzocco et cartouches de filtration', categorie: 'Local', montant: 60, note: 'Si non compris dans l’abonnement d’entretien.' },
    { type: 'fixe', nom: 'Logiciel de planning et paie', categorie: 'Gestion', montant: 40, note: 'Optionnel.' },
    { type: 'ponctuelle', nom: 'Vaisselle réemployable pour 25 places (loi AGEC)', montant: 800, mois: 1, note: 'Tasses, verres, assiettes, couverts.' },
    { type: 'ponctuelle', nom: 'Four ou grill pour le snacking (absent du plan du bar)', montant: 1200, mois: 2, note: 'Kubaneh et focaccias toastées dès M2.' },
    { type: 'ponctuelle', nom: 'Formation hygiène HACCP 14 h', montant: 350, mois: 1, note: 'Souvent prise en charge par l’OPCO AKTO.' },
    { type: 'ponctuelle', nom: 'Stand au Paris Café Festival', montant: 1500, mois: 6, note: 'Tarif exposant à demander.' }
  ];

  var REPARTITION_COFFRETS = [0.40, 0.22, 0.10, 0.12, 0.09, 0.07];
  var MOLLIE_SANS_FRAIS = [1, 0.4, 0, 0, 0, 0];   // part du mois couverte par l'offre 0 % (jusqu'au 31/12)

  // ------------------------------------------------------- leviers d'exploration
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
    semainesStock: 0
  };

  // ------------------------------------------------------- hypothèses prudentes
  var CORRECTIONS = [
    { cle: 'rampe', titre: 'Montée en charge de la boutique',
      resume: 'Le plan vise 135 tasses par jour dès le 20 novembre. Une adresse neuve construit sa clientèle sur plusieurs mois, et le Centre Pompidou voisin est fermé jusqu’en 2030 : moins de touristes devant la porte.',
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
    { cle: 'port', titre: 'Frais de port du e-commerce',
      resume: 'La livraison offerte sur le premier coffret et le seuil de gratuité restent à votre charge. Tarifs publics 2026 pour 1 kg : Colissimo 8,89 à 9,59 €, Mondial Relay 5,99 € (dès 3,89 € en compte pro).',
      detail: '5,00 € HT net par colis : transport moyen 6,20 €, moins 2,00 € de participation client en moyenne, plus 0,80 € de carton et calage.',
      parColis: 5.00 },
    { cle: 'pertes', titre: 'Invendus de vitrine',
      resume: 'Le plan ne compte aucune perte. En vitrine fraîche, 6 à 10 % des pièces ne se vendent pas. Too Good To Go ou un « goûter des riverains » à 18 h en récupère une partie.',
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
  function borne(x, min, max, d) { var n = num(x, d); return Math.min(max, Math.max(min, n)); }

  /** Remet des données saisies dans une forme sûre ; convertit les données v2 (coût café compris). */
  function normaliserDonnees(d) {
    var out = clone(DONNEES_DEFAUT);
    if (!d || typeof d !== 'object') return out;
    var v2 = !d.version || d.version < 3;
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
          var g = Math.max(0, num(l.g));
          var cout = Math.max(0, num(l.cout));
          if (v2 && g > 0) cout = Math.max(0, cout - g * VERT_REFERENCE / RENDEMENT / 1000);
          var r = { id: String(l.id || (k + '-' + i)), nom: String(l.nom || 'Produit'), ttc: Math.max(0, num(l.ttc)),
            cout: cout, mix: Math.max(0, num(l.mix)), g: g };
          if (l.parBoisson) r.parBoisson = true;
          return r;
        });
      });
    }
    if (d.b2b && typeof d.b2b === 'object') {
      ['vert', 'torrefie'].forEach(function (k) {
        if (!Array.isArray(d.b2b[k])) return;
        out.b2b[k] = d.b2b[k].map(function (l, i) {
          return { id: String(l.id || (k + '-' + i)), nom: String(l.nom || 'Formule'), prix: Math.max(0, num(l.prix)),
            extra: Math.max(0, num(l.extra)), mix: Math.max(0, num(l.mix)) };
        });
      });
    }
    if (d.parametres && typeof d.parametres === 'object') {
      var p = d.parametres, o = out.parametres;
      o.prixVertKg = borne(p.prixVertKg, 0, 500, o.prixVertKg);
      o.sumupAbonnement = borne(p.sumupAbonnement, 0, 2000, o.sumupAbonnement);
      o.sumupTaux = borne(p.sumupTaux, 0, 0.1, o.sumupTaux);
      o.partCarte = borne(p.partCarte, 0, 1, o.partCarte);
      o.mollieTaux = borne(p.mollieTaux, 0, 0.1, o.mollieTaux);
      o.mollieFixe = borne(p.mollieFixe, 0, 5, o.mollieFixe);
      o.mollieOffre = p.mollieOffre !== false;
      o.tresorerieDepart = num(p.tresorerieDepart, o.tresorerieDepart);
      o.mensualitesEmprunt = borne(p.mensualitesEmprunt, 0, 1e6, 0);
    }
    out.version = 3;
    return out;
  }

  /** Coût total d'une ligne : hors café + café au prix du vert. */
  function coutTotal(l, prixVertKg) { return l.cout + (l.g || 0) * prixVertKg / RENDEMENT / 1000; }

  function moyenneCanal(lignes, tva, prixVert) {
    var vendables = lignes.filter(function (l) { return !l.parBoisson; });
    var somme = vendables.reduce(function (a, l) { return a + l.mix; }, 0);
    var ttc = 0, cout = 0, g = 0;
    if (somme > 0) vendables.forEach(function (l) { var w = l.mix / somme; ttc += l.ttc * w; cout += coutTotal(l, prixVert) * w; g += (l.g || 0) * w; });
    lignes.forEach(function (l) { if (l.parBoisson) { ttc += l.ttc; cout += coutTotal(l, prixVert); } });
    return { ttc: ttc, ht: ttc / (1 + tva), cout: cout, tva: tva, grammes: g, sommeMix: somme };
  }

  function moyenneWeb(lignes, prixVert) {
    var somme = lignes.reduce(function (a, l) { return a + l.mix; }, 0), t = 0, gpe = 0;
    if (somme <= 0) return { tauxCout: 0, grammesParEuro: 0, sommeMix: 0 };
    lignes.forEach(function (l) {
      var ht = l.ttc / (1 + TVA_55); if (ht <= 0) return;
      t += (l.mix / somme) * (coutTotal(l, prixVert) / ht);
      gpe += (l.mix / somme) * ((l.g || 0) / ht);
    });
    return { tauxCout: t, grammesParEuro: gpe, sommeMix: somme };
  }

  function moyenneB2B(lignes, coutCafeKg) {
    var somme = lignes.reduce(function (a, l) { return a + l.mix; }, 0), prix = 0, cout = 0;
    if (somme > 0) lignes.forEach(function (l) { var w = l.mix / somme; prix += l.prix * w; cout += (coutCafeKg + l.extra) * w; });
    return { prix: prix, cout: cout };
  }

  function unitaires(d) {
    var c = d.catalogue, pv = d.parametres.prixVertKg;
    return {
      boisson: moyenneCanal(c.boissons, TVA_10, pv),
      patisserie: moyenneCanal(c.patisseries, TVA_10, pv),
      snacking: moyenneCanal(c.snacking, TVA_10, pv),
      sachet: moyenneCanal(c.sachets, TVA_55, pv),
      coffret: moyenneCanal(c.coffrets, TVA_55, pv),
      web: moyenneWeb(c.web, pv),
      b2bVert: moyenneB2B(d.b2b.vert, pv),
      b2bTorrefie: moyenneB2B(d.b2b.torrefie, pv / RENDEMENT)
    };
  }

  function fixesDuMois(donnees, i, moisFranchise) {
    var lignes = [], total = 0;
    donnees.chargesFixes.forEach(function (c) {
      var montant = ((i + 1) >= c.debut && (i + 1) <= c.fin) ? c.montant : 0;
      if (c.id === 'loyer' && i < moisFranchise) montant = 0;
      lignes.push({ id: c.id, nom: c.nom, categorie: c.categorie, montant: montant });
      total += montant;
    });
    return { lignes: lignes, total: total };
  }

  /** simuler(leviers, actives, donnees) → résultat mensuel et totaux. */
  function simuler(leviersPartiels, actives, donneesBrutes) {
    var p = {}, k;
    for (k in LEVIERS_DEFAUT) p[k] = LEVIERS_DEFAUT[k];
    for (k in (leviersPartiels || {})) if (k in LEVIERS_DEFAUT) p[k] = leviersPartiels[k];
    var on = actives || {};
    var d = normaliserDonnees(donneesBrutes || DONNEES_DEFAUT);
    var pa = d.parametres;
    var u = unitaires(d);
    var cRampe = correction('rampe'), cCal = correction('calendrier'), cRod = correction('rodage');
    var cPort = correction('port'), cPertes = correction('pertes');
    var mois = [];

    for (var i = 0; i < MOIS.length; i++) {
      var jours = on.calendrier ? cCal.jours[i] : 30;
      var fBoutique = jours * (on.calendrier ? cCal.saison[i] : 1) * (on.rampe ? cRampe.valeurs[i] : 1);
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
      var caTotal = 0, coutTotalMois = 0, marge = {};
      for (k in ca) { marge[k] = ca[k] - cout[k]; caTotal += ca[k]; coutTotalMois += cout[k]; }
      var margeBrute = caTotal - coutTotalMois;

      // Paiements (données réelles : contrat SumUp, Mollie)
      var ttcBoutique = (ca.boissons + ca.patisseries + ca.snacking) * (1 + TVA_10) + ca.boutique * (1 + TVA_55);
      var fraisSumUp = ttcBoutique * pa.partCarte * pa.sumupTaux + pa.sumupAbonnement;
      var horsOffre = pa.mollieOffre ? 1 - MOLLIE_SANS_FRAIS[i] : 1;
      var fraisMollie = q.colis * (p.panierWebTtc * pa.mollieTaux + pa.mollieFixe) * horsOffre;
      var fraisPort = on.port ? q.colis * cPort.parColis : 0;
      var pertes = on.pertes ? (cout.patisseries + cout.snacking) * cPertes.taux : 0;

      var fx = fixesDuMois(d, i, p.moisFranchise);
      var ponctuelles = 0;
      d.depensesPonctuelles.forEach(function (dp) { if (dp.mois === i + 1) ponctuelles += dp.montant; });
      var resultat = margeBrute - fraisSumUp - fraisMollie - fraisPort - pertes - fx.total - ponctuelles;

      var netKg = (q.tasses * u.boisson.grammes + q.sachets * u.sachet.grammes + q.coffrets * u.coffret.grammes
        + ca.web * u.web.grammesParEuro) / 1000 + kgTorr;
      mois.push({
        id: MOIS[i].id, periode: MOIS[i].periode, jours: jours, quantites: q, ca: ca, cout: cout, marge: marge,
        caTotal: caTotal, margeBrute: margeBrute, fraisSumUp: fraisSumUp, fraisMollie: fraisMollie,
        fraisPaiement: fraisSumUp + fraisMollie, fraisPort: fraisPort, pertes: pertes,
        fixes: fx.lignes, totalFixes: fx.total, ponctuelles: ponctuelles, resultat: resultat,
        cafeVertKg: netKg / RENDEMENT + kgVert, flux: resultat - pa.mensualitesEmprunt
      });
    }

    var pm = pointMort(p, u, d);
    var achatStock = p.semainesStock * (pm.cafeVertKgMoisRegime * 12 / 52) * pa.prixVertKg;
    mois[0].achatStock = achatStock;
    mois[0].flux -= achatStock;

    var tot = { caTotal: 0, margeBrute: 0, fraisPaiement: 0, fraisSumUp: 0, fraisMollie: 0, fraisPort: 0, pertes: 0, totalFixes: 0,
      ponctuelles: 0, resultat: 0, flux: 0, ca: {}, marge: {}, quantites: {} };
    var cumul = pa.tresorerieDepart, tresoMin = Infinity, moisTresoMin = null, premierMoisPositif = null;
    mois.forEach(function (m) {
      ['caTotal', 'margeBrute', 'fraisPaiement', 'fraisSumUp', 'fraisMollie', 'fraisPort', 'pertes', 'totalFixes', 'ponctuelles', 'resultat', 'flux']
        .forEach(function (c) { tot[c] += m[c]; });
      for (var kk in m.ca) { tot.ca[kk] = (tot.ca[kk] || 0) + m.ca[kk]; tot.marge[kk] = (tot.marge[kk] || 0) + m.marge[kk]; }
      for (kk in m.quantites) tot.quantites[kk] = (tot.quantites[kk] || 0) + m.quantites[kk];
      cumul += m.flux; m.tresorerieFin = cumul;
      if (cumul < tresoMin) { tresoMin = cumul; moisTresoMin = m.id; }
      if (premierMoisPositif === null && m.resultat > 0) premierMoisPositif = m.id;
    });

    return { leviers: p, actives: on, donnees: d, parametres: pa, unitaires: u, mois: mois, totaux: tot,
      tresorerieFin: cumul, tresorerieMin: tresoMin, moisTresorerieMin: moisTresoMin,
      premierMoisPositif: premierMoisPositif, achatStock: achatStock, pointMort: pm };
  }

  /** Point mort en régime (30 jours, sans montée en charge ni saisonnalité), charges fixes de M6. */
  function pointMort(p, u, d) {
    var pa = d.parametres, cPort = correction('port');
    var jours = 30, carte = pa.partCarte * pa.sumupTaux, t = Math.max(p.tassesJour, 1);
    function contribution(unit, tva) { return (unit.ht - unit.cout) - unit.ht * (1 + tva) * carte; }
    var parTasse = contribution(u.boisson, TVA_10)
      + p.tauxPatisserie * contribution(u.patisserie, TVA_10)
      + (p.snackingJour / t) * contribution(u.snacking, TVA_10)
      + (p.sachetsJour / t) * contribution(u.sachet, TVA_55);
    var panierHt = p.panierWebTtc / (1 + TVA_55);
    var autres = (p.coffrets6Mois / 6) * contribution(u.coffret, TVA_55)
      + p.colisMois * (panierHt * (1 - u.web.tauxCout) - (p.panierWebTtc * pa.mollieTaux + pa.mollieFixe))
      + p.kgB2BMois * (p.partVertB2B * (u.b2bVert.prix - u.b2bVert.cout) + (1 - p.partVertB2B) * (u.b2bTorrefie.prix - u.b2bTorrefie.cout));
    var fixes = fixesDuMois(d, 5, 0).total + pa.sumupAbonnement;
    var tasses = parTasse > 0 ? (fixes - autres) / (jours * parTasse) : Infinity;
    var kgVert = p.kgB2BMois * p.partVertB2B, kgTorr = p.kgB2BMois - kgVert;
    var netKg = (jours * p.tassesJour * u.boisson.grammes + jours * p.sachetsJour * u.sachet.grammes
      + (p.coffrets6Mois / 6) * u.coffret.grammes + p.colisMois * panierHt * u.web.grammesParEuro) / 1000 + kgTorr;
    return { tassesJour: Math.max(0, tasses), chargesFixesMois: fixes, contributionParTasse: parTasse,
      contributionAutresCanaux: autres, cafeVertKgMoisRegime: netKg / RENDEMENT + kgVert };
  }

  /** Export CSV (séparateur « ; », décimales à virgule, pour Excel en français). */
  function exportCSV(r) {
    function n(v) { return (Math.round(v * 100) / 100).toFixed(2).replace('.', ','); }
    function ligne(cells) { return cells.map(function (c) { var s = String(c); return /[;"\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; }).join(';'); }
    var ms = r.mois, out = [];
    var tot = function (f) { return ms.reduce(function (a, m) { return a + f(m); }, 0); };
    function row(label, f) { out.push(ligne([label].concat(ms.map(function (m) { return n(f(m)); })).concat([n(tot(f))]))); }
    out.push(ligne(['YEM\'KAF - compte de résultat prévisionnel (HT, en euros)']));
    out.push(ligne(['Hypothèses prudentes appliquées', CORRECTIONS.filter(function (c) { return r.actives[c.cle]; }).map(function (c) { return c.titre; }).join(' / ') || 'aucune']));
    out.push('');
    out.push(ligne(['Poste'].concat(ms.map(function (m) { return m.id + ' (' + m.periode + ')'; })).concat(['6 mois'])));
    [['boissons', 'CA boissons'], ['patisseries', 'CA pâtisseries'], ['snacking', 'CA snacking'], ['boutique', 'CA sachets et coffrets'], ['web', 'CA e-commerce'], ['b2b', 'CA B2B']]
      .forEach(function (c) { row(c[1], function (m) { return m.ca[c[0]]; }); });
    row('Chiffre d\'affaires HT', function (m) { return m.caTotal; });
    row('Coût matière et emballages', function (m) { return -(m.caTotal - m.margeBrute); });
    row('Marge brute', function (m) { return m.margeBrute; });
    row('Commissions SumUp (abonnement compris)', function (m) { return -m.fraisSumUp; });
    row('Commissions Mollie', function (m) { return -m.fraisMollie; });
    row('Frais de port e-commerce', function (m) { return -m.fraisPort; });
    row('Invendus de vitrine', function (m) { return -m.pertes; });
    r.donnees.chargesFixes.forEach(function (c) { row(c.nom, function (m) { var l = m.fixes.filter(function (x) { return x.id === c.id; })[0]; return -(l ? l.montant : 0); }); });
    row('Dépenses ponctuelles', function (m) { return -m.ponctuelles; });
    row('Résultat d\'exploitation', function (m) { return m.resultat; });
    row('Mensualités et stock de sécurité', function (m) { return -(m.resultat - m.flux); });
    out.push(ligne(['Trésorerie en fin de mois'].concat(ms.map(function (m) { return n(m.tresorerieFin); })).concat([n(r.tresorerieFin)])));
    out.push('');
    out.push(ligne(['Volumes'].concat(ms.map(function (m) { return m.id; })).concat(['6 mois'])));
    [['tasses', 'Tasses'], ['patisseries', 'Pâtisseries'], ['snacking', 'Snacking et formules'], ['sachets', 'Sachets boutique'], ['coffrets', 'Coffrets'], ['colis', 'Colis web'], ['kgB2B', 'Café B2B (kg)']]
      .forEach(function (c) { row(c[1], function (m) { return m.quantites[c[0]]; }); });
    row('Café vert à acheter (kg)', function (m) { return m.cafeVertKg; });
    return '﻿' + out.join('\r\n') + '\r\n';
  }

  var API = {
    MOIS: MOIS, DONNEES_DEFAUT: DONNEES_DEFAUT, SUGGESTIONS: SUGGESTIONS, CORRECTIONS: CORRECTIONS,
    LEVIERS_DEFAUT: LEVIERS_DEFAUT, REPARTITION_COFFRETS: REPARTITION_COFFRETS,
    RENDEMENT: RENDEMENT, TVA_10: TVA_10, TVA_55: TVA_55,
    normaliserDonnees: normaliserDonnees, simuler: simuler, unitaires: unitaires,
    fixesDuMois: fixesDuMois, coutTotal: coutTotal, exportCSV: exportCSV
  };
  if (typeof module !== 'undefined' && module.exports) module.exports = API;
  else root.YemkafModele = API;
})(this);
