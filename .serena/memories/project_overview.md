# SpicyMatch — Project Overview

## Purpose
Full-stack aromatic matching application (spices, aromatic compounds, aromatic groups, preparation methods) with user space, gamification, and admin back-office.

## Tech Stack
- **Backend**: Symfony 7.4, PHP 8.4, Doctrine ORM 3.x (attributes, schema:update — no migrations)
- **Frontend**: Twig 3.x SSR + Alpine.js 3.14.9 + Symfony UX (LiveComponent, Turbo, Stimulus)
- **CSS**: Tailwind CSS 4.2.x (CLI build, no PostCSS/Webpack)
- **DB**: MariaDB 10.4 (Docker, DSN: mysql://root:root@mysql:3306/spicymatch)
- **Async**: Symfony Messenger (Doctrine transport)
- **Admin**: EasyAdmin 4.x
- **Assets**: Symfony AssetMapper (importmap.php)

## Key Directories
- `src/Controller/` — Symfony controllers
- `src/Entity/` — Doctrine entities (PHP 8 attributes)
- `src/Service/` — Business logic services
- `src/Service/Education/` — Mini-games (AcademyManager, GameSessionManager, etc.)
- `src/Gamification/` — Gamification interfaces & strategies
- `src/Repository/` — Doctrine repositories
- `src/Enum/` — PHP 8.1 enums (GameMode, GameDifficulty, AchievementTrigger, etc.)
- `src/MessageHandler/` — Async Messenger handlers
- `src/Twig/Components/` — LiveComponent classes
- `templates/` — Twig templates
- `templates/components/Education/` — LiveComponent templates (IntrusGame, SurvivalGame, etc.)
- `assets/styles/app.css` — Tailwind design system (tokens: saffron-*, paprika-*, turmeric-*, cream)
