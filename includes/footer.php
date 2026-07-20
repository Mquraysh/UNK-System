<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
         * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { font-family: 'Inter', sans-serif; background: #f7f9fc; color: #1e293b; }
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        /* Footer */
        .footer {
            background: #2c3e50;
            color: #bdc3c7;
            padding: 50px 0 20px;
            margin-top: 50px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .footer-section h4 {
            color: white;
            margin-bottom: 15px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-section h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background: #e67e22;
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section ul li {
            margin-bottom: 10px;
        }
        
        .footer-section ul li a {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-section ul li a:hover {
            color: #e67e22;
            padding-left: 5px;
        }
        
        .footer-section ul li i {
            margin-right: 8px;
            width: 20px;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #34495e;
            font-size: 12px;
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @media (max-width: 480px) {
            
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .footer-section h4::after {
                left: 50%;
                transform: translateX(-50%);
            }
        }
    </style>
</head>
    <body>
        <!-- Footer -->
        <footer class="footer">
            <div class="container">
                <div class="footer-content">
                    <div class="footer-section">
                        <h4>UNK System</h4>
                        <p>Ulipo ni Kariakoo - Your trusted online marketplace for quality products and reliable delivery services in Tanzania.</p>
                    </div>
                    <div class="footer-section">
                        <h4>Quick Links</h4>
                        <ul>
                            <li><a href="../../customer/products/index.php"><i class="fas fa-store"></i> Browse Products</a></li>
                            <li><a href="../../business/register.php"><i class="fas fa-chart-line"></i> Sell on UNK</a></li>
                            <li><a href="../../delivery/register.php"><i class="fas fa-truck"></i> Become a Driver</a></li>
                            <li><a href="../../customer/login.php"><i class="fas fa-sign-in-alt"></i> Customer Login</a></li>
                        </ul>
                    </div>
                    <div class="footer-section">
                        <h4>Contact Us</h4>
                        <ul>
                            <li><i class="fas fa-map-marker-alt"></i> Kariakoo, Dar es Salaam</li>
                            <li><i class="fas fa-phone"></i> +255 615 215 404</li>
                            <li><i class="fas fa-envelope"></i> info@unksystem.com</li>
                        </ul>
                    </div>
                </div>
                <div class="footer-bottom">
                    <p>&copy; <?php echo date('Y'); ?> UNK System. All rights reserved. | <a href="#" style="color: #e67e22;">Privacy Policy</a> | <a href="#" style="color: #e67e22;">Terms of Service</a></p>
                </div>
            </div>
        </footer>
        <script>

        </script>

    </body>
</html>