# Landing Blocks API (Sous-projet A) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Faire de `LandingPage` la page de contenu canonique d'une box (par locale), avec un catalogue de blocs typé, validé et sécurisé côté API.

**Architecture:** Une classe `BlockCatalog` est l'unique source d'autorité du contrat (types + champs requis). Un validateur `ValidBlocks` l'utilise pour rejeter tout `blocks` mal formé avec des chemins d'erreur précis (422). `LandingPage` est repurposée (drop `slug`, box requise, unique `(box, locale)`) avec sécurité `ROLE_ADMIN or BOX_EDIT(box)`.

**Tech Stack:** Symfony 7.3, API Platform 4.1, Doctrine ORM, MySQL/MariaDB. Pas de phpunit dans ce repo → validation testée par un harnais headless (Symfony Validator standalone) ; entité/migration via `bin/console` ; endpoints via curl.

**Spec de référence:** `docs/superpowers/specs/2026-06-26-landing-blocks-api-design.md`

---

## Structure des fichiers

| Fichier | Responsabilité | Action |
|---|---|---|
| `src/Service/Content/BlockCatalog.php` | source d'autorité : types + champs requis + sources de `collection` | Créer |
| `docs/landing-blocks-example.json` | exemple de référence (contrat lisible pour B et C) | Créer |
| `src/Validator/ValidBlocks.php` | contrainte Symfony (attribut sur `blocks`) | Créer |
| `src/Validator/ValidBlocksValidator.php` | logique de validation (utilise `BlockCatalog`) | Créer |
| `src/Entity/Content/LandingPage.php` | page canonique : box requise, unique `(box,locale)`, drop `slug`, `#[ValidBlocks]`, sécurité, filtres | Modifier |
| `migrations/VersionYYYYMMDDHHMMSS.php` | drop `slug`, `box_id` NOT NULL, unique `(box_id, locale)` | Créer (généré) |

État de départ de `src/Entity/Content/LandingPage.php` : `id, slug(120), locale(5), box(ManyToOne Box, nullable), title(180), metaDescription(280?), blocks(json)`. Unique `(box_id, slug, locale)`, `UniqueEntity(['box','slug','locale'])`, filtre `['slug','box.slug','locale']`, `ApiResource` sans liste d'`operations` (donc CRUD complet, public).

---

## Task 1 : `BlockCatalog` (source d'autorité du contrat) + exemple

**Files:**
- Create: `src/Service/Content/BlockCatalog.php`
- Create: `docs/landing-blocks-example.json`

- [ ] **Step 1 : Créer `BlockCatalog`**

```php
<?php

namespace App\Service\Content;

/**
 * Source d'autorité du contrat de blocs des landing pages.
 * B (TypeScript) et C (formulaires) DOIVENT refléter ce catalogue.
 */
final class BlockCatalog
{
    /** type de bloc => liste des champs de 1er niveau requis */
    public const TYPES = [
        'hero'        => ['title'],
        'richText'    => ['markdown'],
        'gallery'     => ['images'],
        'cta'         => ['heading', 'button'],
        'collection'  => ['source'],
        'features'    => ['items'],
        'stats'       => ['items'],
        'testimonial' => ['quote'],
        'faq'         => ['items'],
        'video'       => ['url'],
        'newsletter'  => ['title'],
    ];

    /** sources autorisées pour le bloc 'collection' */
    public const COLLECTION_SOURCES = ['products', 'articles', 'trips', 'childBoxes'];

    public static function isType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    /** @return list<string> champs requis du type, [] si type inconnu */
    public static function requiredFields(string $type): array
    {
        return self::TYPES[$type] ?? [];
    }
}
```

- [ ] **Step 2 : Créer l'exemple de référence**

