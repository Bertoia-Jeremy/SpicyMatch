# CLAUDE.md — SpicyMatch

## Règles dures
- Env : container PHP **`p8.5`** (PHP 8.5). Jamais p8.4. Préfixe : `docker exec -w /var/www/html/spicymatch p8.5 …`
- URL dev : `https://spicymatch.sf4.p85.dbm-local.com`. Tester p85 avant de conclure « vhost cassé ».
- **ZÉRO commentaire** dans le code (`//`, `/* */`, `#`, `{# #}`, prose docblock). Exceptions : annotations PHPStan (`@param/@return/@var/@throws`), attributs, demande explicite.
- Interlocuteur : dev senior, tutoiement, concis, reco tranchée. Incertitude > 20 % → `⚠️ Incertain :`. Signaler sécu, effets de bord, breaking changes.
- Pas de rétrocompatibilité (projet non en ligne). Pas de `schema:drop`/reload ni suppression de données curées (PreparationMethods, CookingTips) sans accord explicite.
- Liens markdown : chemins relatifs à la racine du workspace.
- Commits : Conventional Commits. `composer ci` obligatoire avant commit.
- Fin de session : reporter dans ce fichier toute décision, convention ou gotcha nouveau.

## Stack
Symfony 8.1 / PHP 8.5 · Doctrine ORM 3 (`schema:update`, pas de migrations) · MariaDB 10.4 · Messenger (Doctrine) · EasyAdmin 5.1 · Vich · KNP · UX 3 (Live Component, Turbo) · AssetMapper · Tailwind 4.2 (CLI) · Alpine 3.14 · FontAwesome 6.7.2 FREE self-hosté · Twig SSR (pas de SPA). Form theme : `templates/form/tailwind_layout.html.twig`.

## Commandes
```bash
docker exec -w /var/www/html/spicymatch p8.5 composer ci
docker exec -w /var/www/html/spicymatch p8.5 composer fix-cs
docker exec -w /var/www/html/spicymatch p8.5 composer phpstan
docker exec -w /var/www/html/spicymatch p8.5 composer rector-dry
docker exec -w /var/www/html/spicymatch p8.5 composer test-unit
docker exec -w /var/www/html/spicymatch p8.5 composer test-integration
docker exec -w /var/www/html/spicymatch p8.5 composer test-controller
docker exec -w /var/www/html/spicymatch p8.5 php bin/console doctrine:schema:update --dump-sql
docker exec -w /var/www/html/spicymatch p8.5 php bin/console doctrine:fixtures:load --append --group=X
yarn build
```
- `composer ci` = check-cs + phpstan + test-unit + schema-test + test-integration + test-controller + check-data.
- Env frais : DB `spicymatch_test` seedée (30 épices + `app:recompute:oav --sync --env=test`).
- `yarn build` après tout changement de classes Tailwind.
- Baseline PHPStan : `phpstan analyze --generate-baseline=phpstan-baseline.neon`, uniquement après un vrai fix.
- `schema:update` : lire `--dump-sql` avant `--force` (diff permanent préexistant `fat_ratio`/`water_ratio`).
- Import OAV : `app:check:compounds` → `app:import:{odt,flavordb,physical,acquisition-csv}` → `app:check:data [--strict]` → `app:recompute:oav --sync`. `app:validate:compounds [--apply]` et `app:fetch:pubchem` sont en ligne ; `--force` est dangereux.
- Après import massif de slugs : `app:slug:backfill`.

## Gotchas Symfony 8
- `#[Route]` = `Symfony\Component\Routing\Attribute\Route`. Un mauvais import échoue silencieusement, seul PHPStan le voit.
- Contraintes de validation en arguments nommés : `new Length(min: 6)`.
- `orderBy`, `addOrderBy` et `#[ORM\OrderBy]` prennent `\SortDirection::Ascending|Descending`.
- User courant : `#[CurrentUser] Users $user` (`?Users $user = null` sur route publique).
- Fonctions Twig : `#[AsTwigFunction]`.
- Interdit : `#[IsCsrfTokenValid]` (302 vers /login, faux 200 côté fetch). Garder `isCsrfTokenValid()` manuel (403 JSON ou flash+redirect).
- `#[Target('x')]` pour l'autowiring nommé.
- `MenuItem::linkTo(XxxCrudController::class, label, icon)` (`linkToCrud` supprimé).
- EasyAdmin : le Dashboard porte `#[AdminDashboard(routePath: '/admin', routeName: 'admin')]` et `config/routes/easyadmin.yaml` doit exister, sinon 500 sur tout l'admin. Garde-fou : `AdminCrudSmokeTest`.
- MariaDB : `trigger` est réservé, donc `name: 'trigger_type'`. `rank` backtické. Jointures : ManyToMany = `spices_id`, shadow `spice_active_compound` = `spice_id`.
- `Users::initializeTimestamps()` (PrePersist) gère `created_at`/`updated_at`. Un PreUpdate qui écrit un champ est ignoré.
- Appels HTTP externes : `catch ExceptionInterface`, pas seulement `TransportExceptionInterface`.
- Messenger Doctrine sur MariaDB : polling 60 s, donc le rebuild OAV n'est pas instantané.

