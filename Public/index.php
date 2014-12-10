<?php

include "../vendor/autoload.php";
//include "/Mail/contactform.php";
include "Mail/email.php";
require_once 'config.php';

session_start();

$app = new \Slim\Slim(
        array(
    'view' => new \Slim\Views\Twig(),
    'templates.path' => '../Templates'
        )
);

$view = $app->view();
$view->parserOptions = array(
    'debug' => true,
        //'cache' => '../cache'
);

$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
);

//-- PORTADA + LOGIN --//
$app->get('/', function() use($app) {
    $app->render('Login.html.twig');
})->name('login');

//-- PULSAMOS EL BOTÓN 'CONECTAR' o RECIBIMOS LA COMPROBACIÓN DE NOMBRE DE USUARIO --//
$app->post('/', function() use($app) {
    if (isset($_POST['Conectar'])) {

        $usuario = $app->request->post('usuario');
        $password = $app->request->post('password');

        //echo $usuarioRegistrado['password']. "</br>";
        //echo crypt($password, $usuarioRegistrado['password']);

        $usuarioRegistrado = ORM::for_table('Usuario')->where('nombre_usuario', $usuario)->find_one();
        if ($usuarioRegistrado && (crypt($password, $usuarioRegistrado['password']) === $usuarioRegistrado['password'])) {
            $_SESSION['usuario'] = $usuarioRegistrado['id'];
            $app->redirect($app->urlFor('principal'));
        } else {
            $app->flash('error', 'Usuario y/o contraseña incorrectos');
            $app->redirect($app->urlFor('login'));
        }
    }

    if (isset($_POST['buscar_usuario']) != '') {
        $dato = "Si";
        $usuarioRegistrado = ORM::for_table('Usuario')->where('nombre_usuario', $_POST['buscar_usuario'])
                ->find_one();
        if ($usuarioRegistrado) {
            $dato = "No";
        } elseif (($_POST['buscar_usuario'] == ' ') || ($_POST['buscar_usuario'] == '')) {
            $dato = "";
        }
        echo $dato;
    }

    if (isset($_POST['regRegistrar'])) {
        try {
            $nuevoUsuario = ORM::for_table('Usuario')->create();
            $nuevoUsuario->nombre_usuario = $_POST['regUsuario'];
            $nuevoUsuario->password = crypt($_POST['regPassword']);
            $nuevoUsuario->email = $_POST['regEmail'];
            $nuevoUsuario->save();

            $app->flash('mensaje', 'Registrado correctamente. Ha recibido un email con los datos de usuario');

            //Enviar correo electrónico de bienvenida a la plataforma
            mandarCorreo($_POST['regEmail'], $_POST['regUsuario']);
        } catch (Exception $e) {
            $app->flash('error', 'Fallo en la inserción del usuario');
        }
        $app->redirect($app->urlFor('login'));
    }
});

//-- ADMINISTRACIÓN --//
$app->get('/Administrar/', function() use($app) {

    //Desde aquí cargamos todas las pestañas de la admnistración, y luego las operaciones
    //las hacemos aparte

    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $numeroNotificaciones = ORM::for_table('Notificacion')->
            where('usuario_id_fk', $usuarioRegistrado['id'])->
            where('leido', 0)->
            count();

    $todosUsuarios = ORM::for_table('Usuario')->find_many();

    $todosAcontecimientos = ORM::for_table('Acontecimiento')->
            select('titulo')->
            select('Acontecimiento.id')->
            select('Acontecimiento.descripcion')->
            select('nombre_imagen')->
            select('nombre_video')->
            select('fecha')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            select('Tema.nombre', 'tema_nombre')->
            join('Usuario', array('Acontecimiento.usuario_id_fk', '=', 'Usuario.id'))->
            join('Tema', array('Acontecimiento.tema_id_fk', '=', 'Tema.id'))->
            order_by_desc('fecha')->
            where('publicado', 0)->
            find_many();

    $todosComentarios = ORM::for_table('Comentario')->
            select('texto')->
            select('Comentario.id')->
            select('Comentario.nombre_imagen')->
            select('Comentario.nombre_video')->
            select('Comentario.fecha')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            select('Acontecimiento.titulo', 'acontecimiento_titulo')->
            join('Usuario', array('Comentario.usuario_id_fk', '=', 'Usuario.id'))->
            join('Acontecimiento', array('Comentario.acontecimiento_id_fk', '=', 'Acontecimiento.id'))->
            order_by_desc('fecha')->
            where('publicado', 0)->
            find_many();

    $app->render('Administracion.html.twig', array("datos_usuario" => $usuarioRegistrado, "numeroNotificaciones" => $numeroNotificaciones, "usuarios" => $todosUsuarios, "acontecimientos" => $todosAcontecimientos, "comentarios" => $todosComentarios));
})->name('administrar');

