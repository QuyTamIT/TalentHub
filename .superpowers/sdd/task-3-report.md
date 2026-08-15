# Task 3 — Shared-Core Student Page Context

## Implemented behavior

- Added `StudentAppContext`, a page-level composition root for learner pages that uses the shared `Connection`, `SessionManager`, `AuthService`, `PermissionService`, and `StudentProfileService`.
- The context redirects anonymous, expired, or deactivated sessions to login; routes a wrong role through `AuthPortalRouter`; and sends a missing Student profile to role selection.
- The returned payload contains the refreshed authenticated user, canonical Student profile, baseline dashboard, and shared CSRF token. It has no learner demo identity or learner-specific database configuration.

## TDD evidence

### RED

Command:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_student_app_context_test.php
```

Result: exit 1.

```text
Assertion failed: StudentAppContext exists
```

### GREEN

Commands:

```powershell
& 'D:\xampp\php\php.exe' -l src\Bootstrap\StudentAppContext.php
& 'D:\xampp\php\php.exe' tests\learner_student_app_context_test.php
```

Result: both exit 0.

```text
No syntax errors detected in src\Bootstrap\StudentAppContext.php
learner_student_app_context_test: OK
```

## Verification

- Full learner suite: 12 PHP learner test files and 3 JavaScript learner test files passed (15 files total).
- Task-scoped whitespace/diff checks passed before commit.
- Self-review confirmed the context rejects a cached non-student role, refreshes the cached user against shared identity data, requires `student_profile.read_own`, destroys only invalid sessions, preserves database-connection exceptions for the Task 5 boundary, and does not touch teacher, school, or enterprise paths.

## Changed files

- `src/Bootstrap/StudentAppContext.php`
- `tests/learner_student_app_context_test.php`
- `.superpowers/sdd/task-3-report.md`

## Commit

`feat(student): add shared authenticated page context`

## Concerns

- Existing unrelated learner, design, documentation, migration, and test working-tree changes were preserved and excluded from this task's commit.

## Review-fix addendum — refreshed role validation

The context now validates the role returned by `AuthService::current()` before refreshing the session or checking `student_profile.read_own`. A valid session whose role changed since login is redirected through `AuthPortalRouter::destination()` for the refreshed role.

### Regression TDD evidence

RED command:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_student_app_context_test.php
```

RED result: exit 1.

```text
Assertion failed: context rechecks refreshed user role
```

GREEN commands:

```powershell
& 'D:\xampp\php\php.exe' -l src\Bootstrap\StudentAppContext.php
& 'D:\xampp\php\php.exe' tests\learner_student_app_context_test.php
```

GREEN result: both exit 0.

```text
No syntax errors detected in src\Bootstrap\StudentAppContext.php
learner_student_app_context_test: OK
```

The regression contract requires the refreshed-role guard and portal redirect to occur before both `refreshUser($user)` and Student permission enforcement.

### Verification

- Full learner suite: 12 PHP learner test files and 3 JavaScript learner test files passed (15 files total).
- Task-scoped whitespace/diff checks passed before the review-fix commit.