## Tests
- Suites : Unit (Service, Entity, Enum, Gamification, MessageHandler, Twig, ValueObject, EventSubscriber), Integration (Integration, Repository), Controller. Tout nouveau répertoire sous `tests/` va dans une `<testsuite>` de `phpunit.dist.xml`.
- Un comportement, un owner : test exhaustif au niveau le plus bas (VO/service), câblage au-dessus (1 nominal + 1 erreur).
- ≥ 3 variantes de même forme → `#[DataProvider]` avec datasets nommés.
- Asserter l'état, pas le trajet. Exception : services de persistance à EM mocké.
- Repository avec JOIN/HAVING/SQL natif → test sur vraie DB. Zéro test d'accesseur pur.
- Classes `final` : vraie instance + dépendances mockées, pas de `createMock()`.
- PHPUnit 13 :
  - `createMock()` sans `expects()` → `createStub()` ou `#[AllowMockObjectsWithoutExpectations]`.
  - `with*()` sans `expects()` → `willReturnCallback()`.
  - `willReturn()` dans `setUp()` prime sur `willReturnCallback()` du test.
- Propriété private en test : `new \ReflectionProperty(Cls::class, 'f')->setValue($o, $v)`.
- Test DB sans pollution : `beginTransaction()` → insert → assert → `rollBack()` en `finally`.
- Slugs null sur groupes/composés/méthodes → 500 au rendu : `app:slug:backfill --env=test`.

## Architecture
MVC Symfony (Controller > Service > Repository > Entity), REST par controllers, pas d'API Platform. Admin EasyAdmin.

### Moteur OAV
- Doc : `ARCHITECTURE_MOTEUR_COMPATIBILITE.md`. Endpoint public `GET /api/match` (rate limit 30/min/IP, validation `is_numeric`+`is_finite`+plage AVANT cast → 400).
- Veto : candidat retenu ssi ≥ 1 composé OAV-actif partagé avec CHAQUE épice du mortier.
- Score : Tanimoto pondéré, `w = OAV>1 ? ln(OAV) : 0`. Le log est OBLIGATOIRE et vit dans `OavTanimotoScorer`, pas dans la shadow table. Toute modif du scorer passe par `GoldenPairingsTest`.
- Nernst (`OavPartitionCalculator`) s'applique en runtime avant le log.
- Shadow table `SpiceActiveCompound` : PK triple, sans FK, rebuild via `RecomputeOavTableHandler` (GET_LOCK sur la connexion, `DelayStamp` 60 s, `MAX_REBUILD_ATTEMPTS=3`). `--sync` retourne FAILURE sur abandon.
- La locale n'entre JAMAIS dans pipeline, scorer ou cache OAV, seulement dans l'hydratation finale des noms.
- Données actuelles fictives (tier D). Plan réel : `docs/PLAN_ACQUISITION_DONNEES.md`.

### Gamification
- `level = max(1, floor((xp/100) ** (1/1.3)))`. XP niveau L = `100 * L^1.3`.
- Sources XP : match +10, nouvelle vue d'épice +5, partie (≤ 60), badge (≤ 200). Easter eggs = badge-only, zéro XP direct.
- Nouveau badge = échelle XP badges R1–R5 : plafond 200, bandes common ≤ 25 / rare ≤ 50 / epic ≤ 100 / legendary ≤ 200, valeur = 0,25 × XP direct du palier, arrondie à 5. Fixtures et DB identiques sur (slug, icon, trigger, triggerValue, xpReward, rarity).
- Icône de badge : FA 6.7.2 FREE uniquement (les noms Pro/FA5 rendent un `<i>` vide sans erreur).
- 1 trigger = 1 evaluator (`App\Gamification\Evaluator\*`, tag, doublons refusés au compile).
- Handlers Messenger :
  - Gardes (`getOrCreateProgression` + `isGamificationEnabled`) AVANT `claim()`.
  - Corps dans `wrapInTransaction()` avec `lockForUpdate($progression)` EN PREMIER.
