Student Information Management System (SIMS)

Minimal PHP-based SIMS with role-based access (Admin, Teacher, Student).

Prereqs:
- XAMPP (Apache + MySQL) on Windows
- PHP 7.4+

Email verification (optional setup):
- Install PHPMailer via Composer (recommended):

	```bash
	composer require phpmailer/phpmailer
	```

- Then edit `config.php` and set `GMAIL_SMTP_USER` and `GMAIL_SMTP_PASS` (use an app password) and `SITE_URL` to your hostname (e.g., `http://localhost`).
- Registration sends a verification email through Gmail SMTP; if PHPMailer is not installed the system will fall back to PHP `mail()`.

Setup:
1. Create a database named `sims` or run `sql/schema.sql` from phpMyAdmin.
2. Place this project in your webroot (e.g., `c:\xampp\htdocs\sims`).
3. Edit `config.php` if your DB credentials differ.
4. Open `setup.php` in browser once to create an initial admin user (password: `adminpass`).

Login:
- Admin: full CRUD and hard delete
- Teacher: view/update (soft-delete)
- Student: view/update own profile

Files of interest:
- `config.php` - DB credentials
- `includes/db.php`, `includes/auth.php` - helpers
- `admin/`, `teacher/`, `student/` - role pages
- `sql/schema.sql` - DB schema
