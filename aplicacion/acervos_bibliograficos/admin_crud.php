<?php
// CRUD para acervos bibliográficos (tabla acervos_bibliograficos)
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

function crear_acervo($titulo, $autor, $descripcion, $tipo, $archivo) {
    $sql = "INSERT INTO acervos_bibliograficos (titulo, autor, descripcion, tipo, archivo) VALUES (?, ?, ?, ?, ?)";
    $stmt = db()->prepare($sql);
    $stmt->execute([$titulo, $autor, $descripcion, $tipo, $archivo]);
    return db()->lastInsertId();
}

function obtener_acervos() {
    $sql = "SELECT * FROM acervos_bibliograficos ORDER BY fecha_subida DESC";
    return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_acervo($id) {
    $sql = "SELECT * FROM acervos_bibliograficos WHERE id = ?";
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function actualizar_acervo($id, $titulo, $autor, $descripcion, $tipo, $archivo) {
    $sql = "UPDATE acervos_bibliograficos SET titulo = ?, autor = ?, descripcion = ?, tipo = ?, archivo = ? WHERE id = ?";
    $stmt = db()->prepare($sql);
    return $stmt->execute([$titulo, $autor, $descripcion, $tipo, $archivo, $id]);
}

function eliminar_acervo($id) {
    $sql = "DELETE FROM acervos_bibliograficos WHERE id = ?";
    $stmt = db()->prepare($sql);
    return $stmt->execute([$id]);
}
