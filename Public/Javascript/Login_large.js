//Esta variables se utilizan en todos los ficheros JS del Login, son el número de pixeles en el cual aparecerán
//nuestras imágenes a la vista.
var px_l1 = 0;
var px_l2 = 0;
var px_l3 = 0;

if($(window).width() >= 1280){
	px_l1 = 630;
	px_l2 = 1810;
	px_l3 = 2800;
	movimiento(px_l1, px_l2, px_l3);		
}

function movimiento(p1, p2, p3){
	$(document).ready(function(){
		$("h3").hide();
		$("img").hide();
		$(window).scroll(function(){
			//console.log($(this).scrollTop());
			if($(this).scrollTop()>= p1){
				$("#scroll1 h3").fadeIn(700);
				$("#scroll1 img").fadeIn(0);
				$("#scroll1 img").addClass("animate");
			}
			if($(this).scrollTop()>= p2){
				$("#scroll2 h3").fadeIn(700);
				$("#scroll2 img").fadeIn(0);
				$("#scroll2 img").addClass("animate");
			}
			if($(this).scrollTop()>= p3){
				$("#scroll3 h3").fadeIn(700);
				$("#scroll3 img").fadeIn(300);
			}					
		});
	});	
}
