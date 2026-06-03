<?php
require_once __DIR__ . '/../includes/session.php';
requireAdmin();
header('Content-Type: application/json');
$db = getDB();
$action = sanitizeInput($_GET['action'] ?? '');
switch ($action) {
    case 'reservation':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT res.*,u.name AS tenant_name,u.email,u.phone,r.room_number,f.floor_number,b.bed_number,r.price AS room_price FROM bh.reservations res JOIN bh.users u ON u.id=res.user_id JOIN bh.rooms r ON r.id=res.room_id JOIN bh.floors f ON f.id=r.floor_id JOIN bh.beds b ON b.id=res.bed_id WHERE res.id=?");
        $stmt->execute([$id]); echo json_encode($stmt->fetch() ?: ['error'=>'Not found']); break;
    case 'receipt':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT p.*,u.name AS tenant_name,r.room_number,f.floor_number,b.bed_number FROM bh.payments p JOIN bh.tenants t ON t.id=p.tenant_id JOIN bh.users u ON u.id=t.user_id JOIN bh.rooms r ON r.id=t.room_id JOIN bh.floors f ON f.id=r.floor_id JOIN bh.beds b ON b.id=t.bed_id WHERE p.id=?");
        $stmt->execute([$id]); echo json_encode($stmt->fetch() ?: ['error'=>'Not found']); break;
    default: echo json_encode(['error'=>'Unknown action']);
}
