<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'toggle_status':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = intval($data['id'] ?? 0);

            if (!$id) {
                throw new Exception('ID не указан');
            }

            $product = $db->find('products', $id);
            if (!$product) {
                throw new Exception('Товар не найден');
            }

            $product['status'] = $product['status'] === 'active' ? 'inactive' : 'active';
            $product['updated_at'] = date('Y-m-d H:i:s');

            $db->save('products', $product, $id);

            echo json_encode(['success' => true, 'status' => $product['status']]);
            break;

        case 'delete':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = intval($data['id'] ?? 0);

            if (!$id) {
                throw new Exception('ID не указан');
            }

            if (!$db->delete('products', $id)) {
                throw new Exception('Ошибка удаления');
            }

            echo json_encode(['success' => true]);
            break;

        case 'duplicate':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = intval($data['id'] ?? 0);

            if (!$id) {
                throw new Exception('ID не указан');
            }

            $product = $db->find('products', $id);
            if (!$product) {
                throw new Exception('Товар не найден');
            }

            unset($product['id']);
            $product['name'] .= ' (копия)';
            $product['sku'] = ($product['sku'] ?? '') . '-copy';
            $product['external_id'] = null;
            $product['status'] = 'inactive';
            $product['created_at'] = date('Y-m-d H:i:s');
            $product['updated_at'] = date('Y-m-d H:i:s');

            $newId = $db->save('products', $product);

            echo json_encode(['success' => true, 'new_id' => $newId]);
            break;

        case 'mass_activate':
            $data = json_decode(file_get_contents('php://input'), true);
            $ids = $data['ids'] ?? [];

            if (empty($ids)) {
                throw new Exception('Не выбраны товары');
            }

            $processed = 0;
            foreach ($ids as $id) {
                $product = $db->find('products', $id);
                if ($product) {
                    $product['status'] = 'active';
                    $product['updated_at'] = date('Y-m-d H:i:s');
                    $db->save('products', $product, $id);
                    $processed++;
                }
            }

            echo json_encode(['success' => true, 'processed' => $processed]);
            break;

        case 'mass_deactivate':
            $data = json_decode(file_get_contents('php://input'), true);
            $ids = $data['ids'] ?? [];

            if (empty($ids)) {
                throw new Exception('Не выбраны товары');
            }

            $processed = 0;
            foreach ($ids as $id) {
                $product = $db->find('products', $id);
                if ($product) {
                    $product['status'] = 'inactive';
                    $product['updated_at'] = date('Y-m-d H:i:s');
                    $db->save('products', $product, $id);
                    $processed++;
                }
            }

            echo json_encode(['success' => true, 'processed' => $processed]);
            break;

        case 'mass_delete':
            $data = json_decode(file_get_contents('php://input'), true);
            $ids = $data['ids'] ?? [];

            if (empty($ids)) {
                throw new Exception('Не выбраны товары');
            }

            $processed = 0;
            foreach ($ids as $id) {
                if ($db->delete('products', $id)) {
                    $processed++;
                }
            }

            echo json_encode(['success' => true, 'processed' => $processed]);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}