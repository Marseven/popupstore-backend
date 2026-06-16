# PopUp Store — Feuille de route d'extension

> Complète le `CLAUDE.md`. Une tâche à la fois, dans l'ordre §"Ordre d'exécution". Cycle **test-first** : tests d'abord (nominal, négatifs/sécurité, idempotence, bornes) → échec → implémentation. Fin de tâche : tests verts + Pint OK + aucune régression (`CartServiceTest` = suite de référence). Les **principes fondateurs (§0.1)** priment sur la vitesse.

> **⚠️ Contrainte d'hébergement confirmée (Hostinger mutualisé) : PAS de Redis.** La file et le cache utilisent le driver **`database`** (déjà la valeur par défaut du projet). Le worker tourne via **cron** (worker persistant non garanti sur mutualisé). Voir Gap Prod 2 amendé.

---

## 0. Contexte et règles non négociables

Plateforme **déjà en production** (popupstore.ga). On **étend**, on ne refond pas. Respecter le `CLAUDE.md` :

- **Controller → Service → Model.** Contrôleurs fins (~20 lignes/méthode), logique métier dans `app/Services/`.
- **`$fillable` obligatoire** (jamais `$guarded = []`).
- **Eager loading systématique** (`with()`) — `preventLazyLoading` actif hors prod.
- **Pagination obligatoire** (`paginate()`/`cursorPaginate()`).
- **Validation via Form Requests**, **autorisation via Policies**, **statuts via Enums**, **SoftDeletes** sur les entités métier.
- **Sanctum token** (24h), `auth.optional` pour cart/order/payment.
- **Logs JSON structurés** + `CorrelationId`. **Rate limiting** 60/min (`/api/*`), 5/min (auth).
- **Migrations MySQL-only** protégées : `if (DB::getDriverName() !== 'mysql') return;`.
- **Frontend** : Vue 3.5 `<script setup>`, Pinia via composables, API **toujours** via `@/utils/api.js`, mobile-first, tokens design (`gold-*` violet, `terra-*` orange, `teal` audio, violet vidéo, Syne/Outfit).

### ⚠️ Invariants à ne JAMAIS casser
1. **Panier dual-mode** : `user_id` posé ⇒ `session_id = NULL`. `mergeSessionCart` ne fusionne que les vrais invités (`whereNull('user_id')`). Branche-invité filtre toujours `whereNull('user_id') AND session_id` — cohérent avec `OrderController`.
2. **Vidage du panier au paiement réussi**, pas à la création. En ligne → `EbillingService::markOrderAsPaid` (webhook). COD → à la confirmation. Un paiement échoué/abandonné ne perd jamais le panier.
3. **`markOrderAsPaid` reste synchrone.** Tout effet de bord lourd (notifs, splits, points) → **queue, dispatch `afterCommit`**.
4. **XAF en entiers.** Toute répartition produit des montants **entiers** ; reliquat d'arrondi déterministe (par défaut : Plateforme).

### Pattern d'extension transverse
Ajouter `type` (enum) sur `collections` : `shop` (défaut) | `partner` | `campaign`. Active conditionnellement les comportements. Les collections `shop` existantes ne changent **pas** de comportement.

---

## 0.1 Principes fondateurs (TOUTES les tâches)

### 🔒 Security by Design
- **Threat model d'abord** (en tête de PR/ADR) : abus possibles par invité/marchand/acheteur/webhook forgé.
- **Default-deny** : route refusée par défaut, ouverte via middleware + Policy. Jamais d'autorisation uniquement dans le contrôleur.
- **Isolation au niveau requête** (global scope / service centralisé), pas au niveau vue. Oubli de filtre = faille.
- **Ne jamais faire confiance au client** : montants/%/points/prix/statuts calculés **côté serveur**.
- **Intégrité des flux d'argent** : webhook `/payments/callback` **authentifié** (clé partagée/signature) avant traitement ; `payments/initiate` **idempotent** (clé) ; rejeu webhook ne re-crédite jamais (idempotence par `order_id`).
- **Moindre privilège** : `merchant` ⊂ sa collection ; manager ≠ rôles/réglages ; protéger le dernier `super_admin`.
- **Pas de fuite** : numéros payout / tokens / clés jamais en logs ni URL. `CorrelationId` sans PII.
- **Anti-abus** : rate limiting dédié sur paiement / candidature marchand / scan-leaderboard.
- **Contrôles prouvés par tests négatifs** (critères de merge).

