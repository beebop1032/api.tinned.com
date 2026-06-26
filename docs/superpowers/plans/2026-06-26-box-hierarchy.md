# Box Hierarchy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Structurer les 4 types de Box en arbre à parent unique, avec relations typées par couple et validateurs (un-seul-parent + anti-cycle).

**Architecture:** Chaque box porte des FK `ManyToOne` typées vers son/ses parent(s) autorisés ; chaque parent porte les collections `OneToMany` inverses (non sérialisées). Les règles « exactement/au plus un parent » et « anti-cycle » sont des `#[Assert\Callback]` Symfony (→ 422). Un helper `getParentBox()` par sous-type rend le validateur anti-cycle générique.

**Tech Stack:** Symfony 7.3, API Platform 4.1, Doctrine ORM (héritage JOINED), MySQL/MariaDB. Pas de phpunit dans ce repo → vérification via `bin/console` + API.

**Spec de référence:** `docs/superpowers/specs/2026-06-26-box-hierarchy-design.md`

---

## Structure des fichiers

| Fichier | Responsabilité | Action |
|---|---|---|
| `src/Entity/Box/Box.php` | abstract : `getParentBox()` (abstrait) + callback anti-cycle | Modifier |
| `src/Entity/Box/StoreBox.php` | parent Business requis ; enfants Blog | Modifier |
| `src/Entity/Box/BusinessBox.php` | parents Travel/Business ; enfants Travel/Business/Store/Blog | Modifier |
| `src/Entity/Box/BlogBox.php` | parent Travel\|Business\|Store (exactement 1) ; feuille | Modifier |
| `src/Entity/Box/TravelBox.php` | parents Travel/Business ; enfants Travel/Business/Blog | Modifier |
| `migrations/VersionYYYYMMDDHHMMSS.php` | ALTER colonnes FK + DML réconciliation | Créer (généré) |

