<?php

require __DIR__ . '/conexion.php';

const INSTANCIAS = ['1.º trimestre', '2.º trimestre', '3.º trimestre', 'diciembre', 'febrero', 'final'];

ejecutar(function () {
    $usuario = requerirSesion(['administrador', 'directivo', 'docente']);
    $idDictado = (int) (metodo() === 'GET' ? ($_GET['id_dictado'] ?? 0) : (leerJSON()['id_dictado'] ?? 0));

    $consulta = db()->prepare(
        "SELECT d.id_dictado, d.id_docente, m.nombre AS materia, CONCAT(c.anio, '.º ', c.division) AS curso,
                CONCAT(doc.apellido, ', ', doc.nombre) AS docente
         FROM dictado d
         JOIN materia m ON m.id_materia = d.id_materia
         JOIN curso c ON c.id_curso = d.id_curso
         LEFT JOIN docente doc ON doc.id_docente = d.id_docente
         WHERE d.id_dictado = ?"
    );
    $consulta->execute([$idDictado]);
    $dictado = $consulta->fetch();
    if (!$dictado) responderError('La materia no existe en ese curso.', 404);
    if ($usuario['rol'] === 'docente' && (int) $dictado['id_docente'] !== (int) $usuario['id_docente']) {
        responderError('Solo podés cargar notas de tus materias.', 403);
    }

    if (metodo() === 'GET') {
        $consulta = db()->prepare(
            "SELECT a.id_alumno, a.apellido, a.nombre, cal.instancia, cal.nota
             FROM dictado d
             JOIN inscripcion i ON i.id_curso = d.id_curso AND i.estado = 'regular'
             JOIN alumno a ON a.id_alumno = i.id_alumno
             LEFT JOIN calificacion cal ON cal.id_alumno = a.id_alumno AND cal.id_dictado = d.id_dictado
             WHERE d.id_dictado = ?
             ORDER BY a.apellido, a.nombre"
        );
        $consulta->execute([$idDictado]);
        $alumnos = [];
        foreach ($consulta->fetchAll() as $fila) {
            $id = $fila['id_alumno'];
            $alumnos[$id] ??= ['id_alumno' => (int) $id, 'apellido' => $fila['apellido'], 'nombre' => $fila['nombre'], 'notas' => (object) []];
            if ($fila['instancia'] !== null) {
                $alumnos[$id]['notas']->{$fila['instancia']} = (float) $fila['nota'];
            }
        }
        unset($dictado['id_docente']);
        responder(['dictado' => $dictado, 'alumnos' => array_values($alumnos), 'nota_aprobacion' => NOTA_APROBACION]);
    }

    if (metodo() !== 'POST') responderError('Método no permitido.', 405);

    $notas = leerJSON()['notas'] ?? [];
    if (!is_array($notas)) responderError('No llegaron las notas.');

    db()->beginTransaction();
    $guardar = db()->prepare(
        'INSERT INTO calificacion (id_alumno, id_dictado, instancia, nota, fecha) VALUES (?, ?, ?, ?, CURDATE())
         ON DUPLICATE KEY UPDATE fecha = IF(nota <> VALUES(nota), CURDATE(), fecha), nota = VALUES(nota)'
    );
    $borrar = db()->prepare('DELETE FROM calificacion WHERE id_alumno = ? AND id_dictado = ? AND instancia = ?');
    foreach ($notas as $n) {
        $instancia = $n['instancia'] ?? '';
        if (!in_array($instancia, INSTANCIAS, true)) {
            db()->rollBack();
            responderError('Instancia de evaluación no válida.');
        }
        $nota = $n['nota'] ?? null;
        if ($nota === null || $nota === '') {
            $borrar->execute([(int) $n['id_alumno'], $idDictado, $instancia]);
            continue;
        }
        if (!is_numeric($nota) || $nota < 1 || $nota > 10) {
            db()->rollBack();
            responderError('Las notas van de 1 a 10.');
        }
        $guardar->execute([(int) $n['id_alumno'], $idDictado, $instancia, $nota]);
    }
    db()->commit();
    responder(['ok' => true]);
});
