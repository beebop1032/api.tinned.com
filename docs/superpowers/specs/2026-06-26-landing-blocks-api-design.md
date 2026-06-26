# Design — Landing pages de box : schéma de blocs + API (Sous-projet A)

Date : 2026-06-26
Statut : validé (en attente de revue spec)
Périmètre : `api/` uniquement. Sous-projet **A** d'un découpage en 3.

## Contexte

Les pages de box du front (`store-box`, `business-box`, `blog-box`, `travel-box`) n'affichent
aujourd'hui que `name`, `description/tagline` et un logo/cover — pas de quoi faire une « vraie
landing page ». Il faut un modèle de contenu **souple** (blocs) et **compréhensible** (catalogue
typé), éditable côté backoffice, rendu côté front, dans les **6 langues**.

L'entité `LandingPage` existe déjà (`box` + `locale` + `title` + `metaDescription` + `blocks` JSON)
mais : son champ `blocks` n'a aucun schéma, rien ne la lit côté front, et elle n'a aucune sécurité
en écriture. Ce sous-projet la transforme en **page canonique d'une box** et définit le **contrat de
blocs** dont dépendent les sous-projets suivants.

Découpage global (rappel) :
- **A (ce doc)** — schéma de blocs + API + sécurité + validation + endpoint de lecture.
- **B** — rendu front (renderer + 11 composants + fetch par locale).
- **C** — éditeur visuel backoffice (`/dashboard` vendeur + `/admin`).

## Décisions validées

- Contenu **par locale** dès maintenant → on s'appuie sur `LandingPage` (box + locale).
- Catalogue **CMS riche** (11 types de blocs, ci-dessous).
- Édition **vendeur (sa box) + superadmin (toutes)** → sécurité `ROLE_ADMIN or BOX_EDIT(box)`.
- **Une page canonique par (box, locale)**.

## 1. `LandingPage` — page canonique d'une box

Modifications de l'entité `src/Entity/Content/LandingPage.php` :

| Champ | Avant | Après |
|---|---|---|
| `box` (ManyToOne → Box) | nullable | **NOT NULL** (`JoinColumn(nullable:false)` + `Assert\NotNull`) |
| `slug` | `string(120)` | **supprimé** (champ + getter/setter) |
| unicité | `(box_id, slug, locale)` | **`(box_id, locale)`** (`UniqueConstraint` + `#[UniqueEntity(fields:['box','locale'])]`) |
| `locale` | conservé (défaut `fr`) | conservé |
| `title`, `metaDescription` | conservés | conservés |
| `blocks` (json) | conservé | conservé + validation (§3) |

Le filtre `ApiFilter(SearchFilter)` passe de `['slug','box.slug','locale']` à **`['box.slug' => 'exact', 'locale' => 'exact']`**.

Lecture front (sous-projet B) : `GET /api/landing_pages?box.slug=X&locale=Y` → prendre le 1er
élément ; si vide et `locale != fr`, refetch en `fr` (fallback).

## 2. Catalogue de blocs — le contrat partagé

`blocks` est un **tableau ordonné** d'objets `{ "id": string, "type": string, ...props }`.
`id` = identifiant stable (string, ex. `"b_" + random`) pour le réordonnancement et les clés React.

Les 11 types et leurs champs (les champs sans `?` sont requis) :

```
hero        { title: string, subtitle?: string, imagePath?: string,
              cta?: { label: string, href: string } }
richText    { markdown: string }
gallery     { images: Array<{ path: string, caption?: string }> }
cta         { heading: string, text?: string, button: { label: string, href: string } }
collection  { source: 'products'|'articles'|'trips'|'childBoxes', title?: string, limit?: int }
features    { title?: string, items: Array<{ icon?: string, title: string, text: string }> }
stats       { items: Array<{ value: string, label: string }> }
testimonial { quote: string, author?: string, role?: string }
faq         { title?: string, items: Array<{ question: string, answer: string }> }
video       { url: string, title?: string }
newsletter  { eyebrow?: string, title: string, body?: string,
              cta?: { label: string, href: string } }
```

`collection` est **spécial** : il n'embarque pas de données, il référence le contenu propre de la
box. La résolution (charger les produits/articles/trips/sous-box réels) se fait **au rendu côté
front** (sous-projet B), pas en base.

**Source d'autorité du catalogue** : une classe PHP `src/Service/Content/BlockCatalog.php`
exposant, par type, la liste des champs **requis** (champs scalaires/structure de 1er niveau).
Elle sert au validateur (§3). B (TypeScript) et C (formulaires) **reflètent** ce catalogue ;
le présent document en est la spécification de référence.

