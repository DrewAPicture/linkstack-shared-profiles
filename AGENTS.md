# Shared Profiles for LinkStack — Agent Guidelines

## Stop Conditions

**Stop and ask the user before proceeding if any of the following apply:**

- You are about to commit, push, or modify git history in any way
- The working tree is dirty before a branch switch, reset, or merge
- You are considering a force push — this is never permitted
- You are unsure whether a destructive action is reversible
- A file you are about to commit contains sensitive or identifying information (SSH ports, server paths, credentials, local filesystem paths) — strip it first

Commit authorization is **task-scoped**: explicit approval to commit during one task does not carry over to follow-up tasks or future sessions. Always assume you do not have commit permission unless the user has said so in the current task.

See full protocol: [`.agent/context/best-practices/git-safety-protocol.md`](.agent/context/best-practices/git-safety-protocol.md)

---

## Project

Shared Profiles for LinkStack is a self-contained **Laravel package** that installs into [LinkStack](https://github.com/LinkStackOrg/LinkStack) via Composer. It adds three features to a standard LinkStack installation without modifying any core LinkStack files:

1. **API link submission** — a `POST /api/links` endpoint that accepts links on behalf of a shared profile, authenticated via a bearer token stored on the profile account
2. **Telegram multi-user management** — multiple Telegram users can authenticate and manage one shared LinkStack profile; supports both browser-based Socialite login and Telegram Mini App `initData` flows
3. **Link moderation queue** — API-submitted links land in a `pending` state and must be approved by a moderator before appearing on the public profile

**Host application:** LinkStack is a Laravel 10 / PHP 8.2 app. The package targets that version range.

**Integration points (no core files modified):**
- A **view composer** on `linkstack.linkstack` filters pending/rejected links before the Blade template renders
- Package routes are loaded by the service provider at distinct URL prefixes (`/api/links`, `/telegram-auth`, `/studio/moderation`)
- Two migrations add a `status` column to `links` and an `api_token` column to `users`, plus create a `telegram_managers` table
- Socialite is extended with the Telegram driver via the service provider

**Key constraints:**
- `UserController::littlelink()` uses a raw `DB::table()` query — not Eloquent — so the public profile link list cannot be intercepted via a model global scope; the view composer is the correct hook
- Ownership checks in LinkStack use a single `user_id` equality check; Telegram managers authenticate *as* the shared profile's `user_id` so all existing guards pass without modification
- The `status` column defaults to `'published'` so all pre-existing links and studio-created links are unaffected

---

## Best Practices

- [Commit Messages](.agent/context/best-practices/commit-messages.md) — imperative tense, no emoji, HEREDOC format
- [Git Safety Protocol](.agent/context/best-practices/git-safety-protocol.md) — check working tree, no force push, commit scope
- [Accessibility](.agent/context/best-practices/accessibility.md) — WCAG 2.2 Level AA for all rendered views
- [GitHub Actions](.agent/context/best-practices/github-actions.md) — pin actions to SHAs, keep secrets out of workflow files
