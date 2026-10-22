<?php

require __DIR__ . '/conexion.php';


function controlarDictado(array $usuario, int $idDictado): void
{
    $consulta = db()->prepare('SELECT id_docente FROM dictado WHERE id_dictado = ?');
    $consulta->execute([$idDictado]);
    $idDocente = $consulta->fetchColumn();
    if ($idDocente === false) responderError('La materia no existe en ese curso.', 404);
    if ($usuario['rol'] === 'docente' && (int) $idDocente !== (int) $usuario['id_docente']) {
        responderError('Solo podés tomar lista en tus materias.', 403);
    }
}

ejecutar(function () {
    $usuario = requerirSesion(['administrador', 'directivo', 'preceptor', 'docente']);

    if (metodo() === 'GET') {
        $idDictado = (int) ($_GET['id_dictado'] ?? 0);
        $fecha = fechaValida($_GET['fecha'] ?? '') ? $_GET['fecha'] : date('Y-m-d');
        controlarDictado($usuario, $idDictado);

        $consulta = db()->prepare('SELECT id_clase, tema FROM clase WHERE id_dictado = ? AND fecha = ?');
        $consulta->execute([$idDictado, $fecha]);
        $clase = $consulta->fetch() ?: null;

        $consulta = db()->prepare(
            "SELECT a.id_alumno, a.apellido, a.nombre,
                    (SELECT aa.estado FROM asistencia_alumno aa WHERE aa.id_clase = ? AND aa.id_alumno = a.id_alumno) AS estado,
                    (SELECT ROUND(100 * (COUNT(*) - SUM(aa.estado = 'A') - 0.5 * SUM(aa.estado = 'T')) / COUNT(*))
                     FROM asistencia_alumno aa JOIN clase cl ON cl.id_clase = aa.id_clase
                     WHERE cl.id_dictado = d.id_dictado AND aa.id_alumno = a.id_alumno) AS porcentaje
             FROM dictado d
             JOIN inscripcion i ON i.id_curso = d.id_curso AND i.estado = 'regular'
             JOIN alumno a ON a.id_alumno = i.id_alumno
             WHERE d.id_dictado = ?
             ORDER BY a.apellido, a.nombre"
        );
        $consulta->execute([$clase['id_clase'] ?? 0, $idDictado]);
        $alumnos = array_map(function ($a) {
            $a['porcentaje'] = $a['porcentaje'] === null ? null : (int) $a['porcentaje'];
            $a['cerca_limite'] = $a['porcentaje'] !== null && $a['porcentaje'] < LIMITE_ASISTENCIA;
            return $a;
        }, $consulta->fetchAll());

        responder(['clase' => $clase, 'alumnos' => $alumnos, 'limite' => LIMITE_ASISTENCIA]);
    }

    if (metodo() !== 'POST') responderError('Método no permitido.', 405);

    $d = leerJSON();
    $idDictado = (int) ($d['id_dictado'] ?? 0);
    $fecha = $d['fecha'] ?? '';
    $lista = $d['lista'] ?? [];
    controlarDictado($usuario, $idDictado);
    if (!fechaValida($fecha)) responderError('La fecha de la clase no es válida.');
    if ($fecha > date('Y-m-d')) responderError('No se puede tomar lista de una fecha futura.');
    if (!is_array($lista) || !$lista) responderError('La lista está vacía.');
    foreach ($lista as $fila) {
        if (!in_array($fila['estado'] ?? '', ['P', 'A', 'T', 'J'], true)) {
            responderError('Hay alumnos sin marcar.');
        }
    }

    db()->beginTransaction();
    $consulta = db()->prepare(
        'INSERT INTO clase (id_dictado, fecha, tema) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE tema = VALUES(tema), id_clase = LAST_INSERT_ID(id_clase)'
    );
    $consulta->execute([$idDictado, $fecha, texto($d['tema'] ?? '', 200)]);
    $idClase = (int) db()->lastInsertId();

    $consulta = db()->prepare(
        'INSERT INTO asistencia_alumno (id_clase, id_alumno, estado) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE estado = VALUES(estado)'
    );
    foreach ($lista as $fila) {
        $consulta->execute([$idClase, (int) $fila['id_alumno'], $fila['estado']]);
    }
    db()->commit();
    responder(['id_clase' => $idClase]);
});