- `GamificationNotificationSubscriber` : gardes bon marché avant la requête, `MAX_PER_RESPONSE=20`. Index `idx_pgn_user_delivered` obligatoire.
- Easter eggs : aucun déclencheur front (`api_gamification_egg` jamais appelé). Câbler ou retirer.
- Avatar = badge équipé (`_avatar.html.twig`).

### Éducation
- 6 modes : QCM (route-based) + 5 Live Components.
- Cap dur quotidien : 2 sessions/mode en free, 5 en premium, via `GameSessionManager::withDailyQuota()` (transaction + `PESSIMISTIC_WRITE`).
  - Tout appelant (5 LC + QCM) DOIT attraper `\RuntimeException` : purger le secret de session, flasher `flash.daily_limit_reached`, rediriger sur `education_index`.
- XP : `MAX_XP_PER_SESSION=60` sur les deux branches. Un `overrideScore` = points de jeu, convertis par `convertGamePoints()` ; ne pas pré-appliquer le multiplicateur.
- Réponses correctes en session HTTP, jamais en `#[LiveProp]`. Aucune valeur scoring-critique en `LiveProp`, aucun `writable: true` dans les LC Education.
- `doFinish()`/`finishSession()` de LC : session vide (secret absent ou 0 réponse) → redirect sans persister.
- LC de jeu : `#[IsGranted('ROLE_USER')]` au niveau classe (`/_components/*` non préfixé).
- Intrus : sélection ordinale pure (intrus tiré d'abord, compatibles au-dessus de la coupure). Les 4 options passent par `AcademyManager::toOption()`. Jamais de localisation par entité sur les Spices du cache (détachées).
- QCM R1 : max unique atteint par la bonne réponse, distracteurs strictement en dessous ; pool à scores égaux → `null`.
- Survie : `compatibleCount` jamais dérivé d'un ratio × `optionCount`.
- Difficulté : jamais de changement automatique de `preferredDifficulty`, suggestion seulement (`SkillAssessor`).
- Sessions sans accuracy (`tracksAccuracy()` false, cas SURVIVAL) exclues des stats de précision.

### Duos / Lab
- `CookingMoment` enum PRE=0…PLATING=4 mappé sur la colonne `step`. Valeur hors 0–4 → 500 (garde `app:check:data`). Le libellé vient de `moment.label|trans`.
- `SpiceDuo` : un duo ne se crée que depuis deux tips existants de la même épice. Import : `app:import:spice-duos <fichier sous data/> [--dry-run]`, toujours `--dry-run` d'abord.
- Finalisation : `SpicyMatch::nextStep()` → `SpicyMatchService::start()` (un flush, sans dispatch) → redirect GET `finalize` (sans écriture, id d'historique).
- `edit_spicy_match_history` : écriture idempotente `{spiceId, kind, tipId}`, `wrapInTransaction` + `refresh(PESSIMISTIC_WRITE)`. Règle « un tip par épice et type » dans l'entité.
- XP au scellement : `sealed_at` + `markSealedIfComplete()`. `MatchSavedEvent` est dispatché dans la transaction. Les compteurs filtrent `sealedAt IS NOT NULL`.
- Propriété : `#[IsGranted(SpicyMatchHistoryVoter::OWNER, 'spicyMatchHistory')]`.
- Twig autoescape seul, jamais `|raw` sur un texte de duo.

### Recette finalisée (`view_spicy_match_history`)
- Source unique : `RecipeViewFactory::build()` → `RecipeView` (steps, moments, familles, OAV). Controller = voter + redirect non scellé + render. Jointure prep↔cooking↔duo dans `RecipeStepsBuilder`, jamais en Twig. Duo indexé par paire `prepId|cookId`.
- `preloadForRecipe()` (fetch-join tips/épices/groupes/translations, sauté en `fr`) obligatoire avant rendu. Plafond : `SpicyMatchHistoryViewTest` (en ≤ fr + 2).
- Param container `app.oav_context_enabled` (aussi global Twig) : false → matrice + cinétique jamais calculées.
- Favori = set-state idempotent : `set_favorite_spicy_match_history`, JSON `{favorite: bool}` + header `X-CSRF-Token`, `FavoriteToggledEvent` seulement sur false→true. Rename borné `TITLE_MAX_LENGTH=120` serveur.
- Données Alpine via `data-*` + `this.$root.dataset` (`x-data="recetteView"` sans args). Mode cuisine dans `<template x-if>`, fermé sur `turbo:before-cache`.
- Composants anonymes `templates/components/Recipe/` (SpiceHex, MomentHex, ChefWord), partials `templates/spicy_match_history/recipe/`, CSS `.recipe-*` dans `etamine-recipe-mobile.css`.
- Rate limit `RateLimitListener` : les chemins sont préfixés `/{_locale}`, la regex doit l'accepter.

### i18n (FR défaut / EN / ES)
- Toutes les URLs de contenu sont préfixées `/{_locale}`, sauf Root, Api, Admin, Security, Registration, Newsletter, Consent, EasterEgg, Locale. `LocaleSubscriber` résout la locale.
- Catalogues `translations/messages.{fr,en,es}.yaml`, plus les domaines `validators`, `admin`, `js`. Namespaces : common, form, flash, ui, gamification, enum. Les clés edu/lab/catalog sont SOUS `ui`.
- Entités traduisibles : pattern Translation Table (pas Gedmo). `getLocalizedXxx()` = COALESCE avec fallback FR, court-circuit `fr`. Colonne `reviewed=1` = jamais écrasée par `app:i18n:seed-translations` (`--overwrite` écrase tout ce qui n'est pas reviewed).
- Slugs : FR canonique sur l'entité, EN/ES sur `*_translation`. 301 via `CanonicalSlugTrait`. Le slug n'est jamais resynchronisé au rename.
- Collision de routes : `view_spice` a `priority:-10` + lookahead négatif. Ajouter une sous-section sous `/epices` = mettre à jour le lookahead.

### Accès
- Site ouvert en anonyme. `IsGranted` par MÉTHODE (pas par classe) sur `EducationController` et `SpicyMatchController`, sinon il prime sur l'`access_control`.
- Anonyme + clic jeu → pop-in `gate_login_modal` (event `gate-login`).

### RGPD / sécurité front
- Tout `<script>` inline DOIT porter `nonce="{{ csp_nonce('script') }}"`.
- ALTCHA : widget v3 avec attribut `challenge="<url>"`, sinon ignoré silencieusement. Champ caché `altcha` + contrainte `AltchaSolved`.
- ModSecurity 403 sur `/vendor/` → assets sous `public/lib/`, jamais `public/vendor/`.
- Zéro CDN tiers. Seuls tiers CSP : EthicalAds + Carbon.
- Anonymisation plutôt que hard delete (`UserAnonymizer`). Crons : `app:purge-expired-consents`, `app:gdpr:purge`.
- ⚠️ Placeholders légaux à compléter avant mise en ligne : `[NOM PRÉNOM]`, `[ADRESSE]`, `[SIREN]`, `[HÉBERGEUR]`, `VOTRE-DOMAINE` (robots.txt).

### Monétisation
- `Users.premiumUntil` + `isPremium()`. Pubs via `AdsExtension`, `ADS_ENABLED=false` par défaut.
- Interdits : interstitiel, footer, Étamine, LiveComponents, jeux en cours, formulaires, sticky. AdSense hors whitelist (CMP TCF requise).

## Design system
- Source : `assets/styles/app.css`. Tokens : `saffron`, `paprika`, `turmeric`, `cream`, `spice-surface`, `spice-border`.
- Jamais `orange-*`/`amber-*` natifs. Cartes `card-warm`, boutons `btn-pill-*`, tags `tag-*`, focus `focus:ring-saffron-600/30`.
- Inline style réservé aux couleurs dynamiques BDD.
- Tailwind v4 : pas de `tailwind.config.js`, utiliser `@source` dans le CSS. `--spacing: 0.25rem` dans `@theme`.
- `<twig:Button>` : `label` toujours `|trans`. Alpine : `x-bind:disabled`/`x-bind:class` (pas de shorthand `:`).
- `<img>` : `decoding=async` + `width`/`height` + `loading=lazy` (sauf LCP : `fetchpriority=high` + preload). Filtres Liip : `spice_thumb`, `spice_card`(+`_2x`), `spice_hero`. Fallback : `fa-seedling`.
- Labels Alpine `x-text` visibles au 1er paint : fallback SSR dans le span (anti-CLS).

## JS
- Alpine : `this.$root` (pas `this.$el`) pour lire les data-attrs depuis un handler d'enfant. Fetch : toujours `try/catch` + `response.ok`.
- Live Component :
  - `data-model` uniquement DANS le template du LC.
  - `debounce` natif via `data-model`.
  - État Alpine local : `data-live-ignore`.
  - Éviter les modales dans un LC (morphdom).
  - Anti double-clic : `x-data submitting` + `x-bind:disabled`.
- Turbo : `data-turbo="false"` sur les forms d'upload et de suppression de compte.

## CI connue
- `lint:twig` : `_macro_rarity.html.twig` en erreur préexistante (macro morte à nettoyer).
