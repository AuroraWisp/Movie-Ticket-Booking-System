<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['last_booking'])) {
    header("Location: movies.php");
    exit();
}

$booking = $_SESSION['last_booking'];
$booking_id = $booking['ref'] ?? ("TKT-" . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8)));
$user_name = $_SESSION['user_name'] ?? 'Customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineVerse</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .success-banner {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid #10b981;
            color: #34d399;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .ticket-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border: 1px solid #334155;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            margin-bottom: 25px;
            text-align: left;
            position: relative;
        }

        .ticket-header {
            background-color: #38bdf8;
            color: #0f172a;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ticket-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .ticket-id {
            font-size: 0.85rem;
            font-weight: bold;
            background-color: #0f172a;
            color: #38bdf8;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .ticket-body { padding: 24px; }

        .ticket-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .ticket-field {
            display: flex;
            flex-direction: column;
        }

        .ticket-field .label {
            color: #64748b;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .ticket-field .value {
            color: #f8fafc;
            font-weight: 600;
            font-size: 1rem;
        }

        .seat-badge {
            background-color: #0284c7;
            color: #ffffff;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
            font-size: 0.9rem;
        }

        .stub-divider {
            border-top: 2px dashed #334155;
            position: relative;
            margin: 20px 0;
        }

        .stub-divider::before,
        .stub-divider::after {
            content: '';
            position: absolute;
            top: -12px;
            width: 24px;
            height: 24px;
            background-color: #0b0f19;
            border-radius: 50%;
        }

        .stub-divider::before { left: -36px; }
        .stub-divider::after { right: -36px; }

        .ticket-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 5px;
        }

        .total-paid {
            font-size: 1.2rem;
            color: #34d399;
            font-weight: bold;
        }

        .btn-group-3 {
            display: flex !important;
            gap: 12px !important;
            width: 100% !important;
            margin-top: 10px !important;
            box-sizing: border-box !important;
        }

        .btn-action {
            flex: 1 1 0% !important;
            width: 33.33% !important;
            height: 48px !important;
            padding: 0 10px !important;
            margin: 0 !important;
            border-radius: 8px !important;
            font-size: 0.88rem !important;
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

        .btn-action.danger {
            background-color: rgba(248, 113, 113, 0.1) !important;
            color: #f87171 !important;
            border: 1px solid #f87171 !important;
        }

        .btn-action.danger:hover {
            background-color: #ef4444 !important;
            color: #ffffff !important;
            border-color: #ef4444 !important;
        }

        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            .success-banner, .btn-group-3 {
                display: none !important;
            }
            .ticket-card {
                border: 2px solid #000 !important;
                box-shadow: none !important;
                background: #fff !important;
                color: #000 !important;
            }
            .ticket-header {
                background: #000 !important;
                color: #fff !important;
            }
            .ticket-field .value, .ticket-field .label {
                color: #000 !important;
            }
            .seat-badge {
                background: #000 !important;
                color: #fff !important;
            }
            .stub-divider::before, .stub-divider::after {
                background: #fff !important;
            }
        }
    </style>
</head>
<body>

<div class="container" style="max-width: 650px;">

    <div class="success-banner">
        <span>🎉 Booking Confirmed! Payment Received.</span>
    </div>

    <div class="ticket-card">
        <div class="ticket-header">
            <h3>CINEMA MOVIE TICKET</h3>
            <span class="ticket-id"><?= $booking_id ?></span>
        </div>

        <div class="ticket-body">
            <div class="ticket-row">
                <div class="ticket-field">
                    <span class="label">Customer Name</span>
                    <span class="value"><?= htmlspecialchars($user_name) ?></span>
                </div>
                <div class="ticket-field">
                    <span class="label">Movie Title</span>
                    <span class="value"><?= htmlspecialchars($booking['movie']) ?></span>
                </div>
            </div>

            <div class="ticket-row">
                <div class="ticket-field">
                    <span class="label">Show Date</span>
                    <span class="value">📅 <?= date('M d, Y', strtotime($booking['date'])) ?></span>
                </div>
                <div class="ticket-field">
                    <span class="label">Showtime</span>
                    <span class="value">⏰ <?= date('g:i A', strtotime($booking['time'])) ?></span>
                </div>
            </div>

            <div class="ticket-row">
                <div class="ticket-field">
                    <span class="label">Reserved Seats</span>
                    <div>
                        <span class="seat-badge"><?= implode(', ', $booking['seats']) ?></span>
                    </div>
                </div>
                <div class="ticket-field">
                    <span class="label">Status</span>
                    <span class="value" style="color: #34d399;">PAID / BOOKED</span>
                </div>
            </div>

            <div class="stub-divider"></div>

            <div class="ticket-footer">
                <div class="ticket-field">
                    <span class="label">Total Amount</span>
                    <span class="total-paid">৳<?= number_format($booking['amount'], 2) ?> BDT</span>
                </div>
                <div class="ticket-field" style="text-align: right;">
                    <span class="label">Payment Status</span>
                    <span class="value">Completed</span>
                </div>
            </div>
        </div>
    </div>

    <div class="btn-group-3">
        <button onclick="window.print()" class="btn-action secondary">🖨️ Print Ticket</button>
        <a href="logout.php" class="btn-action danger">Logout</a>
    </div>

</div>

</body>
</html>