### 🧪 Test-First (TDD)
- **Liste des tests d'abord** (nominal + négatifs/autorisation + idempotence + bornes) → voir échouer → implémenter le minimum → refactorer.
- **Tests de sécurité écrits en premier** : isolation A≠B ; rôle non habilité = 403 ; webhook forgé rejeté ; double webhook = un seul crédit.
- **Flux d'argent non mergés sans test d'idempotence** (double `PaymentReceived`, double `initiate`) + **arrondi entier XAF** (reliquat déterministe).
- **Bornes/invariants** : fenêtre campagne (avant/pendant/après), somme des parts ≤ 100, panier dual-mode intact.
- Backend PHPUnit (sqlite in-memory), Frontend Vitest. `CartServiceTest` reste vert.
- PR : **plan de test en tête** avant le code.

---

## MODULE 1 — Répartition des revenus (collections « partner ») — Effort M, Phase 2

Répartition configurable du produit des ventes (ex. 70 % marchand / 30 % plateforme), calculée à l'encaissement.

### Data model (migrations guardées MySQL)
- `collections` : `type` enum (`shop`/`partner`/`campaign`, défaut `shop`), `owner_user_id` (nullable, FK users, index).
- `revenue_shares` : `id`, `collection_id` (FK, index), `beneficiary_label`, `payout_phone`, `payout_provider` (enum `airtelmoney`/`moovmoney4`), `percentage` decimal(5,2), timestamps, softDeletes.
- `payout_entries` : `id`, `order_id` (FK, index), `collection_id` (FK, index), `revenue_share_id` (nullable FK), `gross_amount` int, `commission_amount` int, `net_amount` int, `status` enum (`pending`/`paid`/`cancelled`, index), `paid_at` nullable, `payout_reference` nullable, timestamps. Index composite `(collection_id, status)`.
- Enum : `PayoutStatus`.

### Backend
- `RevenueSplitService` : `validateShares` (somme ≤ 100, part Plateforme = 100 − somme) ; `recordForOrder(Order)` (calcul gross/commission/net **entiers**, reliquat Plateforme, crée `payout_entries` `pending`, **idempotent par `order_id`**).
- Listener `RecordRevenueShares` (`ShouldQueue`, `afterCommit`) sur **`PaymentReceived`** (couvre aussi COD → `paid`).
- Endpoints admin : `GET|POST|PUT|DELETE /api/admin/collections/{id}/revenue-shares` (super_admin) ; `GET /api/admin/payouts` (filtres, paginé), `POST /api/admin/payouts/{id}/mark-paid`.
- Policies : super_admin gère les parts ; manager consulte.

### Frontend
- Éditeur de répartition (formulaire collection) avec contrôle live « somme ≤ 100 % ».
- Vue « Reversements » paginée + filtres + action « marquer payé ». `formatPrice()` XAF.

### Tests
- Unit `RevenueSplitService` : maths split, arrondi entier + reliquat, idempotence.
- Feature : listing payouts, contrôle somme, scoping autorisations.

---

## MODULE 2 — Campagne / Compétition (collections « campaign ») — Effort L, Phase 3

Format « Battle » (N équipes, codes équipe, points/achat, classement, fenêtre temporelle, déblocage avantage type « ticket finale »).

### Data model (migrations guardées MySQL)
- `campaigns` : `id`, `name`, `slug` (unique, index), `starts_at`, `ends_at` (index), `status` enum (`draft`/`active`/`closed`, index), `sales_goal` int nullable, `settings` json, softDeletes.
- `campaign_teams` : `id`, `campaign_id` (FK, index), `name`, `slug`, `team_code` (unique, index), `producer_name`, `artist_name`, `color_accent`, `qr_code_path` nullable, `points_total` int défaut 0 (dénormalisé), `sort_order`.
- `campaign_points` : `id`, `campaign_id` (FK), `team_id` (FK, index), `order_id` (FK, index), `points` int, timestamps. Index `(team_id, created_at)`.
- `campaign_entitlements` : `id`, `order_id` (FK, index), `team_id` (FK), `type` enum (`catalog`/`exclusive_tracks`/`finale_ticket`), `code` (unique), `redeemed_at` nullable.
- `products` : `campaign_team_id` (nullable, FK, index).
- Enums : `CampaignStatus`, `EntitlementType`.

