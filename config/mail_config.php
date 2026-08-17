<?php
// config/mail_config.php
$phpmailer_path = __DIR__ . '/../vendor/phpmailer/src/';

// Check if folder exists
if (!file_exists($phpmailer_path)) {
    // Try alternative paths
    $alt_paths = [
        __DIR__ . '/../phpmailer/src/',
        __DIR__ . '/../../vendor/phpmailer/src/',
        __DIR__ . '/vendor/phpmailer/src/',
        'C:/xampp/htdocs/UNK-System2/vendor/phpmailer/src/'
    ];
    
    foreach ($alt_paths as $path) {
        if (file_exists($path)) {
            $phpmailer_path = $path;
            break;
        }
    }
}

// Include PHPMailer files
if (file_exists($phpmailer_path . 'Exception.php')) {
    require_once $phpmailer_path . 'Exception.php';
    require_once $phpmailer_path . 'PHPMailer.php';
    require_once $phpmailer_path . 'SMTP.php';
} else {
    // If files not found, show error with debugging info
    $error = "PHPMailer files not found at: " . $phpmailer_path . "<br>";
    $error .= "Current directory: " . __DIR__ . "<br>";
    $error .= "Please check that PHPMailer is installed in: vendor/phpmailer/src/";
    die($error);
}

// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ============================================
// STEP 2: Mailer Class
// ============================================
class Mailer {
    private static $instance = null;
    private $mail;
    
    // ============================================
    // EMAIL CREDENTIALS - UPDATED
    // ============================================
    private $host = 'smtp.gmail.com';                    // SMTP server
    private $username = 'albinokh425@gmail.com';         // ← UPDATED: Your email
    private $password = 'hgww grom kage sadr';           // ← UPDATED: App Password
    private $port = 587;                                 // SMTP port
    private $from_email = 'albinokh425@gmail.com';       // ← UPDATED: From email
    private $from_name = 'UNK Delivery System';          // Sender name
    
