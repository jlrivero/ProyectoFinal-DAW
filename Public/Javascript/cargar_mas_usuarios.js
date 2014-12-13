$(document).ready(function () {
    //Añadimos los usuarios a la lista para autocompletar
    $.post("/cargar_admins", {admins: 10}, dameAdmins);

    function dameAdmins(datos) {
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
