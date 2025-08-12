
# Lobsters ➜ Slim PHP Port Vision

## Overview
The goal is to create a faithful port of the Lobsters community site, preserving the **exact frontend design** while replacing the backend with Slim PHP and MySQL/MariaDB.

## Core Objectives
- Match core UX and visuals 1:1 by copying HTML/CSS/assets from the Lobsters repo.
- Maintain feature parity for stories, comments, voting, tagging, invite-only signup, moderation, ranking, caching, feeds, and API.
- Keep database schema isomorphic to simplify migration from the original Rails schema.

---

## Architecture

### Framework & Components
- **Slim 4** (routing, middleware, PSR-7/15 support)
- **PHP-DI** for dependency injection
- **Plates** or **Twig** for templating
- **Eloquent ORM** standalone for database abstraction
- **Symfony Mailer** for email handling
- **Argon2id** password hashing, **TOTP** 2FA (`spomky-labs/otphp`)
- **MySQL/MariaDB** (maintain Lobsters' schema naming where possible)
- **Cron jobs + CLI commands** for background work
- **File or HTTP caching** for fragments/pages
- **Static asset pipeline** with Vite or plain static

### Why This Stack
Mirrors Lobsters' small, low-dependency, SQL-first philosophy while leveraging Slim PHP’s flexibility.

---

## Data Model
Key tables (mirroring Lobsters):
- `users`
- `stories`
- `comments`
- `tags`, `taggings`
- `votes` (story and comment voting)
- `invitations`
- `moderations` / `mod_logs`
- `saved_stories`, `hidden_stories`
- `hats`, `user_hats`

---

## Features

### 1. Story Submission
- URL deduplication
- Self-posts
- Manual tag selection

### 2. Ranking / Hotness
- Time-decay + score algorithm
- Cron-based recalculation

### 3. Comments
- Threaded hierarchy
- Markdown + HTML sanitization
- Collapse/expand UI

### 4. Voting & Karma
- Unique per-user votes
- Karma adjustments
- Moderation immune actions

### 5. Tags
- Story filtering
- Tag moderators (optional)

### 6. Invite-only Signup
- Invitation codes
- Email onboarding
- Rate-limited requests

### 7. Moderation
- Soft deletes
- Domain bans
- Mod logs

### 8. Search
- MySQL FULLTEXT search
- Meilisearch upgrade path

### 9. Feeds & API
- JSON/Atom feeds
- Read-only JSON endpoints

### 10. Caching
- Page/fragment caching
- CLI cache purge

### 11. Emails
- Notifications
- Invitations
- Password resets

### 12. Theming
- Copy Lobsters' CSS/JS/assets
- Preserve layout

### 13. Branding
- Replace Lobster name

---

## Routing Map
- `/` – Front page
- `/newest` – Newest stories
- `/s/:id/:slug` – Story page
- `/stories` – Create story
- `/stories/:id/vote` – Vote on story
- `/comments` – Create comment
- `/comments/:id/vote` – Vote on comment
- `/t/:tag` – Tag listing
- `/u/:username` – User profile
- `/login` / `/logout`
- `/invitations`
- `/feeds/*`
- `/search`

---

## Background Work & Ops
- Cron every 5 minutes:
  - Recompute rankings
  - Expire caches
  - Send queued mail
- Eloquent migrations
- `.env` config for secrets
- Deploy via NGINX + PHP-FPM

---

## Data Migration
1. Snapshot Lobsters DB
2. Create compatible MySQL schema
3. Write ETL scripts to map Rails data to PHP schema
4. Validate row counts and checksums

---

## Security
- CSRF tokens
- SameSite cookies
- Rate limiting
- TOTP 2FA
- HTML sanitization
- Audit logs

---

## Testing
- Unit tests for models/services
- HTTP route tests
- Contract tests for feeds/API
- Load tests for main pages

---

## Roadmap (10 Sprints)
0. Scaffold Slim app
1. Users & invites
2. Stories & tags
3. Comments
4. Voting & ranking
5. Feeds & search
6. Moderation
7. Caching & performance
8. Theming parity
9. Migration & staging
10. Production launch

---

## Next Steps
- Scaffold Slim repo with Composer, DI, ORM, templating, auth skeleton
- Create first migration set for core tables
- Begin porting ERB templates to PHP views
