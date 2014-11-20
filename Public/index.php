<?php

include "../vendor/autoload.php";
require_once 'config.php';

//session_start();

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
$app->get('/', function() use($app){
    $app->render('Login.html.twig');
})->name('login');

//-- PULSAMOS EL BOTÓN 'CONECTAR' --//
$app->post('/', function() use($app) {
    if(isset($_POST['Conectar'])){
        $app->redirect($app->urlFor('principal'));
        //$usuario = $app->request->post('TBusuario');
        //$pass = $app->request->post('TBpass');

        //echo "USER: " . $usuario . ", PASS: " .$pass;

        /*$usuarioRegistrado = ORM::for_table('usuario')->where('usuario', $usuario)->
                        where('pass', $pass)->find_one();

        if ($usuarioRegistrado) {
            $_SESSION['usuario'] = $usuarioRegistrado['id'];
            $app->redirect($app->urlFor('principal'));
        } 
        else {
            $app->flash('error', 'Usuario o contraseña incorrectos');
            $app->redirect($app->urlFor('login'));
        } */
    }
});

$app->get('/Principal/', function() use($app){
    //$usuarioRegistrado = ORM::for_table('usuario')->find_one($_SESSION['usuario']);
    //$app->render('Principal.html.twig', array("datos_usuario" => $usuarioRegistrado));
    $app->render('Principal.html.twig');
})->name('principal');


//-- PULSAMOS EL BOTÓN 'SALIR' --//
$app->post('/salir', function() use($app){
    //unset($_SESSION);
    //session_destroy();
    $app->redirect('/');   
})->name('salir');

$app->run();

?>