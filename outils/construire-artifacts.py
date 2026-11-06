"""Prépare les fichiers des deux pages publiées sur claude.ai.

Usage : python3 outils/construire-artifacts.py <dossier_sortie> <url_feuille_de_route> <url_simulateur>

- <sortie>/simulateur/ : page du simulateur (modele.js intégré) et ses images ; capacité « db » déclarée à la publication.
- <sortie>/salle/      : feuille de route (page principale) et toutes les pages des modules, avec la charte et les images.
Les pages principales perdent leur squelette HTML (la publication l'ajoute) ; les sous-pages le gardent.
Les liens vers le simulateur et vers la feuille de route deviennent les adresses des pages publiées.
"""
import pathlib
import shutil
import sys

RACINE = pathlib.Path(__file__).resolve().parent.parent
ASSETS = ['assets/yemkaf.css', 'assets/marque/logo-creme.png', 'assets/marque/logo-vert.png',
          'assets/marque/logo-creme-signature.png', 'assets/marque/frise-sable.png', 'assets/marque/frise-verte.png']
ASSETS_SALLE = ASSETS + ['assets/marque/devanture.jpg', 'assets/marque/sachet-vert.jpg', 'assets/marque/sachet-bordeaux.jpg',
                         'assets/marque/cartes-harazi.jpg', 'assets/marque/nuancier.jpg']
SOUS_PAGES = ['00-marque/index.html', '02-onboarding-baristas/index.html', '03-marketing-lancement/index.html',
              '04-ecommerce/index.html', '04-ecommerce/architecture.md', '05-b2b-juridique/index.html',
              '05-b2b-juridique/contrat-cafe-vert-torrefacteurs.md', '05-b2b-juridique/contrat-cafe-torrefie-chr.md']


def corps(html):
    """Garde le contenu entre les marqueurs et retire head/body (la publication fournit le squelette)."""
    b = html.split('<!-- @artifact-start -->', 1)[1].split('<!-- @artifact-end -->', 1)[0]
    b = b.replace('</head>\n<body>\n', '\n').replace('</head>', '').replace('<body>', '')
    assert '<!doctype' not in b.lower() and '<html' not in b and '<body' not in b
    return b.strip() + '\n'


def copier(rel, dest):
    cible = dest / rel
    cible.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(RACINE / rel, cible)


def main(sortie, url_salle, url_simu):
    sortie = pathlib.Path(sortie)
    shutil.rmtree(sortie, ignore_errors=True)

    # Simulateur
    simu = sortie / 'simulateur'
    html = corps((RACINE / '01-dashboard-financier/index.html').read_text(encoding='utf-8'))
    js = (RACINE / '01-dashboard-financier/modele.js').read_text(encoding='utf-8')
    html = html.replace('<script src="modele.js"></script>', '<script>\n' + js + '\n</script>')
    html = html.replace('../assets/', 'assets/')
    html = html.replace('href="../index.html"', 'href="' + url_salle + '" target="_blank" rel="noopener"')
    simu.mkdir(parents=True)
    (simu / 'index.html').write_text(html, encoding='utf-8')
    for a in ASSETS:
        copier(a, simu)

    # Salle de pilotage : feuille de route + modules
    salle = sortie / 'salle'
    html = corps((RACINE / 'index.html').read_text(encoding='utf-8'))
    html = html.replace("simulateur: '01-dashboard-financier/index.html'", "simulateur: '" + url_simu + "'")
    salle.mkdir(parents=True)
    (salle / 'index.html').write_text(html, encoding='utf-8')
    for rel in SOUS_PAGES:
        copier(rel, salle)
        if rel.endswith('.html'):
            p = salle / rel
            t = p.read_text(encoding='utf-8')
            t = t.replace('href="../01-dashboard-financier/index.html"', 'href="' + url_simu + '" target="_blank" rel="noopener"')
            t = t.replace('href="../index.html"', 'href="' + url_salle + '" target="_top"')
            p.write_text(t, encoding='utf-8')
    for a in ASSETS_SALLE:
        copier(a, salle)
    print('ok :', simu, salle)


if __name__ == '__main__':
    main(*sys.argv[1:4])
