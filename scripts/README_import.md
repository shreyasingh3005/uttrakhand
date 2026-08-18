Import database/all.sql

Automated (recommended):

1. Open File Explorer and navigate to `c:\xampp\htdocs\abhi\scripts`.
2. Right-click `import_db.bat` and choose "Run as administrator" (not strictly required, but may help if permissions are restricted).
3. Follow prompts for `mysql.exe` path, MySQL host, user and password. Defaults assume XAMPP: `C:\xampp\mysql\bin\mysql.exe`, `root` user, empty password.

Manual CLI import (example):

Open a Command Prompt as Administrator and run:

```bat
C:\xampp\mysql\bin\mysql.exe -u root < "C:\xampp\htdocs\abhi\database\all.sql"
```

Or, if you need to specify a password:

```bat
C:\xampp\mysql\bin\mysql.exe -u root -pYourPassword < "C:\xampp\htdocs\abhi\database\all.sql"
```

Notes:
- The SQL file includes `CREATE DATABASE employee_management` so no pre-created database is required.
- If import errors occur, check `C:\xampp\mysql\data\mysql_error.log` and Apache/PHP error logs.
- After import, open `http://abhi.local/` (or `http://localhost/abhi/`) to use the app.
