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
- `composer ci` = check-cs + lint-twig + phpstan + test-unit + schema-test + test-integration + test-controller + check-data.
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
- Suites : Unit (Service, Entity, Enum, Gamification, MessageHandler, Twig, ValueObject, EventSubscriber, Security), Integration (Integration, Repository), Controller. Tout nouveau répertoire sous `tests/` va dans une `<testsuite>` de `phpunit.dist.xml`.
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
- Icône de badge : FA 6.7.2 FREE uniquement ET présente dans le subset (les noms absents rendent un `<i>` vide sans erreur). L'admin choisit parmi `IconSubsetManifest::solidChoices()`.
- 1 trigger = 1 evaluator (`App\Gamification\Evaluator\*`, tag, doublons refusés au compile).
- Handlers Messenger :
  - Gardes (`getOrCreateProgression` + `isGamificationEnabled`) AVANT `claim()`.
  - Corps dans `wrapInTransaction()` avec `lockForUpdate($progression)` EN PREMIER.
- `GamificationNotificationSubscriber` : gardes bon marché avant la requête, `MAX_PER_RESPONSE=20`. Index `idx_pgn_user_delivered` obligatoire.
- Easter eggs : aucun déclencheur front (`api_gamification_egg` jamais appelé). Câbler ou retirer.
- Avatar = badge équipé (`_avatar.html.twig`).
- Lecture de contenu (épice, composé, saveur) comptée UNIQUEMENT via `POST /api/gamification/read/{kind}/{id}` (`ContentKind`) : ticket HMAC `ContentReadTicket` (kind + user + id + issuedAt, ≥ `MIN_READ_SECONDS`=5, ≤ 24 h) émis par `content_read_ticket()` dans `partials/_content_read.html.twig`, envoyé par Alpine `contentRead` après 5 s visibles. Épice → `recordView` + `SpiceReadEvent` ; composé/saveur → `ContentReadEvent` (badge-only, zéro XP, idempotent via `UserStat.readContentKinds`). Jamais d'enregistrement au rendu d'une fiche.
- Badge `curious_mind` (trigger `ALL_CONTENT_KINDS_READ`) = un de chaque `ContentKind`. Nouveau type de contenu suivi = case `ContentKind` + include du partial + entité dans `ContentReadController`.
- Handlers qui écrivent `UserStat` : `em->refresh($stats)` APRÈS `lockForUpdate` (stats chargées avant le lock sinon lost update).
- Série = activité (tout event XP) : `recordActivityStreak()` dans `GamificationManager::process()` sous lock, avant les achievements. Colonnes BDD `*_reading_streak`/`last_read_date` conservées (seules les propriétés PHP renommées).
- Renommage d'une valeur d'enum persistée (`AchievementTrigger`…) : script `docs/sql/` appliqué AVANT le code, sinon hydratation → 500.
- `AROMATIC_GROUPS_VISITED` : atteint si `visités >= min(max(1, triggerValue), total groupes)`.
- Fixtures badges : `--group=achievements`.
- Gamification off ⇒ jeux fermés : `GameAccessVoter::PLAY` (redirect + flash `flash.gamification_disabled` côté `EducationController`, `#[IsGranted]` sur les 5 LC). Twig : `gamification_on()` seule source (true en anonyme), aucune trace XP/niveau/série affichée si false.

### Éducation
- 6 modes : QCM (route-based) + 5 Live Components.
- Cap dur quotidien : `GameSessionManager::MAX_DAILY_SESSIONS_FREE`=5 / `_PREMIUM`=10 sessions/mode (constantes publiques, relues par home et page premium, jamais de chiffre en dur dans les traductions → `%count%`), XP sur toutes les parties, via `GameSessionManager::withDailyQuota()` (transaction + `PESSIMISTIC_WRITE`).
  - Tout appelant (5 LC + QCM) DOIT attraper `\RuntimeException` : purger le secret de session, flasher `flash.daily_limit_reached`, rediriger sur `education_index`.