$app->post('/Administrar/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    //-- GESTIÓN DE COMENTARIOS --//
    if (isset($_POST['Aceptar_comentario'])) {
        try {
            $comentario = ORM::for_table('Comentario')->find_one($_POST['Aceptar_comentario']);
            $comentario->publicado = 1;
            $comentario->save();

            $acontecimientoRelacionado = $comentario->acontecimiento_id_fk;
            //Notificamos al autor del comentario la publicación de este
            $usuarioComentario = ORM::for_table('Comentario')->
                    select('Comentario.usuario_id_fk')->
                    select('Acontecimiento.titulo', 'acontecimiento_titulo')->
                    join('Acontecimiento', array('Comentario.acontecimiento_id_fk', '=', 'Acontecimiento.id'))->
                    find_one($comentario['id']);

            notificar($acontecimientoRelacionado, $usuarioComentario['usuario_id_fk'], 'Administración: Tu comentario para el acontecimiento ' . $usuarioComentario['acontecimiento_titulo'] . ' ha sido publicado');

            //Notificamos al usuario autor de este acontecimiento de que ha recibido comentarios
            $usuarioAcontecimiento = ORM::for_table('Acontecimiento')->find_one($acontecimientoRelacionado);

            //Si el autor del comentario es distinto que el autor del acontecimiento le mandamos una notificación
            if ($usuarioComentario['usuario_id_fk'] != $usuarioAcontecimiento['usuario_id_fk']) {
                notificar($acontecimientoRelacionado, $usuarioAcontecimiento['usuario_id_fk'], 'Tu acontecimiento: ' . $usuarioAcontecimiento['titulo'] . ' ha sido comentado');
            }


            //Notificamos ahora a los usuarios que tienen comentarios publicados en este acontecimiento
            $infoComentario = ORM::for_table('Comentario')->
                    distinct()->select('Comentario.usuario_id_fk', 'comentario_usuario_id')->
                    select('Acontecimiento.titulo', 'acontecimiento_titulo')->
                    join('Acontecimiento', array('Comentario.acontecimiento_id_fk', '=', 'Acontecimiento.id'))->
                    join('Usuario', array('Comentario.usuario_id_fk', '=', 'Usuario.id'))->
                    where('Comentario.acontecimiento_id_fk', $acontecimientoRelacionado)->
                    where('Comentario.publicado', 1)->
                    where_not_equal('Comentario.usuario_id_fk', $usuarioComentario['usuario_id_fk'])->
                    find_many();

            foreach ($infoComentario as $fila) {
                //Con este if comprobamos que el usuario que ha publicado el comentario es el autor y así no recibe dos notificaciones
                if ($fila['comentario_usuario_id'] != $usuarioAcontecimiento['usuario_id_fk']) {
                    notificar($acontecimientoRelacionado, $fila['comentario_usuario_id'], 'Comentario/s en el acontecimiento: ' . $fila['acontecimiento_titulo']);
                }
            }

            $app->flash('mensaje', 'Comentario publicado en la web');
        } catch (Exception $e) {
            $app->flash('error', 'Se ha producido un error al publicar el comentario. ' . $e->getMessage());
        }
        $app->redirect('/Administrar/#Comentarios');
    } elseif (isset($_POST['Descartar_comentario'])) {
        $comentario = ORM::for_table('Comentario')->find_one($_POST['Descartar_comentario']);

        $acontecimientoRelacionado = $comentario->acontecimiento_id_fk;
        //Notificamos al autor del comentario que no ha sido publicado
        $usuarioComentario = ORM::for_table('Comentario')->
                select('Comentario.usuario_id_fk')->
                select('Acontecimiento.titulo', 'acontecimiento_titulo')->
                join('Acontecimiento', array('Comentario.acontecimiento_id_fk', '=', 'Acontecimiento.id'))->
                find_one($comentario['id']);

        notificar($acontecimientoRelacionado, $usuarioComentario['usuario_id_fk'], 'Administración: Tu comentario para el acontecimiento ' . $usuarioComentario['acontecimiento_titulo'] . ' no ha pasado el filtro de contenido');

        $comentario->delete();

        $app->flash('mensaje', 'Comentario eliminado correctamente');

        $app->redirect('/Administrar/#Comentarios');
    }
    //-- GESTIÓN DE ACONTECIMIENTOS --/ 
    elseif (isset($_POST['Aceptar'])) {
        try {
            $acontecimiento = ORM::for_table('Acontecimiento')->find_one($_POST['Aceptar']);
            $acontecimiento->publicado = 1;
            $acontecimiento->save();

            //Notificamos al autor del acontecimiento la publicación de este
            $usuarioAcontecimiento = ORM::for_table('Acontecimiento')->
                    select('titulo')->
                    select('usuario_id_fk')->
                    find_one($acontecimiento['id']);

            notificar($acontecimiento['id'], $usuarioAcontecimiento['usuario_id_fk'], 'Administración: Tu acontecimiento ' . $usuarioAcontecimiento['titulo'] . ' ha sido publicado');

            $app->flash('mensaje', 'Acontecimiento publicado en la web');
        } catch (Exception $e) {
            $app->flash('error', 'Se ha producido un error al publicar el acontecimiento. ' . $e->getMessage());
        }

        $app->redirect('/Administrar/#Acontecimientos');
    } elseif (isset($_POST['Descartar'])) {
        $acontecimiento = ORM::for_table('Acontecimiento')->find_one($_POST['Descartar']);
        
        $acontecimiento->delete();
        
        $app->flash('mensaje', 'Acontecimiento eliminado correctamente');

        $app->redirect('/Administrar/#Acontecimientos');

        
    } else {
        //-- GESTIÓN DE USUARIOS --//

        if ((isset($_POST['Insertar'])) && ($_POST['TBnuevo_usuario'] != "") && ($_POST['TBnuevo_email'] != "") && ($_POST['TBnuevo_admin'] != "")) {
            try {
                $nuevoUsuario = ORM::for_table('Usuario')->create();
                $nuevoUsuario->nombre_usuario = $_POST['TBnuevo_usuario'];
                $nuevoUsuario->password = crypt($_POST['TBnuevo_password']);
                $nuevoUsuario->email = $_POST['TBnuevo_email'];
                $nuevoUsuario->admin = $_POST['TBnuevo_admin'];
                $nuevoUsuario->save();

                $app->flash('mensaje', 'Nuevo usuario registrado correctamente');
            } catch (Exception $e) {
                $app->flash('error', 'Fallo en la inserción del usuario');
            }
        } elseif ((isset($_POST['Modificar'])) && ($_POST['TBusuario'] != "") && ($_POST['TBemail'] != "") && ($_POST['TBadmin'] != "")) {
            try {
                $usuario = ORM::for_table('Usuario')->find_one($_POST['Modificar']);
                $usuario->nombre_usuario = $_POST['TBusuario'];
                $usuario->email = $_POST['TBemail'];
                $usuario->admin = $_POST['TBadmin'];
                $usuario->save();

                $app->flash('mensaje', 'Usuario modificado');
            } catch (Exception $e) {
                $app->flash('error', 'Fallo al modificar usuario, compruebe que no está todos los campos vacios y el usuario no está repetido');
            }
        } elseif (isset($_POST['Borrar'])) {
            $usuario = ORM::for_table('Usuario')->find_one($_POST['Borrar']);
            $usuario->delete();
            $app->flash('mensaje', ' Usuario eliminado correctamente');
        } else {
            $app->flash('error', 'Introduzca valores en todos los campos');
        }
        $app->redirect('/Administrar/#Usuarios');
    }
});

