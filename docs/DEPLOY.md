# Déploiement prod — api.tinned.com

Utilisateur d'exécution : **`www-data`** (Apache + php-fpm 8.4).
Répertoire : `/var/www/api.tinned.com`.

## La règle d'or

Le 500 sur `/api/register` venait de fichiers dans `var/cache/prod/` appartenant à
`root` (cache reconstruit en root pendant un déploiement), que `www-data` ne pouvait
pas réécrire. Symfony écrit en permanence dans `var/cache` et `var/log` → ces deux
dossiers **doivent toujours rester accessibles en écriture à `www-data`**.

## Étape unique à faire UNE SEULE FOIS (le vrai « propre »)

Les **ACL par défaut** font que tout fichier créé dans `var/` hérite
automatiquement des bons droits, peu importe qui le crée (root, toi, www-data).
Une fois ceci posé, les permissions ne cassent plus jamais.

```bash
# en root, sur le serveur
cd /var/www/api.tinned.com

# acl doit être installé (déjà le cas sur Debian/Ubuntu standard) :
apt-get install -y acl

# on repart d'un cache sain
rm -rf var/cache/prod/*

# droits courants ET droits par défaut (le -d) hérités par les futurs fichiers
setfacl -R  -m u:www-data:rwX -m u:root:rwX var/
setfacl -dR -m u:www-data:rwX -m u:root:rwX var/
```

> Si le système de fichiers ne supporte pas les ACL (rare), remplace par
> `chown -R www-data:www-data var/` à la fin de chaque déploiement.

## À chaque déploiement

```bash
sudo bash /var/www/api.tinned.com/deploy.sh
```

Le script (`deploy.sh`) enchaîne, dans le bon ordre :

1. `git pull`
2. `composer install --no-dev --optimize-autoloader` (en `www-data`)
3. migrations Doctrine
4. **reconstruction du cache en tant que `www-data`** (donc bon propriétaire d'emblée)
5. réapplication des ACL (filet de sécurité instantané et idempotent)

## Vérifier que c'est réparé

```bash
curl -sX POST https://api.tinned.com/api/register \
  -H "Content-Type: application/ld+json" \
  -d '{"email":"test+'"$RANDOM"'@tinned.com","password":"motdepasse123","firstName":"Test","lastName":"User","phone":"+32470000000","acceptedTerms":true}'
```

Attendu : un JSON avec `token`, pas un `"status":500`.
