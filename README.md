# api.tinned.com

Backend Symfony pour la plateforme Tinned v2.

## Stack

- Symfony 7.3
- API Platform 4.1
- Doctrine ORM 3.x
- JWT (lexik/jwt-authentication-bundle)
- Mollie (paiement)
- BPost, DPD, Mondial Relay (livraison)
- DomPDF (factures PDF)

## Responsabilités

- Authentification JWT
- Ressources API Platform (Box, Produit, Commande, Livraison, Utilisateur)
- Webhooks Mollie
- Génération d'étiquettes de livraison
- Génération de factures PDF
