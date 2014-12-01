$(document).ready(function () {
    $('#buscar_admin').click(function () {
        $.post("/Administrar/", {autores: "todos"}, buscarUsuario);
    });

    function buscarUsuario(datos) {
        datos = JSON.parse(xmlhttp.responseText);
        for (var dato in datos) {
            $(function () {
                var availableTags = dato[datos]["nombre_usuario"];
            });

            $("#buscar").autocomplete({
                source: availableTags
            });
        }
    }
});