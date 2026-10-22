<?php

require __DIR__ . '/conexion.php';

ejecutar(function () {
    requerirSesion();
    $docentes = db()->query(
        "SELECT id_docente, dni, CONCAT(apellido, ', ', nombre) AS nombre
         FROM docente ORDER BY apellido, nombre"
    )->fetchAll();
    responder($docentes);
});
