<?php
// config/otp_helper.php - OTP Functions with Email
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mail_config.php';

class DeliveryOTP {
    
    // Generate 6-digit OTP
    public static function generateOTP($length = 6) {
        return str_pad(random_int(0, 999999), $length, '0', STR_PAD_LEFT);
    }
    
    // Generate OTP for delivery
    public static function generateDeliveryOTP($conn, $delivery_id, $customer_id, $agent_id) {
        $otp = self::generateOTP();
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Get customer and agent details
        $sql = "SELECT c.first_name, c.last_name, u.email, c.phone,
                       b.business_name, a.first_name as agent_first, a.last_name as agent_last,
                       a.email as agent_email
                FROM deliveries d
                JOIN orders o ON d.order_id = o.order_id
                JOIN customers c ON o.customer_id = c.customer_id
                JOIN users u ON c.user_id = u.user_id
                JOIN businesses b ON o.business_id = b.business_id
                JOIN delivery_agents a ON d.agent_id = a.agent_id
                WHERE d.delivery_id = '$delivery_id'";
        $result = mysqli_query($conn, $sql);
        $details = mysqli_fetch_assoc($result);
        
        if (!$details) {
            return ['success' => false, 'message' => 'Delivery details not found'];
        }
        
        $customerName = $details['first_name'] . ' ' . $details['last_name'];
        $customerEmail = $details['email'];
        $businessName = $details['business_name'];
        $agentName = $details['agent_first'] . ' ' . $details['agent_last'];
        $agentEmail = $details['agent_email'];
        
        // Insert OTP verification record
        $sql = "INSERT INTO delivery_otp_verifications 
                (delivery_id, customer_id, otp_code, expires_at) 
                VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iiss', $delivery_id, $customer_id, $otp, $expires_at);
        mysqli_stmt_execute($stmt);
        $verification_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Update deliveries table
        $sql = "UPDATE deliveries SET 
                otp_code = ?, 
                otp_generated_at = NOW(), 
                otp_status = 'pending',
                otp_attempts = 0
                WHERE delivery_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $otp, $delivery_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Send emails using PHPMailer
        $mailer = Mailer::getInstance();
        
        $mailer->sendOTPEmail(
            $customerEmail,
            $customerName,
            $otp,
            $delivery_id,
            $businessName
        );
        
        $mailer->sendAgentNotification(
            $agentEmail,
            $agentName,
            $delivery_id,
            $otp
        );
        
        // Create database notifications
        self::createOTPNotification($conn, $delivery_id, $customer_id, $agent_id, $otp);
        
        return [
            'success' => true,
            'otp' => $otp,
            'verification_id' => $verification_id,
            'expires_at' => $expires_at
        ];
    }
    
    // Create notification in database
    public static function createOTPNotification($conn, $delivery_id, $customer_id, $agent_id, $otp) {
        // Customer notification
        $message = "Your delivery is nearby! OTP: " . $otp . " (Expires in 15 minutes)";
        $sql = "INSERT INTO delivery_notifications 
                (delivery_id, customer_id, agent_id, notification_type, title, message, otp_code, is_sent) 
                VALUES (?, ?, ?, 'in_app', 'Delivery OTP Confirmation', ?, ?, 1)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iiss', $delivery_id, $customer_id, $agent_id, $message, $otp);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Agent notification
        $agentMessage = "OTP for Delivery #" . $delivery_id . ": " . $otp;
        $sql = "INSERT INTO delivery_notifications 
                (delivery_id, customer_id, agent_id, notification_type, title, message, otp_code, is_sent) 
                VALUES (?, ?, ?, 'in_app', 'Delivery OTP Code', ?, ?, 1)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iiss', $delivery_id, $customer_id, $agent_id, $agentMessage, $otp);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    // Verify OTP
    public static function verifyOTP($conn, $delivery_id, $otp_code) {
        // Check if OTP exists and is valid
        $sql = "SELECT v.*, d.status, d.agent_id, d.order_id,
                       c.first_name, c.last_name, u.email,
                       b.business_name, a.first_name as agent_first, a.last_name as agent_last
                FROM delivery_otp_verifications v
                JOIN deliveries d ON v.delivery_id = d.delivery_id
                JOIN orders o ON d.order_id = o.order_id
                JOIN customers c ON o.customer_id = c.customer_id
                JOIN users u ON c.user_id = u.user_id
                JOIN businesses b ON o.business_id = b.business_id
                JOIN delivery_agents a ON d.agent_id = a.agent_id
                WHERE v.delivery_id = ? 
                AND v.otp_code = ? 
                AND v.is_verified = 0
                AND v.expires_at > NOW()
                ORDER BY v.created_at DESC
                LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'is', $delivery_id, $otp_code);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $verification = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$verification) {
            return ['success' => false, 'message' => 'Invalid or expired OTP'];
        }
        
        // Update attempts
        $attempts = $verification['attempts'] + 1;
        $sql = "UPDATE delivery_otp_verifications SET attempts = ? WHERE verification_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $attempts, $verification['verification_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($attempts > 5) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please request a new OTP.'];
        }
        
        // Mark as verified
        $sql = "UPDATE delivery_otp_verifications 
                SET is_verified = 1, verified_at = NOW() 
                WHERE verification_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $verification['verification_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Update deliveries
        $sql = "UPDATE deliveries SET 
                otp_status = 'verified', 
                otp_verified_at = NOW(),
                otp_confirmed = 1,
                otp_confirmed_at = NOW(),
                status = 'delivered',
                delivered_at = NOW()
                WHERE delivery_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $delivery_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Update order
        $sql = "UPDATE orders SET status = 'delivered' 
                WHERE order_id = (SELECT order_id FROM deliveries WHERE delivery_id = ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $delivery_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Send confirmation email
        $mailer = Mailer::getInstance();
        $mailer->sendDeliveryConfirmedEmail(
            $verification['email'],
            $verification['first_name'] . ' ' . $verification['last_name'],
            $delivery_id,
            $verification['business_name'],
            $verification['agent_first'] . ' ' . $verification['agent_last']
        );
        
        return ['success' => true, 'message' => 'OTP verified successfully. Delivery confirmed!'];
    }
    
    // Resend OTP
    public static function resendOTP($conn, $delivery_id) {
        $sql = "SELECT customer_id, agent_id FROM deliveries WHERE delivery_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $delivery_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $delivery = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($delivery) {
            return self::generateDeliveryOTP($conn, $delivery_id, $delivery['customer_id'], $delivery['agent_id']);
        }
        return ['success' => false, 'message' => 'Delivery not found'];
    }
    
    // Get OTP status
    public static function getOTPStatus($conn, $delivery_id) {
        $sql = "SELECT otp_code, otp_generated_at, otp_verified_at, otp_status, 
                       otp_confirmed, otp_confirmed_at
                FROM deliveries 
                WHERE delivery_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $delivery_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $otpData = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $otpData;
    }
}
?>