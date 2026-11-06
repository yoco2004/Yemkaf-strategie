# E-shop Yem'kaf : schémas

Schémas Mermaid de l'architecture (affichés automatiquement sur GitHub). La version illustrée, avec les textes des
e-mails et SMS, est dans [`index.html`](index.html).

## Flux entre les outils

```mermaid
flowchart LR
  subgraph Boutique["Boutique rue du Renard"]
    SU[Caisse SumUp]
    QR[QR codes gobelets et sachets]
  end
  subgraph Site["Site WordPress"]
    WC[WooCommerce<br/>catalogue, stocks, commandes]
    SUB[WooCommerce Subscriptions<br/>abonnement mensuel]
    PT[Pages terroir]
  end
  MO[Mollie<br/>CB, Apple Pay, SEPA]
  BR[Brevo<br/>e-mails et SMS]
  EX[Boxtal, Sendcloud<br/>ou Mondial Relay]
  QO[(Qonto)]
  PL[Pennylane]

  QR --> PT --> WC
  WC -- paiement --> MO
  SUB -- renouvellement --> MO
  MO -- versements --> QO
  SU -- versements --> QO
  WC -- événements panier et commande --> BR
  BR -- relances --> WC
  WC -- étiquettes --> EX
  QO -- relevés --> PL
  WC -- factures --> PL
```

## Parcours client et relances

```mermaid
flowchart TD
  A[Scan du QR code] --> B[Page terroir]
  B --> C{Ajout au panier}
  C -- paiement --> D[Commande payée]
  C -- abandon --> E[E-mail à 45 min<br/>SMS si accord, 8 h-20 h, hors dimanche]
  E --> F[E-mail à 24 h]
  E -- reprise --> D
  F -- reprise --> D
  D --> G[J+3 : bien préparer son café]
  G --> H[J+10 : demande d'avis]
  D -- sachet --> I[J+21 : réassort en un clic]
  I --> J[Deuxième commande]
  J --> K[J+2 : proposition d'abonnement]
  K --> L[Abonné mensuel]
```