//-- NOTIFICACIONES --//
$app->get('/Notificaciones/:id', function($id) use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);

    $numeroNotificaciones = ORM::for_table('Notificacion')->
            where('usuario_id_fk', $usuarioRegistrado['id'])->
            where('leido', 0)->
            count();

    $notificaciones = ORM::for_table('Notificacion')->
            select('Notificacion.id')->
            select('Notificacion.fecha')->
            select('asunto')->
            select('Notificacion.descripcion')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            select('Acontecimiento.titulo', 'acontecimiento_titulo')->
            select('Notificacion.acontecimiento_id_fk')->
            select('leido')->
            join('Acontecimiento', array('Notificacion.acontecimiento_id_fk', '=', 'Acontecimiento.id'))->
            join('Usuario', array('Notificacion.usuario_id_fk', '=', 'Usuario.id'))->
            order_by_desc('fecha')->
            where('Notificacion.usuario_id_fk', $id)->
            find_many();

    $app->render('Notificaciones.html.twig', array("datos_usuario" => $usuarioRegistrado, "numeroNotificaciones" => $numeroNotificaciones, "notificaciones" => $notificaciones));
})->name('notificaciones');

$app->post('/Notificaciones/:id', function($id) use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['irAcontecimiento'])) {
        $notificacion = ORM::for_table('Notificacion')->find_one($_POST['irAcontecimiento']);
        $notificacion->leido = 1;
        $notificacion->save();

        $app->redirect($app->urlFor('mostrarAcontecimiento', array('id' => $notificacion['acontecimiento_id_fk'])));
    }
    if (isset($_POST['leerTodos'])) {
        $noLeidos = ORM::for_table('Notificacion')->where('leido', 0)->
                        where('usuario_id_fk', $usuarioRegistrado['id'])->find_many();
        foreach ($noLeidos as $leer) {
            $leer->leido = 1;
            $leer->save();
        }

        $app->redirect($app->urlFor('notificaciones', array('id' => $usuarioRegistrado['id'])));
    }
});

//-- PÁGINA PRINCIPAL --//
$app->get('/Principal/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $numeroNotificaciones = ORM::for_table('Notificacion')->
            where('usuario_id_fk', $usuarioRegistrado['id'])->
            where('leido', 0)->
            count();

    $acontecimientos = ORM::for_table('Acontecimiento')->
            select('Acontecimiento.id')->
            select('titulo')->
            select('Acontecimiento.descripcion')->
            select('nombre_imagen')->
            select('nombre_video')->
            select('fecha')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            select('Tema.nombre', 'tema_nombre')->
            join('Usuario', array('Acontecimiento.usuario_id_fk', '=', 'Usuario.id'))->
            join('Tema', array('Acontecimiento.tema_id_fk', '=', 'Tema.id'))->
            order_by_desc('fecha')->
            where('publicado', 1)->
            find_many();

    $comentarios = ORM::for_table('Comentario')->
            select('acontecimiento_id_fk')->
            select_expr('COUNT(*)', 'count')->
            group_by('acontecimiento_id_fk')->
            where('publicado', 1)->
            find_many();


    $app->render('Principal.html.twig', array("datos_usuario" => $usuarioRegistrado, "numeroNotificaciones" => $numeroNotificaciones, "acontecimientos" => $acontecimientos, "comentarios" => $comentarios));
})->name('principal');

