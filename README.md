# 🎬 CineVerse 🎬 

CineVerse is a movie ticket booking web app built with plain PHP and MySQL. Users can browse movies, pick a showtime, choose their seats on a live seat map, pay, and get a digital ticket — plus a separate password-protected admin panel to manage movies, showtimes, and seat availability.

---

## 📌 Features

**Customer side**
- Account registration and login (passwords hashed with `password_hash`)
- Browse movies with genre, duration, and available showtimes
- Live seat grid allowing multi-seat selection with real-time status updates (Free, Selected, Booked).
- Secure booking handling that records reserved seats per user, movie, date, and time slot.
- Checkout flow with a booking summary and payment form
- Race-condition-safe seat locking (`SELECT ... FOR UPDATE` inside a transaction) so two people can't book the same seat at once
- Digital ticket with a unique booking reference, printable via the browser's print dialog

**Admin side** (`admin.php`)
- Self-contained, single-file admin panel — not linked anywhere in the public site
- First visit creates the one-and-only admin account; every visit after that requires login
- Dashboard with live stats (movies, showtimes, booked seats, total bookings)
- Add/delete movies and their showtimes
- Reset booked seats for tomorrow, for any specific date, or clear all bookings at once

---

## 🚀 Getting Started

## Tech Stack

- **Backend:** PHP (procedural, PDO for all database access, prepared statements throughout)
- **Database:** MySQL / MariaDB
- **Frontend:** Plain HTML, CSS, and vanilla JavaScript — no frameworks
- **Server:** Designed for Apache via XAMPP/WAMP/MAMP

---

## 📁 Project Structure

```text
movie_booking/
├── db.php                # PDO database connection configuration
├── index.php             # Landing / welcome page
├── register.php          # Account creation
├── login.php             # User authentication (Login)
├── movies.php            # Movie catalog and showtime listing
├── seats.php             # Interactive seat selection screen
├── payment.php           # Checkout, payment form, transaction processing, seat reservation
├── success.php           # Booking confirmation and digital ticket print
├── logout.php            # Session destruction and sign-out
├── admin.php             # Admin panel (setup, login, dashboard — all in one)
├── style.css             # Main stylesheet for UI layout and themes
├── schema.sql            # MySQL database schema and initial data
└──  README.md            # Project documentation for Git

```

## 📝 Database Schema

| Table | Purpose |
|---|---|
| `users` | Customer accounts |
| `movies` | Movie catalog |
| `showtimes` | Date/time slots per movie |
| `seats` | Live seat status per movie/date/time (a row only exists once a seat is booked) |
| `bookings` | Permanent record of every completed booking: user, movie, seats, amount, payment info |
| `admins` | Admin panel accounts, separate from customer `users` |

## ⚙️ Setup

1. **Install a local server stack** — XAMPP, WAMP, or MAMP (Apache + PHP + MySQL).
2. **Copy the project files** into your server's web root (e.g. `C:\xampp\htdocs\movie_booking`).
3. **Create the database** — run `schema.sql` in phpMyAdmin or via:
   ```bash
   mysql -u root < schema.sql
   ```
   This creates the `cinema_db` database and all tables listed above.
4. **Check `db.php`** — confirm `$host`, `$dbname`, `$username`, and `$password` match your MySQL setup (defaults to `root` with no password, matching a stock XAMPP install).
5. **Start Apache and MySQL** in your control panel.
6. **Visit the site** at `http://localhost/movie_booking/index.php` (adjust the folder name to whatever you used in step 2).

### 💻 Setting up the admin panel

1. Go to `http://localhost/movie_booking/admin.php`.
2. Since no admin account exists yet, you'll see a setup form instead of a login form — pick a username and password (minimum 8 characters).
3. From then on, `admin.php` shows the login screen. Log in to reach the dashboard.

## 🔐 Security Notes

- Passwords (both customer and admin) are stored using PHP's `password_hash()` / `password_verify()` — never in plain text.
- All SQL queries use PDO prepared statements to prevent SQL injection.
- Seat booking is wrapped in a database transaction with row locking to prevent double-booking under concurrent requests.
- The admin panel has no links from the public-facing site and is gated behind its own session-based login, separate from customer sessions.
- This is a learning/demo project — the payment step is a simulated checkout and does not integrate with a real payment gateway. Do not use it to process real card or mobile banking numbers in production without adding proper encryption and a licensed payment processor.

## 🪪 License

This project was built for educational purposes as part of coursework.