### Backend
- `CampaignService` : `awardPoints(Order)` (règle de `settings`, insère `campaign_points` + incrémente `points_total`, **idempotent par `order_id`**, **aucun point hors fenêtre/`status != active`**) ; `issueEntitlements(Order)` (codes uniques) ; `leaderboard(campaign)` (tri `points_total`, lecture cache).
- Listener `AwardCampaignPoints` (`ShouldQueue`, `afterCommit`) sur **`PaymentReceived`**.
- QR équipe via `QrCodeService` (deep-link page équipe, PNG HD, correction H).
- Endpoints : public `GET /api/campaigns/{slug}`, `/leaderboard` (cache court), `/teams` ; acheteur `GET /api/orders/{number}/entitlements` ; admin CRUD campaigns/teams + `POST /api/admin/campaigns/{id}/close`.
- Gating streaming : `exclusive_tracks` requiert entitlement valide (`MediaStreamService` + URLs signées).

### Frontend
- Landing campagne (N équipes + classement live polling 30–60 s), page équipe, parcours « choisis ton équipe », vue entitlements/ticket. Réutiliser `MediaLayout`.

### Tests
- Idempotence points (un `order_id` = un crédit), respect fenêtre/statut, tri classement, unicité entitlements.

---

## MODULE 3 — Self-service Marchand — Effort M/L, Phase 2-3 (s'appuie sur Module 1)

Un marchand gère sa collection (produits, médias, commandes le concernant, reversements), sous approbation admin.

### Data model (migrations guardées MySQL)
- Rôle dynamique `merchant` (rôles déjà CRUD via `/api/admin/roles`).
- `merchant_profiles` : `id`, `user_id` (FK, index), `business_name`, `rccm_nif` nullable, `payout_phone`, `payout_provider` enum, `status` enum (`pending`/`approved`/`suspended`, index), `approved_at` nullable, timestamps, softDeletes.
- Propriété collection via `collections.owner_user_id` (Module 1).

### Backend
- Enrôlement : `POST /api/merchant/apply` (auth) → `merchant_profile` `pending`. Admin `POST /api/admin/merchants/{id}/approve` → assigne rôle `merchant`, crée/affecte collection.
- Endpoints scopés (`/api/merchant/...`, middleware `role:merchant`) : `dashboard`, `products` CRUD, `media`, `orders` (uniquement ceux contenant ses produits), `payouts` (ses `payout_entries`).
- **Sécurité critique** : chaque requête marchand filtre par `owner_user_id`/appartenance. Centraliser dans un `MerchantScope` (global scope ou service) — jamais de requête non scopée. Policies fines (`product.collection.owner_user_id === user.id`).
- Réutiliser les Services admin existants en injectant le scope.

### Frontend
- Wizard d'enrôlement (« Kit Marchand »). Dashboard marchand scopé (layout dédié ou `AdminLayout` à menu restreint).

### Tests (sécurité d'abord)
- Isolation : marchand A ne voit jamais produits/commandes/payouts de B. Flux d'approbation, attribution rôle, scoping listings.

---

## GAP PROD 1 — Confirmation de commande invité (email / SMS) — Effort S/M

> À sécuriser AVANT toute campagne de masse. Aujourd'hui l'invité n'a que le numéro à l'écran.

- Notifications `GuestOrderConfirmation` (sur `OrderCreated`) et `GuestPaymentConfirmation` (sur `PaymentReceived`) : **numéro** (`POP-XXXXXXXX-XXXX`) + **lien `/track`**.
- Canaux : **email** si fourni ; **SMS** via passerelle compatible Gabon (Airtel/Moov ou gateway HTTP générique), config `config/sms.php` + clés `.env`.
- **Toujours en queue** (Gap 2), `afterCommit`. Ne pas perturber les notifs admin existantes.
- Tests : déclenchement par event, contenu (numéro + lien), tolérance email-absent (SMS seul).

