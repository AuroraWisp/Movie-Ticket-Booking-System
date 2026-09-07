<?php
require_once "db.php";
session_start();

$success = $_SESSION['flash_success'] ?? '';
$error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$admin_exists = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn() > 0;
$logged_in    = isset($_SESSION['admin_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_admin' && !$admin_exists) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($username) || empty($password) || empty($confirm)) {
            $_SESSION['flash_error'] = "Please fill in all fields.";
        } elseif ($password !== $confirm) {
            $_SESSION['flash_error'] = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $_SESSION['flash_error'] = "Password must be at least 8 characters long.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hashed]);
            $_SESSION['flash_success'] = "Admin account created. You can log in now.";
        }
        header("Location: admin.php");
        exit();
    }

    elseif ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
        } else {
            $_SESSION['flash_error'] = "Invalid admin credentials.";
        }
        header("Location: admin.php");
        exit();
    }

    elseif ($action === 'logout') {
        unset($_SESSION['admin_id'], $_SESSION['admin_name']);
        header("Location: admin.php");
        exit();
    }

    elseif ($logged_in) {

        if ($action === 'add_movie') {
            $title    = trim($_POST['title'] ?? '');
            $genre    = trim($_POST['genre'] ?? '') ?: 'Cinema';
            $duration = filter_input(INPUT_POST, 'duration_min', FILTER_VALIDATE_INT) ?: 120;

            if (empty($title)) {
                $_SESSION['flash_error'] = "Movie title is required.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO movies (title, genre, duration_min) VALUES (?, ?, ?)");
                $stmt->execute([$title, $genre, $duration]);
                $_SESSION['flash_success'] = "Movie added.";
            }
        }

        elseif ($action === 'delete_movie') {
            $movie_id = filter_input(INPUT_POST, 'movie_id', FILTER_VALIDATE_INT);
            if ($movie_id) {
                $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
                $stmt->execute([$movie_id]);
                $_SESSION['flash_success'] = "Movie deleted.";
            }
        }

        elseif ($action === 'add_showtime') {
            $movie_id  = filter_input(INPUT_POST, 'movie_id', FILTER_VALIDATE_INT);
            $show_date = trim($_POST['show_date'] ?? '');
            $showtime  = trim($_POST['showtime'] ?? '');

            if (!$movie_id || empty($show_date) || empty($showtime)) {
                $_SESSION['flash_error'] = "Movie, date and time are all required to add a showtime.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO showtimes (movie_id, show_date, showtime) VALUES (?, ?, ?)");
                $stmt->execute([$movie_id, $show_date, $showtime]);
                $_SESSION['flash_success'] = "Showtime added.";
            }
        }

        elseif ($action === 'delete_showtime') {
            $showtime_id = filter_input(INPUT_POST, 'showtime_id', FILTER_VALIDATE_INT);
            if ($showtime_id) {
                $stmt = $pdo->prepare("DELETE FROM showtimes WHERE id = ?");
                $stmt->execute([$showtime_id]);
                $_SESSION['flash_success'] = "Showtime deleted.";
            }
        }

        elseif ($action === 'reset_tomorrow') {
            $target_date = date('Y-m-d', strtotime('+1 day'));
            $stmt = $pdo->prepare("DELETE FROM seats WHERE show_date = ?");
            $stmt->execute([$target_date]);
            $_SESSION['flash_success'] = "Freed " . $stmt->rowCount() . " seat(s) booked for tomorrow (" . date('M d, Y', strtotime($target_date)) . ").";
        }

        elseif ($action === 'reset_date') {
            $target_date = trim($_POST['target_date'] ?? '');
            if (empty($target_date)) {
                $_SESSION['flash_error'] = "Please choose a date to reset.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM seats WHERE show_date = ?");
                $stmt->execute([$target_date]);
                $_SESSION['flash_success'] = "Freed " . $stmt->rowCount() . " seat(s) booked for " . date('M d, Y', strtotime($target_date)) . ".";
            }
        }

        elseif ($action === 'reset_all') {
            $pdo->query("DELETE FROM seats");
            $_SESSION['flash_success'] = "All seat bookings have been cleared across every date.";
        }

        header("Location: admin.php");
        exit();
    }
}

