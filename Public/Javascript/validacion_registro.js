//Esta función comprueba que los campos no están vacios y coinciden
//Cuando se cumpla todo, el boton 'Registar' se habilita
$(document).ready(function () {
    $('#floginReg').click(function () {
        var usuario =   $("#regUsuario").val();
        if (usuario !== " ") {
            $.post("index.php", {buscar_usuario: usuario}, buscarUsuario);
        }
    });    
    
    $('#floginReg').keyup(function () {
        var usuario =   $("#regUsuario").val();
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

        if (((habilitar) && $("#regPassword").val() === $("#regPassword2").val()) && ($("#regEmail").val() === $("#regEmail2").val()) && ($("#regPassword").val() !== "") && ($("#regPassword2").val() !== "") && ($("#regEmail").val() !== "") && ($("#regEmail2").val() !== "")) {
            $("#regRegistrar").removeProp("disabled");
        }
        else {
            $("#regRegistrar").prop("disabled", true);
        }       
    }
});
