$(document).ready(function () {
    $('#regUsuario').keyup(function () {
        var usuario = $("#regUsuario").val();
        if (usuario !== " ") {
            $.post("index.php", {buscar_usuario: usuario}, buscarUsuario);
        }
    });

    //Función Ajax para buscar el usuario que va escribiendo en la BBDD
    //Habilita o deshabilita el botón 'Registrar'
    function buscarUsuario(comprobacion) {
        var respuesta = "Disponible";
        var clase = "green";
        var borrar = "red";
        var habilitar = true;

        if (comprobacion == "No") {
            respuesta = "Ocupado";
            clase = "red";
            borrar = "green";
            habilitar = false;
        }
        else if (comprobacion == "") {
            respuesta = "";
            borrar = "green red";
            habilitar = false;
        }

        $(".existe").text(respuesta);
        $(".existe").addClass(clase);
        $(".existe").removeClass(borrar);

        if (habilitar) {
            $("#regRegistrar").removeProp("disabled");
        }
        else {
            $("#regRegistrar").prop("disabled", true);
        }       
    }
});
