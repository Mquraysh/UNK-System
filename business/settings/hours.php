<?php
// business/settings/hours.php - BUSINESS HOURS
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);

if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

$business_hours = json_decode($business['business_hours'] ?? '{}', true);
$hours = [
    'monday' => $business_hours['monday'] ?? '9:00 AM - 6:00 PM',
    'tuesday' => $business_hours['tuesday'] ?? '9:00 AM - 6:00 PM',
    'wednesday' => $business_hours['wednesday'] ?? '9:00 AM - 6:00 PM',
    'thursday' => $business_hours['thursday'] ?? '9:00 AM - 6:00 PM',
    'friday' => $business_hours['friday'] ?? '9:00 AM - 6:00 PM',
    'saturday' => $business_hours['saturday'] ?? '10:00 AM - 4:00 PM',
    'sunday' => $business_hours['sunday'] ?? 'Closed'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hours_json = json_encode([
        'monday' => $_POST['monday'], 'tuesday' => $_POST['tuesday'], 'wednesday' => $_POST['wednesday'],
        'thursday' => $_POST['thursday'], 'friday' => $_POST['friday'], 'saturday' => $_POST['saturday'], 'sunday' => $_POST['sunday']
    ]);
    mysqli_query($conn, "UPDATE businesses SET business_hours='$hours_json' WHERE business_id='{$business['business_id']}'");
    $_SESSION['flash_message'] = "Business hours updated!";
    $_SESSION['flash_type'] = "success";
    header("Location: hours.php");
    exit();
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Hours - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box;}
        .business-content { margin-left: 280px; padding: 30px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        .btn-back { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; max-width: 1200px; margin: 0 auto; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .card-header h3 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .card-header h3 i { color: #e67e22; }
        .card-body { padding: 28px; }
        .hours-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 15px; margin-bottom: 15px; align-items: center; }
        .day-label { font-weight: 600; color: #1e293b; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #e67e22; }
        .btn-save { background: #e67e22; color: white; border: none; padding: 12px 28px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; margin-top: 20px; }
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        small { font-size: 11px; color: #94a3b8; display: block; margin-top: 5px; }
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } .hours-grid { grid-template-columns: 1fr; gap: 8px; } }
        @media (max-width: 768px) { .page-header { flex-direction: column; } .btn-save { width: 100%; justify-content: center; } .card-body { padding: 20px; } }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <div><h1><i class="fas fa-clock"></i> Business Hours</h1><p>Set your operating schedule</p></div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    
    <?php if (isset($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>"><i class="fas fa-check-circle"></i><?php echo $flash_message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-calendar-alt"></i> Operating Hours</h3></div>
        <div class="card-body">
            <form method="POST">
                <div class="hours-grid"><div class="day-label">Monday</div><input type="text" name="monday" class="form-control" value="<?php echo $hours['monday']; ?>"></div>
                <div class="hours-grid"><div class="day-label">Tuesday</div><input type="text" name="tuesday" class="form-control" value="<?php echo $hours['tuesday']; ?>"></div>
                <div class="hours-grid"><div class="day-label">Wednesday</div><input type="text" name="wednesday" class="form-control" value="<?php echo $hours['wednesday']; ?>"></div>
                <div class="hours-grid"><div class="day-label">Thursday</div><input type="text" name="thursday" class="form-control" value="<?php echo $hours['thursday']; ?>"></div>
                <div class="hours-grid"><div class="day-label">Friday</div><input type="text" name="friday" class="form-control" value="<?php echo $hours['friday']; ?>"></div>
                <div class="hours-grid"><div class="day-label">Saturday</div><input type="text" name="saturday" class="form-control" value="<?php echo $hours['saturday']; ?>"></div>
                <div class="hours-grid"><div class="day-label">Sunday</div><input type="text" name="sunday" class="form-control" value="<?php echo $hours['sunday']; ?>"></div>
                <small>Format: 9:00 AM - 6:00 PM or "Closed"</small>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Hours</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>