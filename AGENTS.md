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

Shared Profiles for LinkStack is a self-contained **Laravel package** (`werdswords/linkstack-shared-profiles`) that installs into [LinkStack](https://github.com/LinkStackOrg/LinkStack) via Composer. It adds three features to a standard LinkStack installation **without modifying any core LinkStack files**:

1. **API link submission** — `POST /api/links`, bearer token auth, links land as `pending`
2. **Telegram multi-user management** — multiple Telegram users authenticate as one shared profile; browser-based Socialite flow and Telegram Mini App `initData` flow
3. **Link moderation queue** — pending links are approved/rejected via `/studio/moderation`

**Host application:** LinkStack is a Laravel 10 / PHP 8.2 app. The package targets that version range.

See [`.agent/context/architecture.md`](.agent/context/architecture.md) for the full file tree, data model, route table, and integration point details.

---

## Context

- [Architecture](.agent/context/architecture.md) — package structure, data model, routes, service provider, feature summaries
- [Testing](.agent/context/testing.md) — test setup pattern, support models, PHPStan gotchas, Socialite mocking, initData signing

---

## Best Practices

- [Commit Messages](.agent/context/best-practices/commit-messages.md) — imperative tense, no emoji, HEREDOC format
- [Git Safety Protocol](.agent/context/best-practices/git-safety-protocol.md) — check working tree, no force push, commit scope
- [Accessibility](.agent/context/best-practices/accessibility.md) — WCAG 2.2 Level AA for all rendered views
- [GitHub Actions](.agent/context/best-practices/github-actions.md) — pin actions to SHAs, keep secrets out of workflow files
- [PHP Imports](.agent/context/best-practices/php-imports.md) — always `use`-import classes; no leading backslashes inline