    private function __construct() {
        try {
            $this->mail = new PHPMailer(true);
            
            // Server settings
            $this->mail->isSMTP();                                            // Send using SMTP
            $this->mail->Host       = $this->host;                           // Set the SMTP server to send through
            $this->mail->SMTPAuth   = true;                                  // Enable SMTP authentication
            $this->mail->Username   = 'albinokh425@gmail.com';               // ← UPDATED: SMTP username
            $this->mail->Password   = 'hgww grom kage sadr';                 // ← UPDATED: SMTP password (App Password)
            $this->mail->Port       = $this->port;                           // TCP port to connect to
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;        // Enable TLS encryption
            
            // Default from
            $this->mail->setFrom('albinokh425@gmail.com', 'UNK Delivery System'); // ← UPDATED
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
            
            // Debug mode (uncomment to see errors)
            // $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
            
        } catch (Exception $e) {
            error_log("Mailer initialization failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Send email to single recipient
     */
    public function sendEmail($to, $subject, $body, $altBody = null) {
        try {
            $mail = clone $this->mail;
            $mail->clearAddresses();
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $altBody ?? strip_tags($body);
            
            $mail->send();
            
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send email to multiple recipients
     */
    public function sendBulkEmail($recipients, $subject, $body, $altBody = null) {
        try {
            $mail = clone $this->mail;
            $mail->clearAddresses();
            
            foreach ($recipients as $email => $name) {
                $mail->addAddress($email, $name);
            }
            
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $altBody ?? strip_tags($body);
            
            $mail->send();
            
            return [
                'success' => true,
                'message' => 'Emails sent successfully',
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send emails',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send OTP email to customer
     */
    public function sendOTPEmail($to, $customerName, $otp, $deliveryId, $businessName) {
        $subject = '🔐 Your Delivery OTP - UNK System';
        
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #eef2f8; }
                .header h1 { color: #e67e22; margin: 0; font-size: 28px; }
                .header p { color: #64748b; margin: 5px 0 0; }
                .otp-box { background: #f8fafc; border-radius: 12px; padding: 20px; text-align: center; margin: 25px 0; }
                .otp-code { font-size: 48px; font-weight: 800; color: #e67e22; letter-spacing: 8px; }
                .otp-label { font-size: 14px; color: #64748b; margin-bottom: 10px; }
                .info-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 8px; margin: 20px 0; }
                .info-box strong { color: #92400e; }
                .footer { text-align: center; padding-top: 20px; border-top: 1px solid #eef2f8; font-size: 12px; color: #94a3b8; }
                .footer a { color: #e67e22; text-decoration: none; }
                .delivery-details { background: #f1f5f9; border-radius: 8px; padding: 15px; margin: 15px 0; }
                .delivery-details .row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #e2e8f0; }
                .delivery-details .row:last-child { border-bottom: none; }
                .label { color: #64748b; font-size: 13px; }
                .value { font-weight: 600; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🚚 UNK Delivery</h1>
                    <p>Your OTP for delivery confirmation</p>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <p style="font-size: 16px; color: #1e293b;">
                        Hello <strong>' . htmlspecialchars($customerName) . '</strong>!
                    </p>
                    <p style="color: #64748b;">
                        Your delivery from <strong>' . htmlspecialchars($businessName) . '</strong> is on its way.
                        Please provide the following OTP to your delivery agent to confirm receipt:
                    </p>
                </div>
                
                <div class="otp-box">
                    <div class="otp-label">Your One-Time Password (OTP)</div>
                    <div class="otp-code">' . $otp . '</div>
                    <p style="font-size: 12px; color: #94a3b8; margin-top: 10px;">
                        ⏰ Expires in 15 minutes
                    </p>
                </div>
                
                <div class="delivery-details">
                    <div class="row">
                        <span class="label">Delivery ID</span>
                        <span class="value">#' . $deliveryId . '</span>
                    </div>
                    <div class="row">
                        <span class="label">Business</span>
                        <span class="value">' . htmlspecialchars($businessName) . '</span>
                    </div>
                </div>
                
                <div class="info-box">
                    <strong>⚠️ Important:</strong>
                    <ul style="margin: 5px 0 0 20px; color: #92400e; font-size: 13px;">
                        <li>Never share this OTP with anyone</li>
                        <li>This OTP is valid for 15 minutes only</li>
                        <li>Only provide it to your delivery agent</li>
                    </ul>
                </div>
                
                <div style="text-align: center;">
                    <p style="color: #64748b; font-size: 13px; margin-top: 10px;">
                        <i class="fas fa-shield-alt"></i> This is an automated message. Do not reply.
                    </p>
                </div>
                
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' UNK Delivery System. All rights reserved.</p>
                    <p>Need help? <a href="mailto:support@unksystem.com">Contact Support</a></p>
                </div>
            </div>
        </body>
        </html>';
        
        $altBody = "Hello " . $customerName . ",\n\n";
        $altBody .= "Your delivery from " . $businessName . " is on its way.\n";
        $altBody .= "Your OTP for delivery confirmation is: " . $otp . "\n";
        $altBody .= "This OTP expires in 15 minutes.\n";
        $altBody .= "Delivery ID: #" . $deliveryId . "\n\n";
        $altBody .= "Never share this OTP with anyone.\n";
        $altBody .= "Only provide it to your delivery agent.\n\n";
        $altBody .= "This is an automated message. Do not reply.";
        
        return $this->sendEmail($to, $subject, $body, $altBody);
    }
    
    /**
     * Send delivery confirmation email to customer
     */
    public function sendDeliveryConfirmedEmail($to, $customerName, $deliveryId, $businessName, $agentName) {
        $subject = '✅ Delivery Confirmed - UNK System';
        
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #eef2f8; }
                .header h1 { color: #27ae60; margin: 0; font-size: 28px; }
                .success-icon { font-size: 64px; color: #27ae60; text-align: center; display: block; margin: 20px 0; }
                .info-box { background: #d1fae5; border-left: 4px solid #27ae60; padding: 15px; border-radius: 8px; margin: 20px 0; }
                .footer { text-align: center; padding-top: 20px; border-top: 1px solid #eef2f8; font-size: 12px; color: #94a3b8; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✅ Delivery Confirmed</h1>
                </div>
                
                <div style="text-align: center;">
                    <div class="success-icon">✅</div>
                    <h2 style="color: #1e293b;">Your delivery has been confirmed!</h2>
                    <p style="color: #64748b;">
                        Thank you for using UNK Delivery System.
                    </p>
                </div>
                
                <div class="info-box">
                    <strong>📦 Delivery Details:</strong>
                    <ul style="margin: 10px 0 0 20px; color: #065f46; font-size: 14px;">
                        <li>Delivery ID: #' . $deliveryId . '</li>
                        <li>Business: ' . htmlspecialchars($businessName) . '</li>
                        <li>Delivered by: ' . htmlspecialchars($agentName) . '</li>
                        <li>Time: ' . date('F d, Y h:i A') . '</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <p style="color: #64748b; font-size: 14px;">
                        We hope you enjoyed our service!
                    </p>
                    <p style="color: #64748b; font-size: 13px;">
                        <i class="fas fa-star" style="color: #f59e0b;"></i>
                        <a href="' . $_SERVER['HTTP_HOST'] . '/customer/rate.php?delivery_id=' . $deliveryId . '" style="color: #e67e22; text-decoration: none;">
                            Rate your delivery experience
                        </a>
                    </p>
                </div>
                
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' UNK Delivery System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $this->sendEmail($to, $subject, $body);
    }
    
    /**
     * Send OTP notification to agent
     */
    public function sendAgentNotification($to, $agentName, $deliveryId, $otp) {
        $subject = '📋 New OTP for Delivery #' . $deliveryId;
        
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #eef2f8; }
                .header h1 { color: #e67e22; margin: 0; font-size: 28px; }
                .otp-box { background: #f8fafc; border-radius: 12px; padding: 20px; text-align: center; margin: 25px 0; }
                .otp-code { font-size: 48px; font-weight: 800; color: #e67e22; letter-spacing: 8px; }
                .footer { text-align: center; padding-top: 20px; border-top: 1px solid #eef2f8; font-size: 12px; color: #94a3b8; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📋 Delivery OTP</h1>
                </div>
                
                <div style="text-align: center;">
                    <p style="font-size: 16px; color: #1e293b;">
                        Hello <strong>' . htmlspecialchars($agentName) . '</strong>!
                    </p>
                    <p style="color: #64748b;">
                        OTP for Delivery #' . $deliveryId . ' has been sent to the customer.
                    </p>
                </div>
                
                <div class="otp-box">
                    <div style="font-size: 14px; color: #64748b; margin-bottom: 10px;">Customer OTP</div>
                    <div class="otp-code">' . $otp . '</div>
                    <p style="font-size: 12px; color: #94a3b8; margin-top: 10px;">
                        ⏰ Expires in 15 minutes
                    </p>
                </div>
                
                <div style="text-align: center; background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <p style="color: #92400e; font-size: 14px; margin: 0;">
                        <strong>📌 Note:</strong> Enter this OTP in the verification page to confirm delivery.
                    </p>
                </div>
                
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' UNK Delivery System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $this->sendEmail($to, $subject, $body);
    }
}
?>