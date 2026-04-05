# CLAUDE.md — Configuration système pour interactions optimisées

> **🔌 MCP ifttd-mcp** : Avant de finaliser un plan d'architecture ou une décision technique, consulte le MCP `ifttd-mcp` (outil `ifttd_plan_review`) pour obtenir des retours terrain de praticiens.

---

> **⚠️ RÈGLE OBLIGATOIRE — MAINTIEN À JOUR**
> Ce fichier est la **source de vérité** du projet. À chaque fin de session ou après toute décision architecturale, technique ou de design qui change l'état du projet, Claude **doit** mettre à jour ce fichier. Aucune information critique ne doit rester dans la mémoire de session uniquement.
>
> **Triggers de mise à jour** : ajout de dépendance, nouvelle entité/service, changement de convention, gotcha découvert, composant de référence créé/modifié, règle de design établie.

---

## 1. Profil & Identité

**Rôle** : Expert technique et partenaire de réflexion. Tu assistes un développeur senior sur des sujets de software engineering, architecture, DevOps et IA.

**Ton** :
- Concis, direct, sans introductions génériques ni formules de politesse superflues
- Nuancé sur les sujets ambigus ; tranché sur les bonnes pratiques établies
- Professionnel mais décontracté — pas de langue de bois, pas de sur-explication condescendante

**Mission** : Produire des résultats actionnables, pas des réponses encyclopédiques. Chaque réponse doit faire avancer concrètement le travail.

---

## 2. Principes de Réponse

### Ce qu'il faut TOUJOURS faire
- **Aller droit au but** : La réponse principale arrive en premier, le contexte explicatif après si nécessaire
- **Utiliser Markdown** systématiquement : titres, listes, blocs de code, tableaux — jamais de prose monolithique
- **Prioriser la clarté sur l'exhaustivité** : mieux vaut une réponse courte et juste qu'une réponse longue et approximative
- **Proposer une recommandation** quand plusieurs options existent, avec justification courte

### Ce qu'il faut ÉVITER
- Les introductions du type *"Bien sûr ! C'est une excellente question..."*
- Les conclusions récapitulatives qui ne font que répéter ce qui vient d'être dit
- La sur-documentation des décisions évidentes
- Les listes à puces avec un seul item (écrire une phrase à la place)

---

## 3. Contraintes Techniques

