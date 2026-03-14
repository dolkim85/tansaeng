<?php
/**
 * Products API Endpoint
 * Handles product-related API requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = DatabaseConfig::getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            handleGetRequest($db, $action);
            break;

        case 'POST':
            handlePostRequest($db, $action);
            break;

        case 'PUT':
            handlePutRequest($db, $action);
            break;

        case 'DELETE':
            handleDeleteRequest($db, $action);
            break;

        default:
            throw new Exception('Method not allowed', 405);
    }

} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 500;
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}

function handleGetRequest($db, $action) {
    switch ($action) {
        case 'list':
            getProducts($db);
            break;

        case 'detail':
            getProduct($db);
            break;

        case 'categories':
            getCategories($db);
            break;

        case 'featured':
            getFeaturedProducts($db);
            break;

        case 'search':
            searchProducts($db);
            break;

        default:
            getProducts($db);
            break;
    }
}

function getProducts($db) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 12);
    $category = $_GET['category'] ?? '';
    $sort = $_GET['sort'] ?? 'created_at';
    $order = $_GET['order'] ?? 'DESC';
    $featured = $_GET['featured'] ?? '';

    $offset = ($page - 1) * $limit;

    $whereClause = "WHERE status = 'active'";
    $params = [];

    if ($category) {
        $whereClause .= " AND category_id = ?";
        $params[] = $category;
    }

    if ($featured === '1') {
        $whereClause .= " AND featured = 1";
    }

    // Valid sort columns
    $validSorts = ['name', 'price', 'created_at', 'views', 'stock_quantity'];
    if (!in_array($sort, $validSorts)) {
        $sort = 'created_at';
    }

    $validOrders = ['ASC', 'DESC'];
    if (!in_array($order, $validOrders)) {
        $order = 'DESC';
    }

    // Get products
    $sql = "
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $whereClause
        ORDER BY p.$sort $order
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Process images
    foreach ($products as &$product) {
        if ($product['images']) {
            $product['images'] = json_decode($product['images'], true);
            $product['image'] = $product['images'][0] ?? null;
        } else {
            $product['images'] = [];
            $product['image'] = null;
        }
    }

    // Get total count
    $countSql = "SELECT COUNT(*) FROM products p $whereClause";
    $countParams = array_slice($params, 0, -2); // Remove limit and offset
    $stmt = $db->prepare($countSql);
    $stmt->execute($countParams);
    $total = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => $products,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ],
        'timestamp' => date('c')
    ]);
}

function getProduct($db) {
    $id = $_GET['id'] ?? '';

    if (!$id) {
        throw new Exception('Product ID is required', 400);
    }

    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.status = 'active'
    ");

    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception('Product not found', 404);
    }

    // Process images
    if ($product['images']) {
        $product['images'] = json_decode($product['images'], true);
    } else {
        $product['images'] = [];
    }

    // Update view count
    $updateStmt = $db->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
    $updateStmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'data' => $product,
        'timestamp' => date('c')
    ]);
}

function getCategories($db) {
    $stmt = $db->prepare("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY c.sort_order, c.name
    ");

    $stmt->execute();
    $categories = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $categories,
        'timestamp' => date('c')
    ]);
}

function getFeaturedProducts($db) {
    $limit = (int)($_GET['limit'] ?? 6);

    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active' AND p.featured = 1
        ORDER BY p.created_at DESC
        LIMIT ?
    ");

    $stmt->execute([$limit]);
    $products = $stmt->fetchAll();

    // Process images
    foreach ($products as &$product) {
        if ($product['images']) {
            $product['images'] = json_decode($product['images'], true);
            $product['image'] = $product['images'][0] ?? null;
        } else {
            $product['images'] = [];
            $product['image'] = null;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $products,
        'timestamp' => date('c')
    ]);
}

function searchProducts($db) {
    $query = $_GET['q'] ?? '';
    $limit = (int)($_GET['limit'] ?? 20);
    $page = (int)($_GET['page'] ?? 1);
    $offset = ($page - 1) * $limit;

    if (empty($query)) {
        throw new Exception('Search query is required', 400);
    }

    $searchTerm = '%' . $query . '%';

    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
        AND (p.name LIKE ? OR p.description LIKE ? OR p.specifications LIKE ?)
        ORDER BY p.name
        LIMIT ? OFFSET ?
    ");

    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
    $products = $stmt->fetchAll();

    // Process images
    foreach ($products as &$product) {
        if ($product['images']) {
            $product['images'] = json_decode($product['images'], true);
            $product['image'] = $product['images'][0] ?? null;
        } else {
            $product['images'] = [];
            $product['image'] = null;
        }
    }

    // Get total count
    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM products p
        WHERE p.status = 'active'
        AND (p.name LIKE ? OR p.description LIKE ? OR p.specifications LIKE ?)
    ");
    $countStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $total = $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => $products,
        'query' => $query,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ],
        'timestamp' => date('c')
    ]);
}

function handlePostRequest($db, $action) {
    // For admin use only - would need authentication
    throw new Exception('Not implemented', 501);
}

function handlePutRequest($db, $action) {
    // For admin use only - would need authentication
    throw new Exception('Not implemented', 501);
}

function handleDeleteRequest($db, $action) {
    // For admin use only - would need authentication
    throw new Exception('Not implemented', 501);
}
?>