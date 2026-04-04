Laragon Project Menu (PHP + MySQL)

1) Copy this folder into your Laragon web root, e.g.
   C:\Users\russe\OneDrive\Desktop\laragon\www\000_project_index\

2) Create a MySQL database (phpMyAdmin):
   - Name: 000_project_index   (or change $db_name in db.php)

3) Browse:
   http://localhost/000_project_index/

Notes:
- The schema auto-installs/repairs itself on every request (bootstrap.php -> schema.php).
- The directory scanner scans the *same folder this app is in* (scanner.php uses __DIR__).
  If you want to scan the parent folder instead, open scanner.php and change $base.

Optional pages:
- install_schema.php (manual schema install/repair)
- debug_scan.php (shows what folders PHP sees)