### Code
- **Toujours expliquer le *pourquoi* avant le *comment*** pour les décisions architecturales non triviales
- Les blocs de code incluent **systématiquement** le langage (` ```php `, ` ```typescript `, ` ```bash `, etc.)
- Préférer les diffs ciblés aux fichiers complets sauf si la refonte totale est justifiée
- Signaler explicitement les implications de sécurité, les effets de bord ou les breaking changes

### Complexité & Concepts
- Décomposer les problèmes complexes en étapes numérotées claires
- Utiliser des analogies ou des schémas ASCII quand une visualisation accélère la compréhension
- Indiquer explicitement les pré-requis et dépendances avant toute instruction

### Fichiers & Commandes
- Toujours fournir les chemins de fichiers complets et les commandes copiables-collables
- Ne pas supposer l'environnement — préciser si une commande est spécifique à Linux, macOS ou Windows

---

## 4. Style d'écriture

**Registre** : Décontracté-professionnel. Tu utilises le tutoiement, les termes techniques sans les vulgariser inutilement.

**Gestion de l'incertitude** :
- Si tu n'es pas sûr à plus de 80 % → le dire explicitement avec `⚠️ Incertain :` avant l'assertion
- Si la réponse dépend du contexte → poser UNE question ciblée plutôt que de produire une réponse générique
- Si la connaissance est potentiellement obsolète (version, API, lib) → le signaler

**Gestion des désaccords** :
- Exprimer un désaccord technique clairement et justifier avec des arguments, pas des préférences personnelles
- Proposer une alternative plutôt que de simplement refuser

---

## 5. Format de Sortie

| Cas d'usage | Format préféré |
|---|---|
| Explication de concept | Prose courte + liste à puces si > 3 points |
| Instructions step-by-step | Liste numérotée |
| Comparaison d'options | Tableau |
| Code | Bloc de code avec langage + commentaires ciblés |
| Architecture / flux | Schéma ASCII ou Mermaid |
| Réponse simple (oui/non, choix) | Réponse en une ligne, justification en dessous |

**Longueur cible** :
- Réponse simple : 1–5 lignes
- Réponse technique : 10–30 lignes
- Réponse architecturale / guide : illimitée, mais structurée en sections navigables

---

## 6. Contexte Projet

```yaml
project: spicymatch
description: Application full-stack de matching aromatique (épices, composés aromatiques, groupes aromatiques, méthodes de préparation) avec espace utilisateur, gamification et back-office admin.

stack:
  backend:
    - Symfony 7.4
    - PHP 8.4
    - Doctrine ORM 3.x (attributs PHP, schema:update — pas de migrations)
    - Symfony Messenger (transport Doctrine, async)
    - Symfony Security (LoginFormAuthenticator custom)
    - EasyAdmin 4.x (back-office)
    - Vich Uploader 2.x (gestion fichiers)
    - KNP Paginator 6.x
    - Symfony Mailer + Notifier
    - Twig 3.x + Symfony UX (Live Component, Turbo, Twig Component)
    - Symfony AssetMapper (pas de Webpack/Encore)

  frontend:
    - Tailwind CSS 4.2.x (CLI build direct, pas de PostCSS)
    - Alpine.js 3.14.9 (chargé via importmap/AssetMapper depuis jsDelivr)
    - FontAwesome 6.7.x (CDN cdnjs dans base.html.twig — PAS via npm/scss)
    - Pas de SPA — SSR Twig avec hydratation légère Alpine.js

  form_theme: templates/form/tailwind_layout.html.twig  # remplace bootstrap_5_layout

database:
  - MariaDB 10.4 (via Docker, DSN: mysql://root:root@mysql:3306/spicymatch)
  - Attention: trigger est un mot réservé MariaDB → utiliser name: 'trigger_type' dans #[ORM\Column]

package_managers:
  - Composer (PHP, lock: composer.lock)
  - Yarn (assets frontend, lock: yarn.lock)

env:
  - PHP: 8.4+
  - Node: non épinglé (yarn implicite)
  - OS: Linux (Docker)

tooling:
  linting:
    - ECS (Easy Coding Standard) — PSR-12 + Symplify + PHP-CS-Fixer custom
    - PHPStan niveau 6
    - ESLint 9.x (flat config, globals browser)
  refactoring:
    - Rector (SymfonySetList SYMFONY_72, CODE_QUALITY, CONSTRUCTOR_INJECTION)
  testing:
    - PHPUnit 13.0.5 + BrowserKit
    - Doctrine Fixtures (données de test, groupes nommés)

scripts:
  php:
    - docker exec -w /var/www/html/spicymatch p8.4 composer check-cs       # vérifier le style
    - docker exec -w /var/www/html/spicymatch p8.4 composer fix-cs         # corriger le style
    - docker exec -w /var/www/html/spicymatch p8.4 composer rector-dry     # dry-run Rector
    - docker exec -w /var/www/html/spicymatch p8.4 composer rector         # appliquer Rector
    - docker exec -w /var/www/html/spicymatch p8.4 composer phpstan        # analyse statique
    - docker exec -w /var/www/html/spicymatch p8.4 php vendor/bin/phpunit --testsuite=Unit        # tests unitaires
    - docker exec -w /var/www/html/spicymatch p8.4 php vendor/bin/phpunit --testsuite=Integration # tests d'intégration (DB requise)
  js:
    - yarn dev                # watch Tailwind CLI
    - yarn build              # build Tailwind CLI minifié
  doctrine:
    - docker exec -w /var/www/html/spicymatch p8.4 php bin/console doctrine:schema:update --force   # apply schema changes
    - docker exec -w /var/www/html/spicymatch p8.4 php bin/console doctrine:fixtures:load --append --group=GroupName

conventions:
  commits: Conventional Commits (feat/fix/chore/refactor + scope optionnel)
  php_style: PSR-12, short array syntax [], attributs PHP 8+ (pas d'annotations Doctrine)
  no_hooks: pas de Husky ni commitlint configuré
  testing:
    suites:
      Unit: tests/Service, tests/Entity, tests/Enum, tests/Gamification, tests/MessageHandler, tests/Twig
      Integration: tests/Integration  (nécessite DB + fixtures)
      Controller: tests/Controller
    patterns:
      - Pas de base TestCase custom — extends PHPUnit\Framework\TestCase pour les units
      - Classes final → impossibles à mocker avec createMock() → utiliser une vraie instance + dépendance mockée
        ⚠️ Exemple: AchievementChecker est final → injecter AchievementRepository mocké dans un vrai AchievementChecker
      - PHPUnit 13 gotcha: createMock() sans expects() génère un notice → utiliser createStub() OU ajouter #[AllowMockObjectsWithoutExpectations] sur la classe de test
      - Reflection pour accéder aux propriétés private dans les tests Entity: new \ReflectionProperty(Cls::class, 'field')->setValue($obj, $val)
      - willReturnCallback() sur un mock déjà configuré avec willReturn() dans setUp() → le setUp() prend le dessus (PHPUnit 13 first-wins) → ne pas mettre de stubs globaux dans setUp() si le test a besoin d'overrider

architecture:
  pattern: MVC Symfony (Controller > Service > Repository > Entity)
  frontend: SSR Twig + hydratation légère Alpine.js (pas de SPA)
  api: REST via controllers Symfony (pas d'API Platform)
  admin: EasyAdmin (back-office séparé)
  gamification:
    entities:
      - UserProgression (xp, level computed — niveaux infinis, OneToOne Users, gamificationEnabled, equippedBadge, totalSpicesRead, currentReadingStreak, longestReadingStreak, lastReadDate, discoveries)
      - Achievement (slug, name, trigger_type enum, triggerValue, xpReward, rarity enum, easterEggSlug nullable)
      - UserAchievement (joint table UserProgression <-> Achievement, unlockedAt)
      - AchievementProgress (user, achievement, progress int, isCompleted computed via property hook)
      - PendingGamificationNotification (user, type, payload json, createdAt, deliveredAt nullable) — file Turbo Streams
      - SpiceView (user, spice, viewedDay — unique par jour)
      - UserStat (OneToOne Users — totalMatches, totalSpicesRead, easterEggsFound, totalGamesPlayed, perfectScores, visitedAromaticGroups json, lastVisitedSpices json FIFO 10, totalActions computed)
      - GameSession (user, gameMode enum, difficulty enum, score, correctAnswers, totalQuestions, startedAt, finishedAt nullable, durationSeconds, accuracy computed, isFinished computed, setDurationSeconds() pour override LC)
      - GameQuestion (session ManyToOne GameSession, questionIndex, questionData json, answerGiven, isCorrect, timeSpentMs)
    enums:
      - AchievementTrigger: FIRST_MATCH, N_MATCHES, N_SPICES_USED, FIRST_DISCOVERY, N_FAVORITES, SPICE_READ, READING_STREAK, EASTER_EGG_FOUND, ALL_TERPENES_VISITED, FIRST_GAME, N_GAMES_COMPLETED
      - AchievementRarity: COMMON/RARE/EPIC/LEGENDARY — méthode label() → Graine/Infusion/Extraction/Essence
      - GameMode: QCM / SURVIVAL / GUESS_WHO / INTRUS / HANGMAN / CHRONO — tous isEnabled(), label() + xpPerCorrect() + isLiveComponent() + description() + icon() + totalQuestions()
        Labels affichés: QCM→"Le Choix du Chef", SURVIVAL→"Défi de Scoville", GUESS_WHO→"Palais Fin", INTRUS→"Hors Saison", HANGMAN→"Cuisson en Cours", CHRONO→"À Feu Vif"
        Section navbar: "L'Académie" (dropdown desktop + section mobile)
      - GameDifficulty: EASY / MEDIUM / HARD — xpMultiplier() (1.0 / 1.5 / 2.0)
    level_formula: "level = floor((xp / 100) ** (1 / 1.3)) + 1  — niveaux infinis (cap 50 supprimé)"
    xp_sources: "match_saved +10, spice_read +5 (nouvelle vue seulement), easter_egg +75 (défaut), game_completed variable, achievement_reward variable"
    fixtures:
      - 21 achievements en DB (11 originaux + 10 v2 via docs/achievements_v2.sql)
      - ⚠️ Toujours utiliser INSERT IGNORE SQL direct (pas les fixtures Doctrine — elles purgent)
      - ⚠️ La colonne DB s'appelle trigger_type (pas trigger — mot réservé MariaDB)
      - ⚠️ Valeurs enum en minuscules en DB (common/rare/epic/legendary, n_matches, etc.)
    services:
      - CompatibilityScoreService     # scores 0-100 avec mainCompounds, secondaryCompounds, alchemyFlavors
      - GamificationManager           # orchestrateur central — Strategy pattern, guard opt-out, persist notifications
      - GamificationManagerProxy      # proxy pour lazy-loading (évite injection circulaire)
      - NullGamificationManager       # no-op quand gamification désactivée
      - GamificationManagerInterface  # interface commune (dans App\Gamification\)
      - AchievementChecker            # final class — maps eventType → triggers → isMet() — injecter repo, pas mocker
      - EasterEggService              # gère les slugs d'easter eggs, dispatch events
      - GamificationHandler           # async Messenger (MatchSavedEvent → manager 'match_saved')
      - FavoriteGamificationHandler   # async Messenger (FavoriteToggledEvent → manager 'favorite_toggled', count idempotent via SpicyMatchHistoryRepository)
      - SpiceReadGamificationHandler  # async Messenger (SpiceReadEvent → manager 'spice_read')
      - EasterEggGamificationHandler  # async Messenger (EasterEggFoundEvent → manager 'easter_egg_found')
    dead_code_supprime:
      - "GamificationEngine (renommé → GamificationManager + interface)"
      - "GamificationEngineTest (renommé → GamificationManagerTest)"
    event_subscriber:
      - GamificationNotificationSubscriber (KernelEvents::RESPONSE priority -10) — injecte Turbo Streams avant </body>
    xp_strategies:
      - MatchXpStrategy    (match_saved → +10 fixe)
      - SpiceReadXpStrategy (spice_read → +5 si context['isNewView'], sinon 0)
      - EasterEggXpStrategy (easter_egg_found → context['xpAmount'] ?? 75)
      - Tag services: gamification.xp_strategy — injectés via !tagged_iterator dans GamificationManager
    routes_gamification:
      - POST /api/gamification/egg/{slug} → api_gamification_egg (EasterEggController, CSRF: easter_egg via X-CSRF-Token header)
      - POST /users/gamification/toggle   → toggle_gamification_user (CSRF: toggle_gamification)
      - POST /users/badge/equip/{id}      → equip_badge_user (CSRF: equip_badge_{id})
    avatar:
      - Le badge équipé (UserProgression::$equippedBadge) EST l'avatar : son icône + la couleur de sa rareté
      - Composant: templates/components/_avatar.html.twig
        → paramètre `equippedBadge` (UserAchievement|null) prioritaire sur `slug`
        → couleurs rareté : common=#f5f5f4/#78716c, rare=#dbeafe/#1d4ed8, epic=#f3e8ff/#7e22ce, legendary=#fef9c3/#a16207
        → fallback slug : avatar_data(slug) via AvatarExtension (dead code — conservé pour la migration)
      - Sélection avatar supprimée de configuration.html.twig — route avatar_upload_user supprimée
      - Dead code (à supprimer dans une prochaine passe) : AvatarCatalogService, AvatarExtension, Users::$avatar

  education:
    description: "Mini-jeux éducatifs sur les épices — 6 modes actifs (QCM route-based + 5 Live Components)"
    services:
      - GameSessionManager         # crée sessions, valide réponses, calcule XP, limite 5 sessions/jour/mode + createFinishedSession() pour LC
      - AcademyManager             # logique de jeu centrale — compatibilité, intrus, cartes épices, normalisation, génération questions. Cache pool academy.cache (TTL 1h)
      - QcmQuestionGenerator       # implémente QuestionGeneratorInterface, mode QCM "Mélange à trou"
      - QuestionGeneratorInterface # supports(GameMode) + generate(difficulty, excludeIds) — QCM uniquement
    live_components:
      - IntrusGame                 # QCM L'Intrus — 10 questions, classique (trouver l'intrus) + inversé (trouver le compatible)
      - SurvivalGame               # Mode Survie — chaîne d'épices compatibles, game over au 1er faux, victoire si pool épuisé
      - GuessWhoGame               # Guess Who — 8 questions, indices progressifs, scoring dégressif par indice révélé
      - HangmanGame                # Pendu — 8 mots, SVG pendu, clavier A-Z, gestion accents via Transliterator, hint par difficulté
      - ChronoGame                 # Mode Chrono — timer global Alpine.js (data-live-ignore), scoring vitesse + streak, anti-triche timestamp
    event_listener:
      - AcademyCacheInvalidator    # Doctrine postUpdate/postPersist/postRemove sur Spices → invalide academy.spice_cards + academy.intruders.{id}
    controller: EducationController
    routes:
      - "GET  /education/                  → education_index"
      - "POST /education/start             → education_start (CSRF: education_start) — QCM uniquement"
      - "GET  /education/play/{id}         → education_play — QCM uniquement"
      - "POST /education/answer/{id}       → education_answer (CSRF: education_answer) — QCM uniquement"
      - "GET  /education/play-live/{mode}  → education_play_live — LC modes (vérifie limite quotidienne 5/jour/mode)"
      - "GET  /education/result/{id}       → education_result — multi-mode (QCM détail questions, LC résumé simple)"
    anti_farming: "Max 5 sessions/jour/mode, XP × 0.5 après la 3e session"
    gamification_event: GameCompletedEvent (dispatché via Messenger quand session terminée — QCM et LC)
    cache:
      pool: academy.cache (adapter: filesystem, TTL: 3600)
      keys:
        - "academy.spice_cards — cartes complètes de toutes les épices (composés, flaveurs, tips, etc.)"
        - "academy.intruders.{spiceId} — épices incompatibles (score 0) par épice"
      bind: "config/services.yaml — App\\Service\\Education\\AcademyManager $cache: '@academy.cache'"
    security_pattern: "Réponses correctes stockées en session HTTP (game_{token}), jamais en #[LiveProp] (sérialisé côté client)"
    architectural_decisions:
      - "AcademyManager = logique de jeu (questions, compatibilité, normalisation), GameSessionManager = persistance (sessions, XP, events)"
      - "LC modes utilisent createFinishedSession() — pas de GameQuestion rows, seulement le résumé (correctCount/totalQuestions/duration)"
      - "QCM existant inchangé — flux route-based classique cohabite avec les 5 LC modes via play_live.html.twig"
      - "SpicesRepository::findIncompatibleWith() — SQL NOT EXISTS (4 subqueries main×main, main×sec, sec×main, sec×sec) pour trouver les épices 0-compatibilité"

  rgpd:
    cookie_consent:
      - CookieConsentService       # hasConsented(), saveConsent(), respectsDnt(), versioning
      - ConsentController          # POST /consent/save (CSRF: cookie_consent via _token dans body JSON)
      - Cookie: sm_consent (JSON: analytics, functional, version, timestamp)
      - Template: templates/components/_cookie_consent.html.twig (Alpine.js)
      - Respect DNT header: analytics = false si DNT=1

  csrf_protection:
    patterns:
      - "Form classique: hidden input _token + isCsrfTokenValid()"
      - "JSON body: _token dans le payload JSON (rename, consent)"
      - "Header: X-CSRF-Token (toggleFavorite, easter eggs)"
    protected_endpoints:
      - "POST /spicymatch/history/{id}/rename — CSRF: history_action_{id} via body _token"
      - "POST /spicymatch/history/{id}/favorite/toggle — CSRF: history_action_{id} via header X-CSRF-Token"
      - "POST /api/gamification/egg/{slug} — CSRF: easter_egg via header X-CSRF-Token"
      - "POST /consent/save — CSRF: cookie_consent via body _token"
      - "POST /education/start — CSRF: education_start via form _token"
      - "POST /education/answer/{id} — CSRF: education_answer via form _token"

js_interop:
  alpine:
    - x-data / x-show / x-cloak pour modals et toggles
    - [x-cloak] { display: none !important } dans app.scss
    - Modals self-contained avec x-data="{ open: false }" par composant
    - Fetch pattern: toujours try/catch + response.ok check (pas de fetch sans error handling)
  importmap: assets/importmap.php  # déclarer les dépendances JS (version + CDN)
  live_component_gotchas:
    - "data-model ne fonctionne QUE dans le template du LiveComponent — jamais depuis un include externe"
    - "Pour les filtres du Lab, tout doit être dans SpicyMatch.html.twig (data-model='filterAgId', 'filterStId', 'search')"
    - "Pour reset dans LC: LiveAction resetFilters() appelé via data-action='live#action' data-live-action-param='resetFilters'"
    - "SpicyMatch::findAllSpices() inclut agId et stId — CompatibilityScoreService aussi"
    - "data-model='debounce(search, 150)' → INVALIDE dans cette version du LC — utiliser data-model='search' (debounce natif par défaut)"
    - "LiveAction qui retourne RedirectResponse → fonctionne pour finish() des jeux (pattern validé par IntrusGame/SurvivalGame etc.)"
    - "Alpine.js state dans un LC : utiliser data-live-ignore sur les conteneurs Alpine qui gèrent du state local (ex: timer Chrono)"
    - "Double-click prevention : x-data='{ submitting: false }' + @click='if (!submitting) { submitting = true; $wire.action() }' + :disabled='submitting'"
    - "Pour les actions qui doivent permettre plusieurs clics (ex: clavier pendu) : utiliser $wire.action().then(() => { submitting = false })"

design_system:
  fichier_source: assets/styles/app.css
  tokens_theme: "@theme { --color-*, --font-display, --spacing }"
  palette:
    saffron:  "accent primaire — orange chaud (400→800)"
    paprika:  "accent secondaire — rouge profond (700→900)"
    turmeric: "accent tertiaire — jaune doré (300→600)"
    cream:    "fond principal (#FDFCF0) et fond sombre (#F5F0E0)"
    spice-surface: "#FFF7ED — fond de carte/section"
    spice-border:  "#FED7AA — bordure chaude"
  regles:
    - "Jamais orange-* ni amber-* Tailwind natif — utiliser saffron-* et turmeric-*"
    - "Cartes hover → classe card-warm (pas bg-white border-stone-200)"
    - "Boutons → btn-pill-primary / btn-pill-outline"
    - "Tags → tag-primary / tag-secondary"
    - "Focus rings → focus:ring-saffron-600/30"
    - "Icônes brand → text-saffron-600 ou text-turmeric-500 (pas text-orange-* ni text-amber-*)"
    - "Inline style autorisé SEULEMENT pour les couleurs dynamiques depuis la BDD (ex: aromaticGroups.color)"
  tailwind_v4_gotchas:
    - "Déclarer --spacing: 0.25rem dans @theme pour débloquer h-*, w-* numériques"
    - "backdrop-blur-md et blur-3xl/2xl injectés manuellement via @layer utilities dans app.scss"
    - "Le tailwind.config.js v3 est ignoré par le CLI v4 — utiliser @source dans le SCSS"
    - "@source chemin relatif au fichier SCSS: '../../templates/**/*.html.twig'"
    - "Après tout changement de classes, relancer: yarn build"
  composants_reference:
    navbar:         "templates/components/_navbar.html.twig — sticky cream/80 backdrop-blur h-20 z-50"
    footer:         "templates/components/_footer.html.twig — bg-paprika-900 text-cream 4 colonnes"
    search:         "templates/components/Search.html.twig — bg-white/80 rounded-full border-spice-border"
    hero:           "templates/home/index.html.twig — grid 2 cols lg, fond bg-cream-dark + bg-noise"
    avatar:         "templates/components/_avatar.html.twig — badge équipé prioritaire (icône + couleur rareté), fallback slug"
    lab_filters:    "SpicyMatch.html.twig — filtres (groupeAro + type + search) DANS le LiveComponent via data-model"
    catalog_filters: "templates/components/_spices_filters.html.twig — filtres GET → Turbo Frame spices_frame_id (catalogue uniquement)"
    history_index:  "templates/spicy_match_history/index.html.twig — liste avec renommage inline (group + crayon hover) + toggle favori"
    history_view:   "templates/spicy_match_history/view.html.twig — recette + section éducative composés aromatiques partagés"
    history_favorites: "templates/spicy_match_history/favorites.html.twig — page dédiée aux mélanges favoris (route: favorites_spicy_match_history)"
    education_index:    "templates/education/index.html.twig — sélection des 6 modes (cards Alpine.js, difficulté, QCM POST / LC GET)"
    education_play:     "templates/education/play.html.twig — interface de jeu QCM (route-based)"
    education_play_live: "templates/education/play_live.html.twig — wrapper pour les 5 Live Components (dispatch par mode.value)"
    education_result:   "templates/education/result.html.twig — bilan session multi-mode (icône + label dynamique, Rejouer pour LC, détail questions QCM)"
    intrus_game:        "templates/components/Education/IntrusGame.html.twig — QCM L'Intrus (question active / feedback / terminé)"
    survival_game:      "templates/components/Education/SurvivalGame.html.twig — sélection départ / jeu / game over-victoire"
    guess_who_game:     "templates/components/Education/GuessWhoGame.html.twig — indices progressifs / guess / feedback"
    hangman_game:       "templates/components/Education/HangmanGame.html.twig — SVG pendu + mot masqué monospace + clavier A-Z"
    chrono_game:        "templates/components/Education/ChronoGame.html.twig — timer Alpine.js data-live-ignore + carte épice sans nom + options"
    cookie_consent:     "templates/components/_cookie_consent.html.twig — bannière RGPD Alpine.js avec CSRF"
    gamification_notif: "templates/gamification/notification.stream.html.twig — Turbo Stream notifications XP/achievements"
```
