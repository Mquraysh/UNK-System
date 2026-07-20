<?php
// customer/products/search_by_serapi.php
require_once '../../config/database.php';

header('Content-Type: application/json');


// API KEYS
$SERAPI_KEY = '198f55084dded1184ed503a86d27ff6f6b0149c8871d8ef2ed14ff0f91490002';
$IMGBB_KEY = '3ea8e6eb3f83d73d7c5962357ea3f8c5';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create logs directory
$logDir = '../../logs/';
if (!file_exists($logDir)) mkdir($logDir, 0777, true);
$logFile = $logDir . 'serapi_search.log';

function logMessage($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

logMessage("=== New search request ===");

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'No valid image uploaded';
    if (isset($_FILES['product_image']['error'])) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File too large',
            UPLOAD_ERR_FORM_SIZE => 'File too large',
            UPLOAD_ERR_PARTIAL => 'Partial upload',
            UPLOAD_ERR_NO_FILE => 'No file',
            UPLOAD_ERR_NO_TMP_DIR => 'No temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write file'
        ];
        $errorMsg = $errors[$_FILES['product_image']['error']] ?? 'Unknown error';
    }
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

// Create temp directory
$tempDir = '../../temp/';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// Save uploaded image
$tempFile = $tempDir . uniqid() . '_' . time() . '_' . basename($_FILES['product_image']['name']);
move_uploaded_file($_FILES['product_image']['tmp_name'], $tempFile);

logMessage("Temp file saved: " . $tempFile);


// Upload to ImgBB
function uploadToImgBB($imagePath, $apiKey) {
    $imageData = base64_encode(file_get_contents($imagePath));
    $url = "https://api.imgbb.com/1/upload?key={$apiKey}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $imageData]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => "HTTP $httpCode: " . substr($response, 0, 200)];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['data']['url'])) {
        return ['success' => true, 'url' => $result['data']['url']];
    } else {
        return ['error' => 'Invalid response from ImgBB', 'response' => $response];
    }
}

logMessage("Uploading to ImgBB...");
$uploadResult = uploadToImgBB($tempFile, $IMGBB_KEY);
unlink($tempFile);

if (isset($uploadResult['error'])) {
    logMessage("ImgBB upload failed: " . $uploadResult['error']);
    echo json_encode(['success' => false, 'message' => 'Failed to upload image: ' . $uploadResult['error']]);
    exit;
}

$imageUrl = $uploadResult['url'];
logMessage("Image uploaded to: " . $imageUrl);


// Search with Google Lens via SerpApi
function searchGoogleLens($imageUrl, $apiKey) {
    $params = [
        'engine' => 'google_lens',
        'api_key' => $apiKey,
        'url' => $imageUrl
    ];
    
    $url = 'https://serpapi.com/search?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => "HTTP $httpCode"];
    }
    
    return json_decode($response, true);
}

logMessage("Calling SerpApi Google Lens...");
$lensResults = searchGoogleLens($imageUrl, $SERAPI_KEY);

if (isset($lensResults['error'])) {
    logMessage("SerpApi error: " . ($lensResults['error'] ?? 'Unknown'));
    echo json_encode(['success' => false, 'message' => 'Google Lens error: ' . ($lensResults['error'] ?? 'Unknown')]);
    exit;
}

logMessage("SerpApi response received");


// Search in your database
$detectedProducts = [];
$searchTerms = [];

// A. Extract from visual matches
if (isset($lensResults['visual_matches']) && is_array($lensResults['visual_matches'])) {
    logMessage("Found " . count($lensResults['visual_matches']) . " visual matches");
    
    foreach ($lensResults['visual_matches'] as $match) {
        $title = $match['title'] ?? '';
        if (!empty($title) && strlen($title) > 3) {
            $searchTerms[] = $title;
        }
    }
}

// B. Extract from knowledge graph
if (isset($lensResults['knowledge_graph'])) {
    $kg = $lensResults['knowledge_graph'];
    if (!empty($kg['title'])) $searchTerms[] = $kg['title'];
    if (!empty($kg['brand'])) $searchTerms[] = $kg['brand'];
    if (!empty($kg['category'])) $searchTerms[] = $kg['category'];
    logMessage("Knowledge graph: " . json_encode($kg));
}

