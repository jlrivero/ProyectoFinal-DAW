$(document).ready(function () {
    //Añadimos los usuarios a la lista para autocompletar
    $.post("/buscar_admin", {autores: "todos"}, buscarUsuario);

    function buscarUsuario(datos) {
        var availableTags = [];
        for (var dato in datos) {
            $(function () {
                availableTags.push({id: datos[dato]["id"], label: datos[dato]["nombre_usuario"]});
            });
        }
        $("#buscar_admin").autocomplete({
            source: availableTags,
            select: function(event, ui){
                window.location.href = "/Administrar/#" + ui.item.id;
            }
        });
    }
});