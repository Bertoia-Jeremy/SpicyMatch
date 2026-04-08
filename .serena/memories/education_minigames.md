# SpicyMatch — Education Mini-games

## Architecture
- `AcademyManager`: game logic (questions, compatibility, normalization). Cache pool `academy.cache` TTL 1h
- `GameSessionManager`: persistence (sessions, XP, events), max 5 sessions/day/mode
- 6 game modes: QCM (route-based) + 5 LiveComponents

## LiveComponents
- `IntrusGame`: Find the intruder, 10 questions
- `SurvivalGame`: Compatible spice chain, game over on first wrong answer
- `GuessWhoGame`: 8 questions, progressive hints, decreasing score
- `HangmanGame`: 8 words, SVG hangman, A-Z keyboard
- `ChronoGame`: Global Alpine.js timer (data-live-ignore), speed scoring

## Routes
- `GET /education/` → `education_index`
- `POST /education/start` → `education_start` (QCM only, CSRF: education_start)
- `GET /education/play/{id}` → `education_play` (QCM only)
- `POST /education/answer/{id}` → `education_answer` (CSRF: education_answer)
- `GET /education/play-live/{mode}` → `education_play_live` (LC modes)
- `GET /education/result/{id}` → `education_result`

## Security
- Correct answers stored in HTTP session (`game_{token}`), never in #[LiveProp]
- Anti-farming: XP × 0.5 after 3rd session/day/mode

## LiveComponent Gotchas
- `data-model` only works inside the LC template, never from external includes
- `LiveAction` returning `RedirectResponse` works for finish() (validated pattern)
- `data-live-ignore` for Alpine containers with local state (e.g. Chrono timer)
