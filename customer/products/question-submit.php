<?php
// customer/products/question-submit.php - SUBMIT QUESTION
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = (int)$_POST['product_id'];
    $question = mysqli_real_escape_string($conn, trim($_POST['question']));
    $user_id = $_SESSION['user_id'];
    
    $customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
    $customer_result = mysqli_query($conn, $customer_sql);
    $customer_data = mysqli_fetch_assoc($customer_result);
    $customer_id = $customer_data['customer_id'];
    
    // Create questions table if not exists
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS product_questions (
        question_id INT PRIMARY KEY AUTO_INCREMENT,
        product_id INT NOT NULL,
        customer_id INT NOT NULL,
        question TEXT NOT NULL,
        answer TEXT,
        answer_date DATETIME,
        status ENUM('pending', 'answered') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $insert_sql = "INSERT INTO product_questions (product_id, customer_id, question) VALUES ('$product_id', '$customer_id', '$question')";
    mysqli_query($conn, $insert_sql);
    
    $_SESSION['flash_message'] = "Your question has been submitted. The seller will answer soon.";
    $_SESSION['flash_type'] = "success";
}

header("Location: details.php?id=$product_id");
exit();
?>