Forme de `BlockCatalog` (esquisse, détaillée dans le plan) :
```php
public const TYPES = [
    'hero'        => ['required' => ['title']],
    'richText'    => ['required' => ['markdown']],
    'gallery'     => ['required' => ['images']],
    'cta'         => ['required' => ['heading', 'button']],
    'collection'  => ['required' => ['source']],
    'features'    => ['required' => ['items']],
    'stats'       => ['required' => ['items']],
    'testimonial' => ['required' => ['quote']],
    'faq'         => ['required' => ['items']],
    'video'       => ['required' => ['url']],
    'newsletter'  => ['required' => ['title']],
];
public const COLLECTION_SOURCES = ['products', 'articles', 'trips', 'childBoxes'];
```

## 3. Validation des `blocks`

Contrainte Symfony custom `#[ValidBlocks]` sur le champ `blocks` (l'API protège le contrat → 422
propre, dans l'esprit de la leçon « unicité des slugs »). Règles (pragmatiques, 1er niveau) :

1. `blocks` est une **liste** (tableau séquentiel) d'**objets**.
2. Chaque bloc a un `id` (string non vide) et un `type` ∈ `BlockCatalog::TYPES`.
3. Pour chaque bloc, les **champs requis** du type (`BlockCatalog`) sont présents et non vides.
4. Pour `collection`, `source` ∈ `BlockCatalog::COLLECTION_SOURCES`.

On ne valide PAS récursivement chaque sous-champ profond (ça reste « souple ») ; la validation fine
de forme vit dans l'éditeur (C) + les types TS (B). Fichiers : `src/Validator/ValidBlocks.php`
(contrainte) + `src/Validator/ValidBlocksValidator.php`.

## 4. Sécurité

Opérations API Platform sur `LandingPage` :

- `GetCollection`, `Get` : **publiques** (aucune auth).
- `Post` : `securityPostDenormalize: "is_granted('ROLE_ADMIN') or is_granted('BOX_EDIT', object.box)"`
  (postDenormalize pour que `object.box` soit résolu depuis le payload).
- `Patch`, `Delete` : `security: "is_granted('ROLE_ADMIN') or is_granted('BOX_EDIT', object.box)"`.

Réutilise le voter `BOX_EDIT` existant (`src/Security/Voter/BoxOwnerVoter.php`), appliqué à
`object.box`. Aucun nouveau voter nécessaire.

## 5. Migration & données

- Migration : `DROP` colonne `slug` (+ son éventuel index), `box_id` → **NOT NULL**, remplacer
  l'unique `(box_id, slug, locale)` par `(box_id, locale)`.
- Pré-vérif data : `LandingPage` est a priori vide (aucun usage front). Si des lignes existent :
  s'assurer que chaque `box_id` est non nul et qu'il n'y a pas deux lignes même `(box, locale)`
  avant la migration.
- **Seed** : insérer **une** landing d'exemple en `fr` pour une box existante (ex. `casa-do-sul`),
  avec 3-4 blocs représentatifs (hero, richText, collection(products), cta), pour prouver le
  pipeline et alimenter le sous-projet B.

## 6. Déploiement (rappel)

`deploy.sh` prod utilise `d:s:u --force` (pas les migrations). Le `DROP slug` et le `NOT NULL box_id`
seront appliqués par `d:s:u` — vérifier d'abord qu'aucune `landing_page` prod n'a `box_id` NULL.
Le seed (DML) ne sera pas joué par `d:s:u` ; le faire à la main si besoin en prod.

## Vérification (end-to-end de A)

1. `doctrine:schema:validate` → mapping OK.
2. `make:migration` ne produit que les ALTER attendus ; `migrate` OK après pré-vérif data.
3. Idempotence : 2e `make:migration` → « No changes detected ».
4. `GET /api/landing_pages?box.slug=casa-do-sul&locale=fr` → 200, renvoie la landing seedée avec
   ses `blocks`.
5. Validation (`ValidBlocks`) via Swagger authentifié :
   - POST avec un bloc de `type` inconnu → 422.
   - POST avec un `hero` sans `title` → 422.
   - POST avec un `collection.source` invalide → 422.
   - POST avec des blocs valides → 201.
6. Sécurité :
   - POST/PATCH par un non-owner non-admin → 403.
   - POST/PATCH par l'owner de la box ou un admin → 201/200.
7. Une 2e landing même `(box, locale)` → 422 (unicité).

## Hors périmètre (A)

- Rendu des blocs et composants front → B.
- Éditeur visuel, formulaires par bloc, LocaleTabs → C.
- Traduction réelle des 6 langues (on seed `fr`, le reste se remplit via C).
- Versioning/brouillon/preview des landings (non demandé).
