# Moderation Queue

The moderation queue lets profile owners and managers review links submitted via the API before they appear on the public profile. Pending links are listed at `/studio/moderation` and can be approved or rejected individually.

## Overview

When a link is submitted with `status = pending`, it is held out of the public profile view until approved. A view composer intercepts the `linkstack.linkstack` template render and strips any non-published links, so pending and rejected links never appear publicly — even without modifying any LinkStack core files.

## Routes

| Method | URL | Middleware | Description |
|---|---|---|---|
| `GET` | `/studio/moderation` | `web`, `auth`, `blocked` | List pending links for the authenticated profile |
| `POST` | `/studio/moderation/{id}/approve` | `web`, `auth`, `blocked` | Approve a pending link |
| `POST` | `/studio/moderation/{id}/reject` | `web`, `auth`, `blocked` | Reject a pending link |

All three routes are scoped to the authenticated user — a manager can only act on links belonging to their own profile. Cross-profile changes are structurally impossible: approve and reject queries include a `WHERE user_id = Auth::id()` guard.

## Customising the View

The moderation view can be published and overridden within the host application's own layout:

```bash
php artisan vendor:publish --tag=linkstack-shared-profiles-views
```

The template is published to:

```
resources/views/vendor/linkstack-shared-profiles/moderation/index.blade.php
```

Extend your host application's layout from there as needed.

---

Previous: [Platform Authentication](02-platform-authentication.md) · Next: [Adding a Provider](04-adding-a-provider.md)
