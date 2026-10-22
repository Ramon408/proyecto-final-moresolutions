<?php

require __DIR__ . '/conexion.php';

ejecutar(function () {
    $usuario = requerirSesion();

    $presentes = db()->query(
        'SELECT COUNT(*) FROM asistencia_docente WHERE fecha = CURDATE() AND hora_entrada IS NOT NULL'
    )->fetchColumn();
    $totalDocentes = db()->query('SELECT COUNT(*) FROM docente')->fetchColumn();

    
    $diaSemana = (int) date('N'); 
    $consulta = db()->prepare(
        'SELECT COUNT(DISTINCT h.id_dictado)
         FROM horario h
         JOIN dictado d ON d.id_dictado = h.id_dictado
         JOIN curso c ON c.id_curso = d.id_curso
         WHERE h.dia_semana = ? AND c.ciclo_lectivo = ?
           AND NOT EXISTS (SELECT 1 FROM clase cl WHERE cl.id_dictado = h.id_dictado AND cl.fecha = CURDATE())'
    );
    $consulta->execute([$diaSemana, cicloActual()]);

    responder([
        'usuario'          => $usuario,
        'ciclo'            => cicloActual(),
        'presentes_hoy'    => (int) $presentes,
        'total_docentes'   => (int) $totalDocentes,
        'clases_sin_lista' => $diaSemana <= 5 ? (int) $consulta->fetchColumn() : 0,
        'instancia'        => instanciaActual(),
    ]);
});