$success = $success ?: ($_SESSION['flash_success'] ?? '');
$error   = $error ?: ($_SESSION['flash_error'] ?? '');

$movie_count = $showtime_count = $booked_seat_count = $booking_count = 0;
$movies = [];
$showtimes_by_movie = [];
$booked_by_date = [];
$tomorrow = date('Y-m-d', strtotime('+1 day'));

if ($logged_in) {
    $movie_count       = $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
    $showtime_count    = $pdo->query("SELECT COUNT(*) FROM showtimes")->fetchColumn();
    $booked_seat_count = $pdo->query("SELECT COUNT(*) FROM seats WHERE status = 'booked'")->fetchColumn();
    $booking_count     = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();

    $movies = $pdo->query("SELECT * FROM movies ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

    $showtime_rows = $pdo->query("SELECT * FROM showtimes ORDER BY show_date ASC, showtime ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($showtime_rows as $row) {
        $showtimes_by_movie[$row['movie_id']][] = $row;
    }

    $booked_by_date = $pdo->query("
        SELECT show_date, COUNT(*) AS booked_count
        FROM seats
        WHERE status = 'booked'
        GROUP BY show_date
        ORDER BY show_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineVerse - Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-badge {
            display: inline-block; background-color: rgba(56,189,248,0.1); color: #38bdf8;
            border: 1px solid rgba(56,189,248,0.3); border-radius: 6px; padding: 4px 10px;
            font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 14px;
        }
        .auth-card {
            background-color: #1e293b; padding: 35px; border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); max-width: 420px; width: 90%; margin: 0 auto;
        }
        .form-group { margin-bottom: 18px; text-align: left; }
        .form-group label { display: block; margin-bottom: 6px; color: #94a3b8; font-size: 0.9rem; }
        .form-group input {
            width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155;
            background-color: #0f172a; color: #f8fafc; font-size: 1rem; box-sizing: border-box;
        }
        .form-group input:focus { outline: none; border-color: #38bdf8; }
        .submit-btn {
            width: 100%; padding: 12px; background-color: #e11d48; color: #fff; border: none;
            border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer;
        }
        .submit-btn:hover { background-color: #be123c; }
        .success-msg {
            color: #4ade80; background-color: rgba(74,222,128,0.1); border: 1px solid rgba(74,222,128,0.2);
            border-radius: 8px; padding: 10px; font-size: 0.9rem; margin-bottom: 20px; text-align: center;
        }

        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid #334155; padding-bottom: 15px; }
        .logout-btn { color: #f87171; text-decoration: none; font-weight: 600; background: none; border: none; cursor: pointer; font-size: 0.95rem; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 25px; }
        @media (max-width: 640px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        .stat-card { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 16px; text-align: left; }
        .stat-card .num { font-size: 1.6rem; font-weight: bold; color: #38bdf8; }
        .stat-card .label { color: #94a3b8; font-size: 0.8rem; margin-top: 4px; }

        .section-title { text-align: left; margin: 30px 0 14px; color: #f8fafc; }

        .add-card, .tool-card, .movie-card { background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-bottom: 16px; text-align: left; }
        .add-card h3, .tool-card h3, .movie-card-header h3 { color: #f8fafc; margin-bottom: 6px; }
        .tool-card p { color: #94a3b8; font-size: 0.85rem; margin-bottom: 14px; }

        .form-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .form-row .form-group { flex: 1; min-width: 140px; margin-bottom: 12px; }
        .form-row .form-group label { font-size: 0.85rem; }
        .form-row .form-group input { padding: 10px; background-color: #1e293b; font-size: 0.9rem; }

        .add-btn, .quick-btn {
            padding: 10px 20px; background-color: #38bdf8; color: #0f172a; border: none;
            border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.95rem;
        }
        .add-btn:hover, .quick-btn:hover { background-color: #0284c7; color: #fff; }

        .secondary-submit { padding: 10px 18px; background-color: #334155; color: #f8fafc; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .secondary-submit:hover { background-color: #475569; }

        .danger-submit { padding: 10px 18px; background-color: rgba(248,113,113,0.1); color: #f87171; border: 1px solid #f87171; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .danger-submit:hover { background-color: #ef4444; color: #fff; }

        .movie-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .movie-meta { color: #38bdf8; font-size: 0.8rem; }
        .delete-btn { background-color: rgba(248,113,113,0.1); color: #f87171; border: 1px solid #f87171; border-radius: 6px; padding: 6px 12px; font-size: 0.8rem; cursor: pointer; }
        .delete-btn:hover { background-color: #ef4444; color: #fff; }

        .showtime-chip { display: inline-flex; align-items: center; gap: 8px; background-color: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 6px 10px; margin: 4px 6px 4px 0; font-size: 0.85rem; }
        .showtime-chip form { display: inline; }
        .showtime-chip button { background: none; border: none; color: #f87171; cursor: pointer; font-size: 0.9rem; padding: 0; }

        .mini-form { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; align-items: end; }
        .mini-form input { padding: 8px; border-radius: 6px; border: 1px solid #334155; background-color: #1e293b; color: #f8fafc; }
        .mini-form button { padding: 8px 14px; background-color: #334155; color: #f8fafc; border: none; border-radius: 6px; cursor: pointer; }
        .mini-form button:hover { background-color: #475569; }

        .inline-form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .inline-form input[type="date"] { padding: 10px; border-radius: 8px; border: 1px solid #334155; background-color: #1e293b; color: #f8fafc; }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #334155; font-size: 0.9rem; }
        th { color: #94a3b8; font-weight: 600; }
        td { color: #f8fafc; }
    </style>
</head>
<body>

<?php if (!$admin_exists): ?>

    <div class="auth-card">
        <span class="admin-badge">🔒 First-Time Setup</span>
        <h2 style="margin-bottom: 20px;">Create Admin Account</h2>
        <?php if ($success): ?><p class="success-msg"><?= htmlspecialchars($success) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error-msg"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post" action="admin.php">
            <input type="hidden" name="action" value="create_admin">
            <div class="form-group">
                <label for="username">Admin Username</label>
                <input type="text" id="username" name="username" required placeholder="admin">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="At least 8 characters">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="submit-btn">Create Admin Account</button>
        </form>
    </div>

<?php elseif (!$logged_in): ?>

    <div class="auth-card">
        <span class="admin-badge">🔒 Admin Only</span>
        <h2 style="margin-bottom: 20px;">Admin Login</h2>
        <?php if ($error): ?><p class="error-msg"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post" action="admin.php">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="submit-btn">Log In</button>
        </form>
    </div>

<?php else: ?>

    <div class="container" style="max-width: 850px;">
        <div class="header-bar">
            <div>
                <span class="admin-badge">🔒 Admin</span>
                <div style="margin-top: 6px;">Logged in as <strong><?= htmlspecialchars($_SESSION['admin_name']) ?></strong></div>
            </div>
            <form method="post" action="admin.php">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>

        <?php if ($success): ?><p class="success-msg"><?= htmlspecialchars($success) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error-msg"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="num"><?= $movie_count ?></div><div class="label">Movies</div></div>
            <div class="stat-card"><div class="num"><?= $showtime_count ?></div><div class="label">Showtimes</div></div>
            <div class="stat-card"><div class="num"><?= $booked_seat_count ?></div><div class="label">Booked Seats</div></div>
            <div class="stat-card"><div class="num"><?= $booking_count ?></div><div class="label">Total Bookings</div></div>
        </div>

        <h3 class="section-title">🎬 Manage Movies</h3>

        <div class="add-card">
            <h3>Add a Movie</h3>
            <form method="post" action="admin.php">
                <input type="hidden" name="action" value="add_movie">
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" required placeholder="Movie title">
                    </div>
                    <div class="form-group">
                        <label for="genre">Genre</label>
                        <input type="text" id="genre" name="genre" placeholder="e.g. Action">
                    </div>
                    <div class="form-group">
                        <label for="duration_min">Duration (min)</label>
                        <input type="number" id="duration_min" name="duration_min" placeholder="120" min="1">
                    </div>
                </div>
                <button type="submit" class="add-btn">Add Movie</button>
            </form>
        </div>

        <?php foreach ($movies as $movie): ?>
            <div class="movie-card">
                <div class="movie-card-header">
                    <div>
                        <h3><?= htmlspecialchars($movie['title']) ?></h3>
                        <span class="movie-meta"><?= htmlspecialchars($movie['genre']) ?> • <?= (int)$movie['duration_min'] ?> mins</span>
                    </div>
                    <form method="post" action="admin.php" onsubmit="return confirm('Delete this movie and all of its showtimes? This cannot be undone.');">
                        <input type="hidden" name="action" value="delete_movie">
                        <input type="hidden" name="movie_id" value="<?= $movie['id'] ?>">
                        <button type="submit" class="delete-btn">Delete Movie</button>
                    </form>
                </div>

                <div>
                    <?php if (!empty($showtimes_by_movie[$movie['id']])): ?>
                        <?php foreach ($showtimes_by_movie[$movie['id']] as $st): ?>
                            <span class="showtime-chip">
                                <?= date('M d', strtotime($st['show_date'])) ?> · <?= date('g:i A', strtotime($st['showtime'])) ?>
                                <form method="post" action="admin.php" onsubmit="return confirm('Remove this showtime?');">
                                    <input type="hidden" name="action" value="delete_showtime">
                                    <input type="hidden" name="showtime_id" value="<?= $st['id'] ?>">
                                    <button type="submit" title="Remove showtime">✕</button>
                                </form>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:#64748b; font-size:0.85rem;">No showtimes scheduled yet.</span>
                    <?php endif; ?>
                </div>

                <form method="post" action="admin.php" class="mini-form">
                    <input type="hidden" name="action" value="add_showtime">
                    <input type="hidden" name="movie_id" value="<?= $movie['id'] ?>">
                    <input type="date" name="show_date" required>
                    <input type="time" name="showtime" required>
                    <button type="submit">+ Add Showtime</button>
                </form>
            </div>
        <?php endforeach; ?>

        <h3 class="section-title">🔄 Reset Seats</h3>

        <div class="tool-card">
            <h3>Reset Seats for Tomorrow</h3>
            <p>Frees every booked seat for <?= date('M d, Y', strtotime($tomorrow)) ?> across all movies. Booking history is kept — this only affects seat availability.</p>
            <form method="post" action="admin.php" onsubmit="return confirm('Free all booked seats for tomorrow?');">
                <input type="hidden" name="action" value="reset_tomorrow">
                <button type="submit" class="quick-btn">Reset Tomorrow's Seats</button>
            </form>
        </div>

        <div class="tool-card">
            <h3>Reset Seats for a Specific Date</h3>
            <p>Choose any date to free up all seats booked for that day.</p>
            <form method="post" action="admin.php" class="inline-form" onsubmit="return confirm('Free all booked seats for the selected date?');">
                <input type="hidden" name="action" value="reset_date">
                <input type="date" name="target_date" required>
                <button type="submit" class="secondary-submit">Reset This Date</button>
            </form>
        </div>

        <div class="tool-card">
            <h3>⚠️ Reset All Seats</h3>
            <p>Clears every seat booking across every date and movie. Use with caution.</p>
            <form method="post" action="admin.php" onsubmit="return confirm('This will free ALL booked seats across every date. Are you sure?');">
                <input type="hidden" name="action" value="reset_all">
                <button type="submit" class="danger-submit">Reset All Seats</button>
            </form>
        </div>

        <div class="tool-card">
            <h3>Currently Booked Seats by Date</h3>
            <?php if (empty($booked_by_date)): ?>
                <p style="color:#64748b;">No seats are currently booked.</p>
            <?php else: ?>
                <table>
                    <tr><th>Date</th><th>Booked Seats</th></tr>
                    <?php foreach ($booked_by_date as $row): ?>
                        <tr>
                            <td><?= date('M d, Y (D)', strtotime($row['show_date'])) ?></td>
                            <td><?= (int)$row['booked_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

</body>
</html>
