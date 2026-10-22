<?php

require __DIR__ . '/conexion.php';

ejecutar(function () {
    if (metodo() === 'GET') {
        requerirSesion();
        $desde = fechaValida($_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-d');
        $hasta = fechaValida($_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m-d');
        $consulta = db()->prepare(
            "SELECT a.id_asist_doc, CONCAT(d.apellido, ', ', d.nombre) AS docente, a.fecha,
                    TIME_FORMAT(a.hora_entrada, '%H:%i') AS hora_entrada,
                    TIME_FORMAT(a.hora_salida, '%H:%i') AS hora_salida, a.observacion
             FROM asistencia_docente a JOIN docente d ON d.id_docente = a.id_docente
             WHERE a.fecha BETWEEN ? AND ?
             ORDER BY a.fecha DESC, d.apellido"
        );
        $consulta->execute([$desde, $hasta]);
        responder($consulta->fetchAll());
    }

    if (metodo() !== 'POST') responderError('Método no permitido.', 405);
    requerirSesion(['administrador', 'directivo', 'preceptor']);

    $d = leerJSON();
    $accion = $d['accion'] ?? '';
    $idDocente = (int) ($d['id_docente'] ?? 0);
    $fecha = $d['fecha'] ?? '';
    $hora = trim($d['hora'] ?? '') ?: date('H:i');
    $observacion = texto($d['observacion'] ?? '', 200);

    if (!$idDocente || !fechaValida($fecha)) responderError('Elegí el docente y la fecha.');
    if (!horaValida($hora)) responderError('La hora no es válida.');
    if ($fecha > date('Y-m-d')) responderError('No se puede registrar asistencia de una fecha futura.');

    $consulta = db()->prepare(
        "SELECT id_asist_doc, TIME_FORMAT(hora_entrada, '%H:%i') AS entrada, hora_salida
         FROM asistencia_docente WHERE id_docente = ? AND fecha = ?"
    );
    $consulta->execute([$idDocente, $fecha]);
    $registro = $consulta->fetch();

    if ($accion === 'entrada') {
        if ($registro && $registro['entrada']) {
            responderError('Ese docente ya tiene registrada la entrada de ese día.', 409);
        }
        if ($registro) {
            $consulta = db()->prepare('UPDATE asistencia_docente SET hora_entrada = ?, observacion = ? WHERE id_asist_doc = ?');
            $consulta->execute([$hora, $observacion, $registro['id_asist_doc']]);
        } else {
            $consulta = db()->prepare('INSERT INTO asistencia_docente (id_docente, fecha, hora_entrada, observacion) VALUES (?, ?, ?, ?)');
            $consulta->execute([$idDocente, $fecha, $hora, $observacion]);
        }
        responder(['hora' => $hora], 201);
    }

    if ($accion === 'salida') {
        if (!$registro || !$registro['entrada']) {
            responderError('Ese docente no tiene entrada registrada ese día.');
        }
        if ($hora <= $registro['entrada']) {
            responderError('La hora de salida tiene que ser mayor que la de entrada.');
        }
        $consulta = db()->prepare('UPDATE asistencia_docente SET hora_salida = ?, observacion = COALESCE(?, observacion) WHERE id_asist_doc = ?');
        $consulta->execute([$hora, $observacion, $registro['id_asist_doc']]);
        responder(['hora' => $hora]);
    }

    responderError('Acción no válida.');
});