- XP : `MAX_XP_PER_SESSION=60` sur les deux branches. Un `overrideScore` = points de jeu, convertis par `convertGamePoints()` ; ne pas pré-appliquer le multiplicateur.
- Jeu du jour : `DailyChallengeResolver` (modes débloqués pour le niveau, rotation `GameDay::ordinal % count`, null si gamif off). Jour = `GameDay` (`ClockInterface` + param `app.timezone` = Europe/Paris, doit égaler le TZ PHP). Tests : `MockClock`.
- Bonus du jour : cap `MAX_XP_PER_SESSION` PUIS ×2, 1 fois/jour (1ʳᵉ session terminée sur le mode du jour, `hasDailyBonusSince` sur `finishedAt`), décidé sous lock user (`withDailyQuota` pour les LC, transaction explicite pour `finishSession` QCM). `GameSession.score` inclut le bonus (le « meilleur score » aussi).
- Réponses correctes en session HTTP, jamais en `#[LiveProp]`. Aucune valeur scoring-critique en `LiveProp`, aucun `writable: true` dans les LC Education.
- `doFinish()`/`finishSession()` de LC : session vide (secret absent ou 0 réponse) → redirect sans persister.
- LC de jeu : `#[IsGranted('ROLE_USER')]` au niveau classe (`/_components/*` non préfixé).
- Intrus : sélection ordinale pure (intrus tiré d'abord, compatibles au-dessus de la coupure). Les 4 options passent par `AcademyManager::toOption()`. Jamais de localisation par entité sur les Spices du cache (détachées).
- QCM R1 : max unique atteint par la bonne réponse, distracteurs strictement en dessous ; pool à scores égaux → `null`.
- Survie : `compatibleCount` jamais dérivé d'un ratio × `optionCount`.
- Difficulté : jamais de changement automatique de `preferredDifficulty`, suggestion seulement (`SkillAssessor`).
- Sessions sans accuracy (`tracksAccuracy()` false, cas SURVIVAL) exclues des stats de précision.
- Record perso (page résultat) : `GameSessionRepository::findBestScoreBefore()` (sessions terminées avant celle-ci, même mode). Affiché seulement si gamif on.

### Duos / Lab
- Composition : `SpicyMatchService::MIN_SPICES = 2` (serveur + boutons désactivés). `MortarIds::MIN_COUNT = 1` reste pour `/api/match`.
- Recherche Lab : `SearchNormalizer` (casse + accents + apostrophes, contient), noms localisés via `findAllSpices($locale)`.
- `CookingMoment` enum PRE=0…PLATING=4 mappé sur la colonne `step`. Valeur hors 0–4 → 500 (garde `app:check:data`). Le libellé vient de `moment.label|trans`.
- `SpiceDuo` : un duo ne se crée que depuis deux tips existants de la même épice. Import : `app:import:spice-duos <fichier sous data/> [--dry-run]`, toujours `--dry-run` d'abord.
- Finalisation : `SpicyMatch::nextStep()` → `SpicyMatchService::start()` (un flush, sans dispatch) → redirect GET `finalize` (sans écriture, id d'historique).
- `edit_spicy_match_history` : écriture idempotente `{spiceId, kind, tipId}`, `wrapInTransaction` + `refresh(PESSIMISTIC_WRITE)`. Règle « un tip par épice et type » dans l'entité.
- XP au scellement : `sealed_at` + `markSealedIfComplete()`. `MatchSavedEvent` est dispatché dans la transaction. Les compteurs filtrent `sealedAt IS NOT NULL`.
- Propriété : `#[IsGranted(SpicyMatchHistoryVoter::OWNER, 'spicyMatchHistory')]`.
- Twig autoescape seul, jamais `|raw` sur un texte de duo.
- Préremplissage Lab : `?spice=<slug localisé>` → `SpicyMatch::mount(initialSpiceId)` (ignoré si supprimée ou exclue par l'user).
- Fiche épice : rail « Se marie avec » (top `AcademyManager::findCompatibleSpices`, actives via `findActiveByIdsInOrder`, 4) + CTA Lab, puis rail famille hors compatibles. Carte commune `spices/_rail_card.html.twig`.

### Recette finalisée (`view_spicy_match_history`)
- Source unique : `RecipeViewFactory::build()` → `RecipeView` (steps, moments, familles, OAV). Controller = voter + redirect non scellé + render. Jointure prep↔cooking↔duo dans `RecipeStepsBuilder`, jamais en Twig. Duo indexé par paire `prepId|cookId`.
- `preloadForRecipe()` (fetch-join tips/épices/groupes/translations, sauté en `fr`) obligatoire avant rendu. Plafond : `SpicyMatchHistoryViewTest` (en ≤ fr + 2).
- Param container `app.oav_context_enabled` (aussi global Twig) : false → matrice + cinétique jamais calculées.
- Favori = set-state idempotent : `set_favorite_spicy_match_history`, JSON `{favorite: bool}` + header `X-CSRF-Token`, `FavoriteToggledEvent` seulement sur false→true. Rename borné `TITLE_MAX_LENGTH=120` serveur.
- Données Alpine via `data-*` + `this.$root.dataset` (`x-data="recetteView"` sans args). Mode cuisine dans `<template x-if>`, fermé sur `turbo:before-cache`.
- Composants anonymes `templates/components/Recipe/` (SpiceHex, MomentHex, ChefWord), partials `templates/spicy_match_history/recipe/`, CSS `.recipe-*` dans `etamine-recipe-mobile.css`.
- Rate limit `RateLimitListener` : les chemins sont préfixés `/{_locale}`, la regex doit l'accepter.

### i18n (FR défaut / EN / ES)
- Toutes les URLs de contenu sont préfixées `/{_locale}`, sauf Root, Robots, Api, Admin, Security, Registration, Newsletter, EasterEgg, Locale. `LocaleSubscriber` résout la locale et ne touche la session que si `hasPreviousSession()` (sinon toute réponse anonyme devient `private`) ; la locale Accept-Language n'est pas persistée.
- Catalogues `translations/messages.{fr,en,es}.yaml`, plus les domaines `validators`, `admin`, `js`. Namespaces : common, form, flash, ui, gamification, enum. Les clés edu/lab/catalog sont SOUS `ui`.
- Entités traduisibles : pattern Translation Table (pas Gedmo). `getLocalizedXxx()` = COALESCE avec fallback FR, court-circuit `fr`. Colonne `reviewed=1` = jamais écrasée par `app:i18n:seed-translations` (`--overwrite` écrase tout ce qui n'est pas reviewed).
- Slugs : FR canonique sur l'entité, EN/ES sur `*_translation`. 301 via `CanonicalSlugTrait`. Le slug n'est jamais resynchronisé au rename.
- Lookup slug : `LocalizedSlugLookupTrait` (repos) résout slug locale → slug FR → slug d'une autre locale → id numérique ; le controller 301 vers le slug canonique. Filtres `index_spices` (`aromatic_group`, `spicy_type`) : id ou slug étranger → 301.
- Catalogue = routes localisées Symfony : préfixes classe dans `App\Routing\CatalogPath` (segments traduits fr/en/es, kebab-case). Noms internes `xxx.fr|en|es` : lire `_canonical_route ?? _route`, jamais `_route` seul. `path('xxx')` suit la locale courante, `{_locale: 'en'}` choisit la variante.
- Collision de routes : `view_spice` a `priority:-10` + `CatalogPath::SPICE_SLUG_REQUIREMENT` (lookahead sur les segments des 3 langues). Nouvelle sous-section sous `/epices` = l'ajouter au lookahead ET à `spiceSubsections()` (garde `CatalogRoutingTest`).
- Canonical/hreflang/switcher : `locale_alternates(absolute)` (lit `hreflang_slugs` du contexte). `switch_locale` redirige vers `?target=` si chemin interne (`/`, pas `//`, pas de `\` ni caractère de contrôle), sinon `home`. Plus de réécriture du referer.
- Index catalogue EN/ES : `findAllForLocale($locale)` (fetch-join traductions, anti N+1) ; `SpicesRepository::findFiltered(..., $locale)` idem. Composants anonymes `templates/components/Catalog/` (Header, Grid, Card, Hero, Section, LinkedSpices), couleur BDD validée hex avant inline.

### SEO
- Sitemap : `SitemapSubscriber` = routes statiques + `DETAIL_ROUTES` (classe → repo `SitemapSourceInterface::findSitemapRows()`, 1 requête scalaire/entité via `LocalizedSlugLookupTrait`). 1 `<url>` par locale, 3 alternates + x-default fr, `lastmod` = `updated_at`, slug traduit absent → slug FR. Cache public 1 h (Presta), qui dépend de l'absence de session.
- `/robots.txt` = `RobotsController` (hors locale, sitemap en URL absolue). Jamais de `public/robots.txt`.
- JSON-LD : `SchemaProviderInterface` (autotag, `supports(?object)`/`build`) agrégés par `json_ld(subject)` (`@graph` si plusieurs). Rendu UNIQUEMENT via `partials/_json_ld.html.twig` (nonce) ; `Markup` encodé `SeoExtension::JSON_FLAGS` (HEX_*), jamais de `|raw`. Home = `WebSite` + `SearchAction` (`search_results?q=`), fiches catalogue = `BreadcrumbList`, épice = `Thing` (+ image `spice_hero`). Pas de `Recipe` (recettes privées).
- `base.html.twig` : blocks `meta_description` (fiches : `getLocalizedDescription(loc)|seo_summary`, 160 car.), `og_image`, `structured_data` ; OG title/description relus depuis les blocks.
- Pages privées : `#[NoIndex]` (classe ou méthode) → `X-Robots-Tag: noindex, nofollow` via `NoIndexSubscriber`. En dev/test Symfony pose déjà `X-Robots-Tag: noindex` partout (`disallow_search_engine_index`), les tests comparent la directive exacte.
- Font Awesome = subset versionné `public/lib/fontawesome-subset/` (CSS élaguée + woff2). Toute nouvelle icône (template, JS, PHP, BDD) : `php bin/console app:icons:list` (+ `--env=test --output=var/fontawesome-tokens.test.json`) puis `yarn icons:subset var/fontawesome-tokens.json var/fontawesome-tokens.test.json`. Garde : `FontAwesomeSubsetTest`. Classes FA construites dynamiquement interdites (le scan ne les voit pas).
- Build prod : `app:icons:list` sur la BDD prod + `yarn icons:subset`, `yarn build`, `php bin/console asset-map:compile`.

### Homepage
- Narration : hero (promesse + démo) → méthode → Expérimenter → Apprendre → Jouer (si `gamification_on()`) → Valeurs + soutien → CTA final. Partials `templates/home/_*.html.twig`, clés `ui.home.*` + `ui.toile.*`. CTA primaire = Lab pour tous (invités inclus), jamais l'inscription.
- Démo hero : `HomeController::DEMO_SPICE_SLUGS` (slugs FR, doivent exister dans le seed test) → `SpicesRepository::findDemoCards($slugs, $locale)`. Alpine `homeDemo` appelle `/api/match?spices=id&limit=3` avec header `Accept-Language` (API hors locale) ; libellés d'affinité passés en `data-labels`.
- Message : savoir 100 % gratuit, jeux = fonctionnalité, premium = soutien + confort. Interdits : faux témoignages, compteurs/chiffres inventés, compteur d'utilisateurs, promesse d'un savoir réservé au premium.
- Fiches gestes : classes `technique-*` (jamais `recipe-*` dans `home.css`, importé globalement → collision avec la vue recette).
- `home.css` mobile first : styles de base = 375 px, puis `min-width` 360/480/640/768/1024/1100/1280, jamais de `max-width`. Hover sous `@media (hover: hover)`. Rails horizontaux scroll-snap en mobile (chips de démo, fiches gestes) ; maquette Lab masquée sous 1100 px ; toile réordonnée via `.solar-side { display: contents }`.
- Barre CTA collante mobile `_sticky_cta` (Alpine `homeStickyCta`, visible entre le CTA hero et `.home-final`, cachée ≥ 768 px) : pose `html.home-sticky-on`, qui remonte le bouton retour-haut via `.scroll-top-anchor` (footer). État recalculé depuis les rects à chaque callback IO (hero + final + footer observés), jamais depuis `entry.isIntersecting` (un saut de scroll ne franchit aucun seuil).
- Largeur desktop : contenu 1216 px aligné sur la navbar (`max-w-7xl` + padding), via `--home-max`/`--home-gutter` (16/24/32) et `padding-inline: max(gutter, (100% - max) / 2)` sur `.home-section`, `.home-hero-grid`, `.home-ad-band`. Fonds pleine largeur, jamais de `max-width` sur la section.

### Footer
- `components/_footer.html.twig` : macro `footer_link`, conteneur identique à la navbar, classes `site-footer-*` dans `footer.css`, zéro style inline. Colonnes : marque + engagements + soutien, Explorer, Cuisiner & jouer (Académie si `gamification_on()`), Mon espace (onglets profil ou inscription). Liens légaux avec `padding-inline-end` sous 1400 px pour ne pas passer sous le bouton retour-haut.

### Navbar
- `<div x-data="navMenu" class="site-nav-root">` en `display: contents` : sinon le sticky de `.site-nav` n'a aucune course (wrapper de même hauteur) et la barre défile. Hangman masque via `body.has-hangman > .site-nav-root`.
- Backdrop et panneau unique `#nav-panel` (tiroir droit mobile, overlay ≥ lg, classe `is-open`) sont FRÈRES de `<nav>` : `backdrop-filter` crée un containing block pour les `fixed`.
- Logo en centrage absolu, `--nav-height` 4rem / 5rem ≥ 64rem (`theme.css`), consommé par Lab, pendu, recette mobile. Z : nav 50, panneau 49, backdrop 48, carte profil 55, gate 60.
- Barre : CTA « Composer » (Lab) pour tous, connexion secondaire, jamais l'inscription. Panneau = logo complet (mobile seul) + recherche + carte Lab (ligne compacte < lg : icône + titre + lien, carte entière cliquable via `::after` de `.nav-lab-cta`) + 6 liens savoir (même liste que la colonne « Explorer » du footer, 2 × 3 ≥ lg) + lien Académie (`nav-item` standard, dernier, sous la colonne de gauche ≥ lg, si `gamification_on()`) + pied (aide, contact, espace, langues). Interdits dans le panneau : connexion/inscription, liens vers les jeux individuels (réservés aux connectés), titres de section numérotés. Garde : `NavbarRenderingTest`.
- Pilule profil `[data-tour=nav-profile]` : niveau en chiffre seul < lg, « Niv. N » ≥ lg, nom + grade ≥ xl. Micro-barre décorative, la `progressbar` vit dans la carte. Gamif off : avatar seul.
- Scroll lock = `html.nav-locked body { overflow: hidden }` + `inert` main/footer. Jamais `overflow` sur `html` (casse le sticky). `scrollbar-gutter: stable` sur `html`.
- Gotcha : le CSS Font Awesome n'est pas en layer, `.fa-solid { display: inline-block }` bat `@layer components`. Masquer une icône = masquer un `<span>` enveloppant.
- Mesures Playwright headless : `ignoreDefaultArgs: ['--hide-scrollbars']`, sinon le centrage paraît décalé de ~7 px (`scrollbar-gutter`).
- Grade = `UserProgression::getGrade()` → `ChefGrade::fromLevel()` (seuils 20/50/80), libellé `grade.label|trans`. Jamais de seuils en Twig.
- Sélecteur de langue : `app.enabled_locales` + `loc|locale_name(loc)`, jamais de liste en dur. `aria-current` via `{% if %}`, pas d'attribut interpolé.
- Logout CSRF (`enable_csrf: true`, token stateless `logout`) : lien = `logout_path()` uniquement, jamais `path('app_logout')` (→ 403). Logout programmatique = `Security::logout(false)` (cf. `UsersController::delete`, garde `AccountDeletionTest`).
- Blur barre : `@supports` combiné `backdrop-filter` ET `color-mix`, fond plein sinon.

### Accès
- Site ouvert en anonyme. `IsGranted` par MÉTHODE (pas par classe) sur `EducationController` et `SpicyMatchController`, sinon il prime sur l'`access_control`.
- Anonyme + clic jeu → pop-in `gate_login_modal` (event `gate-login`).
- Lab anonyme : composer/finaliser/voir sans compte. Renommer et favori = connectés : 401 JSON en anonyme (route laissée publique dans `access_control` pour éviter le 302), titre non éditable côté invité, clic → pop-in `gate-login` (`context: 'rename'`). `SpicyMatch.user` nullable ; propriété invité = ids en session (`GuestHistoryRegistry`, max 20) lue par `SpicyMatchHistoryVoter`. Favori anonyme → 401 + favori en attente (pas de titre en attente) → pop-in `gate-login` (`context: 'favorite'`). `GuestHistoryClaimSubscriber` (LoginSuccessEvent, login ET inscription) rattache les mélanges, applique les favoris en attente, dispatche `MatchSavedEvent` des scellés. Aucun XP en invité. Purge invités > 7 j dans `app:gdpr:purge`. Nouvelle route history publique = l'ajouter à la regex `access_control`.
- LiveAction réservée aux connectés : jamais `denyAccessUnlessGranted` (302 /login perd la cible), `redirectToRoute('app_login', ['target' => …])`.
- Onboarding (anonymes inclus, zéro état serveur) :
  - État en localStorage `sm_tours = {clé: version}` (`'*'` = tout ignoré via « Je connais déjà »). Lecture/écriture toujours en try/catch. « Revoir les tutos » (profil) purge la clé.
  - Tour de page = `<twig:Tour:Page name version :steps next transition />` dans le template de la page, hors LiveComponent. Étape = `{target, key, position?, advance?}` ; `target` vise un `data-tour="…"`, textes `ui.onboarding.tours.{name}.{key}.{title,text}`. Bumper `version` réaffiche le tour.
  - `pageTour` écarte les étapes sans cible visible, marque vu à l'affichage effectif de la 1ʳᵉ étape, se coupe sur `gate-login`/`turbo:before-cache`, attend `account-created-closed`. Étapes conditionnelles (XP, profil) filtrées en Twig.
  - Welcome modal : incluse dans `base` seulement si `_route == 'home'` (hors `<main>`, sinon `inert` la neutralise).
  - E2E : `PW_CHANNEL=chrome yarn e2e page-tour` (chromium Playwright local désynchronisé).

### RGPD / sécurité front
- Tout `<script>` inline DOIT porter `nonce="{{ csp_nonce('script') }}"`.
- ALTCHA : widget v3 avec attribut `challenge="<url>"`, sinon ignoré silencieusement. Champ caché `altcha` + contrainte `AltchaSolved`.
- ModSecurity 403 sur `/vendor/` → assets sous `public/lib/`, jamais `public/vendor/`.
- Zéro CDN tiers. Seuls tiers CSP : EthicalAds + Carbon.
- CSP `script-src` : `data:` requis (AssetMapper aliase `live.min.css` en shim `data:application/javascript`), ne pas le retirer. Carbon exige `cdn.carbonads.com` + `srv.carbonads.net`.
- Anonymisation plutôt que hard delete (`UserAnonymizer`). Cron : `app:gdpr:purge`. Aucune bannière cookies : seuls cookies strictement nécessaires + localStorage fonctionnel (stack consent supprimée, table `cookie_consent` droppée).
- ⚠️ Placeholders légaux à compléter avant mise en ligne : `[NOM PRÉNOM]`, `[ADRESSE]`, `[SIREN]`, `[HÉBERGEUR]`.

### Monétisation
- `Users.premiumUntil` + `isPremium()`. Pubs via `AdsExtension`, `ADS_ENABLED=false` par défaut.
- Interdits : interstitiel, footer, hero et CTA final de la home, Étamine, LiveComponents, jeux en cours, formulaires, sticky. AdSense hors whitelist (CMP TCF requise).
- Zones autorisées : index Académie (slot `.poster-grid-ad` pleine largeur entre 3ᵉ et 4ᵉ jeu) + résultat, fiche épice, index catalogue (`<twig:Catalog:Grid>` inclut le slot, `ads=false` pour l'omettre), liste historique, home (2 bandes `home/_ad_band.html.twig` : après Expérimenter, puis avant Valeurs avec `secondary: true`, rendue seulement si `ads_multi_slot()`, Carbon = 1 pub/page via `AdsExtension::SINGLE_SLOT_PROVIDERS`). Jamais premium ni vue recette (garde `AdSlotPlacementTest`).
- Slot `partials/_ads_banner.html.twig` : zéro `<script>` SSR. Alpine `adSlot` charge le script régie au 1er passage dans le viewport (`rootMargin 200px`) depuis `AD_SCRIPTS` (URL en dur côté JS, jamais depuis un `data-*`). EthicalAds `data-ea-manual` + `ethicalads.load()` ; Carbon = `<script id="_carbonads_js">` enfant du slot, vidé sur `turbo:before-cache`.

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
- `lint-twig` (`lint:twig templates --show-deprecations`) fait partie de `composer ci`.
