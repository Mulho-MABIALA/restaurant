# Copilot Instructions for Mulho-MABIALA/restaurant

## Project Overview

This is a PHP-based restaurant management system with both public and admin interfaces. The codebase is organized by feature, with major components in the root and `admin/` directories. Data is stored in a MySQL database, accessed via PDO. Security and session management are handled with custom logic and configuration files.

## Key Architectural Patterns

- **Feature-based Structure:**
  - Public site: root PHP files (e.g., `index.php`, `menu.php`, `gallery_public.php`).
  - Admin dashboard: `admin/` directory (e.g., `dashboard.php`, `gestion_plats.php`, `commandes.php`).
- **Database Access:**
  - All DB connections use `config.php` for PDO setup (`$conn`).
  - SQL queries are embedded in PHP, often with prepared statements for security.
- **Session & Security:**
  - Admin authentication via sessions (`$_SESSION['admin_logged_in']`).
  - Security config in `admin/config_security.php` (login attempts, lockout, headers).
- **QR Code Generation:**
  - Employee QR codes via `phpqrcode/qrlib.php` (see `employee_details.php`).
- **Email Integration:**
  - Uses PHPMailer (see `admin/login.php` and `admin/config_security.php`).

## Developer Workflows

- **Local Development:**
  - Designed for WAMP (Windows, Apache, MySQL, PHP). DB credentials in `config.php`.
- **No Build Step:**
  - Pure PHP/JS/CSS; changes are live after file save.
- **Testing:**
  - No automated tests detected. Manual testing via browser and DB.
- **Debugging:**
  - Use browser dev tools and PHP error logs. Errors are logged with `error_log()` in critical files.

## Project-Specific Conventions

- **Language Support:**
  - Multi-language via `langues/` (`fr.php`, `en.php`, `wo.php`).
- **Admin Access:**
  - All admin pages check for session and redirect to `login.php` if not authenticated.
- **Security:**
  - Custom security headers set in `config_security.php`.
  - Passwords hashed with `password_hash()` (default or Argon2id).
- **AJAX & JS:**
  - Some admin actions use AJAX (see `commandes.php`).
  - Cart logic in `cart.js` uses localStorage.

## Integration Points

- **External Libraries:**
  - PHPMailer, PHPQRCode, DomPDF, BaconQrCode, Endroid QrCode (see `vendor/` and `assets/vendor/`).
- **Data Flow:**
  - User actions (orders, reservations) update MySQL tables; admin views aggregate and display stats.
- **File Uploads:**
  - Images stored in `uploads/`.

## Examples of Important Patterns

- **Admin Authentication:**
  ```php
  session_start();
  if (!isset($_SESSION['admin_logged_in'])) {
      header('Location: login.php');
      exit;
  }
  ```
- **Database Query:**
  ```php
  $stmt = $conn->prepare("SELECT * FROM plats WHERE id = ?");
  $stmt->execute([$id]);
  $plat = $stmt->fetch(PDO::FETCH_ASSOC);
  ```
- **Security Headers:**
  ```php
  function setSecurityHeaders() {
      header('X-Content-Type-Options: nosniff');
      // ...
  }
  ```

## Key Files & Directories

- `config.php` — DB connection
- `admin/config_security.php` — security settings
- `admin/dashboard.php` — admin overview
- `admin/gestion_plats.php` — menu management
- `admin/commandes.php` — order management
- `admin/employee_details.php` — employee QR logic
- `langues/` — language files
- `uploads/` — image uploads

---

**If any section is unclear or missing, please specify what needs improvement or additional detail.**
