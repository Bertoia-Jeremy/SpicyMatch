# CLAUDE.md — SpicyMatch

> **⚠️ RÈGLE — MAINTIEN À JOUR** : ce fichier est la source de vérité du projet. Toute décision architecturale, nouvelle entité/service, convention ou gotcha découvert doit y être reporté en fin de session.

## Règles d'interaction

- Interlocuteur : développeur senior. Tutoiement, direct, concis, pas d'intro/conclusion de politesse. Recommandation tranchée quand plusieurs options.
- **🚫 ZÉRO commentaire dans le code (RÈGLE DURE)** : ni `//`, `/* */`, `#`, ni `{# #}` Twig, ni prose dans les docblocks. Seules exceptions : annotations requises par PHPStan (`@param`/`@return`/`@var`/`@throws`), attributs PHP, ou demande explicite. Le *pourquoi* s'explique en chat/commit, jamais dans le code.
- Blocs de code toujours avec langage ; diffs ciblés plutôt que fichiers complets ; chemins complets et commandes copiables.
- Signaler explicitement implications sécu, effets de bord, breaking changes.
- Incertitude > 20 % → préfixer `⚠️ Incertain :`. Réponse dépendante du contexte → UNE question ciblée.

## Contexte Projet

```yaml
project: spicymatch
description: Matching aromatique (épices, composés, groupes aromatiques, méthodes de préparation) + espace utilisateur, gamification, back-office admin.

stack:
  backend:
    - Symfony 7.4 / PHP 8.4
    - Doctrine ORM 3.x (attributs, schema:update — PAS de migrations)
    - Symfony Messenger (transport Doctrine, async)
    - Symfony Security (LoginFormAuthenticator custom)
    - EasyAdmin 5.1 # ⚠️ MenuItem::linkToCrud() SUPPRIMÉ → MenuItem::linkTo(XxxCrudController::class, label, icon)
    - Vich Uploader 2.x, KNP Paginator 6.x, Mailer + Notifier
    - Twig 3.x + Symfony UX (Live Component, Turbo, Twig Component)
    - AssetMapper (pas de Webpack/Encore)
  frontend:
    - Tailwind CSS 4.2.x (CLI direct, pas de PostCSS)
    - Alpine.js 3.14.9 (importmap/AssetMapper)
    - FontAwesome 6.7.2 self-hosté (public/lib/fontawesome/) — PAS de CDN
    - SSR Twig + hydratation Alpine.js, pas de SPA
  form_theme: templates/form/tailwind_layout.html.twig

database:
  - MariaDB 10.4 (Docker, DSN: mysql://root:root@mysql:3306/spicymatch)
  - trigger = mot réservé MariaDB → name: 'trigger_type' dans #[ORM\Column]

package_managers: Composer (composer.lock) + Yarn (yarn.lock)

tooling:
  - ECS (PSR-12) / PHPStan niveau 6 (baseline phpstan-baseline.neon) / ESLint 9 flat config
  - Rector (SYMFONY_72, CODE_QUALITY, CONSTRUCTOR_INJECTION)
  - PHPUnit 13.0.5 + BrowserKit, Doctrine Fixtures (groupes nommés)

scripts:
  php:
    - docker exec -w /var/www/html/spicymatch p8.4 composer ci              # ⭐ check-cs + phpstan + test-unit — OBLIGATOIRE avant commit
    - docker exec -w /var/www/html/spicymatch p8.4 composer fix-cs
    - docker exec -w /var/www/html/spicymatch p8.4 composer rector-dry / rector
    - docker exec -w /var/www/html/spicymatch p8.4 composer phpstan
    - docker exec -w /var/www/html/spicymatch p8.4 composer test-unit
    - docker exec -w /var/www/html/spicymatch p8.4 php vendor/bin/phpunit --testsuite=Integration  # DB requise
    # Baseline après vrai fix : phpstan analyze --generate-baseline=phpstan-baseline.neon
  js:
    - yarn dev    # watch Tailwind
    - yarn build  # build minifié — à relancer après tout changement de classes
  moteur_oav:
    - docker exec -w /var/www/html/spicymatch p8.4 php bin/console app:import:odt
    - docker exec -w /var/www/html/spicymatch p8.4 php bin/console app:import:flavordb
    - docker exec -w /var/www/html/spicymatch p8.4 php bin/console app:import:physical
    - docker exec -w /var/www/html/spicymatch p8.4 php bin/console app:check:compounds [--strict]
    - docker exec -w /var/www/html/spicymatch p8.4 php bin/console app:check:data [--strict]
    - docker exec -w /var/www/html/spicymatch p8.4 php bin/console app:validate:compounds [--apply]  # ONLINE PubChem
    - docker exec -w /var/www/html/spicymatch p8.4 php bin/console app:recompute:oav --sync          # après TOUT import
    # Séquence import : check:compounds → import:* → check:data → recompute:oav --sync
  doctrine:
    - docker exec -w /var/www/html/spicymatch p8.4 php bin/console doctrine:schema:update --force
    - docker exec -w /var/www/html/spicymatch p8.4 php bin/console doctrine:fixtures:load --append --group=GroupName

conventions:
  commits: Conventional Commits (feat/fix/chore/refactor + scope optionnel)
  php: PSR-12, short arrays, attributs PHP 8+ (pas d'annotations)
  testing:
    suites: Unit (tests/Service|Entity|Enum|Gamification|MessageHandler|Twig), Integration (tests/Integration, DB), Controller
    gotchas:
      - Classes final → pas de createMock() → vraie instance + dépendances mockées (ex AchievementChecker)
      - PHPUnit 13 : createMock() sans expects() = notice → createStub() OU #[AllowMockObjectsWithoutExpectations]
      - PHPUnit 13 first-wins : willReturn() dans setUp() prime sur willReturnCallback() du test → pas de stubs globaux en setUp() si override nécessaire
      - Propriétés private en test Entity : new \ReflectionProperty(Cls::class, 'field')->setValue($obj, $val)

architecture:
  pattern: MVC Symfony (Controller > Service > Repository > Entity), REST via controllers (pas d'API Platform), admin EasyAdmin

  gamification:
    entities:
      - UserProgression (xp, level computed infini, OneToOne Users, gamificationEnabled, equippedBadge, totalSpicesRead, streaks, lastReadDate, discoveries)
      - Achievement (slug, trigger_type enum, triggerValue, xpReward, rarity enum, easterEggSlug nullable)
      - UserAchievement (UserProgression <-> Achievement, unlockedAt)
      - AchievementProgress (user, achievement, progress, isCompleted property hook)
      - PendingGamificationNotification (user, type, payload json, deliveredAt nullable) — file Turbo Streams
      - SpiceView (user, spice, viewedDay — unique/jour)
      - UserStat (OneToOne Users — compteurs, visitedAromaticGroups json, lastVisitedSpices json FIFO 10)
      - GameSession (gameMode/difficulty enums, score, correctAnswers, totalQuestions, durationSeconds, accuracy/isFinished computed)
      - GameQuestion (session, questionIndex, questionData json, answerGiven, isCorrect, timeSpentMs)
    enums:
      - AchievementTrigger (FIRST_MATCH, N_MATCHES, N_SPICES_USED, FIRST_DISCOVERY, N_FAVORITES, SPICE_READ, READING_STREAK, EASTER_EGG_FOUND, ALL_TERPENES_VISITED, FIRST_GAME, N_GAMES_COMPLETED)
      - AchievementRarity COMMON/RARE/EPIC/LEGENDARY → labels Graine/Infusion/Extraction/Essence
      - GameMode QCM/SURVIVAL/GUESS_WHO/INTRUS/HANGMAN/CHRONO — isEnabled(), label(), xpPerCorrect(), isLiveComponent(), totalQuestions(). Section navbar "L'Académie"
      - GameDifficulty EASY/MEDIUM/HARD — xpMultiplier() 1.0/1.5/2.0
    level_formula: "level = floor((xp / 100) ** (1 / 1.3)) + 1"
    xp_sources: "match_saved +10, spice_read +5 (nouvelle vue), easter_egg +75 défaut, game_completed variable, achievement_reward variable"
    fixtures:
      - "21 achievements en DB. ⚠️ INSERT IGNORE SQL direct uniquement (fixtures Doctrine purgent). Colonne trigger_type, valeurs enum en minuscules."
    services:
      - GamificationManager (orchestrateur, Strategy pattern, guard opt-out) + GamificationManagerProxy (anti-injection circulaire) + NullGamificationManager + GamificationManagerInterface (App\Gamification\)
      - AchievementChecker (final — injecter AchievementRepository, pas mocker)
      - EasterEggService
      - Handlers Messenger async : GamificationHandler (match_saved), FavoriteGamificationHandler (idempotent via SpicyMatchHistoryRepository), SpiceReadGamificationHandler, EasterEggGamificationHandler
    xp_strategies: MatchXpStrategy / SpiceReadXpStrategy (context isNewView) / EasterEggXpStrategy (context xpAmount) — tag gamification.xp_strategy, !tagged_iterator
    event_subscriber: GamificationNotificationSubscriber (RESPONSE prio -10) — injecte Turbo Streams avant </body>
    routes:
      - POST /api/gamification/egg/{slug} (CSRF easter_egg via X-CSRF-Token)
      - POST /users/gamification/toggle (CSRF toggle_gamification)
      - POST /users/badge/equip/{id} (CSRF equip_badge_{id})
    avatar: "L'avatar EST le badge équipé (UserProgression::$equippedBadge) : icône + couleur rareté. Composant templates/components/_avatar.html.twig, param equippedBadge (UserAchievement|null). Couleurs : common #f5f5f4/#78716c, rare #dbeafe/#1d4ed8, epic #f3e8ff/#7e22ce, legendary #fef9c3/#a16207."

  education:
    description: "6 jeux : QCM route-based + 5 Live Components (IntrusGame, SurvivalGame, GuessWhoGame, HangmanGame, ChronoGame)"
    services:
      - GameSessionManager  # sessions, XP, limite 5/jour/mode, createFinishedSession() pour LC
      - AcademyManager      # logique de jeu (compatibilité, intrus, cartes, questions), cache pool academy.cache TTL 1h
      - QcmQuestionGenerator (QuestionGeneratorInterface)
    event_listener: AcademyCacheInvalidator (Doctrine post* sur Spices → invalide academy.spice_cards + academy.intruders.{id})
    routes:
      - "GET /education/ (index PUBLIC), POST /education/start (CSRF education_start), GET /education/play/{id}, POST /education/answer/{id} (CSRF education_answer) — QCM"
      - "GET /education/play-live/{mode} — LC. GET /education/result/{id} — multi-mode"
    anti_farming: "5 sessions/jour/mode, XP × 0.5 après la 3e"
    gamification_event: GameCompletedEvent via Messenger
    cache: "pool academy.cache (filesystem, 3600s), clés academy.spice_cards + academy.intruders.{spiceId}, bind services.yaml $cache"
    regles:
      - "Réponses correctes en session HTTP (game_{token}), JAMAIS en #[LiveProp] (sérialisé client)"
      - "LC = createFinishedSession(), pas de GameQuestion rows"
      - "SpicesRepository::findIncompatibleWith() : SQL NOT EXISTS 4 subqueries (main/sec × main/sec)"

  moteur_oav:
    description: "Compatibilité aromatique par OAV (Odor Activity Values). Référence : ARCHITECTURE_MOTEUR_COMPATIBILITE.md"
    algorithmes:
      - "Algo 1 Veto : candidat retenu ssi ≥ 1 composé OAV-actif partagé avec CHAQUE épice du mortier (JOIN+HAVING)."
      - "Algo 2 Score : Tanimoto pondéré OAV LOG-COMPRESSÉ. w_i = OAV_i>1 ? ln(OAV_i) : 0. S = Σmin/Σmax ∈ [0,1], affiché ×100 floor."
      - "⚠️ LOG obligatoire : OAV brut ~6 ordres de grandeur → Tanimoto linéaire = candidats à 0%. Clamp 0 sous OAV=1 (seuil van Gemert). OAV = concentration_ppm / odt_ppm."
      - "Algo 3 : OavPartitionCalculator — Nernst (partition gras/eau, K_ow=10^logP) × décroissance thermique exp(-k(T)·t), runtime AVANT le log, skip si contexte neutre."
    contexte_culinaire:
      - "CulinaryContext VO readonly : matrix + fatRatio + waterRatio + cookingTimeMin + temperatureCelsius. Bornes = constantes publiques du VO (source unique API/UI). signature()/signatureHash() = clé cache. isCustom()/getLabel()/getIcon()."
      - "OdtMatrix enum air/water/oil. AromaKinetics HEAD(<150°C)/HEART(150-250)/BASE(>250) selon point d'ébullition."
    endpoint:
      - "GET /api/match?spices=id1,id2&limit&matrix&fat&water&cooking_time&temperature — PUBLIC, rate limit 30/min/IP sliding window. Validation is_numeric+is_finite+plage AVANT cast → 400."
      - "Réponse : mortar, results[{id,name,score}], oav_mode (false = fallback présence), contexte, confidence, confidence_tier, count"
    entites:
      - "SpiceActiveCompound — shadow table (spice_id, aromatic_compound_id, matrix, oav_value). PK composite TRIPLE, 4 indexes, PAS de FK (RENAME). Rebuild 3 passes en transaction."
      - "AromaticCompound (name, cas_number UNIQUE, formula, soft-delete). SpiceCompoundConcentration (ppm DECIMAL(14,4), PK composite). CompoundOdt (odt_ppm DECIMAL(14,8), PK (compound, matrix)). CompoundPhysical (logP, bp, vp — OneToOne)."
      - "SpicyMatch persiste le contexte culinaire (getCulinaryContext()/setCulinaryContext())."
      - "⚠️ CHECK (oav_value > 1) non émis par Doctrine → enforced par WHERE du INSERT + app:check:data."
    services:
      - "MatchPipeline — orchestrateur 7 étapes (profil → veto → hydratation → correction Nernst → Tanimoto log → tri). NON-final (mock)."
      - "OavPartitionCalculator (readonly) — needsCorrection(), correctionFactor(), effectiveOav(). K_AT_BOILING=0.1/min, T_INERT=50°C."
      - "MortarProfileBuilder — cache profil par (matrix, mortier), sentinel [] TTL 5min si vide, invalidateAll() après rebuild."
      - "OavTanimotoScorer — log-compressé, O(N). MatrixComparator — 3× pipeline + cache insights. CookingTimelineBuilder — HEAD/HEART/BASE + rétention."
      - "MatchConfidenceAssessor (readonly, NON-final) — DataConfidence = maillon faible concentrations+ODT."
      - "CompatibleSpiceFinder (adaptateur UI/éducation), CandidateVetoRepository, SpiceActiveCompoundRepository::loadOavProfilesBatch, CompoundPhysicalRepository (NON-final)."
      - "GeometricMean (src/Service/Math), DataConsistencyChecker (src/Service/Data)."
    commands:
      - "app:import:odt — odt_ppm OU odt_min/max (→ geomean), lit confidence"
      - "app:import:acquisition-csv — data/acquisition/*.csv (gitignoré), SEULE commande créant les composés, upsert, dry-run, idempotent"
      - "app:fetch:physical [--all] — PubChem XLogP3 + formule par CAS, confidence ESTIMATED"
      - "app:check:compounds / app:check:data / app:validate:compounds [--apply] / app:recompute:oav [--sync]"
    qualite_donnees:
      - "DataConfidence enum MEASURED(A)/LITERATURE(B)/ESTIMATED(C)/PLACEHOLDER(D), colonne confidence sur les 3 tables data. CasNumber VO (checksum). Badge qualité UI dans le Lab. GoldenPairingsTest = ancres anti-régression chimie."
    handler: "RecomputeOavTableHandler — DROP tmp → CREATE LIKE → 3 INSERT (OAV>1) en transaction → RENAME → DROP old → invalidateAll(). ⚠️ double worker = race (acceptable single-worker)."
    listener: "SpiceConcentrationChangedListener — post* sur SpiceCompoundConcentration + CompoundOdt, dedup postFlush, reset flag AVANT dispatch."
    cache:
      - "match.mortar_profile.cache — TTL air 24h / water+oil 1h / vide 5min. match.insights.cache TTL 1h (clé signatureHash)."
    rate_limit: "match_api sliding_window 30/min/IP. when@test : fixed_window 10000/min."
    gotchas:
      - "Le log vit dans le SCORER, pas la shadow table (la correction Nernst multiplie en runtime). Changer OavTanimotoScorer change tous les scores → GoldenPairingsTest."
      - "⚠️ COLONNES JOINTURE : tables ManyToMany = spices_id (PLURIEL) ; shadow spice_active_compound = spice_id (SINGULIER). findSurvivorsWithPresence doit projeter spices_id AS spice_id."
      - "Messenger Doctrine + MariaDB : use_notify ignoré → polling 60s, rebuild OAV pas instantané."
      - "CompatibilityScoreService SUPPRIMÉ (remplacé par le moteur OAV)."
    data_status: "⚠️ DONNÉES FICTIVES (tier D) : 15 composés, 105 concentrations. Plan réel : docs/PLAN_ACQUISITION_DONNEES.md"

  i18n:
    description: "FR (défaut) / EN / ES. Socle + entités traduisibles + câblage vues + recherche + admin FAITS. Reste : seed du contenu réel EN/ES, tests Controller /{_locale}. Guide : ~/.claude/plans/objet-sp-cification-technique-snappy-teapot.md"
    socle:
      - "Users.locale (string(5), défaut fr). LocaleSubscriber (kernel.request prio 15) : route _locale → Users.locale → session → Accept-Language → défaut. Const publique SUPPORTED_LOCALES."
      - "RootController GET / → redirect home locale préférée. URLs de contenu non préfixées = 404 (volontaire, pas de rétro-compat)."
      - "LocaleController GET /locale/{locale} (switch_locale) : set session + Users.locale, réécrit le segment locale du referer (rewriteLocaleInUrl, host validé anti open-redirect)."
      - "Préfixe /{_locale} sur 14 contrôleurs de CONTENU. NON préfixés : Root, Api/, Admin/, Security, Registration, Newsletter, Consent, EasterEgg, Locale."
      - "base.html.twig : <html lang> + hreflang/canonical. Switcher navbar. format_date/format_number (twig/intl-extra)."
    catalogues:
      - "translations/messages.{fr,en,es}.yaml + validators + admin + js. Namespaces top-level : common, form, flash, ui, gamification, enum. ⚠️ edu/catalog/labo/lab/game… sont SOUS ui (ui.edu.*). Domaines séparés : validators.*, admin.*, js.*"
      - "JS bridge : js_i18n_json() dumpe le domaine js dans #js-i18n, lu par assets/i18n.js t(). Pluriel = pipe Symfony + %count%. HTML inline = clés *_html |raw (échapper les params |e)."
    entites_traduisibles:
      - "Pattern Translation Table (PAS Gedmo — N+1). 9 entités : Spices, AromaticGroups, AromaticCompound, Achievement, CookingTips, PreparationMethods, PreparationTips, AlchemyFlavors, SpicyType. Table {x}_translation, unique (owner_id, locale), FK CASCADE, cascade persist/remove + orphanRemoval."
      - "TranslatableInterface : getTranslation(locale) — court-circuit return null en 'fr' (zéro requête). getLocalizedXxx(locale) = COALESCE fallback FR par champ. Champs traduits = textes user-facing uniquement (PAS color/cas/formula/slug/icon/enums)."
      - "Hot-paths : findNamesById(ids, ?locale) (LEFT JOIN + COALESCE) sur Spices/AromaticGroups/AromaticCompound. CompatibleSpiceFinder::findEnrichedByIds localise name + groupName en 1 requête."
      - "Recherche : SpicesRepository::search(word, ?locale) — LEFT JOIN translations filtré locale, FR = requête simple."
      - "Admin : CollectionField 'translations' dans les CRUD → form types App\\Form\\Admin\\Translation\\*. Colonne 'reviewed' : cochée = traduction validée, JAMAIS écrasée par le re-seed."
      - "Commande app:i18n:seed-translations <en|es> [--overwrite] : copie le FR (update-in-place), respecte reviewed."
      - "Gamification : payload notification 'name' localisé au déblocage."
    regles:
      - "Moteur OAV agnostique : locale JAMAIS dans pipeline/scorer/cache OAV — seule l'hydratation finale des noms est localisée."
      - "Exceptions dev-facing non traduites (jamais rendues à l'utilisateur — catch → flash traduit)."
      - "Lab : panneau gauche (sélection) reste FR (groupName = clé de groupement) ; résultats localisés via findEnrichedByIds."
    reste_a_faire:
      - "Seed contenu réel EN/ES via admin puis cocher reviewed (tant que vide → COALESCE sert le FR)."
      - "Tests Controller préfixe /{_locale} (DB spicymatch_test absente en local)."

  rgpd:
    cookie_consent:
      - "CookieConsentService (hasConsented, saveConsent, respectsDnt, versioning). POST /consent/save (CSRF cookie_consent via _token JSON). Cookie sm_consent. Template _cookie_consent.html.twig. DNT=1 → analytics false."
      - "Purge preuves : app:purge-expired-consents (cron 0 4 * * *, worker scheduler_gamification)."
    contact: "ContactType checkbox consent (IsTrue) + lien politique. Contact.consented_at horodaté à la soumission = preuve."
    droits:
      - "GdprRequestController GET/POST /{_locale}/confidentialite/mes-droits + /confirmation. Entité GdprRequest (email, request_type enum access/rectification/erasure/portability/opposition, treated_at nullable). CRUD admin (NEW désactivé, seul treated_at éditable, traiter sous 1 mois)."
    purge_retention:
      - "app:gdpr:purge (cron 30 4 * * *) : contacts > 12 mois, demandes RGPD > 6 ans, users soft-deleted > 30 j → ANONYMISATION (UserAnonymizer : username anonyme-{id}, mail null, password aléatoire !, roles [], + purge NewsletterSubscription). Anonymisation > hard delete (FKs sans onDelete). Durées = constantes publiques + documentées dans ui.legal.privacy.retention_text."
      - "Droit à l'effacement = self-service delete_user (soft-delete) puis anonymisation J+30."
    altcha:
      - "ALTCHA (PoW self-hosté, zéro cookie/tiers) sur contact + demande RGPD + inscription. Lib altcha-org/altcha ^2 ; widget 3.1.0 dans public/lib/altcha/altcha.js (PAS importmap : /assets/vendor/ → 403 ModSecurity)."
      - "AltchaManager (src/Service/Security) : createChallenge() SHA-256 cost 10 TTL 600s ; verify() = anti-replay (cache.app) + HMAC (env ALTCHA_HMAC_KEY). GET /api/altcha/challenge (no-store, public)."
      - "Pattern form : champ caché unmapped 'altcha' + contrainte App\\Validator\\AltchaSolved. Template : form.altcha.setRendered() + include components/_altcha.html.twig with {name: field_name(form.altcha)}."
      - "⚠️ WIDGET v3 : attributs challenge=\"<url>\" + language + name — challengeurl/strings (v1/v2) IGNORÉS silencieusement. Dict i18n : 17 clés complètes sinon anglais (pas de merge)."
      - "CSP : worker-src 'self' blob: data: requis."
      - "⚠️ NONCE CSP : base.html.twig appelle csp_nonce('script') sur chaque page → 'unsafe-inline' ignoré → TOUT <script> inline DOIT porter nonce=\"{{ csp_nonce('script') }}\" sinon bloqué silencieusement."
    pages_legales:
      - "LegalController : /{_locale}/mentions-legales + /{_locale}/confidentialite. Clés ui.legal.* (fr/en/es). Liens footer : legal_notice, privacy_policy, accessibility_declaration, contact."
      - "⚠️ PLACEHOLDERS à compléter avant mise en ligne : [NOM PRÉNOM], [ADRESSE], [SIREN], [HÉBERGEUR] dans messages.*.yaml."
    assets_self_hosted:
      - "ZÉRO CDN tiers : fonts woff2 public/fonts/ + fonts.css ; FontAwesome public/lib/fontawesome/ ; chart.js public/lib/chartjs/. Référencés via asset('lib/...')."
      - "⚠️ ModSecurity renvoie 403 sur tout chemin /vendor/ → JAMAIS d'asset sous public/vendor/, utiliser public/lib/. Symptôme : webfonts 403, icônes invisibles."
      - "CSP : seuls tiers = EthicalAds (media/server.ethicalads.io) + Carbon (cdn.carbonads.com, srv.carbonads.net)."

  acces_anonyme:
    - "Site OUVERT en anonyme. access_control : ^/[a-z]{2}/education/?$ PUBLIC ; ^/[a-z]{2}/(users|spicymatch|education) + ^/api/gamification → ROLE_USER ; ^/ PUBLIC_ACCESS ; admin → ROLE_ADMIN. Regex [a-z]{2} générique (pas (fr|en|es))."
    - "⚠️ EducationController : IsGranted par MÉTHODE (pas classe — un IsGranted de classe PRIME sur l'access_control YAML). index() null-safe anonyme."
    - "SpicesController::view ne dispatch SpiceReadEvent que si user non null. DifficultyExtension fallback EASY."

  slugs_per_locale:
    stockage:
      - "Slug FR canonique = colonne slug (unique) sur l'entité ; slug EN/ES = colonne slug nullable sur *_translation, UNIQUE(locale, slug). getLocalizedSlug(locale) = COALESCE, court-circuit fr. Interface Sluggable (entités + translations)."
    generation:
      - "SlugGenerator (AsciiSlugger + suffixe -2/-3) + SlugListener (prePersist/preUpdate — slug JAMAIS resynchronisé sur rename). app:slug:backfill [--dry-run] après import massif."
      - "Admin : SerializesSlugGenerationTrait (GET_LOCK MariaDB) sur les 6 CRUD sluggables — sérialise la génération (race check-then-act)."
    routing:
      - "6 routes détail {slug} (view_spice, quick_view_spice, view_aromatic_compound, view_preparation_methods, view_alchemy_flavors, view_spicy_type). Résolution translation-first (findOneByLocalizedSlug) puis fallback FR. 301 canonique via CanonicalSlugTrait si slug URL ≠ slug localisé."
      - "⚠️ COLLISION : view_spice /{_locale}/epices/{slug} vs index frères → priority:-10 + negative-lookahead excluant groupes_aromatiques|composes_aromatiques|saveurs_aromatiques|types_epices. Ajouter une sous-section sous /epices = MAJ le lookahead."
    filtres:
      - "Catalogue : ?aromatic_group=<slug> & ?spicy_type=<slug> (dégradation douce si inconnu). Lab LC : filterAgId/filterStId portent un SLUG, résolution mémoïsée, le 'checked' compare des IDs (locale-indépendant)."
    seo:
      - "hreflang/canonical : controllers détail passent hreflang_slugs={fr,en,es}. Slugs FR = hyphens."
      - "Sitemap : PrestaSitemapBundle v4 (/sitemap.xml). SitemapSubscriber : pages + content, alternates par locale. robots.txt statique (⚠️ remplacer VOTRE-DOMAINE avant prod)."
      - "Reste : seed slugs EN/ES réels (sinon alternates = slug FR)."

  monetisation:
    premium:
      - "Users.premiumUntil (?DateTimeImmutable) + isPremium(?now) — Stripe-ready, pas de cron. Page publique /{_locale}/premium (CTA auth-aware). Stripe/CGV = au jour 1 de l'encaissement, pas avant."
    ads:
      - "AdsExtension = source unique régies : PROVIDER_TEMPLATES {ethicalads, carbon, placeholder (DEV-only, fallback ethicalads en prod)}. ads_enabled() = ADS_ENABLED && non premium. env : ADS_ENABLED=false (opt-in), ADS_PROVIDER, ADS_PUBLISHER_ID. Ajouter une régie = 1 entrée + 1 partial + hosts CSP."
      - "partials/_ads_banner.html.twig : garde ads_enabled() 1re ligne, label Publicité + lien 'Retirer les pubs' → premium, min-h-[160px] anti-CLS."
      - "Slots (1/page max) : home (milieu), spices/view (milieu), education/index (bas), spicy_match_history/view, education/result."
      - "INTERDITS (actés) : interstitiel (Better Ads → filtrage Chrome), footer, Étamine, LiveComponents (morphdom), jeux en cours, formulaires, profil, sticky/anchored. EthicalAds format texte only."
      - "⚠️ AdSense ≠ switch env : CMP TCF v2.2 + consent marketing + CSP frame-src → volontairement hors whitelist."
      - "CHECKLIST ACTIVATION : (1) ADS_ENABLED + ADS_PUBLISHER_ID en env serveur ; (2) placeholders légaux (BLOQUANT LCEN) ; (3) acceptation EthicalAds incertaine (audience cuisine) ; (4) ajuster min-h au format réel."
    roadmap: "Phase 1 : affiliation + dons + newsletter. Phase 2 : Stripe + premium + ads si trafic. Phase 3 : API B2B. Plan : ~/.claude/plans/objet-sp-cification-et-tranquil-ocean.md"

  csrf_protection:
    patterns: "form classique = hidden _token ; JSON body = _token dans payload ; AJAX = header X-CSRF-Token"
    endpoints:
      - "POST /spicymatch/history/{id}/rename — history_action_{id} (body)"
      - "POST /spicymatch/history/{id}/favorite/toggle — history_action_{id} (header)"
      - "POST /api/gamification/egg/{slug} — easter_egg (header)"
      - "POST /api/onboarding/state — onboarding (header)"
      - "POST /consent/save — cookie_consent (body)"
      - "POST /education/start|answer — education_start|education_answer (form)"

js_interop:
  alpine:
    - "x-data/x-show/x-cloak pour modals ([x-cloak]{display:none!important}). Modals self-contained. Fetch : toujours try/catch + response.ok."
    - "⚠️ this.$el dans un handler déclenché depuis un ENFANT = l'enfant, pas la racine → this.$root pour lire les data-attrs du composant (sinon fetch(undefined) silencieux)."
  importmap: assets/importmap.php
  live_component:
    - "data-model uniquement DANS le template du LC (jamais include externe) — filtres Lab dans SpicyMatch.html.twig"
    - "debounce(search, 150) INVALIDE → data-model='search' (debounce natif)"
    - "LiveAction retournant RedirectResponse = OK (finish() des jeux)"
    - "État Alpine local dans un LC : data-live-ignore (ex timer Chrono)"
    - "Anti double-clic : x-data submitting + x-bind:disabled ; clics multiples autorisés → $wire.action().then(() => submitting = false)"

performance_frontend:
  images:
    - "LiipImagineBundle 2.17 (câblage manuel, recette ignorée). Filtres WebP : spice_thumb 96², spice_card 400² + spice_card_2x 800² (srcset), spice_hero 800². Usage {{ url|imagine_filter('spice_card') }}. Cache public/media/cache/ (extension .jpg mais contenu WebP — OK, magic bytes)."
    - "RÈGLE : toute <img> = decoding=async + width/height + loading=lazy si below-the-fold. Image LCP = fetchpriority=high SANS lazy + preload."
    - "Fallback image = icône fa-seedling locale. JAMAIS d'asset tiers."
  fonts: "3 woff2 critiques préchargés avec crossorigin (obligatoire même same-origin). font-display swap partout."
  cls: "Labels Alpine x-text visibles au premier paint = fallback SSR dans le span."
  gotcha_ci: "lint:twig : _macro_rarity.html.twig en erreur PRÉEXISTANTE (caller() — macro morte à nettoyer)."

design_system:
  fichier_source: assets/styles/app.css
  palette:
    saffron: "accent primaire orange" ; paprika: "accent secondaire rouge" ; turmeric: "jaune doré" ; cream: "fonds (#FDFCF0 / #F5F0E0)" ; spice-surface: "#FFF7ED" ; spice-border: "#FED7AA"
  regles:
    - "JAMAIS orange-*/amber-* natifs → saffron-*/turmeric-*. Cartes → card-warm. Boutons → btn-pill-*. Tags → tag-*. Focus → focus:ring-saffron-600/30."
    - "Inline style UNIQUEMENT pour couleurs dynamiques BDD (aromaticGroups.color)."
  tailwind_v4:
    - "--spacing: 0.25rem dans @theme pour h-*/w-* numériques. backdrop-blur/blur injectés via @layer utilities. tailwind.config.js v3 ignoré → @source dans le CSS (chemin relatif au fichier). yarn build après changement de classes."
  composants:
    button: "templates/components/Button.html.twig — <twig:Button> unique pour les pills. Props : label (TOUJOURS |trans), variant (primary|secondary|danger|ghost|back), size (xs→xl), href (→<a>), icon, iconOnly, disabled, external, extra. Attributs non déclarés forwardés. ⚠️ Alpine : PAS de shorthand :disabled/:class (Twig le prend pour une prop) → x-bind:. CSS : buttons.css. NON migrés volontairement : Lab (rounded-xl), toggles rounded-lg, carousel, widgets dynamiques, décoratifs."
    navbar: "_navbar.html.twig — sticky cream/80 backdrop-blur h-20 z-50"
    footer: "_footer.html.twig — bg-paprika-900, 4 colonnes"
    avatar: "_avatar.html.twig — badge équipé (icône + couleur rareté), fallback cercle neutre"
    lab: "SpicyMatch.html.twig — filtres data-model DANS le LC. Véracité par omission : sélecteur matrice = matricesWithData(), label ui.lab.presence_mode si pas de données OAV."
    catalog_filters: "_spices_filters.html.twig — GET → Turbo Frame spices_frame_id"
    education: "education/index (cards 6 modes), play (QCM), play_live (wrapper 5 LC), result (bilan multi-mode), components/Education/*.html.twig (5 jeux)"
    cookie_consent: "_cookie_consent.html.twig — bannière RGPD Alpine + CSRF"
    onboarding: "_onboarding.html.twig — modale bienvenue + 3 tours spotlight (spices 3 / lab 5 / academy 2 étapes). Config déclarative : map tourSteps {target, key, position, noClickAdvance} → clés ui.onboarding.tours.{tour}.{key}.title|text ; ajouter une étape = 1 entrée Twig + 2 clés YAML/locale. Moteur spotlightTour (alpine_components.js) : resolveTarget = 1re cible VISIBLE, scrollIntoView auto, prev(), compteur, bottom-sheet <640px, coordonnées VIEWPORT (position:fixed — pas de scrollX/Y), comparaison URL via pathWithoutLocale(). Ancres Lab : lab-context, lab-workspace, lab-compose. ÉTAT EN BASE : Users.onboardingState (varchar(20) nullable) null→spices→lab→academy→done ; null = modale sur la home. Écriture POST /api/onboarding/state (CSRF header 'onboarding', whitelist ALLOWED_STATES, fetch keepalive) ; lecture via data-onboarding-state sur les racines x-data. Inscription : RegistrationController purge le targetPath → atterrissage home ; modale compte créé d'abord (CTA → home), l'onboarding attend sa fermeture. Bouton 'revoir l'intro' (users/tabs/_lab) = onboardingReset → POST null + redirect home. A11Y : tooltip role=dialog + focus programmatique, aria-live=polite, dots/overlay aria-hidden."
    gamification_notif: "gamification/notification.stream.html.twig — Turbo Streams XP/achievements"
```
