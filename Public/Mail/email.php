<?php
     include("PHPMailer-master/class.phpmailer.php"); 
     include("PHPMailer-master/class.smtp.php");

    function mandarCorreo($email, $usuario){
        
	$mail = new PHPMailer();
        $mail->CharSet = 'UTF-8';
  	$mail->IsSMTP();
	$mail->SMTPAuth = true;
	$mail->SMTPSecure = "ssl";
	$mail->Host = "smtp.gmail.com";
	$mail->Port = 465;
	$mail->Username = "phpmail90@gmail.com";
	$mail->Password = "Rivero90";

      $mail->From = "phpmail90@gmail.com";
      $mail->FromName = "Bienvenid@ a Generación de los 90";
      $mail->Subject = "Administrador";
      $mail->MsgHTML('
<h3>Bienvenid@ a la Generación de los 90</h3>

<p><u>Datos de contacto</i></p>

<p>Nombre de Usuario: '. $usuario .'</p>
<p>Email: ' . $email . '</p>
');
      
      //$email->AltBody("mensaje");
      //AltBody se envía el mensaje en texto plano y 
      //MsgHTML el mensaje en formato HTML
     	$mail->AddAddress($email, "Administrador"); 
     	$mail->IsHTML(true);
     	if(!$mail->Send()) 
     	{
		echo "Error: " . $mail->ErrorInfo . "<br/>";
		echo $email;
	} 
	else 
	{
		echo "Enviado!";
	}
    }
?>
