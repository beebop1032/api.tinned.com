# Upload d'une image — `POST /api/admin/media`

Endpoint du backoffice pour héberger une image et récupérer son chemin/URL public.
C'est une route Symfony custom (`AdminMediaController`), pas une ressource API Platform,
mais elle est documentée dans Swagger (`https://api.tinned.com/api`, tag **Media**).

## Contraintes

- Réservé aux administrateurs (`ROLE_ADMIN`) → nécessite un JWT admin.
- `multipart/form-data`, un seul champ : **`file`**.
- Formats acceptés : `gif`, `jpg/jpeg`, `png`, `svg`, `webp`.
- Taille max : **3 Mo**.
- Le fichier est stocké dans `public/uploads/media/` et servi par l'API.

## Étape 1 — Obtenir un token admin

```bash
curl -X POST https://api.tinned.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ADMIN_EMAIL","password":"ADMIN_PASSWORD"}'
```

Réponse :

```json
{ "token": "eyJhbGciOiJ..." }
```

## Étape 2 — Uploader l'image

```bash
curl -X POST https://api.tinned.com/api/admin/media \
  -H "Authorization: Bearer <TOKEN>" \
  -F "file=@/chemin/vers/photo.png"
```

Réponse (`200`) :

```json
{
  "path": "/uploads/media/photo-ab12cd34ef.png",
  "url":  "https://api.tinned.com/uploads/media/photo-ab12cd34ef.png"
}
```

- `path` → chemin relatif (à stocker en base / dans le contenu).
- `url` → URL absolue directement affichable.

## En JavaScript (fetch)

```js
const form = new FormData();
form.append("file", fileInput.files[0]); // <input type="file">

const res = await fetch("https://api.tinned.com/api/admin/media", {
  method: "POST",
  headers: { Authorization: `Bearer ${token}` }, // NE PAS fixer Content-Type : le navigateur ajoute la boundary
  body: form,
});
const { path, url } = await res.json();
```

## Erreurs possibles

| Code | Cause |
|------|-------|
| `401` | Token manquant / invalide / expiré |
| `403` | Compte non-admin |
| `400` | Aucun fichier, > 3 Mo, ou format non supporté |

## Depuis Swagger

Sur `https://api.tinned.com/api` : clique **Authorize**, colle le JWT admin, puis
ouvre l'opération **Media → POST /api/admin/media → Try it out**, sélectionne le
fichier et exécute. La réponse contient `path` et `url`.