Fichier `docs/landing-blocks-example.json` (sert de doc lisible ET de seed en Task 5) :
```json
[
  {
    "id": "b_hero",
    "type": "hero",
    "title": "Casa do Sul",
    "subtitle": "Céramiques artisanales du sud du Portugal",
    "imagePath": "https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=1600&q=80",
    "cta": { "label": "Voir la boutique", "href": "#products" }
  },
  {
    "id": "b_intro",
    "type": "richText",
    "markdown": "## Un artisanat vivant\n\nChaque assiette, chaque bol est façonné à la main et émaillé selon des recettes transmises de génération en génération."
  },
  {
    "id": "b_products",
    "type": "collection",
    "source": "products",
    "title": "Notre sélection",
    "limit": 6
  },
  {
    "id": "b_cta",
    "type": "cta",
    "heading": "Restez informé",
    "button": { "label": "S'inscrire à la newsletter", "href": "#newsletter" }
  }
]
```

- [ ] **Step 3 : Vérifier que la classe se charge**

Run :
```bash
cd api
php -r "require 'vendor/autoload.php'; var_dump(\App\Service\Content\BlockCatalog::isType('hero'), \App\Service\Content\BlockCatalog::isType('nope'), \App\Service\Content\BlockCatalog::requiredFields('cta'));"
```
Expected : `bool(true)`, `bool(false)`, `array(2) { [0]=> "heading" [1]=> "button" }`.

- [ ] **Step 4 : Commit**

```bash
git add src/Service/Content/BlockCatalog.php docs/landing-blocks-example.json
git commit -m "feat(content): BlockCatalog — contrat d'autorité des blocs de landing"
```

---

## Task 2 : Contrainte `ValidBlocks` + validateur

**Files:**
- Create: `src/Validator/ValidBlocks.php`
- Create: `src/Validator/ValidBlocksValidator.php`

- [ ] **Step 1 : Créer la contrainte**

```php
<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ValidBlocks extends Constraint
{
    public string $message = 'Le contenu (blocks) est invalide.';
}
```

- [ ] **Step 2 : Créer le validateur**

Chemins d'erreur précis par bloc (ex. `[2].type`) — c'est la « clarté côté API » voulue.
```php
<?php

namespace App\Validator;

use App\Service\Content\BlockCatalog;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ValidBlocksValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidBlocks) {
            throw new UnexpectedValueException($constraint, ValidBlocks::class);
        }

        // null / [] : une landing sans blocs est permise.
        if ($value === null || $value === []) {
            return;
        }

        if (!is_array($value) || !array_is_list($value)) {
            $this->context->buildViolation('Le champ blocks doit être une liste ordonnée de blocs.')->addViolation();
            return;
        }

        foreach ($value as $i => $block) {
            if (!is_array($block)) {
                $this->violation("[$i] : chaque bloc doit être un objet.");
                continue;
            }

            $id = $block['id'] ?? null;
            if (!is_string($id) || $id === '') {
                $this->violation("[$i].id : identifiant de bloc requis (string non vide).");
            }

            $type = $block['type'] ?? null;
            if (!is_string($type) || !BlockCatalog::isType($type)) {
                $this->violation("[$i].type : type inconnu (attendu : " . implode(', ', BlockCatalog::types()) . ').');
                continue;
            }

            foreach (BlockCatalog::requiredFields($type) as $field) {
                if (!isset($block[$field]) || $block[$field] === '' || $block[$field] === []) {
                    $this->violation("[$i].$field : champ requis pour le bloc « $type ».");
                }
            }

            if ($type === 'collection') {
                $source = $block['source'] ?? null;
                if (!in_array($source, BlockCatalog::COLLECTION_SOURCES, true)) {
                    $this->violation("[$i].source : source invalide (attendu : " . implode(', ', BlockCatalog::COLLECTION_SOURCES) . ').');
                }
            }
        }
    }

    private function violation(string $message): void
    {
        $this->context->buildViolation($message)->addViolation();
    }
}
```

- [ ] **Step 3 : Écrire un harnais de validation headless (le « test »)**

