# Local regression checks

Run against a local test database only:

```powershell
C:/xampp/php/php.exe tests/local-regression.php
```

Defaults: MySQL on 127.0.0.1, database `myhonar`, user `root`, empty password.
Override with `TEST_DB_DSN`, `TEST_DB_USER`, and `TEST_DB_PASSWORD` environment variables.
The script requires three existing admin accounts, a class, and a student.
It creates the grade deputy role if missing and temporarily assigns it to the
recipient inside a transaction.
All test rows and role changes are rolled back, including on failure. MySQL
auto-increment counters may advance. The script does not send messages externally.

The local database needed these existing migrations on 2026-09-26:

1. `database/migrations/20260916_register_class_note.sql`
2. `database/migrations/20260916_register_class_quality.sql`
3. `database/migrations/20260916_register_class_optional_date.sql`
4. `database/migrations/20260916_register_class_score_width.sql`
5. `database/migrations/20260924_register_class_homework.sql`
6. `database/migrations/20260917_school_staff_course_access.sql`

Apply these to other test environments if their schema lacks the corresponding
columns or types. Do not assume copying PHP files also upgrades the database.

Coverage: system notification creation and deduplication, direct notification
recipient access and read state, hidden announcement drafts, undated homework
columns, persisted grades/notes/quality, and homework notification audience.
This is a database integration check, not a browser or external-service test.