$app->post('/Principal/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);

    if (isset($_POST['EnviarAdmin'])) {
        if (isset($_POST['tema']) != 0) {
            $idTema = $_POST['tema'];
            $fecha = date('Y-m-d H:i:s');

            //El usuario nos adjunta una imagen, la comprobamos y la guardamos en la carpeta especificada
            $tipo_imagen = $_FILES['imagen']['type'];
            if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/png')) {
                //$carpeta = "/Imagenes/Nuevos_Acontecimientos/";
                $carpeta = $_SERVER['DOCUMENT_ROOT'] . '/Imagenes/Nuevos_Acontecimientos/';
                opendir($carpeta);
                $destino = $carpeta . $_FILES['imagen']['name'];
                copy($_FILES['imagen']['tmp_name'], $destino);
            }

            //Procedemos a insertar el acontecimiento con la imagen creada
            try {
                $nuevoAcontecimiento = ORM::for_table('Acontecimiento')->create();
                $nuevoAcontecimiento->titulo = $_POST['titulo'];
                if ($_FILES['imagen']) {
                    $nuevoAcontecimiento->nombre_imagen = $_FILES['imagen']['name'];
                }

                if ($_POST['descripcion'] != "") {
                    $nuevoAcontecimiento->descripcion = $_POST['descripcion'];
                }
                // $nuevoAcontecimiento->fecha = date_format($fecha, 'Y-m-d H:i:s');
                $nuevoAcontecimiento->fecha = $fecha;
                $nuevoAcontecimiento->usuario_id_fk = $usuarioRegistrado['id'];
                $nuevoAcontecimiento->tema_id_fk = $idTema;
                $nuevoAcontecimiento->save();

                $app->flash('mensaje', 'Su acontecimiento ha sido enviado al administrador correctamente');
            } catch (Exception $e) {
                $app->flash('error', 'Error al enviar la publicación');
            }
        }

        $app->redirect($app->urlFor('principal'));
    }
});

$app->get('/Acontecimiento/:id', function($id) use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $numeroNotificaciones = ORM::for_table('Notificacion')->
            where('usuario_id_fk', $usuarioRegistrado['id'])->
            where('leido', 0)->
            count();

    $acontecimiento = ORM::for_table('Acontecimiento')->
            select('Acontecimiento.id')->
            select('titulo')->
            select('Acontecimiento.descripcion')->
            select('nombre_imagen')->
            select('nombre_video')->
            select('fecha')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            select('Tema.nombre', 'tema_nombre')->
            join('Usuario', array('Acontecimiento.usuario_id_fk', '=', 'Usuario.id'))->
            join('Tema', array('Acontecimiento.tema_id_fk', '=', 'Tema.id'))->
            find_one($id);

    $comentarios = ORM::for_table('Comentario')->
            select('texto')->
            select('Comentario.fecha')->
            select('Comentario.nombre_imagen')->
            select('Comentario.nombre_video')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            join('Acontecimiento', array('Comentario.acontecimiento_id_fk', '=', 'Acontecimiento.id'))->
            join('Usuario', array('Comentario.usuario_id_fk', '=', 'Usuario.id'))->
            order_by_asc('Comentario.fecha')->
            where('acontecimiento_id_fk', $id)->
            where('Comentario.publicado', 1)->
            find_many();

    $app->render('Acontecimiento.html.twig', array("datos_usuario" => $usuarioRegistrado, "numeroNotificaciones" => $numeroNotificaciones, 'acontecimiento' => $acontecimiento, "comentarios" => $comentarios));
})->name('mostrarAcontecimiento');

$app->post('/Acontecimiento/:id', function($id) use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $acontecimiento = ORM::for_table('Acontecimiento')->find_one($id);

    if (isset($_POST['EnviarComent']) && ($_POST['texto']) != "") {
        $fecha = date('Y-m-d H:i:s');

        //El usuario nos adjunta una imagen, la comprobamos y la guardamos en la carpeta especificada
        $tipo_imagen = $_FILES['imagen_comentario']['type'];
        if (($_FILES['imagen_comentario']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/png')) {
            //$carpeta = "/Imagenes/Nuevos_Acontecimientos/";
            $carpeta = $_SERVER['DOCUMENT_ROOT'] . '/Imagenes/Nuevos_Acontecimientos/';
            opendir($carpeta);
            $destino = $carpeta . $_FILES['imagen_comentario']['name'];
            copy($_FILES['imagen_comentario']['tmp_name'], $destino);
        }

        //Procedemos a insertar el comentario con la imagen creada
        try {
            $nuevoComentario = ORM::for_table('Comentario')->create();
            $nuevoComentario->texto = $_POST['texto'];
            // $nuevoAcontecimiento->fecha = date_format($fecha, 'Y-m-d H:i:s');
            $nuevoComentario->fecha = $fecha;
            if ($_FILES['imagen_comentario']) {
                $nuevoComentario->nombre_imagen = $_FILES['imagen_comentario']['name'];
            }

            $nuevoComentario->acontecimiento_id_fk = $acontecimiento['id'];
            $nuevoComentario->usuario_id_fk = $usuarioRegistrado['id'];
            $nuevoComentario->save();

            $app->flash('mensaje', 'Su comentario ha sido enviado correctamente para su comprobación');
        } catch (Exception $e) {
            $app->flash('error', 'Error al enviar el comentario');
        }

        $app->redirect('/Acontecimiento/' . $acontecimiento['id']);
    }
});

