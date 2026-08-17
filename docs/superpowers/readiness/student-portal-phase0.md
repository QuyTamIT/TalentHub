# Student Portal Phase 0 readiness

Run the readiness gate from the repository root:

```powershell
$php = '<PATH_TO_PHP_EXECUTABLE>'
& $php app\learner\tools\readiness-check.php --phase=0 --format=text
& $php app\learner\tools\readiness-check.php --phase=1 --format=json
```

Phase 0 has no database dependency. Phases 1–11 require database mode and the following variable names:

```text
TALENTHUB_LEARNER_SOURCE=database
TALENTHUB_DB_HOST=<DATABASE_HOST>
TALENTHUB_DB_PORT=<DATABASE_PORT>
TALENTHUB_DB_NAME=<DATABASE_NAME>
TALENTHUB_DB_USER=<DATABASE_USER>
TALENTHUB_DB_PASS=<DATABASE_PASSWORD>
```

Exit codes are `0` (`READY`), `2` (`NOT_READY`), and `3` (`BLOCKED`). A database connection or schema failure is `BLOCKED`; it never switches to mock mode. The JSON output deliberately contains variable names and readiness messages only, never credential values.
