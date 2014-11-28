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
        if($usuarioRegistrado && (crypt($password, $usuarioRegistrado['password']) === $usuarioRegistrado['password'])){
            $_SESSION['usuario'] = $usuarioRegistrado['id'];
            $app->redirect($app->urlFor('principal')); 
        }
        else {
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
        try{
        $nuevoUsuario = ORM::for_table('Usuario')->create();
        $nuevoUsuario->nombre_usuario = $_POST['regUsuario'];
        $nuevoUsuario->password = crypt($_POST['regPassword']);
        $nuevoUsuario->email = $_POST['regEmail'];
        $nuevoUsuario->save();
        
        $app->flash('mensaje', 'Registrado correctamente. Ha recibido un email con los datos de usuario');
        
        //Enviar correo electrónico de bienvenida a la plataforma
        mandarCorreo($_POST['regEmail'], $_POST['regUsuario']);
        }
        catch (Exception $e){
            $app->flash('error', 'Fallo en la inserción del usuario');
        }
        $app->redirect($app->urlFor('login'));
    }
});

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
            find_array();
   
    $app->render('Principal.html.twig', array("datos_usuario" => $usuarioRegistrado, "acontecimientos" => $acontecimientos));
})->name('principal');

$app->get('/Videojuegos/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $app->render('Videojuegos.html.twig', array("datos_usuario" => $usuarioRegistrado));
})->name('videojuegos');

$app->get('/Television/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);    
    
    $acontecimientos = ORM::for_table('Acontecimiento')->
            select('titulo')->
            select('descripcion')->
            select('nombre_imagen')->
            select('nombre_video')->
            select('fecha')->
            select('Usuario.nombre_usuario', 'usuario_nombre')->
            join('Usuario', array('Acontecimiento.usuario_id_fk', '=', 'Usuario.id'))->
            order_by_desc('fecha')->
            where('tema_id_fk', 2)->
            find_array();
   
    $app->render('Television.html.twig', array("datos_usuario" => $usuarioRegistrado, "acontecimientos" => $acontecimientos));
               
})->name('television');

$app->get('/Juegos_Infantiles/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $app->render('JuegosInfantiles.html.twig', array("datos_usuario" => $usuarioRegistrado));
})->name('juegosInfantiles');

$app->get('/Deportes/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $app->render('Deportes.html.twig', array("datos_usuario" => $usuarioRegistrado));
})->name('deportes');

$app->get('/Musica/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $app->render('Musica.html.twig', array("datos_usuario" => $usuarioRegistrado));
})->name('musica');

$app->get('/Otros/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $app->render('Otros.html.twig', array("datos_usuario" => $usuarioRegistrado));
})->name('otros');

$app->get('/Administrar/', function() use($app) {
    $usuarioRegistrado = ORM::for_table('Usuario')->find_one($_SESSION['usuario']);
    $app->render('Administracion.html.twig', array("datos_usuario" => $usuarioRegistrado));
})->name('administrar');


//-- PULSAMOS EL BOTÓN 'SALIR' --//
$app->post('/salir', function() use($app) {
    unset($_SESSION);
    session_destroy();
    $app->redirect('/');
})->name('salir');

$app->run();

?>