Pas de phpunit → harnais autonome. Fichier `/private/tmp/claude-501/-Users-oliviervangest-Desktop-app-tinned/95609edb-2e55-44af-916f-be11190ca30d/scratchpad/verify_blocks.php` :
```php
<?php
require '/Users/oliviervangest/Desktop/app/tinned/api/vendor/autoload.php';

use App\Entity\Content\LandingPage;
use Symfony\Component\Validator\Validation;

$v = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
$pass = 0; $fail = 0;
function check(string $l, bool $ok) { global $pass,$fail; echo ($ok?'  OK   ':'  FAIL ').$l."\n"; $ok?$pass++:$fail++; }
function n($v, LandingPage $lp): int { return $v->validate($lp)->count(); }

$ok = (new LandingPage())->setBlocks([
    ['id'=>'b1','type'=>'hero','title'=>'T'],
    ['id'=>'b2','type'=>'collection','source'=>'products'],
]);
check('blocs valides -> 0 violation', n($v,$ok) === 0);

check('blocs vides -> 0 violation', n($v,(new LandingPage())->setBlocks([])) === 0);

$badType = (new LandingPage())->setBlocks([['id'=>'b1','type'=>'nope']]);
check('type inconnu -> >=1', n($v,$badType) >= 1);

$missing = (new LandingPage())->setBlocks([['id'=>'b1','type'=>'hero']]); // title manquant
check('hero sans title -> >=1', n($v,$missing) >= 1);

$noId = (new LandingPage())->setBlocks([['type'=>'hero','title'=>'T']]);
check('bloc sans id -> >=1', n($v,$noId) >= 1);

$badSource = (new LandingPage())->setBlocks([['id'=>'b1','type'=>'collection','source'=>'wrong']]);
check('collection.source invalide -> >=1', n($v,$badSource) >= 1);

$notList = (new LandingPage())->setBlocks(['x'=>1]);
check('blocks non-liste -> >=1', n($v,$notList) >= 1);

echo "\n== $pass OK / $fail FAIL ==\n";
exit($fail===0?0:1);
```
> Note : ce harnais valide une `LandingPage` qui portera `#[ValidBlocks]` sur `blocks` (Task 3). Tant que Task 3 n'est pas faite, l'attribut n'existe pas → exécuter ce harnais en **fin de Task 3**. Le créer ici, l'exécuter là-bas.

- [ ] **Step 4 : Vérifier que les classes se chargent (compile)**

Run :
```bash
php bin/console cache:clear
```
Expected : `[OK] Cache for the "dev" environment ... cleared.`

- [ ] **Step 5 : Commit**

```bash
git add src/Validator/ValidBlocks.php src/Validator/ValidBlocksValidator.php
git commit -m "feat(content): contrainte ValidBlocks (validation du contrat de blocs, 422)"
```

---

## Task 3 : Repurposer l'entité `LandingPage`

**Files:**
- Modify: `src/Entity/Content/LandingPage.php`

- [ ] **Step 1 : Imports**

Dans la zone `use`, ajouter (garder les existants `ApiFilter`, `ApiResource`, `SearchFilter`, `Box`, `ORM`, `UniqueEntity`, `Groups`) :
```php
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Validator\ValidBlocks;
use Symfony\Component\Validator\Constraints as Assert;
```

- [ ] **Step 2 : Attributs de classe — unicité, UniqueEntity, ApiResource avec sécurité, filtre**

Remplacer le bloc d'attributs de classe (de `#[ORM\UniqueConstraint(...)]` jusqu'à la ligne `#[ApiFilter(SearchFilter::class, ...)]`) par :
```php
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_landing_box_locale', columns: ['box_id', 'locale'])]
#[UniqueEntity(fields: ['box', 'locale'], errorPath: 'locale', message: 'Une landing page existe déjà pour cette box et cette langue.')]
#[ApiResource(
    normalizationContext: ['groups' => ['content:read']],
    denormalizationContext: ['groups' => ['content:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(securityPostDenormalize: "is_granted('ROLE_ADMIN') or is_granted('BOX_EDIT', object.box)"),
        new Patch(security: "is_granted('ROLE_ADMIN') or is_granted('BOX_EDIT', object.box)"),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('BOX_EDIT', object.box)"),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['box.slug' => 'exact', 'locale' => 'exact'])]
```
(Garder la ligne `class LandingPage` et le reste.)

