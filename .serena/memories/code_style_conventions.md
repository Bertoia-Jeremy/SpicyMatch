# SpicyMatch — Code Style & Conventions

## PHP
- PSR-12, short array syntax `[]`
- PHP 8+ attributes (no Doctrine annotations)
- ECS (Easy Coding Standard) + PHPStan level 6
- Rector: SymfonySetList SYMFONY_72, CODE_QUALITY, CONSTRUCTOR_INJECTION

## Architecture
- Pattern: MVC Symfony (Controller → Service → Repository → Entity)
- Frontend: SSR Twig + Alpine.js (no SPA)
- REST via Symfony controllers (no API Platform)

## Doctrine
- Schema: `schema:update --force` (no migrations)
- ⚠️ `trigger` is a reserved MariaDB word → use `name: 'trigger_type'` in #[ORM\Column]
- Enum values stored lowercase in DB

## Commits
- Conventional Commits: feat/fix/chore/refactor + optional scope

## Design System (Tailwind)
- Never use native `orange-*` or `amber-*` → use `saffron-*` and `turmeric-*`
- Cards hover → `card-warm`
- Buttons → `btn-pill-primary` / `btn-pill-outline`
- Tags → `tag-primary` / `tag-secondary`
- Inline style allowed ONLY for dynamic DB colors (e.g. aromaticGroups.color)
- After any class change: run `yarn build`

## Testing
- No custom TestCase base — extends `PHPUnit\Framework\TestCase` for units
- `final` classes cannot be mocked → inject mocked dependency into real instance
- PHPUnit 13: use `createStub()` OR `#[AllowMockObjectsWithoutExpectations]` to avoid notices
- Use `ReflectionProperty` to set private properties in Entity tests
- Do NOT set global stubs in setUp() if a test needs to override them (PHPUnit 13 first-wins)

## CSRF
- Classic form: hidden `_token` + `isCsrfTokenValid()`
- JSON body: `_token` in payload
- Header: `X-CSRF-Token` (toggleFavorite, easter eggs)

## Alpine.js
- `x-data` / `x-show` / `x-cloak` for modals and toggles
- Always `try/catch` + `response.ok` check on fetch
- In JS methods: use `this.$wire` (not `$wire` — only valid in template expressions)
- `data-live-ignore` on Alpine containers managing local state inside LiveComponents
