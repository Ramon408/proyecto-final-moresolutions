<?php

require __DIR__ . '/conexion.php';

ejecutar(function () {
    switch (metodo()) {
        case 'GET':
            responder(requerirSesion([], true));

        case 'PUT':
            
            $usuario = requerirSesion([], true);
            $rol = leerJSON()['rol'] ?? '';
            if (!in_array($rol, $usuario['roles'], true)) {
                responderError('Ese rol no está asignado a tu usuario.', 403);
            }
            $_SESSION['usuario']['rol'] = $rol;
            responder($_SESSION['usuario']);

        case 'POST':
            $datos = leerJSON();
            $username = trim($datos['usuario'] ?? '');
            $password = (string) ($datos['password'] ?? '');
            if ($username === '' || $password === '') {
                responderError('Completá el usuario y la contraseña.');
            }

            $consulta = db()->prepare(
                'SELECT u.id_usuario, u.username, u.password_hash, u.activo,
                        d.id_docente, d.nombre, d.apellido
                 FROM usuario u
                 LEFT JOIN docente d ON d.id_usuario = u.id_usuario
                 WHERE u.username = ?'
            );
            $consulta->execute([$username]);
            $u = $consulta->fetch();

            if (!$u || !password_verify($password, $u['password_hash'])) {
                responderError('Usuario o contraseña incorrectos.', 401);
            }
            if (!$u['activo']) {
                responderError('El usuario está dado de baja. Consultá con el administrador.', 403);
            }

            
            $consulta = db()->prepare(
                'SELECT r.nombre FROM usuario_rol ur JOIN rol r ON r.id_rol = ur.id_rol
                 WHERE ur.id_usuario = ? ORDER BY r.id_rol'
            );
            $consulta->execute([$u['id_usuario']]);
            $roles = $consulta->fetchAll(PDO::FETCH_COLUMN);
            if (!$roles) {
                responderError('Tu usuario no tiene ningún rol asignado. Consultá con el administrador.', 403);
            }

            session_regenerate_id(true);
            $_SESSION['usuario'] = [
                'id_usuario' => (int) $u['id_usuario'],
                'username'   => $u['username'],
                'roles'      => $roles,
                'rol'        => count($roles) === 1 ? $roles[0] : null, 
                'id_docente' => $u['id_docente'] ? (int) $u['id_docente'] : null,
                'nombre'     => $u['id_docente'] ? $u['nombre'] . ' ' . $u['apellido'] : $u['username'],
            ];
            responder($_SESSION['usuario']);

        case 'DELETE':
            $_SESSION = [];
            session_destroy();
            responder(['ok' => true]);

        default:
            responderError('Método no permitido.', 405);
    }
});