// C. Extract from reverse image search
if (isset($lensResults['reverse_image_search']) && is_array($lensResults['reverse_image_search'])) {
    foreach ($lensResults['reverse_image_search'] as $result) {
        $title = $result['title'] ?? '';
        if (!empty($title) && strlen($title) > 3) {
            $searchTerms[] = $title;
        }
    }
}

// Remove duplicates and empty values
$searchTerms = array_unique(array_filter($searchTerms));
logMessage("Search terms: " . implode(', ', $searchTerms));

// Search database using extracted terms
if (!empty($searchTerms)) {
    $allProductIds = [];
    
    foreach ($searchTerms as $term) {
        $escapedTerm = mysqli_real_escape_string($conn, $term);
        
        // Split into words for better matching
        $words = explode(' ', $escapedTerm);
        $wordConditions = [];
        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $wordConditions[] = "p.name LIKE '%$word%'";
            }
        }
        
        $whereClause = !empty($wordConditions) ? implode(' OR ', $wordConditions) : "p.name LIKE '%$escapedTerm%'";
        
        $sql = "SELECT DISTINCT p.product_id, p.name, p.price, p.image_url, p.description,
                       b.business_name, c.name as category_name
                FROM products p
                JOIN businesses b ON p.business_id = b.business_id
                JOIN categories c ON p.category_id = c.category_id
                WHERE p.is_available = 1 
                AND p.deleted_at IS NULL
                AND ($whereClause)
                LIMIT 30";
        
        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if (!in_array($row['product_id'], $allProductIds)) {
                    $allProductIds[] = $row['product_id'];
                    
                    // Calculate relevance score based on term match
                    $score = 0;
                    if (stripos($row['name'], $term) !== false) $score += 10;
                    if (stripos($row['description'] ?? '', $term) !== false) $score += 5;
                    if (stripos($row['category_name'], $term) !== false) $score += 3;
                    
                    $row['relevance_score'] = $score;
                    $row['match_term'] = $term;
                    $detectedProducts[$row['product_id']] = $row;
                }
            }
        }
    }
    
    // Sort by relevance score
    usort($detectedProducts, function($a, $b) {
        return $b['relevance_score'] - $a['relevance_score'];
    });
}

logMessage("Found " . count($detectedProducts) . " products in database");


// FIXED: Function to get correct image path
function getCorrectImagePath($imageUrl) {
    // Default image path
    $defaultImage = '../../assets/images/default-product.jpg';
    
    if (empty($imageUrl)) {
        return $defaultImage;
    }
    
    // Clean the path
    $cleanPath = trim($imageUrl);
    $cleanPath = ltrim($cleanPath, './');
    $cleanPath = preg_replace('#^(\.\./)+#', '', $cleanPath);
    
    // List of possible paths to check
    $pathsToCheck = [
        '../../' . $cleanPath,
        '../' . $cleanPath,
        $cleanPath,
        '../../uploads/products/' . basename($cleanPath),
        '../../assets/images/products/' . basename($cleanPath),
        '../../uploads/' . basename($cleanPath),
        '../../images/' . basename($cleanPath)
    ];
    
    // Check if any path exists
    foreach ($pathsToCheck as $path) {
        if (file_exists($path)) {
            logMessage("Image found at: " . $path);
            // Return the path that works (with ../../ prefix for web)
            if (strpos($path, '../../') === 0) {
                return $path;
            } elseif (strpos($path, '../') === 0) {
                return $path;
            } else {
                return '../../' . $cleanPath;
            }
        }
    }
    
    // Log if image not found
    logMessage("Image NOT found for: " . $imageUrl . " (tried: " . implode(', ', $pathsToCheck) . ")");
    
    return $defaultImage;
}

// Prepare response with fixed image URLs
$products = [];
foreach ($detectedProducts as $product) {
    $product['image_url'] = getCorrectImagePath($product['image_url']);
    $products[] = $product;
}

$response = [
    'success' => true,
    'products' => array_slice($products, 0, 15),
    'count' => count($products),
    'search_terms_used' => $searchTerms,
    'lens_info' => [
        'visual_matches' => count($lensResults['visual_matches'] ?? []),
        'has_knowledge_graph' => isset($lensResults['knowledge_graph']),
        'knowledge_graph_title' => $lensResults['knowledge_graph']['title'] ?? null
    ]
];

logMessage("Returning " . count($response['products']) . " products");

echo json_encode($response);
?>