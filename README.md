# SoleMate — PHP Shoe Cleaning & Repair Website

## File Structure

```
solemate/
├── index.php           ← Main website (all pages)
├── admin.php           ← Admin order management panel
├── includes/
│   └── data.php        ← Services, orders, reviews, helpers
└── README.md
```

## Pages

| URL                      | Description                          |
|--------------------------|--------------------------------------|
| `index.php?page=services`   | Services grid (default home page)    |
| `index.php?page=booking`    | Booking form (POST → PHP validation) |
| `index.php?page=tracker`    | Order tracker (GET → PHP lookup)     |
| `index.php?page=calculator` | Interactive price estimator          |
| `index.php?page=reviews`    | Customer reviews                     |
| `admin.php`              | Admin order management panel         |

## How to Run

### With PHP built-in server (development)
```bash
cd solemate
php -S localhost:8080
# Open http://localhost:8080
```

### With Apache/Nginx (production)
1. Copy all files to your web root (e.g. `/var/www/html/solemate/`)
2. Ensure PHP 7.4+ is installed
3. Visit `http://yourdomain.com/solemate/`

## Features

- **Server-side booking** — PHP POST form with validation; generates unique order IDs (`SM-YYYYMMDD-XXXX`)
- **Order tracking** — PHP GET lookup against seed orders + session bookings
- **Session persistence** — New bookings stored in `$_SESSION` during the session
- **Admin panel** — View all orders, update status of session-created bookings
- **Price calculator** — Client-side JS with multi-pair discounts
- **XSS-safe** — All output escaped with `htmlspecialchars()`
- **Responsive** — Mobile-friendly layout

## Upgrading to a Real Database

Replace the arrays in `includes/data.php` with PDO MySQL calls:

```php
$pdo = new PDO('mysql:host=localhost;dbname=solemate', $user, $pass);
// INSERT bookings, SELECT by order ID, UPDATE step, etc.
```

## Demo Order Numbers (pre-seeded)

| Order ID       | Status        |
|----------------|---------------|
| SM-20240421    | In service    |
| SM-20240388    | Ready ✅      |
| SM-20240372    | Assessment    |
| SM-20240301    | Quality check |
| SM-20240255    | Received      |