- [ ] **Step 3 : Supprimer le champ `slug` + ses accesseurs**

Retirer entièrement :
```php
    #[ORM\Column(length: 120)]
    #[Groups(['content:read', 'content:write'])]
    private string $slug = '';
```
et les méthodes `getSlug()` / `setSlug()`.

- [ ] **Step 4 : Rendre `box` requise**

Remplacer le champ `box` par :
```php
    #[ORM\ManyToOne(targetEntity: Box::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Une landing page doit être rattachée à une box.')]
    #[Groups(['content:read', 'content:write'])]
    private ?Box $box = null;
```

- [ ] **Step 5 : Valider `blocks`**

Remplacer le champ `blocks` par :
```php
    #[ORM\Column(type: 'json')]
    #[ValidBlocks]
    #[Groups(['content:read', 'content:write'])]
    private array $blocks = [];
```

- [ ] **Step 6 : Valider le mapping**

Run :
```bash
php bin/console doctrine:schema:validate --skip-sync
```
Expected : `[OK] The mapping files are correct.`

- [ ] **Step 7 : Exécuter le harnais de validation de la Task 2**

Run :
```bash
php /private/tmp/claude-501/-Users-oliviervangest-Desktop-app-tinned/95609edb-2e55-44af-916f-be11190ca30d/scratchpad/verify_blocks.php
```
Expected : `== 7 OK / 0 FAIL ==`.

- [ ] **Step 8 : Commit**

```bash
git add src/Entity/Content/LandingPage.php
git commit -m "feat(content): LandingPage = page canonique par (box, locale), blocks validés, sécurité"
```

---

## Task 4 : Migration de schéma

**Files:** Create: `migrations/VersionYYYYMMDDHHMMSS.php`

- [ ] **Step 1 : Pré-vérif données (la table doit permettre les nouvelles contraintes)**

Run :
```bash
php bin/console dbal:run-sql "SELECT COUNT(*) total, SUM(box_id IS NULL) box_null FROM landing_page"
```
Expected : `total` faible (probablement 0) et `box_null = 0`. Si `box_null > 0` ou doublons `(box_id, locale)`, nettoyer avant de migrer (assigner une box / dédupliquer).

- [ ] **Step 2 : Générer la migration**

Run :
```bash
php bin/console make:migration --no-interaction
```
Expected : `created: migrations/VersionXXXX.php`.

- [ ] **Step 3 : Relire le `up()`**

Doit contenir uniquement, sur `landing_page` :
- `DROP INDEX uniq_landing_box_slug_locale`
- `DROP slug` (colonne)
- `CHANGE box_id box_id INT NOT NULL` (ou équivalent)
- `CREATE UNIQUE INDEX uniq_landing_box_locale (box_id, locale)`

Retirer tout ALTER d'une autre table. Renseigner la description :
```php
        return 'LandingPage canonique: drop slug, box_id NOT NULL, unique (box_id, locale)';
```

- [ ] **Step 4 : Appliquer**

Run :
```bash
php bin/console doctrine:migrations:migrate --no-interaction
```
Expected : `[OK] Successfully migrated`.

- [ ] **Step 5 : Idempotence**

Run :
```bash
php bin/console doctrine:schema:update --dump-sql
```
Expected : `[OK] Nothing to update`.

- [ ] **Step 6 : Commit**

```bash
git add migrations/
git commit -m "feat(content): migration LandingPage canonique (drop slug, unique box+locale)"
```

---

## Task 5 : Seed + vérification end-to-end

