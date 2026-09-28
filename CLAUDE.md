# CLAUDE.md — SpicyMatch

> **⚠️ RÈGLE — MAINTIEN À JOUR** : ce fichier est la source de vérité du projet. Toute décision architecturale, nouvelle entité/service, convention ou gotcha découvert doit y être reporté en fin de session.

## Règles d'interaction

- **🐳 ENV DOCKER (RÈGLE DURE)** : le projet tourne sur le container PHP **`p8.5`** (PHP 8.5). JAMAIS p8.4 / php-8.4 / p84 (n'existent plus). Toute commande : `docker exec -w /var/www/html/spicymatch p8.5 …`. URL dev : `https://spicymatch.sf4.p85.dbm-local.com` (vérifier avec curl). Ne jamais conclure « vhost cassé » avant d'avoir essayé p85.

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
    - "Symfony 8.1 / PHP 8.5 (migré depuis 7.4 ; UX 3.x). ⚠️ Symfony 8 : #[Route] = Symfony\\Component\\Routing\\Attribute\\Route (Annotation\\Route supprimé, échec SILENCIEUX à l'exécution — seul PHPStan le voit) ; contraintes de validation en arguments NOMMÉS (new Length(min: 6), plus de tableau d'options) ; #[ORM\\OrderBy] ET QueryBuilder::orderBy()/addOrderBy() avec \\SortDirection::Ascending|Descending (polyfill php86 ; chaîne 'ASC'/'DESC' dépréciée ORM 3.6 — les tableaux findBy/EasyAdmin ne sont pas concernés) ; utilisateur courant = paramètre #[CurrentUser] Users $user (?Users $user = null sur les routes publiques), plus de /** @var */ + getUser() ; fonctions Twig = #[AsTwigFunction] sur la méthode (plus d'AbstractExtension::getFunctions()) ; ⚠️ NE PAS utiliser #[IsCsrfTokenValid] : InvalidCsrfTokenException est une AuthenticationException → le firewall redirige 302 vers /login, un fetch suit la redirection et voit un 200 (faux succès) — garder les checks manuels isCsrfTokenValid() (403 JSON / flash+redirect) ; services de test référençant une classe supprimée = erreur de compilation container ; #[Target('xxx')] pour un autowiring nommé (ex $oavLogger). 8.2 = sortie nov. 2026, pas encore migrée."
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
  - "trigger = mot réservé MariaDB → name: 'trigger_type' dans #[ORM\\Column]"

package_managers: Composer (composer.lock) + Yarn (yarn.lock)

tooling:
  - ECS (PSR-12) / PHPStan niveau 6 (baseline phpstan-baseline.neon) / ESLint 9 flat config
  - Rector (withComposerBased symfony/doctrine/twig/phpunit + withPhpSets (8.5) + withAttributesSets + deadCode/codeQuality/typeDeclarations, paths src + tests)
  - PHPUnit 13.0.5 + BrowserKit, Doctrine Fixtures (groupes nommés)

scripts:
  php:
    - docker exec -w /var/www/html/spicymatch p8.5 composer ci              # ⭐ check-cs + phpstan + test-unit + schema-test + test-integration + test-controller + check-data — OBLIGATOIRE avant commit
    - docker exec -w /var/www/html/spicymatch p8.5 composer fix-cs
    - docker exec -w /var/www/html/spicymatch p8.5 composer rector-dry / rector
    - docker exec -w /var/www/html/spicymatch p8.5 composer phpstan
    - docker exec -w /var/www/html/spicymatch p8.5 composer test-unit         # rapide, sans DB
    - docker exec -w /var/www/html/spicymatch p8.5 composer test-integration  # DB spicymatch_test requise
    - docker exec -w /var/www/html/spicymatch p8.5 composer test-controller   # DB spicymatch_test requise
    # ⚠️ Pré-requis env frais : DB spicymatch_test seedée (fixtures 30 épices + app:recompute:oav --sync --env=test), sinon test-integration/test-controller rouges
    # Config PHPUnit versionnée = phpunit.dist.xml (phpunit.xml local gitignoré prime s'il existe)
    # Baseline après vrai fix : phpstan analyze --generate-baseline=phpstan-baseline.neon
  js:
    - yarn dev    # watch Tailwind
    - yarn build  # build minifié — à relancer après tout changement de classes
  moteur_oav:
    - docker exec -w /var/www/html/spicymatch p8.5 php bin/console app:import:odt
    - docker exec -w /var/www/html/spicymatch p8.5 php bin/console app:import:flavordb
    - docker exec -w /var/www/html/spicymatch p8.5 php bin/console app:import:physical
    - docker exec -w /var/www/html/spicymatch p8.5 php bin/console app:check:compounds [--strict]
    - docker exec -w /var/www/html/spicymatch p8.5 php bin/console app:check:data [--strict]
    - docker exec -w /var/www/html/spicymatch p8.5 php bin/console app:validate:compounds [--apply]  # ONLINE PubChem
    - docker exec -w /var/www/html/spicymatch p8.5 php bin/console app:recompute:oav --sync          # après TOUT import
    # Séquence import : check:compounds → import:* → check:data → recompute:oav --sync
  doctrine:
    - docker exec -w /var/www/html/spicymatch p8.5 php bin/console doctrine:schema:update --force
    - docker exec -w /var/www/html/spicymatch p8.5 php bin/console doctrine:fixtures:load --append --group=GroupName

conventions:
  commits: Conventional Commits (feat/fix/chore/refactor + scope optionnel)
  php: PSR-12, short arrays, attributs PHP 8+ (pas d'annotations)
  testing:
    suites: "Unit (tests/Service|Entity|Enum|Gamification|MessageHandler|Twig|ValueObject|EventSubscriber, TestCase pur), Integration (tests/Integration|Repository, KernelTestCase+DB), Controller (WebTestCase+DB). Les 3 suites tournent en CI (composer ci). ⚠️ Tout nouveau répertoire sous tests/ DOIT être ajouté à une <testsuite> de phpunit.dist.xml sinon jamais exécuté."
    regles_or:
      - "Un comportement, un owner : testé exhaustivement au niveau le plus bas qui le prouve (VO/service), câblage seul au-dessus (1 nominal + 1 erreur)."
      - "≥3 variantes de même forme → #[DataProvider] avec datasets nommés (yield 'intention' => [...]) ; le nom du dataset remplace le commentaire."
      - "Asserter l'état/l'output, pas le trajet. Exception : service de persistance à EM mocké → persist/flush EST le seul effet observable, le garder (NewsletterService, SpicyMatchService)."
      - "Repository avec SQL/DQL non trivial (JOIN/HAVING/natif) → test DB réelle obligatoire (mock de repo ne prouve rien). Ex CandidateVetoRepositoryTest (gotcha spices_id/spice_id)."
      - "Zéro test d'accesseur pur (PHPStan couvre le typage). Zéro commentaire (y compris séparateurs // ── xxx ──)."
    gotchas:
      - Classes final → pas de createMock() → vraie instance + dépendances mockées (ex AchievementChecker)
      - PHPUnit 13 : createMock() sans expects() = notice → createStub() OU #[AllowMockObjectsWithoutExpectations]
      - "with*() sans expects() = deprecation PHPUnit 14 → willReturnCallback() sur les stubs de routage (ex container mock SpicyMatchTest)"
      - PHPUnit 13 first-wins : willReturn() dans setUp() prime sur willReturnCallback() du test → pas de stubs globaux en setUp() si override nécessaire
      - Propriétés private en test Entity : new \ReflectionProperty(Cls::class, 'field')->setValue($obj, $val)
      - "Integration DB sans polluer : $connection->beginTransaction() → insert → assert → rollBack() en finally (ex FlavorGraphAffinityRepositoryTest). QueryCountTrait cassé (DebugStack retiré en DBAL 4)."
      - "Users : created_at / updated_at sont NOT NULL avec des propriétés PHP nullables. Users::initializeTimestamps() (#[ORM\\PrePersist], entité #[ORM\\HasLifecycleCallbacks]) les renseigne — inutile de les setter à la main dans les tests. ⚠️ Ne PAS tenter un #[ORM\\PreUpdate] pour rafraîchir updated_at : Doctrine a déjà calculé le changeset, une écriture de champ dans un callback PreUpdate est silencieusement ignorée."

architecture:
  pattern: MVC Symfony (Controller > Service > Repository > Entity), REST via controllers (pas d'API Platform), admin EasyAdmin

  gamification:
    entities:
      - UserProgression (xp, level computed infini, OneToOne Users, gamificationEnabled, equippedBadge, totalSpicesRead, streaks, lastReadDate, discoveries)
      - "Achievement (slug, name, description, icon, trigger_type enum, triggerValue, xpReward, rarity enum, easterEggSlug nullable, enabled bool default true, contextGameMode/contextDifficulty/contextAromaticGroup nullables = filtre de contexte, translations)"
      - UserAchievement (UserProgression <-> Achievement, unlockedAt)
      - AchievementProgress (user, achievement, progress, isCompleted property hook)
      - "PendingGamificationNotification (user, type, payload json, deliveredAt nullable) — file Turbo Streams. Index composite idx_pgn_user_delivered (user_id, delivered_at) OBLIGATOIRE : GamificationNotificationSubscriber interroge cette table à CHAQUE réponse HTML authentifiée."
      - "ProcessedGamificationEvent (user, eventType, eventKey) — registre d'idempotence, claim() = INSERT DBAL + catch UniqueConstraintViolationException"
      - SpiceView (user, spice, viewedDay — unique/jour)
      - UserStat (OneToOne Users — compteurs, visitedAromaticGroups json, lastVisitedSpices json FIFO 10)
      - GameSession (gameMode/difficulty enums, score, correctAnswers, totalQuestions, durationSeconds, accuracy/isFinished computed)
      - GameQuestion (session, questionIndex, questionData json, answerGiven, isCorrect, timeSpentMs)
    enums:
      - "AchievementTrigger (FIRST_MATCH, N_MATCHES, N_SPICES_USED, FIRST_DISCOVERY, N_FAVORITES, SPICE_READ, READING_STREAK, EASTER_EGG_FOUND, ALL_TERPENES_VISITED, FIRST_GAME, N_GAMES_COMPLETED, GAME_SCORE_THRESHOLD, GAME_PERFECT_RUN, GROUP_MASTERY_READ, ALL_PREPARATION_METHODS_READ) — 1 trigger = 1 evaluator (App\\Gamification\\Evaluator\\*), enregistrement par tag + TriggerEvaluatorRegistry qui REFUSE les doublons au compile time"
      - AchievementRarity COMMON/RARE/EPIC/LEGENDARY → labels Graine/Infusion/Extraction/Essence
      - GameMode QCM/SURVIVAL/GUESS_WHO/INTRUS/HANGMAN/CHRONO — isEnabled(), label(), xpPerCorrect() (qcm 3 / survival 5 / guess_who 4 / intrus 3 / hangman 8 / chrono 3), isLiveComponent(), totalQuestions(), tracksAccuracy() (false pour SURVIVAL : accuracy dégénérée, correctAnswers == totalQuestions par construction → exclure de toute stat de précision). Section navbar "L'Académie"
      - GameDifficulty EASY/MEDIUM/HARD — xpMultiplier() 1.0/1.5/2.0, rank() 1/2/3, harder()/easier() (null aux bornes)
    level_formula: "level = max(1, floor((xp / 100) ** (1 / 1.3))) — PAS de +1. XP requise pour le niveau L = 100 * L^1.3. Repères : L2=246, L5=810, L10=1995, L24=6270."
    xp_sources: "match_saved +10 (MatchXpStrategy::XP_PER_MATCH), spice_read +5 sur nouvelle vue (SpiceReadXpStrategy::XP_PER_NEW_VIEW), game_completed = context xpEarned (≤ MAX_XP_PER_SESSION=60), achievement_reward variable (≤ 200). ⚠️ easter_egg = modèle BADGE-ONLY : ZÉRO XP direct (EasterEggXpStrategy supprimée), l'œuf ne paie que via le badge EASTER_EGG_FOUND qu'il débloque."
    achievement_xp_scale: "41 badges, 2390 XP au total (common 7/135, rare 18/585, epic 9/620, legendary 7/1050) ≈ niveau 10 si TOUT est débloqué. Ancrage D = journée de jeu engagée free ≈ 200 XP (6 sessions × 30 + 3 lectures × 5 + 2 matchs × 10). R1 plafond dur 200 XP/badge (aucun badge ne vaut plus d'une journée). R2 bandes par rareté = D/8 / D/4 / D/2 / D → common ≤ 25, rare ≤ 50, epic ≤ 100, legendary ≤ 200. R3 valeur = 0,25 × XP direct déjà gagné en atteignant le palier (n_matches 10N, spice_read/first_discovery/reading_streak/n_spices_used 5N, n_games_completed 30N, easter_egg 75, n_favorites et all_terpenes_visited 0), arrondie à 5 et clampée dans la bande. R4 effort identique ⇒ valeur identique (les 8 easter eggs = 30 chacun, tous RARE). R5 en common, un « premier » = 15, un palier = 25. ⚠️ Ajouter un badge = appliquer R1–R5, jamais un chiffre au feeling."
    fixtures:
      - "41 achievements en DB. AchievementFixtures est la source de vérité et est IDEMPOTENT (upsert par slug, jamais de doublon) — il peut être rejoué sans INSERT IGNORE manuel. Colonne trigger_type, valeurs enum en minuscules."
      - "⚠️ Toute modification d'un badge se fait dans AchievementFixtures ET en base : les deux doivent rester identiques sur (slug, icon, trigger, triggerValue, xpReward, rarity)."
      - "⚠️ icon doit exister dans FontAwesome 6.7.2 FREE (public/lib/fontawesome/css/all.min.css, syntaxe `.fa-nom{--fa:...}`). Les noms Pro (fa-salt-shaker, fa-books) et FA5 (fa-fire-flame) rendent un <i> vide sans aucune erreur."
    services:
      - "GamificationManager (orchestrateur, Strategy pattern, guard opt-out) — implémente GamificationManagerInterface (App\\Gamification\\) via #[AsAlias]. ⚠️ GamificationManagerProxy et NullGamificationManager sont SUPPRIMÉS (l'injection circulaire n'existe plus)."
      - AchievementChecker (final — injecter AchievementRepository, pas mocker)
      - EasterEggService
      - "Handlers Messenger async : GamificationHandler (match_saved), FavoriteGamificationHandler (idempotent via SpicyMatchHistoryRepository), SpiceReadGamificationHandler (ClockInterface injectée — streak de lecture testable sans voyager dans le temps), EasterEggGamificationHandler, GameGamificationHandler (game_completed)"
      - "⚠️ ORDRE DES GARDES dans les handlers : getOrCreateProgression() + isGamificationEnabled() AVANT ProcessedGamificationEventRepository::claim(). Claim d'abord = la ligne du registre est posée alors que process() sort en early-return → XP perdue définitivement si l'utilisateur réactive la gamification ensuite (l'événement ne sera jamais rejoué)."
      - "⚠️ SÉRIALISATION XP (obligatoire) : addXp() est un read-modify-write, donc deux workers Messenger traitant en parallèle deux événements du même utilisateur perdraient une mise à jour de xp / readingStreak. Chaque handler enveloppe donc son corps utile dans $em->wrapInTransaction() et appelle GamificationManagerInterface::lockForUpdate($progression) EN PREMIER (SELECT … FOR UPDATE sur user_progression ; flush préalable si la progression n'a pas encore d'id). Bénéfice secondaire : claim() est dans la même transaction → il roule back si le traitement échoue, plus d'événement marqué traité sans XP versée. ⚠️ Verrouiller APRÈS avoir écrit en mémoire est un piège : un lock()+refresh() à ce moment-là écrase les setters déjà appliqués. Les compteurs recalculés par COUNT (totalMatches, uniqueSpicesUsed, discoveries, totalSpicesRead) sont auto-réparants, eux."
    xp_strategies: "MatchXpStrategy (XP_PER_MATCH=10) / SpiceReadXpStrategy (context isNewView, XP_PER_NEW_VIEW=5) / GameXpStrategy (context xpEarned) — tag gamification.xp_strategy, #[AutowireIterator]. EasterEggXpStrategy SUPPRIMÉE (badge-only)."
    easter_eggs: "⚠️ FONCTIONNALITÉ MORTE CÔTÉ FRONT : 8 slugs (EasterEggService::KNOWN_SLUGS) + 8 badges RARE en fixtures, conditions serveur armées (easter_egg.alchimiste_count via toggle_gamification_user, easter_egg.infusion_started_at via PreparationMethodsController), mais AUCUN template ni asset n'appelle api_gamification_egg → aucun œuf n'est réclamable. Soit câbler les déclencheurs JS, soit retirer la feature ; ne pas la laisser en l'état."
    cleanup: "app:gamification:cleanup [--dry-run] — purge notifications délivrées > 90 j (--notification-days), notifications JAMAIS délivrées > 180 j (--undelivered-days, sinon fuite sur les comptes dormants) et registre d'idempotence > 180 j (--ledger-days)."
    backfill: "app:backfill-gamification [--dry-run] [--xp] — recalcule compteurs et, avec --xp, l'XP absolue : matchs × 10 + vues d'épices × 5 + SUM(score des GameSession finies) + SUM(xpReward des badges débloqués). 5 requêtes agrégées groupées par user (repositories *GroupedByUser), zéro N+1."
    event_subscriber: "GamificationNotificationSubscriber (RESPONSE prio -10) — injecte les Turbo Streams avant </body>. Gardes bon marché AVANT la requête DB (main request, pas de Turbo-Frame, Content-Type text/html, </body> présent, user authentifié, gamification activée). Drip plafonné : PendingGamificationNotificationRepository::MAX_PER_RESPONSE = 20 par réponse, le reste part à la page suivante — sans ce cap, un backlog de worker injecterait des centaines de streams d'un coup."
    routes:
      - POST /api/gamification/egg/{slug} (CSRF easter_egg via X-CSRF-Token)
      - POST /users/gamification/toggle (CSRF toggle_gamification)
      - POST /users/badge/equip/{id} (CSRF equip_badge_{id})
    avatar: "L'avatar EST le badge équipé (UserProgression::$equippedBadge) : icône + couleur rareté. Composant templates/components/_avatar.html.twig, param equippedBadge (UserAchievement|null). Couleurs : common #f5f5f4/#78716c, rare #dbeafe/#1d4ed8, epic #f3e8ff/#7e22ce, legendary #fef9c3/#a16207."

  education:
    description: "6 jeux : QCM route-based + 5 Live Components (IntrusGame, SurvivalGame, GuessWhoGame, HangmanGame, ChronoGame)"
    services:
      - GameSessionManager  # sessions, XP, cap dur quotidien, createFinishedSession() pour LC, convertGamePoints()
      - AcademyManager      # logique de jeu (compatibilité, intrus ordinaux, cartes, questions), cache pool academy.cache TTL 1h
      - QcmQuestionGenerator (QuestionGeneratorInterface)
      - SkillAssessor       # logique pure sans DB : fenêtre glissante d'accuracy → SkillAssessment|null
      - DifficultyAdvisor   # seul point de contact DB (GameSessionRepository::findRecentAccuracies) + garde tracksAccuracy()
      - SkillAssessment     # VO readonly (current, suggested, winrate, samples), isPromotion()
    event_listener: AcademyCacheInvalidator (Doctrine post* sur Spices → invalide academy.spice_cards + academy.intruders.{id})
    routes:
      - "GET /education/ (index PUBLIC), POST /education/start (CSRF education_start), GET /education/play/{id}, POST /education/answer/{id} (CSRF education_answer) — QCM"
      - "GET /education/play-live/{mode} — LC. GET /education/result/{id} — multi-mode"
    anti_farming: "CAP DUR quotidien par mode : MAX_DAILY_SESSIONS_FREE = 2, MAX_DAILY_SESSIONS_PREMIUM = 5. PAS de dégressivité XP (REDUCED_XP_THRESHOLD supprimé — il était inatteignable en free et ne touchait que la 5e session en premium). Choix produit assumé : le mur quotidien fait revenir le lendemain, meilleur pour la mémorisation espacée. ⚠️ Le check-then-act du quota est sérialisé : GameSessionManager::withDailyQuota() = wrapInTransaction() + LockMode::PESSIMISTIC_WRITE sur l'utilisateur, donc deux onglets ne peuvent pas franchir le cap ensemble. Dépassement → \\RuntimeException ; TOUT appelant (les 5 LC comme le contrôleur QCM) DOIT l'attraper, purger le secret de session, flasher flash.daily_limit_reached et rediriger sur education_index — sans catch, le joueur perd une partie terminée sur un 500."
    xp_model: "MAX_XP_PER_SESSION = 60 appliqué aux DEUX branches. Modèle plat : correctAnswers × mode->xpPerCorrect() × difficulty->xpMultiplier(), plafonné. Modèle override (Chrono, GuessWho) : overrideScore = POINTS DE JEU, jamais de l'XP — convertis par GameSessionManager::convertGamePoints(points, difficulty) = min(round(points × xpMultiplier), MAX_XP_PER_SESSION). ⚠️ Un LC qui passe overrideScore ne doit PAS pré-appliquer le multiplicateur."
    difficulty_suggestion: "SkillAssessor (pur, testable sans DB) : fenêtre glissante WINDOW=5 dernières sessions finies d'un même (user, mode, difficulty), MIN_SAMPLES=3. Promotion si moyenne ≥ 80 ET min ≥ 60 (stabilité) ; rétrogradation si moyenne ≤ 40 ET max ≤ 60. SURVIVAL exclu via tracksAccuracy(). ⚠️ JAMAIS de changement automatique de preferredDifficulty — seulement une suggestion rendue sur education/result.html.twig (clés ui.edu.skill.*) avec CTA optionnel. Principe DDA : on ajuste la PROPOSITION de difficulté, pas la récompense en douce."
    gamification_event: GameCompletedEvent via Messenger
    cache: "pool academy.cache (filesystem, 3600s), clés academy.spice_cards + academy.intruders.{spiceId}, bind services.yaml $cache"
    regles:
      - "Réponses correctes en session HTTP (game_{token}), JAMAIS en #[LiveProp] (sérialisé client). Nuance : un LiveProp non-writable EST scellé par checksum HMAC (kernel.secret) → pas de falsification directe, mais un payload valide ANTÉRIEUR reste rejouable (checksum sans nonce) — d'où l'état serveur autoritaire."
      - "Aucune valeur scoring-critique en #[LiveProp], même en lecture seule : totalQuestions est SUPPRIMÉ des LC et relu côté serveur via GameMode::totalQuestions(). Un LiveProp = de l'état client à re-valider, pas une source de vérité."
      - "Aucun #[LiveProp(writable: true)] dans les LC Education (seuls Search, EnginePreferences et SpicyMatch en déclarent)."
      - "LC = createFinishedSession(), pas de GameQuestion rows"
      - "Tout doFinish()/finishSession() de LC DOIT garder la session vide (secret absent OU zéro question répondue) → redirect education_index sans persister. Sinon un finish sur composant fraîchement monté crée une session 0/0 qui consomme le quota, dispatche GameCompletedEvent et débloque first_game gratuitement (cause des 172 lignes intrus 0/10 en base)."
      - "Survie : composition monotone (optionCount, compatibleCount) = 6/4 EASY, 5/3 MEDIUM, 4/2 HARD → survie au clic aléatoire 66,7 % / 60 % / 50 %. ⚠️ Ne jamais dériver compatibleCount d'un ratio × optionCount : optionCount DÉCROÎT avec la difficulté, donc un ratio fixe rendait HARD plus facile qu'EASY (75 % contre 66,7 %) tout en payant ×2 XP."
      - "Chrono : seuils de points [t1, t2] = 2 s par option proposée, t2 = t1 × 1,5 → [8,12] EASY (4 options), [12,18] MEDIUM (6), [16,24] HARD (8). Les options se lisent, donc un seuil constant pénalise mécaniquement les difficultés hautes."
      - "SpicesRepository::findIncompatibleWith() : SQL NOT EXISTS 4 subqueries (main/sec × main/sec). N'est PLUS un fallback : c'est le segment permanent en tête de l'ensemble éligible des intrus (servi en EASY, atteint en HARD seulement sur pool pauvre). Disjonction avec findSurvivorsWithPresence() prouvée par SpicesIncompatibilityDisjunctionTest."
      - "Sélection Intrus = ORDINALE PURE (invariants I1..I5) : jamais de seuil absolu ni de ratio sur le score, seulement des rangs et des égalités. L'intrus est tiré EN PREMIER dans l'ensemble éligible ; sa position définit la coupure cut(x) ; les 3 compatibles sont tirées uniquement AU-DESSUS de cette coupure → exclusion mutuelle structurelle, zéro déduplication à l'assemblage. Inversé = symétrique (bonne réponse d'abord)."
      - "La difficulté ne pilote qu'une fenêtre ordinale (OrdinalWindow::select : tiers haut / médian / bas, partagée par AcademyManager et QcmQuestionGenerator) à l'intérieur d'ensembles DÉJÀ admissibles — la justesse vient de la construction, jamais de la fenêtre. Le mode strict binaire (intrusStrictMode / findStrictIntruders / academy.intruders.strict.*) est SUPPRIMÉ : c'était la condition de possibilité du bug des doublons."
      - "I3 : les 4 options d'une question intrus passent par la fabrique unique AcademyManager::toOption() ({id,name,file,color,groupName}). Un littéral inline sans groupName rend l'intrus identifiable à sa seule puce de famille. Localisation : AcademyManager::localizedOptions() réécrit name/groupName des 4 options via findEnrichedByIds(ids, locale) au moment de l'assemblage — 1 requête batch en EN/ES, ZÉRO en FR. Ne JAMAIS localiser par l'entité ici : les Spices viennent du pool academy.cache (filesystem), donc détachées, et getLocalizedName() y déclencherait un lazy-load de collection sans EntityManager."
      - "QCM = règle R1 : le maximum de l'ensemble des options est UNIQUE et atteint par la bonne réponse ; les distracteurs sont tirés strictement SOUS son score. Pool à scores tous égaux → generate() retourne null (question sans solution)."
      - "Anonyme + clic carte jeu = pop-in login/inscription contextuelle (design_system.composants.gate_login_modal), pas de redirect plein-écran. _target_path POST prioritaire dans LoginFormAuthenticator::onAuthenticationSuccess (RedirectTargetGuard anti-open-redirect) → retour direct sur le jeu ciblé après login/inscription."
      - "5 LC de jeu = #[IsGranted('ROLE_USER')] classe (les routes /_components/Education:* ne sont PAS locale-préfixées → tombent dans le catch-all PUBLIC_ACCESS de security.yaml sinon)."

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
      - "app:fetch:pubchem [--all] [--force] — PubChem XLogP3 + formule + CID + InChIKey par CAS (confidence ESTIMATED sur logP ; CID/InChIKey = identifiants, sans confidence). --all = re-fetch même si déjà complet ; --force = écrase aussi les valeurs déjà renseignées (y compris logP MEASURED/LITERATURE, alors rétrogradé ESTIMATED), implique --all — dangereux, dataset actuel 100% fictif donc sans risque, à éviter dès données réelles. Verrou GET_LOCK non bloquant, flush par composé, collision CID/InChIKey → warning + skip (même sous --force)"
      - "app:check:compounds / app:check:data / app:validate:compounds [--apply] / app:recompute:oav [--sync]"
    qualite_donnees:
      - "DataConfidence enum MEASURED(A)/LITERATURE(B)/ESTIMATED(C)/PLACEHOLDER(D), colonne confidence sur les 3 tables data. CasNumber VO (checksum). Badge qualité UI dans le Lab. GoldenPairingsTest = ancres anti-régression chimie."
    handler: "RecomputeOavTableHandler — DROP tmp → CREATE LIKE → 3 INSERT (OAV>1) en transaction → RENAME → DROP old → invalidateAll(). Concurrence : GET_LOCK non bloquant porté par la CONNEXION (immune aux commits implicites du DDL), release en finally ; si déjà pris → abandon + redispatch DelayStamp 60s, borné MAX_REBUILD_ATTEMPTS=3 puis arrêt définitif. ⚠️ app:recompute:oav --sync retourne FAILURE sur abandon (avant : SUCCESS silencieux → séquence d'import no-op)."
    listener: "SpiceConcentrationChangedListener — post* sur SpiceCompoundConcentration + CompoundOdt, dedup postFlush, reset flag AVANT dispatch."
    cache:
      - "match.mortar_profile.cache — TTL air 24h / water+oil 1h / vide 5min. match.insights.cache TTL 1h (clé signatureHash)."
    rate_limit: "match_api sliding_window 30/min/IP. when@test : fixed_window 10000/min."
    gotchas:
      - "Le log vit dans le SCORER, pas la shadow table (la correction Nernst multiplie en runtime). Changer OavTanimotoScorer change tous les scores → GoldenPairingsTest."
      - "⚠️ COLONNES JOINTURE : tables ManyToMany = spices_id (PLURIEL) ; shadow spice_active_compound = spice_id (SINGULIER). findSurvivorsWithPresence doit projeter spices_id AS spice_id."
      - "Messenger Doctrine + MariaDB : use_notify ignoré → polling 60s, rebuild OAV pas instantané."
      - "CompatibilityScoreService SUPPRIMÉ (remplacé par le moteur OAV)."
      - "Appel PubChem PUG REST + toArray() : catch ExceptionInterface (base), PAS seulement TransportExceptionInterface — JsonException (décodage, ex réponse HTML 200) est un SIBLING, pas un enfant, donc non intercepté par le catch transport seul."
      - "app:fetch:pubchem n'écrase JAMAIS un logP existant en confidence MEASURED/LITERATURE (protection par tier, PubChem = ESTIMATED seulement) — cf FetchPubChemDataCommand::applyProperties()."
    data_status: "⚠️ DONNÉES FICTIVES (tier D) : 15 composés, 105 concentrations. Plan réel : docs/PLAN_ACQUISITION_DONNEES.md"

  duos_main_temps:
    description: "Mot du Chef : annotation ternaire (épice × méthode de préparation × moment de cuisson). Le Lab surligne le 2e choix propice, l'historique et la page épice restituent le duo. Choix TOUJOURS libres : le surlignage est de la présentation, jamais validé serveur, un tip sans duo reste neutre."
    entites:
      - "CookingMoment (enum int) PRE=0 / START=1 / SIMMER=2 / FINISH=3 / PLATING=4 — label()/icon()/hint()/chef() → clés enum.cooking_moment.{case}.*. CookingTips::$moment (enumType) est mappé sur la colonne EXISTANTE `step` (name: 'step'). ⚠️ Valeur hors 0-4 = ValueError → 500 : garde app:check:data (checkCookingMoments)."
      - "⚠️ cooking_step (colonne CookingTips + CookingTipsTranslation.cookingStep + form admin + clé admin + fixtures) SUPPRIMÉ : les 5 libellés = les 5 cases de l'enum. Le libellé d'un moment vient de moment.label|trans, jamais d'un champ libre."
      - "SpiceDuo (table spice_duo) : ManyToOne NOT NULL preparationTip + cookingTip (onDelete CASCADE), UNIQUE(preparation_tip_id, cooking_tip_id) + UniqueEntity, rank smallint (1 = recommandé, >1 = possible ; colonne `rank` backtickée, mot réservé), title (accroche) / effect / science / example. Invariant preparationTip.spice === cookingTip.spice ; unicité (épice, méthode) et (épice, moment) NON forcée en base (données curées) mais gardée par app:check:data (checkDuplicatePreparationTips / checkDuplicateCookingMoments, sévérité error) ; title Length(255) : #[Assert\\Callback] + règle check:data (checkSpiceDuoSpices). SpiceDuoTranslation = pattern Translation Table standard (reviewed, COALESCE, court-circuit fr)."
      - "PreparationMethods : 13 méthodes (6 + torrefie_moulu, matiere_grasse, pate, rehydrate + grille « Grillé ou rôti », sirop « Mis en sirop », maceration « Macération » : gestes récurrents hors liste, sinon tips forcés dans une méthode approximative). PreparationMethodsFixtures IDEMPOTENT (upsert par name + addReference). Icône Lab = _etamine_icons.html.twig indexé par preparationMethod.slug (plus de sous-chaîne du titre FR) — toute nouvelle méthode = 1 entrée de la map kinds. ⚠️ Badge ALL_PREPARATION_METHODS_READ compare au count() des méthodes : chaque ajout de méthode relève le seuil."
    repository:
      - "SpiceDuoRepository::findBySpiceIds(ids, locale) (Lab + page épice) et findByTipIds(prepIds, cookIds, locale) (historique) : lecture SCALAIRE (IDENTITY(), LEFT JOIN translation WITH locale + COALESCE hors fr), 0 hydratation, 1 requête, pas de cache applicatif. Requête commune = baseQuery()/fetchRows(). Chaque ligne porte prepTitle (titre de la méthode, localisé) : la page épice n'a plus besoin d'aucune requête supplémentaire."
      - "SpicesRepository::findForLab(ids, locale) : fetch-join cookingTips + preparationTips + preparationMethod (fin du N+1 du Lab) ; hors fr, précharge aussi les traductions de la locale (Spices, CookingTips, PreparationTips via LEFT JOIN WITH locale, collections partielles = lecture seule) — prouvé par SpicyMatchLabQueryCountTest (profiler DB, fr vs en)."
    services:
      - "SpiceDuoMapBuilder (src/Service/Match) : build(rows) → {byPrep:{id:[{c,r}]}, byCook:{id:[{p,r}]}} (data-duo-map) ; tooltips(rows) → mêmes index avec method (titre localisé de la méthode)/title/effect (nœuds SSR role=tooltip et combos page épice)."
    restitution:
      - "Lab (spicy_match/view) : data-duo-map en attribut html_attr (pas de <script> inline → pas de nonce), .tile-wrap + nœuds role=tooltip id=duo-c-{p}-{c} / duo-p-{p}-{c}, aria-describedby dynamique. finalisationMelange (alpine_components.js) : classes de tuile selected|recommended|possible|muted ; muting UNIQUEMENT si le côté choisi possède ≥ 1 duo ; jamais de blocage ni d'auto-sélection ; pas de re-tri des tuiles (les moments sont chronologiques)."
      - "Historique (spicy_match_history/view) : SpicyMatchHistoryController::view calcule duoByPrep via findByTipIds → bloc .chef-word (accroche, effet, science, exemple) dans l'article de préparation. Page épice : SpicesController::view passe duosByCook (method inclus, plus de prepTitles) → .marmite-duos sous chaque marmite."
      - "Twig autoescape uniquement (jamais |raw sur un texte de duo)."
    admin_import:
      - "SpiceDuoCrudController (menu Dashboard) + SpiceDuoTranslationType ; SeedTranslationsCommand gère spice_duo."
      - "app:import:spice-duos <fichier sous data/, ≤ 10 Mo> [--dry-run] — CSV spice_slug,method_slug,moment,rank,title,effect,science,example (moment = int ou nom d'enum insensible à la casse, rank 1-9). Upsert idempotent sur (prepTip, cookTip), UNE transaction (rollback sur erreur ou dry-run). Erreur explicite si le tip méthode ou moment de l'épice n'existe pas : AUCUNE création implicite de tip. Épice/méthode/tips résolus hors soft-delete (deleted_at IS NULL) ; 2 correspondances ou plus (même épice+méthode, même épice+moment) = erreur « rattachement ambigu », jamais un choix arbitraire ; ligne CSV dupliquée (SpiceDuoRow::pairKey) = erreur. Toujours --dry-run d'abord. VO SpiceDuoRow + SpiceDuoImporter (final)."
      - "État dev : 68 épices vivantes (les 34 soft-deleted ont été archivées dans data/archive/purged_spices_2026-09-26/ puis supprimées définitivement), 171 CookingTips, 181 SpiceDuo (data/spice_duos_import.csv + data/spice_duos_gaps.csv ; CSV de travail archivés sous data/csv/). Les 13 méthodes portent ≥ 1 PreparationTip sur une épice vivante (badge ALL_PREPARATION_METHODS_READ atteignable, total dynamique via count). Un duo ne se fabrique que depuis deux tips déjà existants : aucun fait nouveau. Traductions EN/ES des 181 duos et des 170 CookingTips vivants FAITES (machine, reviewed=0 → à relire en admin ; ⚠️ app:i18n:seed-translations --overwrite les écraserait). Descriptions/PreparationTips non traduits (COALESCE sert le FR)."
    gotchas:
      - "Test DB : slugs null sur groupes/composés/méthodes → 500 au rendu (route slug). Corriger avec `app:slug:backfill --env=test` (ou setter les slugs dans la transaction du test, cf SpiceDuoImporterTest::pair())."
      - "⚠️ EasyAdmin 5.5 : le Dashboard DOIT porter #[AdminDashboard(routePath: '/admin', routeName: 'admin')] (pas un #[Route] sur index()) ET config/routes/easyadmin.yaml (resource: . / type: easyadmin.routes) doit exister, sinon AdminContext nul → 500 « attribute i18n on null » sur TOUT l'admin. URLs = routes jolies (/admin/spice-duo, /admin/cooking-tips/{id}/edit), plus de ?crudAction=. Garde-fou : tests/Controller/Admin/AdminCrudSmokeTest."
      - "Le fat_ratio/water_ratio de spicy_match apparaît en diff permanent de schema:update (préexistant) : ne pas lancer schema:update --force à l'aveugle, lire --dump-sql."

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
      - "Tests Controller préfixe /{_locale} : suite Controller en CI depuis la rationalisation (DB spicymatch_test seedée en local)."

  rgpd:
    cookie_consent:
      - "CookieConsentService (hasConsented, saveConsent, respectsDnt, versioning). POST /consent/save (CSRF cookie_consent via _token JSON). Cookie sm_consent. Template _cookie_consent.html.twig. DNT=1 → analytics false."
      - "Purge preuves : app:purge-expired-consents (cron 0 4 * * *, worker scheduler_gamification)."
    contact: "ContactType checkbox consent (IsTrue) + lien politique. Contact.consented_at horodaté à la soumission = preuve."
    droits:
      - "GdprRequestController GET/POST /{_locale}/confidentialite/mes-droits + /confirmation. Entité GdprRequest (email, request_type enum access/rectification/erasure/portability/opposition, treated_at nullable). CRUD admin (NEW désactivé, seul treated_at éditable, traiter sous 1 mois)."
    purge_retention:
      - "app:gdpr:purge (cron 30 4 * * *) : contacts > 12 mois, demandes RGPD > 6 ans, users soft-deleted > 30 j → ANONYMISATION (UserAnonymizer : username anonyme-{id}, mail null, password aléatoire !, roles [], + purge NewsletterSubscription). Anonymisation > hard delete (FKs sans onDelete). Durées = constantes publiques + documentées dans ui.legal.privacy.retention_text."
      - "Droit à l'effacement = self-service delete_user (soft-delete) puis anonymisation J+30. UI = zone danger dans l'onglet Réglages (users/tabs/_lab) : révélation inline Alpine 'deleteAccount' (getter idle CSP-safe) → form POST delete_user (CSRF 'delete{id}', data-turbo=false car redirect logout hors frame). Clés ui.config.delete_account*."
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
    - "Site OUVERT en anonyme. access_control : ^/[a-z]{2}/education/?$ PUBLIC ; ^/[a-z]{2}/spicymatch/?$ PUBLIC ; ^/[a-z]{2}/(users|spicymatch|education) + ^/api/gamification → ROLE_USER ; ^/ PUBLIC_ACCESS ; admin → ROLE_ADMIN. Regex [a-z]{2} générique (pas (fr|en|es))."
    - "⚠️ EducationController / SpicyMatchController : IsGranted par MÉTHODE (pas classe — un IsGranted de classe PRIME sur l'access_control YAML). index() null-safe anonyme dans les deux cas. SpicyMatchController::view() reste #[IsGranted('ROLE_USER')] (scelle/sauvegarde un match en historique, propriété vérifiée)."
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
    saffron: accent primaire orange
    paprika: accent secondaire rouge
    turmeric: jaune doré
    cream: "fonds (#FDFCF0 / #F5F0E0)"
    spice-surface: "#FFF7ED"
    spice-border: "#FED7AA"
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
    gate_login_modal: "_gate_login_modal.html.twig — pop-in connexion/inscription à onglets (Alpine gateLoginModal, activeTab login|register), incluse globalement dans base.html.twig si `not is_granted('ROLE_USER')` ET route courante ∉ {app_login, index_register} (évite doublon d'id avec les pages dédiées). Déclenchée par CustomEvent `gate-login` {url, tab} — dispatché par x-data=\"gateTrigger(url='', tab='login')\" : url vide → fallback window.location courante (usage navbar, 6 emplacements desktop/méga-menu/mobile) ; url explicite → jeu ciblé (education/index cartes anonymes). Onglet Connexion = vrai form vers app_login (_target_path POST prioritaire, cf LoginFormAuthenticator). Onglet Inscription = form RegistrationFormType RÉEL (ALTCHA inclus, Web Component s'initialise même x-cloak) construit à la volée par App\\Twig\\Extension\\RegistrationFormExtension::embeddedRegistrationForm() (TwigFunction embedded_registration_form(), nouvelle instance à chaque rendu — jamais d'état partagé entre pages), submit vers index_register avec _target_path POST. navMenu écoute `gate-login` en window pour se fermer (pas de chaînage `;` dans les directives Alpine — pattern CustomEvent découplé, cohérent avec toast/notificationHost)."
    onboarding: "_onboarding.html.twig — modale bienvenue + 3 tours spotlight INDÉPENDANTS (spices 3 / lab 5 / academy 3 étapes ; academy step 'optout' surligne data-tour=nav-profile → désactiver gamification via profil/Labo). Config déclarative : map tourSteps {target, key, position, noClickAdvance} → clés ui.onboarding.tours.{tour}.{key}.title|text ; ajouter une étape = 1 entrée Twig + 2 clés YAML/locale. Moteur spotlightTour (alpine_components.js) : resolveTarget = 1re cible VISIBLE, scrollIntoView auto, prev(), compteur, bottom-sheet <640px, coordonnées VIEWPORT (position:fixed — pas de scrollX/Y), comparaison URL via pathWithoutLocale(). Ancres Lab : lab-context, lab-workspace, lab-compose. ÉTAT EN BASE = SET de tours VUS (plus de chaîne linéaire) : Users.onboardingState (varchar(64) nullable) = CSV de clés vues parmi {welcome,spices,lab,academy}, null = tout à faire. checkTour() itère les tours et lance le 1er dont la clé n'est PAS dans le set ET dont le path matche, puis markSeen(key) EAGER au démarrage → chaque tour se déclenche indépendamment à la 1re visite de SA page. 'Passer' (skip)/skipTransition ferment juste le tour courant (clé déjà marquée) → ne touchent PAS les autres tours. Modale bienvenue : visible si set ne contient pas 'welcome' & path home ; 'commencer' → set 'welcome' (tours de page restent actifs) ; 'passer'/déjà connu → set 'welcome,spices,lab,academy' (skip GLOBAL, seule exception). Écriture POST /api/onboarding/state (CSRF header 'onboarding', payload {state: csv}, validation tokens ∈ ALLOWED_KEYS, fetch keepalive) ; lecture via data-onboarding-state. Inscription : RegistrationController purge le targetPath → atterrissage home ; modale compte créé d'abord (CTA → home), l'onboarding attend sa fermeture. Bouton 'revoir l'intro' (users/tabs/_lab) = onboardingReset → POST null + redirect home. A11Y : tooltip role=dialog + focus programmatique, aria-live=polite, dots/overlay aria-hidden."
    gamification_notif: "gamification/_notification_stream.html.twig — Turbo Streams XP/achievements"
```
