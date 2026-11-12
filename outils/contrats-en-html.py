"""Transforme les deux modèles de contrat (Markdown) en pages lisibles et imprimables.

Usage : python3 outils/contrats-en-html.py

Les fichiers .md restent la source : on modifie le texte du contrat dans le .md, puis on relance ce script.
Les zones entre crochets [ … ] sont surlignées dans la page pour repérer ce qui reste à compléter.
"""
import html
import pathlib
import re

RACINE = pathlib.Path(__file__).resolve().parent.parent
DOSSIER = RACINE / '05-b2b-juridique'

CONTRATS = [
    {
        'source': 'contrat-cafe-vert-torrefacteurs.md',
        'sortie': 'contrat-cafe-vert.html',
        'titre': 'Contrat café vert · torréfacteurs',
        'sert': 'Modèle de contrat-cadre pour vendre du café vert du Yémen à un torréfacteur : traçabilité et règlement '
                'anti-déforestation, qualité, échantillons, réservation des récoltes, prix par palier, force majeure '
                '(Yémen, mer Rouge), paiement.',
        'qui': 'Khaled prépare et négocie le contrat ; Khaled Al Maweri le signe en tant que président.',
    },
    {
        'source': 'contrat-cafe-torrefie-chr.md',
        'sortie': 'contrat-cafe-torrefie.html',
        'titre': 'Contrat café torréfié · cafés, hôtels, restaurants',
        'sert': 'Modèle de contrat de partenariat pour livrer du café torréfié à un coffee shop, un hôtel ou un restaurant : '
                'fraîcheur, paliers de volume, formation des équipes, usage de la marque, exclusivité de quartier, prix, '
                'paiement.',
        'qui': 'Khaled prépare et négocie le contrat ; Khaled Al Maweri le signe en tant que président.',
    },
]


def en_ligne(t):
    """Mise en forme dans une ligne : code, gras, italique, zones à compléter."""
    t = html.escape(t, quote=False)
    t = re.sub(r'`([^`]+)`', r'<code>\1</code>', t)
    t = re.sub(r'\*\*([^*]+)\*\*', r'<b>\1</b>', t)
    t = re.sub(r'(?<![*\w])\*([^*\n]+)\*(?![*\w])', r'<i>\1</i>', t)
    t = re.sub(r'\[([^\]]*)\]', r'<mark class="blank">[\1]</mark>', t)
    return t


def convertir(md):
    lignes = md.split('\n')
    out, i = [], 0
    while i < len(lignes):
        l = lignes[i]
        if not l.strip():
            i += 1
            continue
        if l.startswith('#'):
            niveau = len(l) - len(l.lstrip('#'))
            balise = {1: 'h2', 2: 'h3', 3: 'h4'}.get(niveau, 'h4')
            out.append('<%s>%s</%s>' % (balise, en_ligne(l[niveau:].strip()), balise))
            i += 1
        elif l.strip() == '---':
            out.append('<hr>')
            i += 1
        elif l.startswith('>'):
            bloc = []
            while i < len(lignes) and lignes[i].startswith('>'):
                bloc.append(lignes[i][1:].strip())
                i += 1
            out.append('<div class="callout warn note"><p>%s</p></div>' % en_ligne(' '.join(bloc)))
        elif l.startswith('|'):
            rangs = []
            while i < len(lignes) and lignes[i].startswith('|'):
                cells = [c.strip() for c in lignes[i].strip().strip('|').split('|')]
                if not all(re.fullmatch(r':?-{3,}:?', c) for c in cells):
                    rangs.append(cells)
                i += 1
            tete, corps = rangs[0], rangs[1:]
            t = '<div class="table-scroll"><table class="text"><thead><tr>' + ''.join('<th>%s</th>' % en_ligne(c) for c in tete) + '</tr></thead><tbody>'
            t += ''.join('<tr>' + ''.join('<td>%s</td>' % en_ligne(c) for c in r) + '</tr>' for r in corps)
            out.append(t + '</tbody></table></div>')
        elif l.startswith('- '):
            items = []
            while i < len(lignes) and (lignes[i].startswith('- ') or (lignes[i].startswith('  ') and items)):
                if lignes[i].startswith('- '):
                    items.append(lignes[i][2:].strip())
                else:
                    items[-1] += ' ' + lignes[i].strip()
                i += 1
            out.append('<ul>' + ''.join('<li>%s</li>' % en_ligne(x) for x in items) + '</ul>')
        else:
            bloc = []
            while i < len(lignes) and lignes[i].strip() and not re.match(r'(#|>|\||- |---\s*$)', lignes[i]):
                bloc.append(lignes[i].strip())
                i += 1
            out.append('<p>%s</p>' % en_ligne(' '.join(bloc)))
    return '\n'.join(out)