État de départ (après merge PR #3 sur `main`) — colonnes FK existantes :
- `store_box` : `business_box_id` (nullable), `travel_box_id` (nullable)
- `business_box` : `travel_box_id` (nullable)
- `blog_box` : `business_box_id`, `store_box_id`, `travel_box_id` (tous nullable)
- `travel_box` : (aucune FK parent)

---

## Task 1 : Réconciliation des données (DB dev)

**Files:** aucun (SQL direct). À rejouer avant la migration sur tout env utilisant les migrations.

- [ ] **Step 1 : Corriger BlogBox(3) à deux parents → garder Business**

Run :
```bash
cd api
php bin/console dbal:run-sql "UPDATE blog_box SET store_box_id = NULL WHERE id = 3 AND business_box_id IS NOT NULL AND store_box_id IS NOT NULL"
```
Expected : `1 rows affected` (ou `0` si déjà nettoyé).

- [ ] **Step 2 : Vérifier qu'aucune StoreBox n'est sans business (bloquerait le NOT NULL)**

Run :
```bash
php bin/console dbal:run-sql "SELECT id FROM store_box WHERE business_box_id IS NULL"
```
Expected : `empty result set`. Si des lignes apparaissent, leur affecter une BusinessBox avant de continuer.

- [ ] **Step 3 : Vérifier chaque BlogBox a exactement un parent**

Run :
```bash
php bin/console dbal:run-sql "SELECT id, (business_box_id IS NOT NULL) + (store_box_id IS NOT NULL) + (travel_box_id IS NOT NULL) AS n FROM blog_box HAVING n <> 1"
```
Expected : `empty result set`.

---

## Task 2 : Remapper les 4 entités box + Box abstract

Toutes les relations bidirectionnelles doivent être cohérentes ensemble → un seul commit, validé par `doctrine:schema:validate --skip-sync`.

**Files:**
- Modify: `src/Entity/Box/Box.php`
- Modify: `src/Entity/Box/StoreBox.php`
- Modify: `src/Entity/Box/BusinessBox.php`
- Modify: `src/Entity/Box/BlogBox.php`
- Modify: `src/Entity/Box/TravelBox.php`

- [ ] **Step 1 : `Box.php` — déclarer `getParentBox()` abstrait**

Ajouter dans la classe abstraite `Box` (à côté de `abstract public function getType(): string;`) :
```php
    /** Le parent unique de ce box dans la hiérarchie, ou null si racine. */
    abstract public function getParentBox(): ?Box;
```

- [ ] **Step 2 : `StoreBox.php` — parent Business requis, retirer travelBox, ajouter blogBoxes + getParentBox**

Remplacer le bloc `businessBox` actuel par (ajout de `JoinColumn(nullable: false)`) :
```php
    #[ORM\ManyToOne(targetEntity: BusinessBox::class, inversedBy: 'storeBoxes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['box:read', 'box:write', 'product:read'])]
    private ?BusinessBox $businessBox = null;
```

Supprimer entièrement le champ `travelBox` (ajouté en PR #3) ET ses accesseurs `getTravelBox`/`setTravelBox`.

Ajouter la collection inverse des blogs (après le champ `products`) :
```php
    /** @var Collection<int, BlogBox> */
    #[ORM\OneToMany(mappedBy: 'storeBox', targetEntity: BlogBox::class)]
    private Collection $blogBoxes;
```

Dans le constructeur, après `$this->products = new ArrayCollection();` :
```php
        $this->blogBoxes = new ArrayCollection();
```

Ajouter les accesseurs (et retirer ceux de travelBox) :
```php
    /** @return Collection<int, BlogBox> */
    public function getBlogBoxes(): Collection { return $this->blogBoxes; }

    public function getParentBox(): ?Box { return $this->businessBox; }
```

- [ ] **Step 3 : `BusinessBox.php` — ajouter parentBusinessBox + collections travelBoxes/childBusinessBoxes + getParentBox**

Le champ `travelBox` (PR #3) reste tel quel (parent Travel). Ajouter juste après lui le parent Business auto-référencé :
```php
    #[ORM\ManyToOne(targetEntity: BusinessBox::class, inversedBy: 'childBusinessBoxes')]
    #[Groups(['box:read', 'box:write'])]
    private ?BusinessBox $parentBusinessBox = null;
```

Ajouter les 2 nouvelles collections enfants (à côté de `storeBoxes`/`blogBoxes` existants) :
```php
    /** @var Collection<int, TravelBox> */
    #[ORM\OneToMany(mappedBy: 'businessBox', targetEntity: TravelBox::class)]
    private Collection $travelBoxes;

    /** @var Collection<int, BusinessBox> */
    #[ORM\OneToMany(mappedBy: 'parentBusinessBox', targetEntity: BusinessBox::class)]
    private Collection $childBusinessBoxes;
```

Dans le constructeur, après les `storeBoxes`/`blogBoxes` :
```php
        $this->travelBoxes = new ArrayCollection();
        $this->childBusinessBoxes = new ArrayCollection();
```

Ajouter les accesseurs :
```php
    public function getParentBusinessBox(): ?BusinessBox { return $this->parentBusinessBox; }
    public function setParentBusinessBox(?BusinessBox $parentBusinessBox): self { $this->parentBusinessBox = $parentBusinessBox; return $this; }
    /** @return Collection<int, TravelBox> */
    public function getTravelBoxes(): Collection { return $this->travelBoxes; }
    /** @return Collection<int, BusinessBox> */
    public function getChildBusinessBoxes(): Collection { return $this->childBusinessBoxes; }

    public function getParentBox(): ?Box { return $this->travelBox ?? $this->parentBusinessBox; }
```

> Note : `BusinessBox.travelBox` (PR #3) a `inversedBy: 'businessBoxes'`. Vérifier que c'est bien le cas ; sinon l'ajouter — il pointe vers `TravelBox.businessBoxes` (conservé).

- [ ] **Step 4 : `BlogBox.php` — inversedBy sur storeBox + getParentBox**

Le champ `storeBox` actuel n'a pas d'`inversedBy`. Le remplacer par :
```php
    #[ORM\ManyToOne(targetEntity: StoreBox::class, inversedBy: 'blogBoxes')]
    #[Groups(['box:read', 'box:write', 'article:read'])]
    private ?StoreBox $storeBox = null;
```

Vérifier que `businessBox` a `inversedBy: 'blogBoxes'` et `travelBox` a `inversedBy: 'blogBoxes'` (déjà le cas après PR #3). Aucune autre modif de champ.

Ajouter l'accesseur de parent (après les setters existants) :
```php
    public function getParentBox(): ?Box { return $this->travelBox ?? $this->businessBox ?? $this->storeBox; }
```

- [ ] **Step 5 : `TravelBox.php` — retirer storeBoxes, ajouter parentTravelBox + businessBox + childTravelBoxes + getParentBox**

Supprimer le champ `storeBoxes` (PR #3) ET son getter `getStoreBoxes()` ET son init dans le constructeur. Conserver `businessBoxes`, `blogBoxes`, `trips`.

Ajouter les 2 FK parent (avant les collections) :
```php
    #[ORM\ManyToOne(targetEntity: TravelBox::class, inversedBy: 'childTravelBoxes')]
    #[Groups(['box:read', 'box:write'])]
    private ?TravelBox $parentTravelBox = null;

    #[ORM\ManyToOne(targetEntity: BusinessBox::class, inversedBy: 'travelBoxes')]
    #[Groups(['box:read', 'box:write'])]
    private ?BusinessBox $businessBox = null;
```

Ajouter la collection enfant auto-référencée :
```php
    /** @var Collection<int, TravelBox> */
    #[ORM\OneToMany(mappedBy: 'parentTravelBox', targetEntity: TravelBox::class)]
    private Collection $childTravelBoxes;
```

Dans le constructeur, remplacer l'init de `storeBoxes` par :
```php
        $this->childTravelBoxes = new ArrayCollection();
```

Ajouter les accesseurs (et retirer `getStoreBoxes`) :
```php
    public function getParentTravelBox(): ?TravelBox { return $this->parentTravelBox; }
    public function setParentTravelBox(?TravelBox $parentTravelBox): self { $this->parentTravelBox = $parentTravelBox; return $this; }
    public function getBusinessBox(): ?BusinessBox { return $this->businessBox; }
    public function setBusinessBox(?BusinessBox $businessBox): self { $this->businessBox = $businessBox; return $this; }
    /** @return Collection<int, TravelBox> */
    public function getChildTravelBoxes(): Collection { return $this->childTravelBoxes; }

    public function getParentBox(): ?Box { return $this->parentTravelBox ?? $this->businessBox; }
```

- [ ] **Step 6 : Valider le mapping**

Run :
```bash
php bin/console doctrine:schema:validate --skip-sync
```
Expected : `[OK] The mapping files are correct.` (le `Database` sera hors-sync, normal — corrigé en Task 3.)

- [ ] **Step 7 : Commit**

```bash
git add src/Entity/Box/
git commit -m "feat(box): hiérarchie typée à parent unique (relations + getParentBox)"
```

---

## Task 3 : Migration de schéma

**Files:** Create: `migrations/VersionYYYYMMDDHHMMSS.php` (généré)

- [ ] **Step 1 : Générer la migration**

Run :
```bash
php bin/console make:migration --no-interaction
```
Expected : `created: migrations/VersionXXXX.php`.

- [ ] **Step 2 : Relire le `up()` généré**

Doit contenir uniquement (l'ordre peut varier) :
- `store_box` : `MODIFY business_box_id ... NOT NULL` (ou équivalent), `DROP FOREIGN KEY` + `DROP INDEX` + `DROP travel_box_id`
- `business_box` : `ADD parent_business_box_id INT DEFAULT NULL` + FK + index
- `travel_box` : `ADD parent_travel_box_id INT DEFAULT NULL` + `ADD business_box_id INT DEFAULT NULL` + 2 FK + 2 index

Si un ALTER inattendu apparaît (autre table), le retirer.

- [ ] **Step 3 : Sécuriser le NOT NULL — injecter la réconciliation en tête de `up()`**

Avant le `MODIFY ... business_box_id ... NOT NULL`, ajouter cette ligne (idempotente, utile aux env basés migrations) :
```php
        $this->addSql('UPDATE blog_box SET store_box_id = NULL WHERE business_box_id IS NOT NULL AND store_box_id IS NOT NULL');
```
Et renseigner la description :
```php
        return 'Hiérarchie box: parent Business requis sur store_box, FK parent self sur travel/business, FK travel_box.business_box';
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
git commit -m "feat(box): migration hiérarchie (NOT NULL business sur store, FK parent self)"
```

---

## Task 4 : Validateurs (un-seul-parent + anti-cycle)

**Files:**
- Modify: `src/Entity/Box/Box.php` (callback anti-cycle, partagé par héritage)
- Modify: `src/Entity/Box/StoreBox.php` (NotNull businessBox)
- Modify: `src/Entity/Box/BlogBox.php` (callback exactement-un)
- Modify: `src/Entity/Box/BusinessBox.php` (callback au-plus-un)
- Modify: `src/Entity/Box/TravelBox.php` (callback au-plus-un)

- [ ] **Step 1 : `Box.php` — imports + callback anti-cycle**

Ajouter les imports :
```php
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
```

Ajouter cette méthode dans la classe `Box` (le `#[Assert\Callback]` est hérité et exécuté pour chaque sous-type) :
```php
    #[Assert\Callback]
    public function validateNoCycle(ExecutionContextInterface $context): void
    {
        $ancestor = $this->getParentBox();
        $steps = 0;
        while ($ancestor !== null && $steps < 100) {
            if ($ancestor === $this) {
                $context->buildViolation('Un box ne peut pas être son propre ancêtre (cycle interdit).')
                    ->addViolation();
                return;
            }
            $ancestor = $ancestor->getParentBox();
            $steps++;
        }
    }
```

- [ ] **Step 2 : `StoreBox.php` — businessBox NotNull**

Ajouter l'import :
```php
use Symfony\Component\Validator\Constraints as Assert;
```
Ajouter l'attribut `#[Assert\NotNull]` sur le champ `businessBox` :
```php
    #[ORM\ManyToOne(targetEntity: BusinessBox::class, inversedBy: 'storeBoxes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Une StoreBox doit être rattachée à une BusinessBox.')]
    #[Groups(['box:read', 'box:write', 'product:read'])]
    private ?BusinessBox $businessBox = null;
```

- [ ] **Step 3 : `BlogBox.php` — callback exactement-un-parent**

Ajouter les imports :
```php
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
```
Ajouter la méthode :
```php
    #[Assert\Callback]
    public function validateSingleParent(ExecutionContextInterface $context): void
    {
        $count = (int) ($this->travelBox !== null) + (int) ($this->businessBox !== null) + (int) ($this->storeBox !== null);
        if ($count !== 1) {
            $context->buildViolation('Une BlogBox doit être rattachée à exactement une box (Travel, Business ou Store).')
                ->atPath('businessBox')
                ->addViolation();
        }
    }
```

- [ ] **Step 4 : `TravelBox.php` — callback au-plus-un-parent**

Ajouter les imports :
```php
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
```
Ajouter la méthode :
```php
    #[Assert\Callback]
    public function validateSingleParent(ExecutionContextInterface $context): void
    {
        if ($this->parentTravelBox !== null && $this->businessBox !== null) {
            $context->buildViolation('Une TravelBox ne peut avoir qu\'un seul parent (Travel ou Business).')
                ->atPath('parentTravelBox')
                ->addViolation();
        }
    }
```

- [ ] **Step 5 : `BusinessBox.php` — callback au-plus-un-parent**

Ajouter les imports :
```php
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
```
Ajouter la méthode :
```php
    #[Assert\Callback]
    public function validateSingleParent(ExecutionContextInterface $context): void
    {
        if ($this->travelBox !== null && $this->parentBusinessBox !== null) {
            $context->buildViolation('Une BusinessBox ne peut avoir qu\'un seul parent (Travel ou Business).')
                ->atPath('travelBox')
                ->addViolation();
        }
    }
```

- [ ] **Step 6 : Vérifier que le container compile**

Run :
```bash
php bin/console cache:clear
```
Expected : `[OK] Cache for the "dev" environment ... was successfully cleared.`

- [ ] **Step 7 : Commit**

```bash
git add src/Entity/Box/
git commit -m "feat(box): validateurs parent unique + anti-cycle (422)"
```

---

## Task 5 : Vérification fonctionnelle

**Files:** aucun. Nécessite le serveur (`symfony server:start -d`) et un JWT pour les POST protégés (`ROLE_USER`).

- [ ] **Step 1 : Navigation inverse en base**

Run :
```bash
php bin/console dbal:run-sql "SELECT b.id, b.business_box_id, b.store_box_id, b.travel_box_id FROM blog_box b"
```
Expected : BlogBox(3) a un seul parent non-null (business_box_id=1).

- [ ] **Step 2 : Anti-cycle (test PHP via tinker-like)**

Comme il n'y a pas de phpunit, vérifier le validateur via l'API (Step 4) ou un script ponctuel. Vérifier au minimum que `getParentBox()` ne boucle pas :
```bash
php bin/console dbal:run-sql "SELECT id, parent_travel_box_id, business_box_id FROM travel_box"
```
Expected : aucune ligne où `parent_travel_box_id = id` (pas d'auto-référence directe).

- [ ] **Step 3 : Contrôle anti-doublon non régressé**

Run :
```bash
php bin/console doctrine:schema:validate --skip-sync
```
Expected : `[OK] The mapping files are correct.`

- [ ] **Step 4 : Validateurs via API (authentifié)**

Avec un token `ROLE_USER` (récupéré via `/auth` selon le flux du projet), tester sur Swagger `/api` ou curl :
- POST `/api/store_boxes` sans `businessBox` → attendu **422**.
- POST `/api/blog_boxes` avec `businessBox` ET `storeBox` → attendu **422** ; avec un seul → **201**.
- POST `/api/travel_boxes` avec `parentTravelBox` ET `businessBox` → attendu **422**.
- PATCH d'une TravelBox pour la rattacher à l'un de ses descendants → attendu **422** (anti-cycle).

Documenter le résultat de chaque appel.

---

## Task 6 : Pull request

- [ ] **Step 1 : Pousser la branche**

```bash
git push -u origin feat/box-hierarchy
```

- [ ] **Step 2 : Ouvrir la PR**

```bash
gh pr create --base main --head feat/box-hierarchy \
  --title "feat(box): hiérarchie de box à parent unique (relations typées + validateurs)" \
  --body "Voir docs/superpowers/specs/2026-06-26-box-hierarchy-design.md. Remplace le hub TravelBox typé (PR #3)."
```

---

## Notes de déploiement (prod)

Le `deploy.sh` prod utilise `d:s:u --force` (pas les migrations). Conséquences à surveiller :
- La **DML de réconciliation** (BlogBox multi-parents) n'est PAS jouée par `d:s:u` → si la prod a des blog_box à plusieurs parents, les nettoyer manuellement AVANT le déploiement, sinon le `NOT NULL`/les FK peuvent échouer.
- Vérifier qu'aucune `store_box` prod n'a `business_box_id NULL` avant le passage au NOT NULL.
