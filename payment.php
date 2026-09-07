<?php
require_once "db.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$movie_id  = filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT);
$show_date = filter_input(INPUT_GET, 'date', FILTER_DEFAULT);
$showtime  = filter_input(INPUT_GET, 'time', FILTER_DEFAULT);
$seats_raw = filter_input(INPUT_GET, 'seats', FILTER_DEFAULT);

if (!$movie_id || !$show_date || !$showtime || !$seats_raw) {
    header("Location: movies.php");
    exit();
}

$selected_seats = array_filter(explode(',', $seats_raw));

if (empty($selected_seats)) {
    header("Location: movies.php");
    exit();
}

$price_per_seat = 350; // ৳350 per seat
$seat_count     = count($selected_seats);
$total_amount   = $seat_count * $price_per_seat;

$stmt = $pdo->prepare("SELECT title, genre, duration_min FROM movies WHERE id = ?");
$stmt->execute([$movie_id]);
$movie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$movie) {
    die("Movie not found.");
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_name   = trim($_POST['card_name'] ?? '');
    $account_num = trim($_POST['account_num'] ?? '');
    
    if (empty($card_name) || empty($account_num)) {
        $error = "Please fill in all required payment details.";
    } else {
        try {
            $pdo->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($selected_seats), '?'));
            $check_sql = "
                SELECT seat_no 
                FROM seats 
                WHERE movie_id = ? AND show_date = ? AND showtime = ? AND status = 'booked' AND seat_no IN ($placeholders)
                FOR UPDATE
            ";
            $check_params = array_merge([$movie_id, $show_date, $showtime], $selected_seats);
            $stmt = $pdo->prepare($check_sql);
            $stmt->execute($check_params);
            $already_booked = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($already_booked)) {
                $pdo->rollBack();
                $error = "Sorry, seat(s) " . implode(', ', $already_booked) . " were just booked by someone else.";
            } else {
                $insert_sql = "
                    INSERT INTO seats (movie_id, show_date, showtime, seat_no, status, booked_by) 
                    VALUES (?, ?, ?, ?, 'booked', ?)
                    ON DUPLICATE KEY UPDATE status = 'booked', booked_by = VALUES(booked_by)
                ";
                $insert_stmt = $pdo->prepare($insert_sql);

                foreach ($selected_seats as $seat_code) {
                    $insert_stmt->execute([$movie_id, $show_date, $showtime, $seat_code, $user_id]);
                }

                $booking_ref = "TKT-" . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));

                $booking_sql = "
                    INSERT INTO bookings
                        (booking_ref, user_id, movie_id, show_date, showtime, seats, seat_count, total_amount, payment_name, payment_account, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')
                ";
                $booking_stmt = $pdo->prepare($booking_sql);
                $booking_stmt->execute([
                    $booking_ref,
                    $user_id,
                    $movie_id,
                    $show_date,
                    $showtime,
                    implode(',', $selected_seats),
                    $seat_count,
                    $total_amount,
                    $card_name,
                    $account_num
                ]);

                $pdo->commit();

                $_SESSION['last_booking'] = [
                    'ref'     => $booking_ref,
                    'movie'   => $movie['title'],
                    'date'    => $show_date,
                    'time'    => $showtime,
                    'seats'   => $selected_seats,
                    'amount'  => $total_amount
                ];

                header("Location: success.php");
                exit();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Database Error: " . $e->getMessage();
        }
    }
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
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            text-align: left;
        }

        @media (max-width: 640px) {
            .checkout-grid { grid-template-columns: 1fr; }
        }

        .summary-card, .payment-card {
            background-color: #0f172a;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #334155;
        }

        .summary-card h3, .payment-card h3 {
            color: #f8fafc;
            margin-bottom: 16px;
            border-bottom: 1px solid #1e293b;
            padding-bottom: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .info-row strong { color: #f8fafc; }

        .seats-badge {
            background-color: #0284c7;
            color: #ffffff;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.85rem;
        }

        .price-divider {
            border-top: 1px dashed #334155;
            margin: 16px 0;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 1.15rem;
            color: #f8fafc;
            font-weight: bold;
        }

        .total-row .amount { color: #38bdf8; }

        .form-group { margin-bottom: 16px; }

        .form-group label {
            display: block;
            color: #94a3b8;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            background-color: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #f8fafc;
            font-size: 0.9rem;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: #38bdf8;
            outline: none;
        }

        .error-msg {
            background-color: rgba(248, 113, 113, 0.1);
            color: #f87171;
            border: 1px solid #f87171;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: left;
        }

        .btn-group {
            display: flex !important;
            gap: 16px !important;
            width: 100% !important;
            margin-top: 20px !important;
            box-sizing: border-box !important;
        }

        .btn-action {
            flex: 1 1 0% !important;
            width: 50% !important;
            height: 48px !important;
            padding: 0 16px !important;
            margin: 0 !important;
            border-radius: 8px !important;
            font-size: 0.95rem !important;
            font-weight: 600 !important;
            font-family: inherit !important;
            text-decoration: none !important;
            box-sizing: border-box !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
            line-height: 1 !important;
            outline: none !important;
            appearance: none !important;
        }

        .btn-action.secondary {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border: 1px solid #334155 !important;
        }

        .btn-action.secondary:hover {
            background-color: #334155 !important;
            border-color: #64748b !important;
        }

        .btn-action.primary {
            background-color: #38bdf8 !important;
            color: #0f172a !important;
            border: 1px solid #38bdf8 !important;
        }

        .btn-action.primary:hover {
            background-color: #0284c7 !important;
            border-color: #0284c7 !important;
            color: #ffffff !important;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-card {
            background-color: #0f172a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
        }

        .modal-card h4 {
            color: #f8fafc;
            font-size: 1.2rem;
            margin-bottom: 12px;
        }

        .modal-card p {
            color: #94a3b8;
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .modal-card .amount-highlight {
            color: #38bdf8;
            font-size: 1.4rem;
            font-weight: bold;
            display: block;
            margin: 8px 0;
        }
    </style>
</head>
<body>

<div class="container" style="max-width: 750px;">

    <h2>Checkout & Payment</h2>

    <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form id="payment-form" method="post" action="payment.php?movie_id=<?= $movie_id ?>&date=<?= urlencode($show_date) ?>&time=<?= urlencode($showtime) ?>&seats=<?= urlencode($seats_raw) ?>">
        <div class="checkout-grid">
            
            <div class="summary-card">
                <h3>Booking Summary</h3>
                <div class="info-row">
                    <span>Movie:</span>
                    <strong><?= htmlspecialchars($movie['title']) ?></strong>
                </div>
                <div class="info-row">
                    <span>Date:</span>
                    <strong><?= date('M d, Y', strtotime($show_date)) ?></strong>
                </div>
                <div class="info-row">
                    <span>Showtime:</span>
                    <strong><?= date('g:i A', strtotime($showtime)) ?></strong>
                </div>
                <div class="info-row">
                    <span>Seats Picked:</span>
                    <span class="seats-badge"><?= implode(', ', $selected_seats) ?></span>
                </div>

                <div class="price-divider"></div>

                <div class="info-row">
                    <span>Price per seat:</span>
                    <span>৳<?= number_format($price_per_seat, 2) ?></span>
                </div>
                <div class="info-row">
                    <span>Ticket Quantity:</span>
                    <span>x<?= $seat_count ?></span>
                </div>

                <div class="price-divider"></div>

                <div class="total-row">
                    <span>Total Pay:</span>
                    <span class="amount">৳<?= number_format($total_amount, 2) ?></span>
                </div>
            </div>

            <div class="payment-card">
                <h3>Payment Info</h3>
                
                <div class="form-group">
                    <label for="card_name">Account Holder Name</label>
                    <input type="text" id="card_name" name="card_name" placeholder="e.g. Tanvir Ahmed" required>
                </div>

                <div class="form-group">
                    <label for="account_num">Card / Mobile Banking Number (bKash/Nagad)</label>
                    <input type="text" id="account_num" name="account_num" placeholder="e.g. 017XXXXXXXX" required>
                </div>
            </div>

        </div>

        <div class="btn-group">
            <a href="seats.php?id=<?= $movie_id ?>&date=<?= urlencode($show_date) ?>&time=<?= urlencode($showtime) ?>" class="btn-action secondary">← Back to Seats</a>
            <button type="button" class="btn-action primary" onclick="showConfirmationModal()">Confirm & Pay ৳<?= number_format($total_amount, 2) ?></button>
        </div>
    </form>

</div>

<div id="confirmModal" class="modal-overlay">
    <div class="modal-card">
        <h4>Confirm Payment</h4>
        <p>You are about to pay: <span class="amount-highlight">৳<?= number_format($total_amount, 2) ?> BDT</span> for <strong><?= $seat_count ?> seat(s)</strong> (<?= implode(', ', $selected_seats) ?>).</p>
        <div class="btn-group">
            <button type="button" class="btn-action secondary" onclick="closeConfirmationModal()">Cancel</button>
            <button type="button" class="btn-action primary" onclick="submitPaymentForm()">Pay Now</button>
        </div>
    </div>
</div>

<script>
function showConfirmationModal() {
    const cardName = document.getElementById('card_name').value.trim();
    const accountNum = document.getElementById('account_num').value.trim();

    if (!cardName || !accountNum) {
        alert("Please fill in both Account Holder Name and Number before proceeding.");
        return;
    }

    document.getElementById('confirmModal').style.display = 'flex';
}

function closeConfirmationModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

function submitPaymentForm() {
    document.getElementById('payment-form').submit();
}
</script>

</body>
</html>
