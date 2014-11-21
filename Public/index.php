<?php

include "../vendor/autoload.php";
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

        //echo "USER: " . $usuario . ", PASS: " . $password;

        $usuarioRegistrado = ORM::for_table('Usuario')->where('nombre_usuario', $usuario)->
                        where('password', $password)->find_one();

        if ($usuarioRegistrado) {
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
        } elseif(($_POST['buscar_usuario'] == ' ') || ($_POST['buscar_usuario'] == '')) {
            $dato = "";
        }
        echo $dato;
    }
    
    if (isset($_POST['regRegistrar'])) {

        $usuario = $app->request->post('regUsuario');
        $password = $app->request->post('regPassword');
        $email = $app->request->post('regEmail');

        //echo "USER: " . $usuario . ", PASS: " . $password;

       /* $usuarioRegistrado = ORM::for_table('Usuario')->where('nombre_usuario', $usuario)->
                        where('password', $password)->find_one();

        if ($usuarioRegistrado) {
            $_SESSION['usuario'] = $usuarioRegistrado['id'];
            $app->redirect($app->urlFor('principal'));
        } else {
            $app->flash('error', 'Usuario y/o contraseña incorrectos');
            $app->redirect($app->urlFor('login'));
        }*/
    }    
});

$app->get('/Principal/', function() use($app) {
    //$usuarioRegistrado = ORM::for_table('usuario')->find_one($_SESSION['usuario']);
    //$app->render('Principal.html.twig', array("datos_usuario" => $usuarioRegistrado));
    $app->render('Principal.html.twig');
})->name('principal');


//-- PULSAMOS EL BOTÓN 'SALIR' --//
$app->post('/salir', function() use($app) {
    unset($_SESSION);
    session_destroy();
    $app->redirect('/');
})->name('salir');

$app->run();
?>