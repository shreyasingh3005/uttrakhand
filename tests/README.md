# Local regression tests

Use PHP 8 with PDO MySQL, local MySQL and Node.js. These tests target only the disposable `crm_validation_20261007` database using the local XAMPP root account. Never configure them for production.

```powershell
C:\xampp\php\php.exe tests\setup.php
$env:CRM_DB_HOST='127.0.0.1'
$env:CRM_DB_NAME='crm_validation_20261007'
$env:CRM_DB_USER='root'
C:\xampp\php\php.exe tests\bootstrap.php
C:\xampp\php\php.exe tests\fixture.php
C:\xampp\php\php.exe -S 127.0.0.1:8091 -t . tests\router.php
```

In a second terminal, run `node tests/http-regression.cjs`.

Fixture credentials and sessions are stored in ignored `tests/.runtime/`. The test router overrides database settings only for its own process and denies web access to test files. Apache also denies `/tests/`. Stop the test server after verification. Test runs leave identifiable QA records in the isolated database for inspection.

Run `node tests/concurrency.cjs` for simultaneous reservation/cancellation validation after fixture setup. It uses the same isolated QA database.
