# Opt-in / opt-out moderne avec vérification d'email — Design

Date : 2026-08-26
Statut : à valider

## Objectif

Remplacer le double opt-in bricolé (et l'opt-in simple qui l'a suivi) par un vrai
système moderne, **centré sur le compte** : chaque adresse email correspond à un
`User`, non vérifié tant que l'email n'est pas confirmé. Vérifier son email une
fois débloque tout, sans friction ensuite. Opt-out réel (lien + `List-Unsubscribe`).

## Principe : tout crée un compte (en attente jusqu'à vérification)

Il n'existe plus de parcours « anonyme ». Trois portes d'entrée, un seul modèle :

| Porte d'entrée | Crée / réutilise | Mot de passe | Après vérif email |
|---|---|---|---|
| Inscription complète | `User` | fourni | compte actif |
| Newsletter (home) | `User` (lead) | aucun (nullable) | lead marketing actif |
| « Préviens-moi » produit | `User` (lead) + `Subscription` | aucun | abonnement activé + mail « c'est noté » |

Un lead (email sans mot de passe) pourra définir un mot de passe plus tard via le
flux « mot de passe oublié » existant. `User.password` est déjà nullable.

## Données

**User** (ajouts) :
- `emailVerifiedAt: ?DateTimeImmutable` — null tant que non vérifié.
- `emailVerifyToken: ?string` — token du lien de vérification (effacé après usage).
- `unsubscribeToken: string` — stable, pour le lien de désinscription des emails.
- `marketingConsent: bool` (existe déjà) — opt-out global.

**Subscription** (ajusté) :
- Toujours rattaché à un `User` (le lead ou le compte). `targetType` product/box.
- Garde `status` (pending/confirmed/unsubscribed) et `notifiedAt`.
- `confirmToken` devient inutile (la vérification passe par le User) → à retirer.

## Parcours

### 1. Inscription / création de lead
- On crée le `User` (email obligatoire ; password seulement pour l'inscription complète),
  `emailVerifiedAt = null`, `emailVerifyToken` généré, `marketingConsent` selon la case.
- Si l'email existe déjà : on réutilise le `User` (pas de doublon) ; s'il est déjà
  vérifié, voir §5 (chemin sans friction).
- On envoie l'**email de vérification** (transactionnel, sobre, peu d'emojis).

### 2. « Préviens-moi » sur un produit
- Réservé à un email connu (connecté, ou saisie qui crée un lead).
- Crée un `Subscription` product `pending` rattaché au User.
- Si l'email est déjà vérifié → passe direct en `confirmed` + mail « C'est noté pour X ».
- Sinon → reste `pending` ; le mail de vérification est envoyé (une fois).

### 3. Newsletter (home)
- Saisie email → crée/réutilise un `User` lead, `marketingConsent = true`.
- Email de vérification. Une fois vérifié → lead marketing actif.

### 4. Vérification de l'email (le pivot — point 6 d'Olivier)
- `GET /api/account/verify-email/{token}` :
  - `emailVerifiedAt = now`, `emailVerifyToken = null`.
  - **Active tous les `Subscription` `pending` du User** → `confirmed`, et envoie pour
    chacun le mail « C'est noté pour X ».
  - Aucun 2e mail « bienvenue » si rien en attente (automation plus tard).
- Page front `/confirmer-email?token=...` appelle cet endpoint et affiche l'état.

### 5. Chemin sans friction (email déjà vérifié)
- Suivre un nouveau produit → `confirmed` direct + « C'est noté » immédiat, sans re-valider.

### 6. Désinscription (opt-out)
- Chaque email **marketing** contient un lien `/desabonnement?token={unsubscribeToken}`
  + en-tête `List-Unsubscribe` / `List-Unsubscribe-Post` (bouton natif Gmail/Apple).
- Clic sur le lien (ou bouton natif) → **coupe tout le marketing** : `marketingConsent = false`.
  Les emails **transactionnels** (vérification, commande, reset mot de passe) restent envoyés.
- La **page** de désinscription permet en plus de couper « le fait d'être informé » :
  passer ses `Subscription` product/box en `unsubscribed`.

## Emails

**Transactionnels (toujours envoyés, pas de lien d'opt-out marketing)** :
- Vérification d'email (inscription / lead) — message d'inscription Tinned sobre, peu d'emojis.
- (Existant) reset mot de passe, confirmations de commande.

**Marketing (respectent `marketingConsent`, footer avec désinscription + `List-Unsubscribe`)** :
- « C'est noté — on te prévient pour X » (à l'activation d'un abonnement produit).
- Mise en ligne / retour en stock (`LaunchNotifier`).

Tous rendus par `EmailRenderer` (layout brandé déjà en place), tutoiement.

## Composants

**API**
- `User` : nouveaux champs + migration.
- `RegisterUserProcessor` : envoie la vérification (au lieu du welcome direct), ne
  marque pas l'email vérifié.
- `SubscriptionProcessor` : crée/réutilise le lead User, statut selon `emailVerifiedAt`.
- `EmailVerificationController` : `GET /api/account/verify-email/{token}` (§4).
- `UnsubscribeController` : `GET` (one-click du lien) + `POST` (page, choix fin).
- `EmailRenderer` : template vérification + footer désinscription ; `ResendMailer`
  ajoute l'en-tête `List-Unsubscribe`.
- `LaunchNotifier` : **ignore** les Users `marketingConsent = false` et les `Subscription`
  `unsubscribed`.
- Retrait : `Subscription.confirmToken`, ancien `SubscriptionConfirmController` (remplacé).

**Front**
- Page `/confirmer-email` (appelle verify-email, affiche « compte activé »).
- Page `/desabonnement` (one-click + réglages fins).
- Formulaires : newsletter + « préviens-moi » créent un lead ; messages « vérifie ton
  email pour activer » (au lieu de « c'est noté » immédiat), sauf email déjà vérifié.

## Règles transverses

- **Transactionnel ≠ marketing** : un flag par email détermine s'il porte l'opt-out.
- **Idempotence** : re-saisir un email vérifié ne recrée rien ; un email non vérifié
  ré-envoie au plus un mail de vérification (throttle simple sur `emailVerifyToken`/date).
- **Sécurité** : tokens en `random_bytes`, non sérialisés, index sur les colonnes de lookup.

## Hors scope (plus tard)

- Séquences / automations marketing post-lancement (Resend Automations, event déjà émis).
- Magic-link login pour les leads (ils passent par « mot de passe oublié » en attendant).
- Centre de préférences complet (granularité par type de contenu) au-delà du on/off.

## Points ouverts / risques

- **Migration des `Subscription` existants** en prod (lignes `pending`/`confirmed` sans
  User rattaché) : script de rattachement par email, ou purge si négligeable.
- **Leads sans mot de passe** : s'assurer qu'ils ne peuvent pas se « connecter » sans
  passer par la définition d'un mot de passe.
