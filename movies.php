<?php
require_once "db.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$stmt = $pdo->query("
    SELECT m.id AS movie_id, m.title, m.genre, m.duration_min, s.show_date, s.showtime
    FROM movies m
    JOIN showtimes s ON m.id = s.movie_id
    ORDER BY m.id ASC, s.show_date ASC, s.showtime ASC
");
$raw_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$movies = [];
foreach ($raw_schedules as $row) {
    $m_id = $row['movie_id'];
    if (!isset($movies[$m_id])) {
        $movies[$m_id] = [
            'id' => $row['movie_id'],
            'title' => $row['title'],
            'genre' => $row['genre'],
            'duration' => $row['duration_min'],
            'schedules' => []
        ];
    }
    $movies[$m_id]['schedules'][] = [
        'date' => $row['show_date'],
        'time' => $row['showtime']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineVerse</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid #334155;
            padding-bottom: 15px;
        }

        .movies-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .movie-card {
            background-color: #0f172a;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid #334155;
            text-align: left;
        }

        .movie-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .movie-title {
            font-size: 1.4rem;
            color: #f8fafc;
            font-weight: bold;
        }

        .movie-meta {
            color: #38bdf8;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .schedule-title {
            color: #94a3b8;
            font-size: 0.9rem;
            margin: 15px 0 10px;
        }

        .showtime-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .showtime-btn {
            background-color: #1e293b;
            border: 1px solid #334155;
            color: #f8fafc;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .showtime-btn:hover {
            background-color: #38bdf8;
            color: #0f172a;
            border-color: #38bdf8;
            font-weight: bold;
        }

        .showtime-btn .date {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            background-color: rgba(248, 113, 113, 0.1);
            color: #f87171;
            border: 1px solid #f87171;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            background-color: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
        }
    </style>
</head>
<body>

<div class="container" style="max-width: 800px;">
    <div class="header-bar">
        <span>Logged in as <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></strong></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <h2>Select Movie & Showtime</h2>

    <div class="movies-list">
        <?php foreach ($movies as $movie): ?>
            <div class="movie-card">
                <div class="movie-header">
                    <div class="movie-title"><?= htmlspecialchars($movie['title']) ?></div>
                    <div class="movie-meta"><?= htmlspecialchars($movie['genre']) ?> • <?= $movie['duration'] ?> mins</div>
                </div>

                <div class="schedule-title">Available Showtimes (Click to Pick Seats):</div>
                <div class="showtime-group">
                    <?php foreach ($movie['schedules'] as $sched): ?>
                        <a href="seats.php?id=<?= $movie['id'] ?>&date=<?= urlencode($sched['date']) ?>&time=<?= urlencode($sched['time']) ?>" class="showtime-btn">
                            <span class="date"><?= date('M d, Y', strtotime($sched['date'])) ?></span>
                            <span>⏰ <?= date('g:i A', strtotime($sched['time'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>