//-- PESTAÑA 'VIDEOJUEGOS' DE NUESTRO MENÚ VERTICAL --/
$app->get('/Videojuegos/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $numeroNotificaciones = ORM::for_table('Notificacion')->
            where('usuario_id_fk', $usuarioRegistrado['id'])->
            where('leido', 0)->
            count();
    $idTema = 1;

    $acontecimientos = ORM::for_table('Acontecimiento')->
            select('Acontecimiento.id')->
            select('titulo')->
            select('descripcion')->
            select('nombre_imagen')->
            select('nombre_video')->
            select('fecha')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            join('Usuario', array('Acontecimiento.usuario_id_fk', '=', 'Usuario.id'))->
            order_by_desc('fecha')->
            where('tema_id_fk', $idTema)->
            where('publicado', 1)->
            find_many();

    $comentarios = ORM::for_table('Comentario')->
            select('acontecimiento_id_fk')->
            select_expr('COUNT(*)', 'count')->
            group_by('acontecimiento_id_fk')->
            where('publicado', 1)->
            find_many();

    $app->render('Videojuegos.html.twig', array("datos_usuario" => $usuarioRegistrado, "numeroNotificaciones" => $numeroNotificaciones, "acontecimientos" => $acontecimientos, "comentarios" => $comentarios));
})->name('videojuegos');

$app->post('/Videojuegos/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 1;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/png')) {
            //$carpeta = "/Imagenes/Nuevos_Acontecimientos/";
            $carpeta = $_SERVER['DOCUMENT_ROOT'] . '/Imagenes/Nuevos_Acontecimientos/';
            opendir($carpeta);
            $destino = $carpeta . $_FILES['imagen']['name'];
            copy($_FILES['imagen']['tmp_name'], $destino);
        }

        //Procedemos a insertar el acontecimiento con la imagen creada
        try {
            $nuevoAcontecimiento = ORM::for_table('Acontecimiento')->create();
            $nuevoAcontecimiento->titulo = $_POST['titulo'];
            if ($_FILES['imagen']) {
                $nuevoAcontecimiento->nombre_imagen = $_FILES['imagen']['name'];
            }

            if ($_POST['descripcion'] != "") {
                $nuevoAcontecimiento->descripcion = $_POST['descripcion'];
            }
            // $nuevoAcontecimiento->fecha = date_format($fecha, 'Y-m-d H:i:s');
            $nuevoAcontecimiento->fecha = $fecha;
            $nuevoAcontecimiento->usuario_id_fk = $usuarioRegistrado['id'];
            $nuevoAcontecimiento->tema_id_fk = $idTema;
            $nuevoAcontecimiento->save();

            $app->flash('mensaje', 'Su acontecimiento ha sido enviado al administrador correctamente');
        } catch (Exception $e) {
            $app->flash('error', 'Error al enviar la publicación');
        }

        $app->redirect($app->urlFor('videojuegos'));
    }
});

//-- PESTAÑA 'TELEVISIÓN' DE NUESTRO MENÚ VERTICAL --/
$app->get('/Television/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $numeroNotificaciones = ORM::for_table('Notificacion')->
            where('usuario_id_fk', $usuarioRegistrado['id'])->
            where('leido', 0)->
            count();
    $idTema = 2;

    $acontecimientos = ORM::for_table('Acontecimiento')->
            select('Acontecimiento.id')->
            select('titulo')->
            select('descripcion')->
            select('nombre_imagen')->
            select('nombre_video')->
            select('fecha')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            join('Usuario', array('Acontecimiento.usuario_id_fk', '=', 'Usuario.id'))->
            order_by_desc('fecha')->
            where('tema_id_fk', $idTema)->
            where('publicado', 1)->
            find_many();

    $comentarios = ORM::for_table('Comentario')->
            select('acontecimiento_id_fk')->
            select_expr('COUNT(*)', 'count')->
            group_by('acontecimiento_id_fk')->
            where('publicado', 1)->
            find_many();

    $app->render('Television.html.twig', array("datos_usuario" => $usuarioRegistrado, "numeroNotificaciones" => $numeroNotificaciones, "acontecimientos" => $acontecimientos, "comentarios" => $comentarios));
})->name('television');

