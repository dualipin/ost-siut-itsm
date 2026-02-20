<?php

use App\Manejadores\SesionProtegida;

require_once __DIR__ . '/../../../src/configuracion.php';

SesionProtegida::proteger();

function buscarDocumento(PDO $conn, int $idDoc): mixed
{
    $sql = "SELECT id, titulo, contenido, fecha_documento, fecha_subida, privado, adjunto FROM documentos_gremiales WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', (int)$_GET['id_doc'], PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}