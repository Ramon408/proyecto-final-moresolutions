<?php

require __DIR__ . '/conexion.php';

ejecutar(function () {
    if (metodo() !== 'POST') responderError('Método no permitido.', 405);
    requerirSesion(['administrador', 'directivo', 'preceptor']);

    $d = leerJSON();
    $idCurso = (int) ($d['id_curso'] ?? 0);
    $dni = trim($d['dni'] ?? '');
    if (!preg_match('/^\d{7,8}$/', $dni)) {
        responderError('El DNI tiene que tener 7 u 8 números, sin puntos.');
    }

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
        
        $consulta = db()->prepare(
            "SELECT CONCAT(c.anio, '.º ', c.division) FROM inscripcion i JOIN curso c ON c.id_curso = i.id_curso
             WHERE i.id_alumno = ? AND i.estado = 'regular' AND c.ciclo_lectivo = ?"
        );
        $consulta->execute([$idAlumno, $ciclo]);
        $otroCurso = $consulta->fetchColumn();
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
