<?php
// delivery/includes/delivery_header.php - DELIVERY TOP HEADER
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f5;
            overflow-x: hidden;
        }
        
        /* Top Navigation Bar */
        .top-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 30px;
            z-index: 99;
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            gap: 25px;
        }
        
        .notification-icon {
            position: relative;
            cursor: pointer;
            color: #64748b;
            transition: all 0.3s;
        }
        
        .notification-icon:hover {
            color: #e67e22;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 5px 12px;
            border-radius: 30px;
            transition: all 0.3s;
        }
        
        .user-menu:hover {
            background: #f8fafc;
        }
        
        .user-avatar-small {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #e67e22, #f39c12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .user-name small {
            font-size: 10px;
            font-weight: 400;
            color: #64748b;
            display: block;
        }
        
        @media (max-width: 1024px) {
            .top-nav {
                left: 0;
                padding: 0 15px;
            }
            .user-name {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-right">
            <div class="user-menu">
                <div class="user-avatar-small">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'D', 0, 1)); ?>
                </div>
                <div class="user-name">
                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Delivery Agent'); ?>
                    <small>Delivery Partner</small>
                </div>
            </div>
        </div>
    </div>