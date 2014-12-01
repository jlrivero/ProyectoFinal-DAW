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
            find_array();

    $app->render('Administracion.html.twig', array("datos_usuario" => $usuarioRegistrado, "usuarios" => $todosUsuarios, "acontecimientos" => $todosAcontecimientos));
})->name('administrar');

$app->post('/Administrar/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);

    //-- GESTIÓN DE ACONTECIMIENTOS --/
    if (isset($_POST['Aceptar'])) {
        try {
            $acontecimiento = ORM::for_table('Acontecimiento')->find_one($_POST['Aceptar']);
            $acontecimiento->publicado = 1;
            $acontecimiento->save();

            $app->flash('mensaje', 'Acontecimiento publicado en la web');
        } catch (Exception $e) {
            $app->flash('error', 'Se ha producido un error al publicar el acontecimiento. ' . $e->getMessage());
        }
    } else {
        //-- GESTIÓN DE USUARIOS --//
        //-- PARA LA BARRA DE BÚSQUEDA--//
        /* if (isset($_POST['autores']) == 'todos') {
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
          } */

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
    }

    $app->redirect($app->urlFor('administrar'));
});

//-- PÁGINA PRINCIPAL --//
$app->get('/Principal/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);

    $acontecimientos = ORM::for_table('Acontecimiento')->
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
            find_array();

    $app->render('Principal.html.twig', array("datos_usuario" => $usuarioRegistrado, "acontecimientos" => $acontecimientos));
})->name('principal');

$app->post('/Principal/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);

    if (isset($_POST['EnviarAdmin'])) {
        if (isset($_POST['tema']) != 0) {
            $idTema = $_POST['tema'];
            $fecha = date('Y-m-d H:i:s');
            //El usuario nos adjunta una imagen, la comprobamos y la guardamos
            $tipo_imagen = $_FILES['imagen']['type'];
            if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/gif') || ($tipo_imagen == 'image/png')) {
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

//-- PESTAÑA 'VIDEOJUEGOS' DE NUESTRO MENÚ VERTICAL --/
$app->get('/Videojuegos/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $idTema = 1;

    $acontecimientos = ORM::for_table('Acontecimiento')->
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
            find_array();

    $app->render('Videojuegos.html.twig', array("datos_usuario" => $usuarioRegistrado, "acontecimientos" => $acontecimientos));
})->name('videojuegos');

$app->post('/Videojuegos/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 1;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/gif') || ($tipo_imagen == 'image/png')) {
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
    $idTema = 2;

    $acontecimientos = ORM::for_table('Acontecimiento')->
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
            find_array();

    $app->render('Television.html.twig', array("datos_usuario" => $usuarioRegistrado, "acontecimientos" => $acontecimientos));
})->name('television');

$app->post('/Television/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 2;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/gif') || ($tipo_imagen == 'image/png')) {
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
    $idTema = 3;

    $acontecimientos = ORM::for_table('Acontecimiento')->
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
            find_array();

    $app->render('Deportes.html.twig', array("datos_usuario" => $usuarioRegistrado, "acontecimientos" => $acontecimientos));
})->name('deportes');

$app->post('/Deportes/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 3;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/gif') || ($tipo_imagen == 'image/png')) {
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
    $idTema = 4;

    $acontecimientos = ORM::for_table('Acontecimiento')->
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
            find_array();

    $app->render('JuegosInfantiles.html.twig', array("datos_usuario" => $usuarioRegistrado, "acontecimientos" => $acontecimientos));
})->name('juegosInfantiles');

$app->post('/Juegos_Infantiles/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 4;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/gif') || ($tipo_imagen == 'image/png')) {
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
    $idTema = 5;

    $acontecimientos = ORM::for_table('Acontecimiento')->
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
            find_array();

    $app->render('Musica.html.twig', array("datos_usuario" => $usuarioRegistrado, "acontecimientos" => $acontecimientos));
})->name('musica');

$app->post('/Musica/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 5;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/gif') || ($tipo_imagen == 'image/png')) {
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
    $idTema = 6;

    $acontecimientos = ORM::for_table('Acontecimiento')->
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
            find_array();

    $app->render('Otros.html.twig', array("datos_usuario" => $usuarioRegistrado, "acontecimientos" => $acontecimientos));
})->name('otros');

$app->post('/Otros/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    if (isset($_POST['EnviarAdmin'])) {
        $idTema = 6;
        $fecha = date('Y-m-d H:i:s');
        //El usuario nos adjunta una imagen, la comprobamos y la guardamos
        $tipo_imagen = $_FILES['imagen']['type'];
        if (($_FILES['imagen']['name'] != "") && ($tipo_imagen == 'image/jpeg') || ($tipo_imagen == 'image/jpg') || ($tipo_imagen == 'image/gif') || ($tipo_imagen == 'image/png')) {
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

//-- PULSAMOS EL BOTÓN 'SALIR' --//
$app->post('/salir', function() use($app) {
    unset($_SESSION);
    session_destroy();
    $app->redirect('/');
})->name('salir');

$app->run();
?>