PAGE = """<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{titre_court}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Open+Sans:wght@400;600;700&display=swap">
<link rel="stylesheet" href="../assets/yemkaf.css">
<style>
/* Page générée par outils/contrats-en-html.py à partir de {source} : modifier le .md, pas cette page. */
.contrat {{ padding: 22px clamp(16px, 4vw, 44px) 30px; display: grid; gap: 12px; max-width: 900px; width: 100%; margin: 0 auto; }}
.contrat h2 {{ font-size: clamp(22px, 3vw, 28px); font-weight: 800; color: var(--brand-ink); }}
.contrat h3 {{ font-size: 17px; font-weight: 700; color: var(--brand-ink); margin-top: 12px; }}
.contrat h4 {{ font-size: 16px; font-weight: 700; margin-top: 14px; }}
.contrat p, .contrat li {{ line-height: 1.65; }}
.contrat ul {{ margin: 0; padding-left: 22px; display: grid; gap: 4px; }}
.contrat hr {{ border: 0; border-top: 1px solid var(--line); margin: 8px 0; width: 100%; }}
.contrat .table-scroll {{ margin: 4px 0; }}
.contrat code {{ font-size: 13px; }}
mark.blank {{ background: var(--warn-wash); color: var(--warn); border-radius: 4px; padding: 0 3px; font-weight: 600; }}
.actions {{ display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }}
@media print {{
  .contrat {{ border: 0; padding: 0; max-width: none; }}
  .contrat .note, .guide, .actions {{ display: none !important; }}
  mark.blank {{ background: none; color: #000; border-bottom: 1px dotted #000; border-radius: 0; font-weight: 400; }}
  .contrat h3 {{ break-after: avoid; }}
  .table-scroll {{ overflow: visible; border-color: #999; }}
}}
</style>
</head>
<body>
<header class="band">
  <div class="wrap">
    <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;min-width:0">
      <img class="logo logo-screen" src="../assets/marque/logo-creme.png" alt="Yem'kaf" width="139" height="58">
      <img class="logo-print" src="../assets/marque/logo-vert.png" alt="Yem'kaf">
      <div class="band-title"><h1>{titre}</h1><div class="lede">Modèle de contrat · module Professionnels et contrats</div></div>
    </div>
    <nav class="band-nav no-print" aria-label="Pages"><a href="../index.html">Feuille de route</a><a href="index.html">Professionnels</a></nav>
  </div>
  <img class="frise" src="../assets/marque/frise-sable.png" alt="">
</header>

<main class="wrap">
  <section class="panel guide" aria-labelledby="h-guide">
    <div class="guide-head"><h2 id="h-guide">Mode d’emploi</h2><span class="muted small">Modèle de contrat</span></div>
    <div class="guide-cols">
      <div class="guide-col"><h3>À quoi il sert</h3><p>{sert}</p></div>
      <div class="guide-col"><h3>Pour qui</h3><p>{qui}</p></div>
      <div class="guide-col"><h3>Comment l’utiliser</h3><ol>
        <li>Les zones <mark class="blank">[surlignées]</mark> sont à compléter pour chaque client (raison sociale, adresse, volumes, dates).</li>
        <li>Cliquez sur « Copier le texte » et collez-le dans Word ou Google Docs pour préparer la version du client.</li>
        <li>Ou cliquez sur « Imprimer ou enregistrer en PDF » : la page s’imprime sans ce mode d’emploi.</li>
        <li>Faites relire le modèle par un avocat avant la première signature, puis signez en deux exemplaires et classez le scan dans le dossier du client.</li>
      </ol></div>
    </div>
  </section>

  <div class="actions">
    <button type="button" class="btn primary" id="imprimer">Imprimer ou enregistrer en PDF</button>
    <button type="button" class="btn" id="copier">Copier le texte</button>
    <span class="small muted" id="etat" role="status"></span>
  </div>

  <article class="panel contrat" id="contrat">
{contenu}
  </article>
</main>

<script>
(function () {{
  'use strict';
  var etat = document.getElementById('etat');
  document.getElementById('imprimer').addEventListener('click', function () {{ window.print(); }});
  document.getElementById('copier').addEventListener('click', function () {{
    var art = document.getElementById('contrat');
    var texte = art.innerText;
    var ok = function () {{ etat.textContent = 'Texte copié : collez-le dans votre traitement de texte.'; }};
    var repli = function () {{
      var r = document.createRange(); r.selectNodeContents(art);
      var s = window.getSelection(); s.removeAllRanges(); s.addRange(r);
      etat.textContent = 'Texte sélectionné : faites Ctrl+C (ou Cmd+C).';
    }};
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(texte).then(ok, repli); else repli();
  }});
}})();
</script>
</body>
</html>
"""


def main():
    for c in CONTRATS:
        md = (DOSSIER / c['source']).read_text(encoding='utf-8')
        page = PAGE.format(titre=html.escape(c['titre']), titre_court=html.escape(c['titre'].replace(' · ', ' : ')),
                           source=c['source'], sert=html.escape(c['sert']), qui=html.escape(c['qui']),
                           contenu=convertir(md))
        (DOSSIER / c['sortie']).write_text(page, encoding='utf-8')
        print('ok :', c['sortie'])


if __name__ == '__main__':
    main()
