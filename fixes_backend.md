# Fixes Backend — Portfolio Laravel 11

> Issu des 3 cycles de revue (revue_fix.md, revue2.md, audit_complet.md / revue_generale_finale.md)
> Classé par priorité : Critique → Haut → Moyen → Bas

---

## CRITIQUE — Bloquants production

### C1. Credentials committés dans le dépôt

**Fichiers :** `backend/.env`

Credentials actifs présents dans le repo :
- Token GitHub : 
- Clé OpenWeatherMap : 
- `APP_KEY` Laravel
- Credentials MySQL

**Actions :**
1. Révoquer et régénérer le token GitHub sur GitHub.com
2. Révoquer et régénérer la clé OpenWeatherMap
3. Régénérer l'`APP_KEY` : `php artisan key:generate`
4. Vérifier que `backend/.env` est bien dans `.gitignore`
5. Utiliser GitHub Secrets pour la CI/CD

> **Statut :** ⚠️ À faire manuellement — rotation des tokens sur les plateformes externes requise. `.env` est bien dans `.gitignore`.

---

### C2. Middleware `admin` absent sur les routes admin métier ✅ CORRIGÉ

**Fichier :** `routes/api.php`

**Correction appliquée :** `['auth:sanctum', 'admin']` ajouté sur :
- `prefix('messages')` — toutes les routes messages
- `prefix('books')` — routes POST/PUT/DELETE/refresh
- `prefix('carousel')` — routes upload/store/put/delete/reorder

---

### C4. Méthodes `show()` et `update()` manquantes dans `CarouselImageController` ✅ CORRIGÉ

**Fichiers :** `app/Http/Controllers/CarouselImageController.php`, `app/Http/Requests/UpdateCarouselImageRequest.php` (créé)

**Correction appliquée :** Méthodes `show()` et `update()` implémentées. `UpdateCarouselImageRequest` créé avec tous les champs optionnels.

---

## HAUT — A corriger avant déploiement

### H1. `ContactController` retourne le modèle Eloquent brut ✅ CORRIGÉ

**Fichier :** `app/Http/Controllers/ContactController.php`

**Correction appliquée :** `new ContactMessageResource($message)` utilisé à la place de `$message` brut.

---

### H2. Endpoint de nettoyage des cookies non authentifié ✅ CORRIGÉ

**Fichier :** `routes/api.php`

**Correction appliquée :** `DELETE /cleanup` protégé par `['auth:sanctum', 'admin']`.

---

### H3. Routes Easter Eggs sans rate limiting ✅ CORRIGÉ

**Fichier :** `routes/api.php`

**Correction appliquée :** `throttle:30,1` ajouté sur `POST /easter-eggs/discover` et `DELETE /easter-eggs/reset`.

---

### H4. `APP_DEBUG=true` et `APP_ENV=local` en production

**Fichier :** `backend/.env`

