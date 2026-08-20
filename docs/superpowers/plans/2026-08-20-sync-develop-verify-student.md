# Sync Develop and Verify Student Integration Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to execute this plan task-by-task with verification checkpoints.

**Goal:** Safely bring approved code and database migrations from `origin/develop` into `feature/student`, verify the learner work without affecting Teacher, School, or Enterprise roles, and commit only verified changes.

**Architecture:** Preserve the current learner worktree changes, compare `feature/student` with the fetched `origin/develop`, and merge only after the worktree is checkpointed. Treat database changes as migration/seed files first, then validate the Laragon MySQL runtime before applying any pending local migrations. Run focused learner/AI tests plus protected-role syntax and regression checks before committing.

**Tech Stack:** Git, PHP 8.3.30 via Laragon/XAMPP CLI, MySQL 8.4.3 via Laragon, JavaScript tests via Node.js when available, repository PHP test scripts.

## Global Constraints

- Do not run `git reset --hard`, `git checkout --`, recursive deletion, or any destructive database cleanup.
- Do not overwrite or discard existing user changes in the worktree.
- Do not commit `.claude/settings.local.json`, `.qwen/settings.json`, `.env`, credentials, or API keys.
- Keep Teacher, School, Enterprise, `src`, and `api` behavior unchanged unless an explicit merge conflict requires review.
- Do not call a real 9Router/Gemini endpoint; use mock-provider tests only unless a separately configured staging secret is explicitly available.
- Apply local database migrations only after inspecting the merge result and confirming they are forward-safe and pending in the configured Laragon database.

---

### Task 1: Audit current worktree and fetched develop

**Files:**
- Read: `git status`, branch refs, current diffs, and `origin/develop`
- Do not modify application files

- [ ] **Step 1: Confirm the current branch and dirty scope**

Run:

```powershell
git status --short --branch
git diff --name-only
git ls-files --others --exclude-standard
```

Expected: current branch is `feature/student`; existing learner changes remain visible; no destructive cleanup is needed.

- [ ] **Step 2: Compare the current branch with fetched `origin/develop`**

Run:

```powershell
git rev-list --left-right --count feature/student...origin/develop
git log --oneline --decorate feature/student..origin/develop
git diff --stat feature/student...origin/develop
git diff --name-status feature/student...origin/develop
```

Expected: the exact commits and files to merge are recorded before any merge.

---

### Task 2: Verify current learner changes before checkpoint commit

**Files:**
- Test: `tests/learner_ai_9router_shadow_integration_test.php`
- Test: learner assessment, catalog, career-group, and recommendation suites selected from the current diff
- Protect: `src/`, `api/`, `app/teacher/`, `app/school/`, `app/enterprise/`

- [ ] **Step 1: Run the focused 9Router shadow integration test**

Run:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_ai_9router_shadow_integration_test.php
```

Expected: exit code `0` and `learner_ai_9router_shadow_integration_test: OK`; no real network request is made.

- [ ] **Step 2: Validate the configured Laragon database without mutation**

Run:

```powershell
& 'D:\xampp\php\php.exe' bin\migrate.php validate
```

Expected: exit code `0`, no pending migrations, and no schema drift. If the connection fails, stop database mutation and report the exact sanitized failure.

- [ ] **Step 3: Run PHP syntax checks over changed learner files**

Run:

```powershell
$files = git diff --name-only -- '*.php'; git ls-files --others --exclude-standard -- '*.php' | ForEach-Object { $files += $_ }; $files | Sort-Object -Unique | ForEach-Object { & 'D:\xampp\php\php.exe' -l $_ }
```

Expected: no syntax errors.

- [ ] **Step 4: Review protected-role diff scope**

Run:

```powershell
git diff --name-only -- app/teacher app/school app/enterprise src api
```

Expected: no output. Any output requires manual conflict review before commit.

---

### Task 3: Checkpoint verified current changes

**Files:**
- Stage only the learner implementation, migrations, seeds, tests, and readiness documents belonging to this task
- Exclude: `.claude/settings.local.json`, `.qwen/settings.json`, `.env`, and unrelated local files

- [ ] **Step 1: Inspect the exact staged candidate**

Run:

```powershell
git add app/learner assets/js/learner-recommendations.js bin/bootstrap.php Database/migrations Database/seeds/learner docs/superpowers tests
git restore --staged .claude/settings.local.json .qwen/settings.json 2>$null
git diff --cached --stat
git diff --cached --name-only
```

Expected: only the reviewed learner/assessment/AI files are staged; local settings and secrets are not staged.

- [ ] **Step 2: Commit the verified checkpoint**

Run:

```powershell
git commit -m "feat(learner): harden assessment and AI shadow readiness"
```

Expected: commit succeeds and the worktree is clean except for intentionally excluded local files.

---

### Task 4: Merge `origin/develop` into `feature/student`

**Files:**
- Modify: files reported by `git diff --name-status feature/student...origin/develop`
- Review: every merge conflict, especially database migrations and protected-role paths

- [ ] **Step 1: Merge the fetched branch**

Run:

```powershell
git merge --no-ff origin/develop -m "merge: sync develop into student"
```

Expected: merge completes without conflicts. If conflicts occur, stop and review each conflict; do not auto-resolve protected-role or migration conflicts.

- [ ] **Step 2: Verify the merge scope**

Run:

```powershell
git diff HEAD^1..HEAD --name-only -- app/teacher app/school app/enterprise src api
git diff --check HEAD^1..HEAD
```

Expected: no protected-role files changed by the merge unless explicitly reviewed, and no whitespace errors.

---

### Task 5: Validate merged code and Laragon database

**Files:**
- Read: merged migrations and seeds under `Database/`
- Test: learner and protected-role suites

- [ ] **Step 1: Validate schema state after merge**

Run:

```powershell
& 'D:\xampp\php\php.exe' bin\migrate.php validate
```

Expected: exit code `0`, or a clearly identified list of pending forward migrations. Apply only reviewed local migrations with the repository migration command, then re-run validation.

- [ ] **Step 2: Run focused learner and AI tests**

Run:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_ai_9router_shadow_integration_test.php
& 'D:\xampp\php\php.exe' tests\learner_assessment_api_test.php
& 'D:\xampp\php\php.exe' tests\learner_assessment_catalog_test.php
```

Expected: all commands exit `0` with no failed assertions.

- [ ] **Step 3: Run protected-role regression tests available in the repository**

Run the repository's existing Teacher, School, Enterprise, and shared auth test scripts discovered during audit. Expected: no regression and no protected-role file changes.

- [ ] **Step 4: Confirm final diff and worktree hygiene**

Run:

```powershell
git diff --check
git status --short --branch
```

Expected: no whitespace errors; only intentionally excluded local settings may remain untracked.

---

### Task 6: Final commit handoff

**Files:**
- Read: final `git log`, `git status`, and test outputs

- [ ] **Step 1: Confirm commits and verification evidence**

Run:

```powershell
git log --oneline --decorate -5
git status --short --branch
```

Expected: the learner checkpoint and develop-sync merge are present; verification commands have exit code `0`.

- [ ] **Step 2: Report exact outcome**

Report the commit IDs, tests run, database validation result, merge conflicts (if any), and any remaining local-only files. Do not claim production readiness if the live provider governance gates remain incomplete.