**Files:** aucun commit de code (données dev). Nécessite le serveur (`symfony server:start -d`).

- [ ] **Step 1 : Seeder une landing d'exemple (store box 2 = casa-do-sul-boutique, fr)**

Charge le JSON d'exemple dans la colonne `blocks`. Run :
```bash
cd api
BLOCKS=$(python3 -c "import json;print(json.dumps(json.load(open('docs/landing-blocks-example.json'))))")
php bin/console dbal:run-sql "INSERT INTO landing_page (box_id, locale, title, meta_description, blocks) VALUES (2, 'fr', 'Casa do Sul — Boutique', 'Céramiques artisanales du sud du Portugal', '$(printf '%s' "$BLOCKS" | sed "s/'/''/g")')"
```
Expected : `1 rows affected`.

- [ ] **Step 2 : Lecture publique OK**

Run :
```bash
curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:8000/api/landing_pages?box.slug=casa-do-sul-boutique&locale=fr"
```
Expected : `200`. Puis vérifier le contenu :
```bash
curl -s "http://localhost:8000/api/landing_pages?box.slug=casa-do-sul-boutique&locale=fr" | python3 -c "import sys,json;d=json.load(sys.stdin);m=d.get('hydra:member',d.get('member',[]));print('count',len(m));print('blocks',len(m[0]['blocks']) if m else 'none')"
```
Expected : `count 1`, `blocks 4`.

- [ ] **Step 3 : Unicité (box, locale)**

Run le même INSERT qu'au Step 1 une 2e fois.
Expected : échec `Duplicate entry` pour la clé `uniq_landing_box_locale` (prouve l'unicité en base).

- [ ] **Step 4 : Validation via API (authentifié, token ROLE_ADMIN ou owner de la box 2)**

Avec un JWT valide sur `http://localhost:8000`, tester (Swagger `/api` ou curl `-H "Authorization: Bearer <token>"`, `Content-Type: application/ld+json`) :
- POST `/api/landing_pages` `{ "box":"/api/store_boxes/2", "locale":"en", "title":"x", "blocks":[{"id":"b1","type":"nope"}] }` → **422** (type inconnu).
- idem avec `blocks:[{"id":"b1","type":"hero"}]` → **422** (title manquant).
- idem avec `blocks:[{"id":"b1","type":"collection","source":"wrong"}]` → **422** (source invalide).
- idem avec `blocks:[{"id":"b1","type":"hero","title":"OK"}]` → **201**.
Documenter chaque code de retour.

- [ ] **Step 5 : Sécurité**

- POST/PATCH **sans** token → **401**.
- POST/PATCH avec un token qui n'est ni admin ni owner de la box → **403**.
- POST/PATCH avec admin ou owner de la box → **201/200**.
Documenter les codes obtenus (si aucun JWT non-owner disponible, au minimum confirmer le 401 non authentifié).

---

## Self-review effectuée (couverture spec)

- LandingPage canonique (box requise, drop slug, unique box+locale) → Task 3 + 4.
- Catalogue 11 blocs (source d'autorité) → Task 1 (`BlockCatalog`).
- Validation ValidBlocks (type connu, champs requis, source collection) → Task 2 + harnais Task 3.
- Sécurité ROLE_ADMIN or BOX_EDIT → Task 3 (operations) + Task 5 (vérif).
- Migration drop slug / NOT NULL / unique → Task 4.
- Seed d'exemple → Task 5.
- Filtre lecture box.slug + locale → Task 3 Step 2.

## Notes de déploiement (prod)

`deploy.sh` prod = `d:s:u --force` (pas les migrations). Le `DROP slug` + `box_id NOT NULL` seront
appliqués par `d:s:u` : **pré-vérifier** qu'aucune `landing_page` prod n'a `box_id` NULL ni doublon
`(box_id, locale)`. Le seed (Task 5) est de la donnée dev — le rejouer manuellement en prod si voulu.