Expose les stack traces complètes (variables d'env, chemins, config) dans les réponses d'erreur.

**Correction :**
```
APP_ENV=production
APP_DEBUG=false
```

> **Statut :** ⚠️ À faire manuellement dans `.env` (non versionné).

---

### H5. CORS non configuré pour la production ✅ CORRIGÉ

**Fichier :** `config/cors.php`

**Correction appliquée :** `array_filter([..., env('FRONTEND_URL')])` — ajouter `FRONTEND_URL=https://votre-domaine.com` dans `.env`.

ok, a definir lors de la mise en prod
---

### H6. N+1 appels API externes dans `BookController::featured()` ✅ CORRIGÉ

**Fichier :** `app/Http/Controllers/BookController.php`

**Correction appliquée :** Lazy refresh via `dispatch(closure)->afterResponse()` sur `featured()` et `show()`.
- La réponse est retournée immédiatement avec les données existantes (même périmées).
- Le cache des livres périmés est rafraîchi en arrière-plan après l'envoi de la réponse.
- `ensureCache()` supprimé (plus utilisé).

---

### H7. Mot de passe admin par défaut `"robin"` dans la config ✅ CORRIGÉ

**Fichier :** `config/admin.php`

**Correction appliquée :** Suppression du fallback `'robin'` — `env('ADMIN_PASSWORD')` sans valeur par défaut. S'assurer que `ADMIN_PASSWORD` est défini dans `.env`.

ok
---

## MOYEN — Dette technique

### M3. Pas de Soft Deletes sur `Book` et `ContactMessage` ✅ CORRIGÉ

**Correction appliquée :**
- Migrations créées : `2026_03_05_000001_add_soft_deletes_to_books.php`, `2026_03_05_000002_add_soft_deletes_to_contact_messages.php`
- `use SoftDeletes` ajouté dans `Book` et `ContactMessage`
- `MessageTest` mis à jour : `assertSoftDeleted` à la place de `assertDatabaseMissing`
- `BookTest` mis à jour : `forceDelete()` pour le nettoyage avant test ISBN

> ⚠️ **Penser à exécuter** `php artisan migrate` après déploiement.

---

### M4. Pas de headers `Cache-Control` sur les routes publiques

**Routes concernées :** `/books`, `/carousel`, `/github/repositories`

> **Statut :** ⏳ Non corrigé — à implémenter via middleware ou dans les contrôleurs.

---

### M6. `cypress.config.js` en doublon dans `cypress/e2e/components/`

> **Statut :** N/A backend — aucun fichier Cypress trouvé dans le dossier backend.

---

### Arch. Colonne `is_read` orpheline en base de données

**Table :** `contact_messages`

> **Statut :** 🚫 Ne pas toucher — à laisser en l'état par décision explicite.

---

## BAS — Qualité et finition

### L2. `laravel.log` versionné ✅ CORRIGÉ

**Fichier :** `.gitignore`

**Correction appliquée :** `storage/logs/*.log` ajouté au `.gitignore`.

---

### L3. Scope du token GitHub à vérifier

> **Statut :** ⚠️ À vérifier manuellement sur GitHub.com — s'assurer que le scope est `public_repo` en lecture seule.

ok

---

### L4. Pas de throttle sur les endpoints Cookie Preferences ✅ CORRIGÉ

**Fichier :** `routes/api.php`

**Correction appliquée :** `throttle:30,1` ajouté sur `POST /cookies/preferences`.

---

### Tests. Aucun test ne couvre la privilege escalation (C2) ✅ CORRIGÉ

**Fichier créé :** `tests/Feature/PrivilegeEscalationTest.php`

**Correction appliquée :** 11 tests couvrant la tentative d'accès par un utilisateur `is_admin=false` sur toutes les routes admin (messages, books, carousel, cookies cleanup).

---

## Récapitulatif

| ID | Sévérité | Description | Statut |
|---|---|---|---|
| C1 | Critique | Credentials committés — rotation immédiate | ⚠️ Manuel |
| C2 | Critique | Middleware admin absent sur routes admin | ✅ Corrigé |
| C4 | Critique | show()/update() manquants CarouselController | ✅ Corrigé |
| H1 | Haut | Modèle brut retourné au visiteur anonyme | ✅ Corrigé |
| H2 | Haut | Endpoint cleanup cookies non authentifié | ✅ Corrigé |
| H3 | Haut | Easter egg routes sans protection | ✅ Corrigé |
| H4 | Haut | APP_DEBUG=true / APP_ENV=local | ⚠️ Manuel (.env) |
| H5 | Haut | CORS production absent | ✅ Corrigé |
| H6 | Haut | N+1 API externes sur /books/featured | ✅ Corrigé |
| H7 | Haut | Mot de passe admin par défaut hardcodé | ✅ Corrigé |
| M3 | Moyen | Pas de soft deletes | ✅ Corrigé |
| M4 | Moyen | Pas de cache HTTP routes publiques | ⏳ À faire |
| M6 | Moyen | cypress.config.js en doublon | N/A backend |
| Arch | Moyen | Colonne is_read orpheline | 🚫 Ne pas toucher |
| Tests | Critique | Aucun test privilege escalation C2 | ✅ Corrigé |
| L2 | Bas | laravel.log versionné | ✅ Corrigé |
| L3 | Bas | Scope token GitHub indéterminé | ⚠️ Manuel |
| L4 | Bas | Pas de throttle cookies/preferences | ✅ Corrigé |
