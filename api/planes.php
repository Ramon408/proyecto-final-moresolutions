<?php

require __DIR__ . '/conexion.php';

ejecutar(function () {
    if (metodo() === 'GET') {
        requerirSesion();
        $planes = db()->query(
            'SELECT id_plan, nombre, resolucion, anio_vigencia, activo
             FROM plan_estudio ORDER BY activo DESC, anio_vigencia DESC'
        )->fetchAll();
        responder($planes);
    }

    if (metodo() === 'POST') {
        requerirSesion(['administrador', 'directivo']);
        $d = leerJSON();
        $nombre = texto($d['nombre'] ?? '', 100);
        $anio = (int) ($d['anio_vigencia'] ?? 0);
        if (!$nombre || $anio < 2000 || $anio > 2100) {
            responderError('Completá el nombre y un año de vigencia válido.');
        }
        $consulta = db()->prepare('INSERT INTO plan_estudio (nombre, resolucion, anio_vigencia, activo) VALUES (?, ?, ?, TRUE)');
        $consulta->execute([$nombre, texto($d['resolucion'] ?? '', 30), $anio]);
        responder(['id_plan' => (int) db()->lastInsertId()], 201);
    }

    responderError('Método no permitido.', 405);
});