$app->post('/Television/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 2;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/png')) {
            //$carpeta = "/Imagenes/Nuevos_Acontecimientos/";
            $carpeta = $_SERVER['DOCUMENT_ROOT'] . '/Imagenes/Nuevos_Acontecimientos/';
            opendir($carpeta);
            $destino = $carpeta . $_FILES['imagen']['name'];
            copy($_FILES['imagen']['tmp_name'], $destino);
        }

        //Procedemos a insertar el acontecimiento con la imagen creada
        try {
            $nuevoAcontecimiento = ORM::for_table('Acontecimiento')->create();
            $nuevoAcontecimiento->titulo = $_POST['titulo'];
            if ($_FILES['imagen']) {
                $nuevoAcontecimiento->nombre_imagen = $_FILES['imagen']['name'];
            }

            if ($_POST['descripcion'] != "") {
                $nuevoAcontecimiento->descripcion = $_POST['descripcion'];
            }
            // $nuevoAcontecimiento->fecha = date_format($fecha, 'Y-m-d H:i:s');
            $nuevoAcontecimiento->fecha = $fecha;
            $nuevoAcontecimiento->usuario_id_fk = $usuarioRegistrado['id'];
            $nuevoAcontecimiento->tema_id_fk = $idTema;
            $nuevoAcontecimiento->save();

            $app->flash('mensaje', 'Su acontecimiento ha sido enviado al administrador correctamente');
        } catch (Exception $e) {
            $app->flash('error', 'Error al enviar la publicación');
        }

        $app->redirect($app->urlFor('television'));
    }
});

//-- PESTAÑA 'DEPORTES' DE NUESTRO MENÚ VERTICAL --/
$app->get('/Deportes/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $numeroNotificaciones = ORM::for_table('Notificacion')->
            where('usuario_id_fk', $usuarioRegistrado['id'])->
            where('leido', 0)->
            count();
    $idTema = 3;

    $acontecimientos = ORM::for_table('Acontecimiento')->
            select('Acontecimiento.id')->
            select('titulo')->
            select('descripcion')->
            select('nombre_imagen')->
            select('nombre_video')->
            select('fecha')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            join('Usuario', array('Acontecimiento.usuario_id_fk', '=', 'Usuario.id'))->
            order_by_desc('fecha')->
            where('tema_id_fk', $idTema)->
            where('publicado', 1)->
            find_many();

    $comentarios = ORM::for_table('Comentario')->
            select('acontecimiento_id_fk')->
            select_expr('COUNT(*)', 'count')->
            group_by('acontecimiento_id_fk')->
            where('publicado', 1)->
            find_many();

    $app->render('Deportes.html.twig', array("datos_usuario" => $usuarioRegistrado, "numeroNotificaciones" => $numeroNotificaciones, "acontecimientos" => $acontecimientos, "comentarios" => $comentarios));
})->name('deportes');

$app->post('/Deportes/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 3;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/png')) {
            //$carpeta = "/Imagenes/Nuevos_Acontecimientos/";
            $carpeta = $_SERVER['DOCUMENT_ROOT'] . '/Imagenes/Nuevos_Acontecimientos/';
            opendir($carpeta);
            $destino = $carpeta . $_FILES['imagen']['name'];
            copy($_FILES['imagen']['tmp_name'], $destino);
        }

        //Procedemos a insertar el acontecimiento con la imagen creada
        try {
            $nuevoAcontecimiento = ORM::for_table('Acontecimiento')->create();
            $nuevoAcontecimiento->titulo = $_POST['titulo'];
            if ($_FILES['imagen']) {
                $nuevoAcontecimiento->nombre_imagen = $_FILES['imagen']['name'];
            }

            if ($_POST['descripcion'] != "") {
                $nuevoAcontecimiento->descripcion = $_POST['descripcion'];
            }
            // $nuevoAcontecimiento->fecha = date_format($fecha, 'Y-m-d H:i:s');
            $nuevoAcontecimiento->fecha = $fecha;
            $nuevoAcontecimiento->usuario_id_fk = $usuarioRegistrado['id'];
            $nuevoAcontecimiento->tema_id_fk = $idTema;
            $nuevoAcontecimiento->save();

            $app->flash('mensaje', 'Su acontecimiento ha sido enviado al administrador correctamente');
        } catch (Exception $e) {
            $app->flash('error', 'Error al enviar la publicación');
        }

        $app->redirect($app->urlFor('deportes'));
    }
});

//-- PESTAÑA 'JUEGOS INFANTILES' DE NUESTRO MENÚ VERTICAL --/
$app->get('/Juegos_Infantiles/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $numeroNotificaciones = ORM::for_table('Notificacion')->
            where('usuario_id_fk', $usuarioRegistrado['id'])->
            where('leido', 0)->
            count();
    $idTema = 4;

    $acontecimientos = ORM::for_table('Acontecimiento')->
            select('Acontecimiento.id')->
            select('titulo')->
            select('descripcion')->
            select('nombre_imagen')->
            select('nombre_video')->
            select('fecha')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            join('Usuario', array('Acontecimiento.usuario_id_fk', '=', 'Usuario.id'))->
            order_by_desc('fecha')->
            where('tema_id_fk', $idTema)->
            where('publicado', 1)->
            find_many();

    $comentarios = ORM::for_table('Comentario')->
            select('acontecimiento_id_fk')->
            select_expr('COUNT(*)', 'count')->
            group_by('acontecimiento_id_fk')->
            where('publicado', 1)->
            find_many();

    $app->render('JuegosInfantiles.html.twig', array("datos_usuario" => $usuarioRegistrado, "numeroNotificaciones" => $numeroNotificaciones, "acontecimientos" => $acontecimientos, "comentarios" => $comentarios));
})->name('juegosInfantiles');

