VirtualHost setup for `abhi.local`

Quick automated method (recommended):

1. Open File Explorer as Administrator.
2. Right-click `c:\xampp\htdocs\abhi\scripts\setup_abhi_vhost.bat` and choose "Run as administrator".
3. Follow on-screen prompts. The script will:
   - Add `127.0.0.1 abhi.local` to your system hosts file (if missing).
   - Append a VirtualHost block to `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (if missing).
   - Attempt to restart Apache via `httpd.exe -k restart`.

Manual method:

1. Edit your hosts file as Administrator:

   - File: `C:\Windows\System32\drivers\etc\hosts`
   - Add line: `127.0.0.1    abhi.local`

2. Edit Apache VirtualHosts file (XAMPP default):

   - File: `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
   - Append this block:

```
<VirtualHost *:80>
    ServerName abhi.local
    DocumentRoot "C:/xampp/htdocs/abhi"
    <Directory "C:/xampp/htdocs/abhi">
        Options Indexes FollowSymLinks Includes ExecCGI
        Require all granted
    </Directory>
    ErrorLog "logs/abhi.local-error.log"
    CustomLog "logs/abhi.local-access.log" common
</VirtualHost>
```

3. Restart Apache using the XAMPP Control Panel.

4. Open `http://abhi.local/` in your browser.

Notes:
- If your XAMPP is installed to a different path, edit the paths above accordingly.
- On Windows, editing the hosts file and Apache config requires Administrator privileges.
