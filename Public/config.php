<?php
            $db_host = "localhost";
            $db_schema = "bbdd_generacion90";
            $db_usuario = "jlrivero";
            $db_password = "proyecto";

            ORM::configure("mysql:host=" . $db_host . ";dbname=" . $db_schema . "; charset=utf8");
            ORM::configure("username", $db_usuario);
            ORM::configure("password", $db_password);
        ?>