$app->post('/Juegos_Infantiles/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 4;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/png')) {
            //$carpeta = "/Imagenes/Nuevos_Acontecimientos/";
            $carpeta = $_SERVER['DOCUMENT_ROOT'] . '/Imagenes/Nuevos_Acontecimientos/';
            opendir($carpeta);
            $destino = $carpeta . $_FILES['imagen']['name'];
            copy($_FILES['imagen']['tmp_name'], $destino);
        }

        //Procedemos a insertar el acontecimiento con la imagen creada
        try {
            $nuevoAcontecimiento = ORM::for_table('Acontecimiento')->create();
            $nuevoAcontecimiento->titulo = $_POST['titulo'];
            if ($_FILES['imagen']) {
                $nuevoAcontecimiento->nombre_imagen = $_FILES['imagen']['name'];
            }

            if ($_POST['descripcion'] != "") {
                $nuevoAcontecimiento->descripcion = $_POST['descripcion'];
            }
            // $nuevoAcontecimiento->fecha = date_format($fecha, 'Y-m-d H:i:s');
            $nuevoAcontecimiento->fecha = $fecha;
            $nuevoAcontecimiento->usuario_id_fk = $usuarioRegistrado['id'];
            $nuevoAcontecimiento->tema_id_fk = $idTema;
            $nuevoAcontecimiento->save();

            $app->flash('mensaje', 'Su acontecimiento ha sido enviado al administrador correctamente');
        } catch (Exception $e) {
            $app->flash('error', 'Error al enviar la publicación');
        }

        $app->redirect($app->urlFor('juegosInfantiles'));
    }
});

//-- PESTAÑA 'MÚSICA' DE NUESTRO MENÚ VERTICAL --/
$app->get('/Musica/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $numeroNotificaciones = ORM::for_table('Notificacion')->
            where('usuario_id_fk', $usuarioRegistrado['id'])->
            where('leido', 0)->
            count();
    $idTema = 5;

    $acontecimientos = ORM::for_table('Acontecimiento')->
            select('Acontecimiento.id')->
            select('titulo')->
            select('descripcion')->
            select('nombre_imagen')->
            select('nombre_video')->
            select('fecha')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            join('Usuario', array('Acontecimiento.usuario_id_fk', '=', 'Usuario.id'))->
            order_by_desc('fecha')->
            where('tema_id_fk', $idTema)->
            where('publicado', 1)->
            find_many();

    $comentarios = ORM::for_table('Comentario')->
            select('acontecimiento_id_fk')->
            select_expr('COUNT(*)', 'count')->
            group_by('acontecimiento_id_fk')->
            where('publicado', 1)->
            find_many();

    $app->render('Musica.html.twig', array("datos_usuario" => $usuarioRegistrado, "numeroNotificaciones" => $numeroNotificaciones, "acontecimientos" => $acontecimientos, "comentarios" => $comentarios));
})->name('musica');

$app->post('/Musica/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 5;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/png')) {
            //$carpeta = "/Imagenes/Nuevos_Acontecimientos/";
            $carpeta = $_SERVER['DOCUMENT_ROOT'] . '/Imagenes/Nuevos_Acontecimientos/';
            opendir($carpeta);
            $destino = $carpeta . $_FILES['imagen']['name'];
            copy($_FILES['imagen']['tmp_name'], $destino);
        }

        //Procedemos a insertar el acontecimiento con la imagen creada
        try {
            $nuevoAcontecimiento = ORM::for_table('Acontecimiento')->create();
            $nuevoAcontecimiento->titulo = $_POST['titulo'];
            if ($_FILES['imagen']) {
                $nuevoAcontecimiento->nombre_imagen = $_FILES['imagen']['name'];
            }

            if ($_POST['descripcion'] != "") {
                $nuevoAcontecimiento->descripcion = $_POST['descripcion'];
            }
            // $nuevoAcontecimiento->fecha = date_format($fecha, 'Y-m-d H:i:s');
            $nuevoAcontecimiento->fecha = $fecha;
            $nuevoAcontecimiento->usuario_id_fk = $usuarioRegistrado['id'];
            $nuevoAcontecimiento->tema_id_fk = $idTema;
            $nuevoAcontecimiento->save();

            $app->flash('mensaje', 'Su acontecimiento ha sido enviado al administrador correctamente');
        } catch (Exception $e) {
            $app->flash('error', 'Error al enviar la publicación');
        }

        $app->redirect($app->urlFor('musica'));
    }
});

