$(document).ready(function () {
    //Añadimos los usuarios a la lista para autocompletar
    $.post("/buscar_acontec", {acontec: "todos"}, buscarAcontecimiento);

    function buscarAcontecimiento(datos) {
        var availableTags = [];
        for (var dato in datos) {
            $(function () {
                availableTags.push({id: datos[dato]["id"], label: datos[dato]["titulo"]});
            });
        }
        $("#buscar").autocomplete({
            source: availableTags,
            select: function(event, ui){
                window.location.href = "/Acontecimiento/" + ui.item.id;
            }
        });
    }
});