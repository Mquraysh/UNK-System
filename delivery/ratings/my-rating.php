<?php
// delivery/ratings/my-rating.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$agent_sql = "SELECT * FROM delivery_agents WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $agent_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$agent_result = mysqli_stmt_get_result($stmt);
$agent = mysqli_fetch_assoc($agent_result);
mysqli_stmt_close($stmt);

if (!$agent) {
    header("Location: ../register.php");
    exit();
}

$agent_id = $agent['agent_id'];
$first_name = $agent['first_name'];
$last_name = $agent['last_name'];
$full_name = $first_name . ' ' . $last_name;


// ================== RATING STATISTICS ==================
$stats_sql = "SELECT 
                COUNT(*) as total_ratings,
                AVG(rating) as avg_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as star5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as star4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as star3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as star2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as star1
              FROM delivery_ratings 
              WHERE agent_id = ?";
$stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stmt, "i", $agent_id);
mysqli_stmt_execute($stmt);
$stats_res = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_res);
mysqli_stmt_close($stmt);

$total_ratings = (int)($stats['total_ratings'] ?? 0);
$avg_rating = $total_ratings > 0 ? round($stats['avg_rating'], 1) : 0;
$star_counts = [
    5 => (int)($stats['star5'] ?? 0),
    4 => (int)($stats['star4'] ?? 0),
    3 => (int)($stats['star3'] ?? 0),
    2 => (int)($stats['star2'] ?? 0),
    1 => (int)($stats['star1'] ?? 0),
];

// Percentages
$percentages = [];
foreach ($star_counts as $stars => $count) {
    $percentages[$stars] = $total_ratings > 0 ? round(($count / $total_ratings) * 100) : 0;
}

// ================== RECENT REVIEWS WITH COMMENTS ==================
$reviews_sql = "SELECT r.*, o.order_id, c.first_name, c.last_name 
                FROM delivery_ratings r
                JOIN orders o ON r.order_id = o.order_id
                JOIN customers c ON r.customer_id = c.customer_id
                WHERE r.agent_id = ?
                ORDER BY r.created_at DESC
                LIMIT 20";
$stmt = mysqli_prepare($conn, $reviews_sql);
mysqli_stmt_bind_param($stmt, "i", $agent_id);
mysqli_stmt_execute($stmt);
$reviews_res = mysqli_stmt_get_result($stmt);
$reviews = [];
while ($row = mysqli_fetch_assoc($reviews_res)) {
    $reviews[] = $row;
}
mysqli_stmt_close($stmt);

// ================== RATING TREND (last 30 days) ==================
$trend_sql = "SELECT DATE(created_at) as date, AVG(rating) as daily_avg
              FROM delivery_ratings
              WHERE agent_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              GROUP BY DATE(created_at)
              ORDER BY date ASC";