//-- PESTAÑA 'OTROS' DE NUESTRO MENÚ VERTICAL --/
$app->get('/Otros/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $numeroNotificaciones = ORM::for_table('Notificacion')->
            where('usuario_id_fk', $usuarioRegistrado['id'])->
            where('leido', 0)->
            count();
    $idTema = 6;

    $acontecimientos = ORM::for_table('Acontecimiento')->
            select('Acontecimiento.id')->
            select('titulo')->
            select('descripcion')->
            select('nombre_imagen')->
            select('nombre_video')->
            select('fecha')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            join('Usuario', array('Acontecimiento.usuario_id_fk', '=', 'Usuario.id'))->
            order_by_desc('fecha')->
            where('tema_id_fk', $idTema)->
            where('publicado', 1)->
            find_many();

    $comentarios = ORM::for_table('Comentario')->
            select('acontecimiento_id_fk')->
            select_expr('COUNT(*)', 'count')->
            group_by('acontecimiento_id_fk')->
            where('publicado', 1)->
            find_many();

    $app->render('Otros.html.twig', array("datos_usuario" => $usuarioRegistrado, "numeroNotificaciones" => $numeroNotificaciones, "acontecimientos" => $acontecimientos, "comentarios" => $comentarios));
})->name('otros');

$app->post('/Otros/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 6;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/png')) {
            //$carpeta = "/Imagenes/Nuevos_Acontecimientos/";
            $carpeta = $_SERVER['DOCUMENT_ROOT'] . '/Imagenes/Nuevos_Acontecimientos/';
            opendir($carpeta);
            $destino = $carpeta . $_FILES['imagen']['name'];
            copy($_FILES['imagen']['tmp_name'], $destino);
        }

        //Procedemos a insertar el acontecimiento con la imagen creada
        try {
            $nuevoAcontecimiento = ORM::for_table('Acontecimiento')->create();
            $nuevoAcontecimiento->titulo = $_POST['titulo'];
            if ($_FILES['imagen']) {
                $nuevoAcontecimiento->nombre_imagen = $_FILES['imagen']['name'];
            }

            if ($_POST['descripcion'] != "") {
                $nuevoAcontecimiento->descripcion = $_POST['descripcion'];
            }
            // $nuevoAcontecimiento->fecha = date_format($fecha, 'Y-m-d H:i:s');
            $nuevoAcontecimiento->fecha = $fecha;
            $nuevoAcontecimiento->usuario_id_fk = $usuarioRegistrado['id'];
            $nuevoAcontecimiento->tema_id_fk = $idTema;
            $nuevoAcontecimiento->save();

            $app->flash('mensaje', 'Su acontecimiento ha sido enviado al administrador correctamente');
        } catch (Exception $e) {
            $app->flash('error', 'Error al enviar la publicación');
        }

        $app->redirect($app->urlFor('otros'));
    }
});

//-- FUNCIÓN PARA LAS NOTIFICACIONES --//
function notificar($acontecimiento, $usuario, $asunto) {
    $fecha = date('Y-m-d H:i:s');

    $nuevaNotificacion = ORM::for_table('Notificacion')->create();
    $nuevaNotificacion->fecha = $fecha;
    $nuevaNotificacion->asunto = $asunto;
    $nuevaNotificacion->acontecimiento_id_fk = $acontecimiento;
    $nuevaNotificacion->usuario_id_fk = $usuario;
    $nuevaNotificacion->save();
}

//-- PARA LA BARRA DE BÚSQUEDA EN LA ZONA DE USUARIOS (ADMINISTRACIÓN) --//
$app->post('/buscar_admin', function() use($app) {
    if (isset($_POST['autores']) == 'todos') {
        $nombresUsuarios = ORM::for_table('Usuario')->select('nombre_usuario')->find_many();

        $array_usuarios = array();

        $i = 0;

        if ($nombresUsuarios) {
            foreach ($nombresUsuarios as $fila) {
                $array_usuarios[$i] = $fila->as_array();
                $i++;
            }
            $json_nombres = json_encode($array_usuarios);
        }

        $app->response->headers->set('Content-Type', 'application/json');
        echo $json_nombres;
    }
});

//-- PARA LA BARRA DE BÚSQUEDA EN LA ZONA DE ACONTECIMIENTOS (PARTE PÚBLICA) --//
$app->post('/buscar_acontec', function() use($app) {
    if (isset($_POST['acontec']) == 'todos') {
        $nombresAcontec = ORM::for_table('Acontecimiento')->select_many('id', 'titulo')->find_many();

        $array_acontec = array();

        $i = 0;

        if ($nombresAcontec) {
            foreach ($nombresAcontec as $fila) {
                $array_acontec[$i] = $fila->as_array();
                $i++;
            }
            $json_acontec = json_encode($array_acontec);
        }

        $app->response->headers->set('Content-Type', 'application/json');
        echo $json_acontec;
    }
});

//-- PULSAMOS EL BOTÓN 'SALIR' --//
$app->post('/salir', function() use($app) {
    unset($_SESSION);
    session_destroy();
    $app->redirect('/');
})->name('salir');

$app->run();
?>