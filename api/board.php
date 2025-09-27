<?php
/**
 * Board API Endpoint
 * Handles board/news-related API requests
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
            getBoardPosts($db);
            break;

        case 'detail':
            getBoardPost($db);
            break;

        case 'categories':
            getBoardCategories($db);
            break;

        case 'recent':
            getRecentPosts($db);
            break;

        default:
            getBoardPosts($db);
            break;
    }
}

function getBoardPosts($db) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 15);
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';

    $offset = ($page - 1) * $limit;

    $whereClause = "WHERE b.status = 'published'";
    $params = [];

    if ($category) {
        // Handle category by slug or ID
        if (is_numeric($category)) {
            $whereClause .= " AND b.category_id = ?";
            $params[] = $category;
        } else {
            $whereClause .= " AND bc.slug = ?";
            $params[] = $category;
        }
    }

    if ($search) {
        $whereClause .= " AND (b.title LIKE ? OR b.content LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Get posts
    $sql = "
        SELECT
            b.*,
            bc.name as category_name,
            bc.slug as category_slug,
            u.name as author_name
        FROM boards b
        LEFT JOIN board_categories bc ON b.category_id = bc.id
        LEFT JOIN users u ON b.user_id = u.id
        $whereClause
        ORDER BY b.is_notice DESC, b.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    // Process posts
    foreach ($posts as &$post) {
        // Truncate content for list view
        $post['summary'] = $post['summary'] ?: mb_substr(strip_tags($post['content']), 0, 200) . '...';

        // Process attached files
        if ($post['attached_files']) {
            $post['attached_files'] = json_decode($post['attached_files'], true);
        } else {
            $post['attached_files'] = [];
        }

        // Format date
        $post['formatted_date'] = date('Y.m.d', strtotime($post['created_at']));
    }

    // Get total count
    $countSql = "
        SELECT COUNT(*)
        FROM boards b
        LEFT JOIN board_categories bc ON b.category_id = bc.id
        $whereClause
    ";
    $countParams = array_slice($params, 0, -2); // Remove limit and offset
    $stmt = $db->prepare($countSql);
    $stmt->execute($countParams);
    $total = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => $posts,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ],
        'timestamp' => date('c')
    ]);
}

function getBoardPost($db) {
    $id = $_GET['id'] ?? '';

    if (!$id) {
        throw new Exception('Post ID is required', 400);
    }

    $stmt = $db->prepare("
        SELECT
            b.*,
            bc.name as category_name,
            bc.slug as category_slug,
            u.name as author_name,
            u.email as author_email
        FROM boards b
        LEFT JOIN board_categories bc ON b.category_id = bc.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.id = ? AND b.status = 'published'
    ");

    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post) {
        throw new Exception('Post not found', 404);
    }

    // Process attached files
    if ($post['attached_files']) {
        $post['attached_files'] = json_decode($post['attached_files'], true);
    } else {
        $post['attached_files'] = [];
    }

    // Update view count
    $updateStmt = $db->prepare("UPDATE boards SET views = views + 1 WHERE id = ?");
    $updateStmt->execute([$id]);

    // Get comments if enabled
    if ($post['allow_comments']) {
        $post['comments'] = getBoardComments($db, $id);
    } else {
        $post['comments'] = [];
    }

    echo json_encode([
        'success' => true,
        'data' => $post,
        'timestamp' => date('c')
    ]);
}

function getBoardComments($db, $boardId) {
    $stmt = $db->prepare("
        SELECT
            bc.*,
            u.name as author_name
        FROM board_comments bc
        LEFT JOIN users u ON bc.user_id = u.id
        WHERE bc.board_id = ? AND bc.status = 'published'
        ORDER BY bc.created_at ASC
    ");

    $stmt->execute([$boardId]);
    return $stmt->fetchAll();
}

function getBoardCategories($db) {
    $stmt = $db->prepare("
        SELECT
            bc.*,
            COUNT(b.id) as post_count
        FROM board_categories bc
        LEFT JOIN boards b ON bc.id = b.category_id AND b.status = 'published'
        WHERE bc.status = 'active'
        GROUP BY bc.id
        ORDER BY bc.sort_order, bc.name
    ");

    $stmt->execute();
    $categories = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $categories,
        'timestamp' => date('c')
    ]);
}

function getRecentPosts($db) {
    $limit = (int)($_GET['limit'] ?? 5);
    $category = $_GET['category'] ?? '';

    $whereClause = "WHERE b.status = 'published'";
    $params = [];

    if ($category) {
        if (is_numeric($category)) {
            $whereClause .= " AND b.category_id = ?";
            $params[] = $category;
        } else {
            $whereClause .= " AND bc.slug = ?";
            $params[] = $category;
        }
    }

    $sql = "
        SELECT
            b.id,
            b.title,
            b.created_at,
            bc.name as category_name,
            bc.slug as category_slug
        FROM boards b
        LEFT JOIN board_categories bc ON b.category_id = bc.id
        $whereClause
        ORDER BY b.created_at DESC
        LIMIT ?
    ";

    $params[] = $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    // Format posts
    foreach ($posts as &$post) {
        $post['formatted_date'] = date('Y.m.d', strtotime($post['created_at']));
    }

    echo json_encode([
        'success' => true,
        'data' => $posts,
        'timestamp' => date('c')
    ]);
}

function handlePostRequest($db, $action) {
    // Would need authentication for posting
    switch ($action) {
        case 'comment':
            addComment($db);
            break;

        default:
            throw new Exception('Action not allowed', 403);
    }
}

function addComment($db) {
    // This would require authentication
    throw new Exception('Authentication required', 401);
}
?>