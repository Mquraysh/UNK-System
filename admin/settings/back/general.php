<?php
// admin/settings/general.php - GENERAL SYSTEM SETTINGS
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Fetch settings
$settings_sql = "SELECT * FROM settings";
$settings_res = mysqli_query($conn, $settings_sql);
$settings = [];
while($row = mysqli_fetch_assoc($settings_res)) {
    $settings[$row['setting_key']] = $row;
}

// Fetch delivery rates
$rates_sql = "SELECT * FROM delivery_rates ORDER BY min_distance ASC";
$rates_res = mysqli_query($conn, $rates_sql);
$rates = [];
while($row = mysqli_fetch_assoc($rates_res)) {
    $rates[] = $row;
}

// Flash message
$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Settings - UNK Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
         * { margin: 0; padding: 0; box-sizing: border-box; }
        .admin-content {
            margin-left: 280px;
            padding: 30px 35px;
            background: #f1f5f9;
        }
        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 {
            font-size: 28px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .settings-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 25px;
        }
        .card-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header i { color: #e67e22; }
        .card-header h3 { margin: 0; font-size: 18px; }
        .card-body { padding: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-save { background: #e67e22; color: white; padding: 12px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; margin-top: 10px; }
        .checkbox-group { display: flex; align-items: center; gap: 12px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .data-table th { background: #f8fafc; font-weight: 600; }
        .btn-add-rate { background: #27ae60; color: white; border: none; padding: 6px 16px; border-radius: 8px; cursor: pointer; margin-top: 15px; }
        .btn-remove-rate { background: #e74c3c; color: white; border: none; padding: 4px 10px; border-radius: 6px; cursor: pointer; }
        .help-text { font-size: 11px; color: #64748b; margin-top: 5px; }
        @media (max-width:1024px) { .admin-content { margin-left:0; padding:20px; } .form-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-cog"></i> General Settings</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>

    <?php if($flash_message): ?>
        <div class="alert alert-<?php echo $flash_type; ?>"><?php echo $flash_message; ?></div>
    <?php endif; ?>

    <form method="POST" action="save-general.php">
        <!-- BASIC INFO -->
        <div class="settings-card">
            <div class="card-header"><i class="fas fa-info-circle"></i><h3>Basic Information</h3></div>
            <div class="card-body">
                <div class="form-group"><label>Site Name</label><input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name']['setting_value'] ?? 'UNK System'); ?>"></div>
                <div class="form-group"><label>Site Logo URL</label><input type="text" name="site_logo" class="form-control" value="<?php echo htmlspecialchars($settings['site_logo']['setting_value'] ?? ''); ?>" placeholder="https://..."></div>
                <div class="form-row">
                    <div class="form-group"><label>Contact Email</label><input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($settings['contact_email']['setting_value'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Contact Phone</label><input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($settings['contact_phone']['setting_value'] ?? ''); ?>"></div>
                </div>
                <div class="form-group"><label>Contact Address</label><input type="text" name="contact_address" class="form-control" value="<?php echo htmlspecialchars($settings['contact_address']['setting_value'] ?? ''); ?>"></div>
                <div class="form-group"><label>About Text (Footer)</label><textarea name="about_text" class="form-control" rows="3"><?php echo htmlspecialchars($settings['about_text']['setting_value'] ?? ''); ?></textarea></div>
                <div class="checkbox-group"><input type="checkbox" name="maintenance_mode" value="1" id="maintenance" <?php echo (($settings['maintenance_mode']['setting_value'] ?? 0) == 1) ? 'checked' : ''; ?>> <label for="maintenance">Enable Maintenance Mode (only admins can access)</label></div>
            </div>
        </div>

        <!-- BUSINESS & DELIVERY -->
        <div class="settings-card">
            <div class="card-header"><i class="fas fa-truck"></i><h3>Business & Delivery</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group"><label>Default Delivery Fee (TSh)</label><input type="number" name="delivery_fee_default" class="form-control" value="<?php echo htmlspecialchars($settings['delivery_fee_default']['setting_value'] ?? 5000); ?>"><div class="help-text">Fallback if no distance rate matches</div></div>
                    <div class="form-group"><label>Commission Rate (%)</label><input type="number" step="0.01" name="commission_rate" class="form-control" value="<?php echo htmlspecialchars($settings['commission_rate']['setting_value'] ?? 10); ?>"></div>
                </div>
                <div class="form-group"><label>Low Stock Threshold</label><input type="number" name="low_stock_threshold" class="form-control" value="<?php echo htmlspecialchars($settings['low_stock_threshold']['setting_value'] ?? 10); ?>"></div>
            </div>
        </div>

        <!-- SOCIAL MEDIA -->
        <div class="settings-card">
            <div class="card-header"><i class="fas fa-share-alt"></i><h3>Social Media Links</h3></div>
            <div class="card-body">
                <div class="form-group"><label>Facebook</label><input type="url" name="facebook_url" class="form-control" value="<?php echo htmlspecialchars($settings['facebook_url']['setting_value'] ?? ''); ?>"></div>
                <div class="form-group"><label>Twitter</label><input type="url" name="twitter_url" class="form-control" value="<?php echo htmlspecialchars($settings['twitter_url']['setting_value'] ?? ''); ?>"></div>
                <div class="form-group"><label>Instagram</label><input type="url" name="instagram_url" class="form-control" value="<?php echo htmlspecialchars($settings['instagram_url']['setting_value'] ?? ''); ?>"></div>
            </div>
        </div>

        <!-- DISTANCE‑BASED DELIVERY RATES -->
        <div class="settings-card">
            <div class="card-header"><i class="fas fa-road"></i><h3>Distance‑Based Delivery Fees</h3></div>
            <div class="card-body">
                <table class="data-table">
                    <thead><tr><th>Min (km)</th><th>Max (km)</th><th>Fee (TSh)</th><th>Description</th><th></th></tr></thead>
                    <tbody id="rates_tbody">
                        <?php foreach($rates as $rate): ?>
                        <tr>
                            <td><input type="number" step="0.01" name="rates[<?php echo $rate['rate_id']; ?>][min_distance]" value="<?php echo $rate['min_distance']; ?>" class="form-control" style="width:100px"></td>
                            <td><input type="number" step="0.01" name="rates[<?php echo $rate['rate_id']; ?>][max_distance]" value="<?php echo $rate['max_distance']; ?>" class="form-control" style="width:100px"></td>
                            <td><input type="number" step="0.01" name="rates[<?php echo $rate['rate_id']; ?>][fee]" value="<?php echo $rate['fee']; ?>" class="form-control" style="width:120px"></td>
                            <td><input type="text" name="rates[<?php echo $rate['rate_id']; ?>][description]" value="<?php echo htmlspecialchars($rate['description']); ?>" class="form-control"></td>
                            <td><button type="button" class="btn-remove-rate" data-id="<?php echo $rate['rate_id']; ?>"><i class="fas fa-trash"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" id="addRateBtn" class="btn-add-rate"><i class="fas fa-plus"></i> Add new rate</button>
                <div class="help-text">Delivery fee will be calculated based on the distance between business and customer.</div>
            </div>
        </div>

        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save All Settings</button>
    </form>
</div>

<script>
    let newIndex = <?php echo count($rates); ?>;
    const tbody = document.getElementById('rates_tbody');
    document.getElementById('addRateBtn').addEventListener('click', function() {
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td><input type="number" step="0.01" name="new_rates[${newIndex}][min_distance]" class="form-control" style="width:100px"></td>
            <td><input type="number" step="0.01" name="new_rates[${newIndex}][max_distance]" class="form-control" style="width:100px"></td>
            <td><input type="number" step="0.01" name="new_rates[${newIndex}][fee]" class="form-control" style="width:120px"></td>
            <td><input type="text" name="new_rates[${newIndex}][description]" class="form-control"></td>
            <td><button type="button" class="btn-remove-rate" data-id="new"><i class="fas fa-times"></i></button></td>
        `;
        tbody.appendChild(newRow);
        newIndex++;
    });

    tbody.addEventListener('click', function(e) {
        if(e.target.closest('.btn-remove-rate')) {
            const btn = e.target.closest('.btn-remove-rate');
            const rateId = btn.getAttribute('data-id');
            if(rateId === 'new') {
                btn.closest('tr').remove();
            } else {
                if(confirm('Delete this rate?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'delete-rate.php';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'rate_id';
                    input.value = rateId;
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
    });
</script>
</body>
</html>