$stmt = mysqli_prepare($conn, $trend_sql);
mysqli_stmt_bind_param($stmt, "i", $agent_id);
mysqli_stmt_execute($stmt);
$trend_res = mysqli_stmt_get_result($stmt);
$trend_labels = [];
$trend_data = [];
while ($row = mysqli_fetch_assoc($trend_res)) {
    $trend_labels[] = date('M d', strtotime($row['date']));
    $trend_data[] = round($row['daily_avg'], 1);
}
mysqli_stmt_close($stmt);

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Rating & Reviews | Delivery Agent</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .delivery-content {
            margin-left: 280px;
            padding: 32px 40px;
            min-height: 100vh;
            background: #f7f9fc;
            transition: all 0.2s;
        }
        .page-header {
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i {
            background: none;
            color: #e67e22;
        }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 6px;
        }
        .btn-refresh {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-refresh:hover {
            background: #1a252f;
            transform: translateY(-2px);
        }
        .agent-badge {
            background: white;
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 13px;
            border: 1px solid #eef2f8;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .agent-badge i { color: #e67e22; }

        /* Rating Summary */
        .rating-summary {
            background: white;
            border-radius: 32px;
            padding: 32px;
            margin-bottom: 32px;
            display: flex;
            gap: 48px;
            flex-wrap: wrap;
            align-items: center;
            border: 1px solid #eef2f8;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }
        .avg-rating {
            text-align: center;
            padding: 20px 32px;
            background: linear-gradient(145deg, #fefaf5, #fff7ed);
            border-radius: 28px;
            min-width: 180px;
        }
        .avg-rating .big-number {
            font-size: 68px;
            font-weight: 800;
            color: #f39c12;
            line-height: 1;
        }
        .avg-rating .stars {
            color: #f39c12;
            font-size: 18px;
            margin: 12px 0 8px;
        }
        .avg-rating .total-count {
            font-size: 13px;
            color: #64748b;
        }
        .rating-bars {
            flex: 1;
        }
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }
        .rating-bar-item .star-label {
            width: 50px;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }
        .rating-bar {
            flex: 1;
            height: 8px;
            background: #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
        }
        .rating-bar-fill {
            height: 100%;
            background: #f39c12;
            border-radius: 20px;
            transition: width 0.3s ease;
        }
        .rating-count {
            width: 55px;
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        /* Cards */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-bottom: 32px;
        }
        .card {
            background: white;
            border-radius: 28px;
            border: 1px solid #eef2f8;
            overflow: hidden;
            transition: box-shadow 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .card:hover {
            box-shadow: 0 12px 24px -12px rgba(0,0,0,0.08);
        }
        .card-header {
            padding: 22px 28px;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header h3 i {
            color: #e67e22;
        }
        .card-body {
            padding: 24px 28px;
        }
        .chart-container {
            height: 260px;
            position: relative;
        }

        /* Reviews List */
        .reviews-list {
            max-height: 450px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .reviews-list::-webkit-scrollbar {
            width: 4px;
        }
        .reviews-list::-webkit-scrollbar-track {
            background: #eef2f8;
            border-radius: 4px;
        }
        .reviews-list::-webkit-scrollbar-thumb {
            background: #e67e22;
            border-radius: 4px;
        }

        .review-item {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        .review-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .review-customer {
            font-weight: 700;
            color: #e67e22;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .review-customer i {
            font-size: 18px;
        }
        .review-rating {
            color: #f39c12;
            font-size: 13px;
            letter-spacing: 1px;
        }
        .review-date {
            font-size: 11px;
            color: #94a3b8;
        }
        .review-text {
            color: #475569;
            font-size: 13px;
            line-height: 1.6;
            margin-top: 8px;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 3px solid #e67e22;
        }
        .review-text .no-comment {
            color: #94a3b8;
            font-style: italic;
            font-weight: 400;
        }
        .order-badge {
            display: inline-block;
            margin-top: 8px;
            font-size: 11px;
            color: #e67e22;
            background: #fef3c7;
            padding: 4px 12px;
            border-radius: 20px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 56px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Toast */
        .toast-message {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1e293b;
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            font-weight: 500;
        }

        /* No Reviews with Comments State */
        .no-comment-state {
            text-align: center;
            padding: 20px;
            color: #94a3b8;
        }
        .no-comment-state i {
            font-size: 32px;
            margin-bottom: 8px;
            opacity: 0.3;
        }

        @media (max-width: 1100px) {
            .delivery-content { margin-left: 0; padding: 24px; }
            .two-columns { grid-template-columns: 1fr; gap: 24px; }
        }
        @media (max-width: 768px) {
            .delivery-content { padding: 16px; }
            .rating-summary { flex-direction: column; align-items: stretch; padding: 24px; }
            .avg-rating { padding: 16px; }
            .card-header, .card-body { padding: 18px 20px; }
            .avg-rating .big-number { font-size: 48px; }
        }
        @media (max-width: 480px) {
            .rating-bar-item { gap: 8px; }
            .star-label { width: 40px; }
            .review-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-star"></i> My Rating & Reviews</h1>
            <p>See how customers rate your delivery service</p>
        </div>
        <!-- <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button class="btn-refresh" onclick="window.location.reload();">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <div class="agent-badge">
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($full_name); ?>
            </div>
        </div> -->
    </div>

    <!-- Rating Summary -->
    <div class="rating-summary">
        <div class="avg-rating">
            <div class="big-number"><?php echo $avg_rating; ?></div>
            <div class="stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star<?php echo $i <= round($avg_rating) ? '' : '-o'; ?>"></i>
                <?php endfor; ?>
            </div>
            <div class="total-count">based on <?php echo $total_ratings; ?> ratings</div>
        </div>
        <div class="rating-bars">
            <?php for ($i = 5; $i >= 1; $i--): ?>
            <div class="rating-bar-item">
                <span class="star-label"><?php echo $i; ?> <i class="fas fa-star" style="color: #f39c12;"></i></span>
                <div class="rating-bar">
                    <div class="rating-bar-fill" style="width: <?php echo $percentages[$i]; ?>%"></div>
                </div>
                <span class="rating-count"><?php echo $star_counts[$i]; ?></span>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="two-columns">
        <!-- Trend Chart -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Rating Trend (Last 30 Days)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($trend_labels)): ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <p>No rating data available for trend</p>
                    </div>
                <?php else: ?>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Reviews -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-comments"></i> Recent Reviews</h3>
                <span style="font-size: 12px; color: #64748b;"><?php echo count($reviews); ?> reviews</span>
            </div>
            <div class="card-body">
                <?php if (empty($reviews)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No reviews yet. Complete deliveries to receive feedback.</p>
                    </div>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $rev): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <span class="review-customer">
                                    <i class="fas fa-user-circle"></i>
                                    <?php echo htmlspecialchars($rev['first_name'] . ' ' . substr($rev['last_name'], 0, 1) . '.'); ?>
                                </span>
                                <span class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $rev['rating'] ? '' : '-o'; ?>"></i>
                                    <?php endfor; ?>
                                </span>
                                <span class="review-date"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></span>
                            </div>
 
                            <!-- Comment Display  -->
                            <?php if (!empty($rev['comment'])): ?>
                                <div class="review-text">
                                    <?php echo nl2br(htmlspecialchars($rev['comment'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="review-text">
                                    <span class="no-comment"><i class="far fa-comment-dots"></i> No comment left</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="order-badge">
                                <i class="fas fa-shopping-bag"></i> Order <?php echo $rev['order_id']; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="toastMessage" class="toast-message"></div>

<script>
    function showToast(message, isError = false) {
        const toast = document.getElementById('toastMessage');
        toast.textContent = message;
        toast.style.backgroundColor = isError ? '#dc2626' : '#10b981';
        toast.style.opacity = '1';
        setTimeout(() => { toast.style.opacity = '0'; }, 3000);
    }

    <?php if (!empty($trend_labels) && !empty($trend_data)): ?>
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_labels); ?>,
            datasets: [{
                label: 'Average Daily Rating',
                data: <?php echo json_encode($trend_data); ?>,
                borderColor: '#e67e22',
                backgroundColor: 'rgba(230,126,34,0.03)',
                borderWidth: 2.5,
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#e67e22',
                pointBorderColor: '#fff',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return 'Rating: ' + ctx.raw.toFixed(1) + ' ★';
                        }
                    }
                },
                legend: {
                    position: 'top',
                    labels: { usePointStyle: true, boxWidth: 8 }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    ticks: { stepSize: 1, callback: (v) => v + ' ★' },
                    grid: { color: '#eef2f8' }
                },
                x: {
                    ticks: { maxRotation: 45, minRotation: 30 },
                    grid: { display: false }
                }
            }
        }
    });
    <?php endif; ?>
</script>
</body>
</html>