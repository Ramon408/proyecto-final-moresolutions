<?php

require __DIR__ . '/conexion.php';

function otroCursoRegular(int $idAlumno, int $ciclo, int $sinInscripcion = 0)
{
    $consulta = db()->prepare(
        "SELECT CONCAT(c.anio, '.º ', c.division) FROM inscripcion i JOIN curso c ON c.id_curso = i.id_curso
         WHERE i.id_alumno = ? AND i.estado = 'regular' AND c.ciclo_lectivo = ? AND i.id_inscripcion <> ?"
    );
    $consulta->execute([$idAlumno, $ciclo, $sinInscripcion]);
    return $consulta->fetchColumn();
}

ejecutar(function () {
    requerirSesion(['administrador', 'directivo', 'preceptor']);

    if (metodo() === 'PUT') {
        $id = (int) ($_GET['id'] ?? 0);
        $d = leerJSON();
        $estado = $d['estado'] ?? '';
        $fecha = $d['fecha'] ?? '';
        $motivo = texto($d['motivo'] ?? '', 150);

        $consulta = db()->prepare(
            'SELECT i.id_alumno, i.estado, i.fecha, c.ciclo_lectivo FROM inscripcion i
             JOIN curso c ON c.id_curso = i.id_curso WHERE i.id_inscripcion = ?'
        );
        $consulta->execute([$id]);
        $ins = $consulta->fetch();
        if (!$ins) responderError('La inscripción no existe.', 404);

        if (!in_array($estado, ['regular', 'pase', 'baja'], true)) responderError('El estado tiene que ser regular, pase o baja.');
        if ($estado === $ins['estado']) responderError("El alumno ya está como $estado.");
        if (!fechaValida($fecha)) responderError('Completá la fecha del cambio.');
        if ($fecha > date('Y-m-d')) responderError('La fecha del cambio no puede ser futura.');
        if ($fecha < $ins['fecha']) responderError('La fecha del cambio no puede ser anterior a la inscripción.');
        if ($estado !== 'regular' && !$motivo) responderError('Escribí el motivo del pase o de la baja.');

        
        if ($estado === 'regular') {
            $otroCurso = otroCursoRegular((int) $ins['id_alumno'], (int) $ins['ciclo_lectivo'], $id);
            if ($otroCurso !== false) responderError("No se puede: el alumno está como regular en $otroCurso.", 409);
        }

        $consulta = db()->prepare('UPDATE inscripcion SET estado = ?, fecha_estado = ?, motivo_estado = ? WHERE id_inscripcion = ?');
        $consulta->execute([$estado, $fecha, $motivo, $id]);
        responder(['ok' => true]);
    }

    if (metodo() !== 'POST') responderError('Método no permitido.', 405);

    $d = leerJSON();
    $idCurso = (int) ($d['id_curso'] ?? 0);
    $dni = trim($d['dni'] ?? '');
    if (!preg_match('/^\d{7,8}$/', $dni)) {
        responderError('El DNI tiene que tener 7 u 8 números, sin puntos.');
    }
    $dni = (int) $dni; 

    $consulta = db()->prepare('SELECT ciclo_lectivo FROM curso WHERE id_curso = ?');
    $consulta->execute([$idCurso]);
    $ciclo = $consulta->fetchColumn();
    if ($ciclo === false) responderError('El curso no existe.', 404);

    db()->beginTransaction();

    $consulta = db()->prepare('SELECT id_alumno FROM alumno WHERE dni = ?');
    $consulta->execute([$dni]);
    $idAlumno = $consulta->fetchColumn();

    if ($idAlumno === false) {
        $partes = array_map('trim', explode(',', $d['alumno'] ?? '', 2));
        $apellido = texto($partes[0] ?? '', 50);
        $nombre = texto($partes[1] ?? '', 50);
        $fechaNac = $d['fecha_nac'] ?? '';
        if (!$apellido || !$nombre) {
            db()->rollBack();
            responderError('Escribí el alumno como "Apellido, Nombre".');
        }
        if (!fechaValida($fechaNac)) {
            db()->rollBack();
            responderError('Completá la fecha de nacimiento del alumno nuevo.');
        }
        $consulta = db()->prepare('INSERT INTO alumno (dni, nombre, apellido, fecha_nac) VALUES (?, ?, ?, ?)');
        $consulta->execute([$dni, $nombre, $apellido, $fechaNac]);
        $idAlumno = (int) db()->lastInsertId();
    } else {
        $otroCurso = otroCursoRegular((int) $idAlumno, (int) $ciclo);
        if ($otroCurso !== false) {
            db()->rollBack();
            responderError("Ese alumno ya está inscripto como regular en $otroCurso.", 409);
        }
    }

    $consulta = db()->prepare("INSERT INTO inscripcion (id_alumno, id_curso, fecha, estado) VALUES (?, ?, CURDATE(), 'regular')");
    $consulta->execute([$idAlumno, $idCurso]);
    db()->commit();
    responder(['id_alumno' => (int) $idAlumno], 201);
});

