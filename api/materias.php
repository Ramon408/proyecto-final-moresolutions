<?php

require __DIR__ . '/conexion.php';

function validarMateria(array $d): array
{
    $nombre = texto($d['nombre'] ?? '', 100);
    $anio = (int) ($d['anio'] ?? 0);
    $carga = (int) ($d['carga_horaria'] ?? 0);
    if (!$nombre) responderError('Completá el nombre de la materia.');
    if ($anio < 1 || $anio > 7) responderError('El año tiene que ser de 1 a 7.');
    if ($carga < 1 || $carga > 20) responderError('La carga horaria tiene que ser de 1 a 20 horas.');
    return [$nombre, $anio, $carga];
}

ejecutar(function () {
    $id = (int) ($_GET['id'] ?? 0);

    switch (metodo()) {
        case 'GET':
            requerirSesion();
            $consulta = db()->prepare(
                'SELECT id_materia, nombre, anio, carga_horaria FROM materia
                 WHERE id_plan = ? ORDER BY anio, id_materia'
            );
            $consulta->execute([(int) ($_GET['id_plan'] ?? 0)]);
            responder($consulta->fetchAll());

        case 'POST':
            requerirSesion(['administrador', 'directivo']);
            $d = leerJSON();
            [$nombre, $anio, $carga] = validarMateria($d);
            $idPlan = (int) ($d['id_plan'] ?? 0);

            db()->beginTransaction();
            $consulta = db()->prepare('INSERT INTO materia (id_plan, nombre, anio, carga_horaria) VALUES (?, ?, ?, ?)');
            $consulta->execute([$idPlan, $nombre, $anio, $carga]);
            $idMateria = (int) db()->lastInsertId();

            
            $consulta = db()->prepare(
                'INSERT INTO dictado (id_curso, id_materia)
                 SELECT id_curso, ? FROM curso WHERE id_plan = ? AND anio = ? AND ciclo_lectivo = ?'
            );
            $consulta->execute([$idMateria, $idPlan, $anio, cicloActual()]);
            db()->commit();
            responder(['id_materia' => $idMateria], 201);

        case 'PUT':
            requerirSesion(['administrador', 'directivo']);
            [$nombre, $anio, $carga] = validarMateria(leerJSON());

            $consulta = db()->prepare('SELECT anio FROM materia WHERE id_materia = ?');
            $consulta->execute([$id]);
            $anioAnterior = $consulta->fetchColumn();
            if ($anioAnterior === false) responderError('La materia no existe.', 404);

            if ((int) $anioAnterior !== $anio) {
                $consulta = db()->prepare('SELECT COUNT(*) FROM dictado WHERE id_materia = ?');
                $consulta->execute([$id]);
                if ($consulta->fetchColumn() > 0) {
                    responderError('No se puede cambiar el año: la materia ya está asignada a cursos.');
                }
            }
            $consulta = db()->prepare('UPDATE materia SET nombre = ?, anio = ?, carga_horaria = ? WHERE id_materia = ?');
            $consulta->execute([$nombre, $anio, $carga, $id]);
            responder(['ok' => true]);

        case 'DELETE':
            requerirSesion(['administrador', 'directivo']);
            db()->beginTransaction();
            
            $consulta = db()->prepare(
                'DELETE FROM dictado WHERE id_materia = ?
                   AND id_dictado NOT IN (SELECT id_dictado FROM clase)
                   AND id_dictado NOT IN (SELECT id_dictado FROM calificacion)'
            );
            $consulta->execute([$id]);
            try {
                $consulta = db()->prepare('DELETE FROM materia WHERE id_materia = ?');
                $consulta->execute([$id]);
            } catch (PDOException $e) {
                db()->rollBack();
                responderError('No se puede borrar: la materia ya tiene clases o notas cargadas.', 409);
            }
            db()->commit();
            responder(['ok' => true]);

        default:
            responderError('Método no permitido.', 405);
    }
});
