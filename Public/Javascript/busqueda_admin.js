$(document).ready(function () {
    //Añadimos los usuarios a la lista para autocompletar
    $.post("/buscar_admin", {autores: "todos"}, buscarUsuario);

    function buscarUsuario(datos) {
        var availableTags = new Array();
        for (var dato in datos) {
            $(function () {
                availableTags.push(datos[dato]["nombre_usuario"]);
            });
        }
        $("#buscar_admin").autocomplete({
            source: availableTags
        });
    }
});