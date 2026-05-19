# SpicyMatch — Gamification Architecture

## Key Entities
- `UserProgression`: xp, level (computed, infinite), gamificationEnabled, equippedBadge, streaks, discoveries
- `Achievement`: slug, name, trigger_type enum, triggerValue, xpReward, rarity
- `UserAchievement`: join table UserProgression ↔ Achievement
- `AchievementProgress`: progress int, isCompleted (property hook)
- `PendingGamificationNotification`: Turbo Streams queue
- `SpiceView`: user, spice, viewedDay (unique per day)
- `UserStat`: totalMatches, totalSpicesRead, easterEggsFound, totalGamesPlayed, etc.
- `GameSession`: user, gameMode, difficulty, score, correctAnswers, isFinished (computed)
- `GameQuestion`: session, questionIndex, questionData json, answerGiven, isCorrect

## Key Services
- `GamificationManager`: central orchestrator, Strategy pattern
- `GamificationManagerProxy`: proxy for lazy-loading (avoids circular injection)
- `NullGamificationManager`: no-op when gamification disabled
- `AchievementChecker`: final class — maps eventType → triggers → isMet()
- `EasterEggService`: manages easter egg slugs

## Enums
- `AchievementTrigger`, `AchievementRarity`, `GameMode`, `GameDifficulty`
- `GameMode` labels: QCM→"Le Choix du Chef", SURVIVAL→"Défi de Scoville", GUESS_WHO→"Palais Fin", INTRUS→"Hors Saison", HANGMAN→"Cuisson en Cours", CHRONO→"À Feu Vif"

## XP Sources
- match_saved: +10, spice_read: +5 (new view only), easter_egg: +75, game_completed: variable
- Level formula: `floor((xp / 100) ** (1 / 1.3)) + 1`

## Async Handlers (Messenger)
- `GamificationHandler`, `FavoriteGamificationHandler`, `SpiceReadGamificationHandler`, `EasterEggGamificationHandler`
