# Design — Hiérarchie de Box (relations typées, parent unique)

Date : 2026-06-26
Statut : validé (en attente de revue spec)
Périmètre : `api/` (Symfony 7.3 + API Platform 4.1)

## Contexte

Les 4 types de box (`StoreBox`, `BusinessBox`, `BlogBox`, `TravelBox`) héritent de
`Box` (héritage JOINED, discriminant `box_type`). Jusqu'ici les liens box↔box étaient
partiels et incohérents :

- `BusinessBox` = hub historique (`OneToMany` storeBoxes/blogBoxes).
- `TravelBox` = hub ajouté récemment (PR #3) avec collections typées storeBoxes/businessBoxes/blogBoxes.
- `BlogBox` portait déjà 3 FK parent (business/store/travel) sans contrainte d'unicité.

Le besoin métier exprimé : structurer les box en **arbre**, chaque box ayant **au plus un
parent**, avec des règles de type précises sur qui peut contenir qui. Ce design
**remplace le hub TravelBox typé de la PR #3** et formalise les liens parent/enfant.

Décision de modélisation (validée) : **relations typées explicites** (une FK par couple de
types), et non un arbre générique auto-référencé sur `Box`. Cardinalité : **parent unique**
(arbre, pas de graphe).

## Règles métier

Arêtes parent → enfant autorisées :

```
Travel   → Travel, Business, Blog
Business → Travel, Business, Blog, Store
Store    → Blog
Blog     → (feuille, aucun enfant)
```

Cardinalité et obligation du parent, par type d'enfant :

| Type | Parent autorisé | FK parent | Obligation |
|---|---|---|---|
| **StoreBox** | Business | `businessBox` | **requis** (NOT NULL) |
| **BlogBox** | Travel \| Business \| Store | `travelBox?` / `businessBox?` / `storeBox?` | **exactement un** non-null |
| **TravelBox** | Travel \| Business | `parentTravelBox?` / `businessBox?` | **au plus un** non-null (peut être racine) |
| **BusinessBox** | Travel \| Business | `travelBox?` / `parentBusinessBox?` | **au plus un** non-null (peut être racine) |

## Modèle Doctrine

### FK parent (côté enfant) — sérialisées `box:read` / `box:write`

Convention de sérialisation existante : on expose le `ManyToOne` enfant→parent (réglable
via l'API), on ne sérialise PAS les collections inverses (anti-récursion).

- **StoreBox**
  - `businessBox` : `ManyToOne(BusinessBox)`, `JoinColumn(nullable: false)`, `inversedBy: 'storeBoxes'`
  - *(suppression de `travelBox` ajouté en PR #3)*
- **BlogBox** (les 3 FK existent déjà, toutes nullable)
  - `travelBox?` `inversedBy: 'blogBoxes'`
  - `businessBox?` `inversedBy: 'blogBoxes'`
  - `storeBox?` `inversedBy: 'blogBoxes'`
- **TravelBox**
  - `parentTravelBox?` : `ManyToOne(TravelBox)`, `inversedBy: 'childTravelBoxes'`
  - `businessBox?` : `ManyToOne(BusinessBox)`, `inversedBy: 'travelBoxes'`
- **BusinessBox**
  - `travelBox?` : `ManyToOne(TravelBox)`, `inversedBy: 'businessBoxes'` *(existe déjà)*
  - `parentBusinessBox?` : `ManyToOne(BusinessBox)`, `inversedBy: 'childBusinessBoxes'`

### Collections enfants (inverse `OneToMany`) — NON sérialisées

- **TravelBox** : `childTravelBoxes`, `businessBoxes`, `blogBoxes` (+ `trips` existant)
  — *suppression de `storeBoxes` (PR #3) : Travel ne parente plus de Store*
- **BusinessBox** : `travelBoxes`, `childBusinessBoxes`, `storeBoxes`, `blogBoxes`
- **StoreBox** : `blogBoxes` (+ `products` existant)
- **BlogBox** : aucune (feuille) (+ `articles` existant)

## Validateurs (Symfony → réponse 422)

1. **BlogBox** — exactement une des 3 FK `{travelBox, businessBox, storeBox}` non-null.
2. **TravelBox** — au plus une des 2 FK `{parentTravelBox, businessBox}` non-null.
3. **BusinessBox** — au plus une des 2 FK `{travelBox, parentBusinessBox}` non-null.
4. **StoreBox** — `businessBox` `NotNull` (doublé par la contrainte DB NOT NULL).
5. **Anti-cycle** — un box ne peut pas être son propre ancêtre. Validateur qui remonte la
   chaîne de parents (`parentTravelBox` / `parentBusinessBox` selon le type) et refuse si la
   cible crée une boucle. S'applique à l'auto-imbrication Travel→Travel et Business→Business.

Implémentation : contraintes 1–4 via un validateur de classe custom (callback ou contrainte
dédiée) car « exactement/au plus un parmi N » n'est pas couvert par les asserts standard.
Le validateur anti-cycle (5) est une contrainte de classe séparée et réutilisable.

## Migration (schéma)

- `store_box` : `business_box_id` → **NOT NULL** ; **DROP** colonne `travel_box_id` (+ FK + index).
- `travel_box` : **ADD** `parent_travel_box_id` (nullable, FK→`travel_box`) ; **ADD** `business_box_id` (nullable, FK→`business_box`).
- `business_box` : **ADD** `parent_business_box_id` (nullable, FK→`business_box`). Conserve `travel_box_id`.
- `blog_box` : colonnes inchangées (les 3 FK existent). La règle « exactement un » est portée par le validateur, pas par le schéma.

## Réconciliation des données (avant `migrate`)

- **BlogBox(3)** a actuellement 2 parents (`business_box_id=1` ET `store_box_id=2`) →
  garder **Business** : `UPDATE blog_box SET store_box_id = NULL WHERE id = 3`.
- **StoreBox(2)** a déjà `business_box_id=1` → la contrainte NOT NULL passe ; `travel_box_id`
  est NULL → DROP sans perte.

Ces correctifs data doivent être joués **avant** le `doctrine:migrations:migrate` (sinon
le NOT NULL / la cohérence échouent). Ils peuvent être intégrés en tête du `up()` de la
migration ou exécutés manuellement.

## Sérialisation & API

- FK parent exposées en `box:read` / `box:write` (set via l'API).
- Collections enfants sans groupe (comme `BusinessBox.storeBoxes` aujourd'hui) → pas de
  récursion de normalisation.
- Les voters/securité existants (`BOX_EDIT`, `BoxPostProcessor`) restent inchangés.

## Hors périmètre

- Pas de profondeur d'imbrication limitée explicitement (seul l'anti-cycle borne l'arbre).
- Pas de helpers typés type « hybride » (getBlogChildren()…) : on s'en tient aux accesseurs
  des relations déclarées.
- Pas de changement sur les contenus (Product/Article/Trip/LandingPage/StaticPage).

## Vérification

1. `doctrine:schema:validate` → mapping OK.
2. `make:migration` ne produit que les ALTER attendus ; `migrate` passe après réconciliation data.
3. Idempotence : 2e `make:migration` → « No changes detected ».
4. Validateurs (via Swagger authentifié) :
   - StoreBox sans `businessBox` → 422.
   - BlogBox avec 0 ou ≥2 parents → 422 ; avec exactement 1 → 201.
   - TravelBox/BusinessBox avec 2 FK parent → 422.
   - Rattacher un box à un de ses descendants → 422 (anti-cycle).
5. Navigation inverse : `BusinessBox.getStoreBoxes()`, `TravelBox.getBlogBoxes()`, etc.
   renvoient les enfants attendus.
