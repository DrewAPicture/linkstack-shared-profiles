# PHP Import Style

Always import classes with a `use` statement at the top of the file, even when the class lives in the global namespace. Never reference a class with a leading backslash inline.

```php
// correct
use SensitiveParameter;

public function sendMessage(#[SensitiveParameter] string $botToken): bool { ... }

// wrong — leading backslash is a sign the import is missing
public function sendMessage(#[\SensitiveParameter] string $botToken): bool { ... }
```

This applies to:

- PHP built-in classes (`stdClass`, `Throwable`, `Generator`, …)
- PHP 8.x attributes (`SensitiveParameter`, `Override`, …)
- Global-namespace classes from any dependency

**Why:** Inline backslashes are easy to miss in review, inconsistent with how namespaced classes are referenced, and rejected by Pint's `fully_qualified_strict_types` fixer if it is ever enabled. A `use` statement at the top of the file is always unambiguous.
