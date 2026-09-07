<?php
require_once "db.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$movie_id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT);
$show_date = filter_input(INPUT_GET, 'date', FILTER_DEFAULT);
$showtime  = filter_input(INPUT_GET, 'time', FILTER_DEFAULT);

if (!$movie_id || !$show_date || !$showtime) {
    header("Location: movies.php");
    exit();
}

$stmt = $pdo->prepare("SELECT title, genre, duration_min FROM movies WHERE id = ?");
$stmt->execute([$movie_id]);
$movie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$movie) {
    die("Movie not found.");
}

$error = "";
$selected_seats = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['seat']) || !is_array($_POST['seat'])) {
        $error = "Please select at least one seat to proceed.";
    } else {
        $selected_seats = array_map('trim', $_POST['seat']);
        
        $query_params = http_build_query([
            'movie_id' => $movie_id,
            'date'     => $show_date,
            'time'     => $showtime,
            'seats'    => implode(',', $selected_seats)
        ]);
        
        header("Location: payment.php?" . $query_params);
        exit();
    }
}

$stmt = $pdo->prepare("
    SELECT seat_no 
    FROM seats 
    WHERE movie_id = ? AND show_date = ? AND showtime = ? AND status = 'booked'
");
$stmt->execute([$movie_id, $show_date, $showtime]);
$booked_seats = $stmt->fetchAll(PDO::FETCH_COLUMN);

$rows = range('A', 'H');
$total_cols = 8;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineVerse</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .auditorium-wrapper {
            background-color: #0b0f19;
            border-radius: 16px;
            padding: 30px 20px;
            border: 1px solid #1e293b;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            margin-bottom: 25px;
            position: relative;
        }

        .booking-details {
            background-color: #0f172a;
            padding: 16px 20px;
            border-radius: 10px;
            border: 1px solid #334155;
            margin-bottom: 25px;
            text-align: left;
        }

        .booking-details h3 {
            color: #f8fafc;
            margin-bottom: 6px;
        }

        .meta-info {
            color: #38bdf8;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .cinema-screen-container {
            perspective: 400px;
            margin-bottom: 35px;
            text-align: center;
        }

        .cinema-screen {
            background: linear-gradient(to bottom, #38bdf8, #0284c7);
            height: 18px;
            width: 85%;
            margin: 0 auto;
            border-radius: 50% 50% 4px 4px;
            box-shadow: 0 8px 25px rgba(56, 189, 248, 0.45);
            transform: rotateX(-10deg);
        }

        .screen-label {
            color: #94a3b8;
            font-size: 0.75rem;
            letter-spacing: 3px;
            margin-top: 10px;
            font-weight: bold;
        }

        .seat-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: center;
            margin-bottom: 35px;
        }

        .seat-row {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .row-label {
            color: #64748b;
            font-weight: bold;
            width: 20px;
            text-align: center;
            font-size: 0.85rem;
        }

        .seat {
            width: 36px;
            height: 36px;
            border-radius: 8px 8px 4px 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            transition: all 0.2s ease;
            position: relative;
        }

        .seat.free {
            background-color: #1e293b;
            border: 1px solid #334155;
            color: #f8fafc;
        }

        .seat.free:hover {
            border-color: #38bdf8;
            background-color: #0f172a;
            transform: scale(1.08);
        }

        .seat.selected {
            background-color: #38bdf8 !important;
            color: #0f172a !important;
            border-color: #38bdf8 !important;
            font-weight: bold;
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.5);
        }

        .seat.booked {
            background-color:  #f11a1a;
            color: #f8fafc;
            border: 1px solid #f8fafc;
            cursor: not-allowed;
            text-decoration: line-through;
            opacity: 0.6;
        }

        .seat input[type="checkbox"] {
            display: none;
        }

        .exit-doors-container {
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
            margin-top: 20px;
            border-top: 1px dashed #334155;
            padding-top: 15px;
        }

        .exit-door {
            display: flex;
            align-items: center;
            gap: 8px;
            background-color: #064e3b;
            border: 1px solid #10b981;
            color: #34d399;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .seat-legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 25px;
            font-size: 0.85rem;
            color: #94a3b8;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-box {
            width: 18px;
            height: 18px;
            border-radius: 4px;
        }

        .error-msg {
            background-color: rgba(248, 113, 113, 0.1);
            color: #f87171;
            border: 1px solid #f87171;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .btn-group {
            display: flex;
            gap: 16px;
            width: 100%;
            margin-top: 10px;
        }

        .btn-action {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 48px;
            padding: 0 20px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            box-sizing: border-box;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-action.secondary {
            background-color: #1e293b;
            color: #f8fafc;
            border-color: #334155;
        }

        .btn-action.secondary:hover {
            background-color: #334155;
            border-color: #64748b;
        }

        .btn-action.primary {
            background-color: #38bdf8;
            color: #0f172a;
            border-color: #38bdf8;
        }

        .btn-action.primary:hover {
            background-color: #0284c7;
            border-color: #0284c7;
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="container" style="max-width: 650px;">

    <div class="booking-details">
        <h3><?= htmlspecialchars($movie['title']) ?></h3>
        <div class="meta-info">
            📅 <?= date('M d, Y', strtotime($show_date)) ?> &nbsp;|&nbsp; 
            ⏰ <?= date('g:i A', strtotime($showtime)) ?> &nbsp;|&nbsp; 
            ⏱️ <?= (int)$movie['duration_min'] ?> mins
        </div>
    </div>

    <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="auditorium-wrapper">
        <div class="cinema-screen-container">
            <div class="cinema-screen"></div>
            <div class="screen-label">CINEMA SCREEN</div>
        </div>

        <form id="seat-form" method="post" action="seats.php?id=<?= $movie_id ?>&date=<?= urlencode($show_date) ?>&time=<?= urlencode($showtime) ?>">
            <div class="seat-grid">
                <?php foreach ($rows as $r): ?>
                    <div class="seat-row">
                        <span class="row-label"><?= $r ?></span>
                        <?php for ($col = 1; $col <= $total_cols; $col++): 
                            $seat_code = $r . $col;
                            $is_booked = in_array($seat_code, $booked_seats);
                            $is_selected = in_array($seat_code, $selected_seats);
                        ?>
                            <?php if ($is_booked): ?>
                                <div class="seat booked" title="Seat <?= $seat_code ?> (Booked)">
                                    <?= $col ?>
                                </div>
                            <?php else: ?>
                                <label class="seat free <?= $is_selected ? 'selected' : '' ?>" title="Seat <?= $seat_code ?>">
                                    <input type="checkbox" name="seat[]" value="<?= htmlspecialchars($seat_code) ?>" <?= $is_selected ? 'checked' : '' ?> onchange="this.parentElement.classList.toggle('selected', this.checked)">
                                    <?= $col ?>
                                </label>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <span class="row-label"><?= $r ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>

        <div class="exit-doors-container">
            <div class="exit-door">🚪 EXIT</div>
            <div class="exit-door">EXIT 🚪</div>
        </div>
    </div>

    <div class="seat-legend">
        <div class="legend-item">
            <div class="legend-box" style="background-color: #1e293b; border: 1px solid #334155;"></div> Available
        </div>
        <div class="legend-item">
            <div class="legend-box" style="background-color: #38bdf8;"></div> Selected
        </div>
        <div class="legend-item">
            <div class="legend-box" style="background-color: #f11a1a;"></div> Booked
        </div>
    </div>

    <div class="btn-group">
        <a href="movies.php" class="btn-action secondary">← Back to Movies</a>
        <button type="submit" form="seat-form" class="btn-action primary">Proceed to Payment →</button>
    </div>
</div>

</body>
</html>
