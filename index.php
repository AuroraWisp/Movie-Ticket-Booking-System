<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: movies.php");
    exit();
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
        .welcome-card {
            background-color: #1e293b;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            max-width: 480px;
            width: 90%;
            margin: 0 auto;
            text-align: center;
        }

        .welcome-card h1 {
            font-size: 2rem;
            color: #f8fafc;
            margin-bottom: 12px;
        }

        .welcome-card p {
            color: #94a3b8;
            font-size: 1.05rem;
            margin-bottom: 30px;
        }

        .enter-btn {
            display: inline-block;
            width: 100%;
            padding: 14px 28px;
            font-size: 1.1rem;
            font-weight: 700;
            background-color: #e11d48;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .enter-btn:hover {
            background-color: #be123c;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(225, 29, 72, 0.4);
        }
    </style>
</head>
<body>

    <div class="welcome-card">
        <h1>🎬 CineVerse 🎬</h1>
        <p>✨ Book your favorite movies quickly and easily ✨</p>
        <a href="login.php" class="enter-btn">Enter System</a>
    </div>

</body>
</html>
