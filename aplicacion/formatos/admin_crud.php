<?php
// CRUD para formatos (tabla formatos)
require_once __DIR__ . '/../../src/configuracion.php';
require_once __DIR__ . '/../../vendor/autoload.php';

function db() {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

function crear_formato($titulo, $descripcion, $archivo) {
    $sql = "INSERT INTO formatos (titulo, descripcion, archivo) VALUES (?, ?, ?)";
    $stmt = db()->prepare($sql);
    $stmt->execute([$titulo, $descripcion, $archivo]);
    return db()->lastInsertId();
}

function obtener_formatos() {
    $sql = "SELECT * FROM formatos ORDER BY fecha_subida DESC";
    return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_formato($id) {
    $sql = "SELECT * FROM formatos WHERE id = ?";
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function actualizar_formato($id, $titulo, $descripcion, $archivo) {
    $sql = "UPDATE formatos SET titulo = ?, descripcion = ?, archivo = ? WHERE id = ?";
    $stmt = db()->prepare($sql);
    return $stmt->execute([$titulo, $descripcion, $archivo, $id]);
}

function eliminar_formato($id) {
    $sql = "DELETE FROM formatos WHERE id = ?";
    $stmt = db()->prepare($sql);
    return $stmt->execute([$id]);
}