---

## GAP PROD 2 — Files asynchrones (driver `database`, **sans Redis**) — Effort M

> À sécuriser AVANT toute campagne de masse. **Hostinger mutualisé = pas de Redis.** Bonne nouvelle : le projet est déjà configuré en `QUEUE_CONNECTION=database` et `CACHE_STORE=database` par défaut, table `jobs` + `cache` déjà migrées.

### Ce qui manque / à faire
- **Migration `failed_jobs`** (absente) — requise pour que `queue:work` enregistre les échecs.
- Passer en `ShouldQueue` (driver `database`) : web push, emails/SMS (Gap 1), `RecordRevenueShares` (M1), `AwardCampaignPoints` (M2). `GenerateQrCode` l'est déjà.
- **Dispatch `afterCommit`** partout. Listeners **idempotents** (rejeu = pas de double effet).
- **Cache (driver `database`/`file`, pas Redis)** : catalogue, catégories (déjà 5 min), **leaderboard** (clé invalidée à l'attribution de points). La *stratégie* de cache (clés, invalidation) est indépendante du driver.
- **Worker via cron** (worker persistant non garanti sur mutualisé) :
  ```
  * * * * * cd ~/domains/popupstore.ga/public_html/api && php artisan queue:work --stop-when-empty --max-time=55 >> storage/logs/worker.log 2>&1
  ```
  Documenter dans `CLAUDE.md` (section Déploiement). `markOrderAsPaid` **reste synchrone** (vidage panier).
- **⚠️ Vérif prod** : confirmer la valeur de `QUEUE_CONNECTION` dans le `.env` serveur. Si déjà `database` sans worker actif, les jobs `GenerateQrCode` s'empilent sans traitement (QR non générés). Le cron règle ce point.

### Tests
- Jobs effectivement mis en file (`Queue::fake`, assertion `ShouldQueue`).
- Idempotence sous double-livraison de webhook (un seul effet).

---

## GAP PROD 3 — Versioning API + durcissement campagne — Effort S/M

> À sécuriser AVANT toute campagne de masse.

- Préfixe **`/api/v1/`** sans casser les clients : groupe `v1` + conservation temporaire de `/api/*` (dépréciation documentée).
- **Idempotency-Key** sur `POST /api/v1/payments/initiate` (anti double-débit sur retry mobile money) : stocker clé + réponse, rejouer la même réponse.
- **Rate limiting dédié** checkout/paiement (séparé du 60/min global).
- **Authentifier le webhook** `/payments/callback` (clé partagée/signature) — cf. §0.1.
- Optionnel : doc OpenAPI (Scribe). Check-list montée en charge (leaderboard caché, index, N+1).
- Tests : rejeu idempotent `initiate`, non-régression routes `/api/*` legacy, webhook forgé rejeté.

---

## Ordre d'exécution recommandé
1. **Gap 2** (queues driver `database` + cron worker) — socle technique.
2. **Gap 1** (confirmation invité) — premier consommateur des queues.
3. **Module 1** (revenue split) — pré-requis payouts.
4. **Gap 3** (versioning + idempotency + webhook auth) — durcissement.
5. **Module 3** (self-service marchand) — s'appuie sur scope collection + payouts.
6. **Module 2** (campagne) — le plus lourd, isolé, en dernier.

## Critères d'acceptation (chaque PR)
- **Test-first** : plan de test en tête, tests avant le code, négatifs/sécurité + idempotence + bornes couverts.
- **Security by Design** : threat model en tête, default-deny + isolation requête, zéro confiance client sur montants/points/parts, webhook authentifié, pas de PII en logs/URL.
- `php artisan test` vert ; migrations MySQL-only guardées ; **aucune régression `CartServiceTest`**.
- Pint OK ; frontend `vitest` + `npm run build` OK.
- Invariants panier + `markOrderAsPaid` intacts ; nouveaux listeners idempotents + `afterCommit`.
- Montants entiers (reliquat Plateforme) ; aucune route marchand non scopée.
- ADR dans `docs/adr/` par module (décision + alternatives + **menaces traitées**).
