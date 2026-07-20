<?php
// scripts/generate_hashes.php

require_once '../config/database.php';
require_once '../config/dhash.php';

echo "Starting to generate DHash for existing products...\n\n";

// Get products without hash or with empty hash
$sql = "SELECT product_id, image_url FROM products 
        WHERE is_available = 1 
        AND deleted_at IS NULL 
        AND (image_hash IS NULL OR image_hash = '')
        AND image_url IS NOT NULL 
        AND image_url != ''";
        
$result = mysqli_query($conn, $sql);
$total = mysqli_num_rows($result);
echo "Found $total products without hash\n\n";

$updated = 0;
$failed = 0;
$notFound = 0;

while ($row = mysqli_fetch_assoc($result)) {
    // Try different possible paths
    $pathsToTry = [
        '../../' . $row['image_url'],
        '../' . $row['image_url'],
        $row['image_url'],
        __DIR__ . '/../' . $row['image_url'],
        __DIR__ . '/../../' . $row['image_url']
    ];
    
    $imagePath = null;
    foreach ($pathsToTry as $path) {
        if (file_exists($path)) {
            $imagePath = $path;
            break;
        }
    }
    
    if (!$imagePath) {
        echo "❌ Product {$row['product_id']}: Image not found - {$row['image_url']}\n";
        $notFound++;
        continue;
    }
    
    // Generate hash using dhash() function from the library
    $hash = dhash($imagePath);
    
    if ($hash && strlen($hash) == 16) {
        $stmt = mysqli_prepare($conn, "UPDATE products SET image_hash = ? WHERE product_id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $hash, $row['product_id']);
        mysqli_stmt_execute($stmt);
        $updated++;
        
        if ($updated % 10 == 0) {
            echo "✓ Processed $updated products...\n";
        }
    } else {
        echo "❌ Product {$row['product_id']}: Failed to generate hash\n";
        $failed++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "✅ Updated: $updated\n";
echo "❌ Failed: $failed\n";
echo "📁 Image not found: $notFound\n";
echo "📊 Total processed: " . ($updated + $failed + $notFound) . "\n";
?>