#!/usr/bin/env bash
#
# Déploiement prod de l'API Tinned.
# À lancer sur le serveur, EN ROOT (ou via sudo) :
#
#     sudo bash /var/www/api.tinned.com/deploy.sh
#
# Pré-requis : avoir lancé UNE SEULE FOIS le bloc "setup ACL" du README ci-dessous
# (voir docs/DEPLOY.md). Une fois fait, ce script ne casse plus jamais les permissions.

set -euo pipefail

APP_DIR="/var/www/api.tinned.com"
WEB_USER="www-data"
PHP="php8.4"
# composer est un phar : on le lance explicitement avec php8.4, car le `php` par
# défaut du serveur est 7.4 et ne sait pas parser le code PHP 8 des dépendances.
COMPOSER_BIN="$(command -v composer)"
# composer tourne en root (utilisateur de déploiement) : vendor/ n'est lu qu'en
# lecture au runtime, donc root peut le posséder. Seul var/ doit rester writable
# par www-data, ce qui est garanti par les ACL (étape 5 + setup unique).
export COMPOSER_ALLOW_SUPERUSER=1

cd "$APP_DIR"

echo "==> 1/5  Récupération du code"
git pull --ff-only

echo "==> 2/5  Dépendances (prod, optimisées)"
# --no-scripts : on ne laisse pas les auto-scripts de Symfony Flex reconstruire
# le cache ici (c'est fait explicitement et proprement en étape 4, en www-data).
"$PHP" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --no-scripts

echo "==> 3/6  Migrations base de données"
sudo -u "$WEB_USER" "$PHP" bin/console d:s:u --force --no-interaction --env=prod

echo "==> 4/6  Clés JWT (générées en root, lisibles par $WEB_USER)"
# Générées seulement si absentes (--skip-if-exists) : pas d'--overwrite, donc on
# n'invalide pas les tokens existants à chaque déploiement. config/ appartient à
# root, donc la génération se fait en root ; on donne ensuite la LECTURE à www-data.
"$PHP" bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction --env=prod
chgrp -R "$WEB_USER" config/jwt
chmod 750 config/jwt
chmod 640 config/jwt/private.pem
chmod 644 config/jwt/public.pem

echo "==> 5/6  Reconstruction du cache (en tant que $WEB_USER)"
# On vide puis on chauffe le cache AVEC l'utilisateur d'exécution :
# les fichiers sont donc créés directement avec le bon propriétaire.
rm -rf var/cache/prod/*
sudo -u "$WEB_USER" "$PHP" bin/console cache:clear  --env=prod --no-debug
sudo -u "$WEB_USER" "$PHP" bin/console cache:warmup --env=prod --no-debug

echo "==> 6/6  Filet de sécurité permissions (idempotent, instantané)"
# Grâce aux ACL par défaut posées une fois (voir docs/DEPLOY.md), c'est déjà bon,
# mais on réapplique au cas où un fichier aurait été créé par root.
setfacl -R  -m u:"$WEB_USER":rwX -m u:root:rwX var/
setfacl -dR -m u:"$WEB_USER":rwX -m u:root:rwX var/

echo "==> OK. Déploiement terminé."
