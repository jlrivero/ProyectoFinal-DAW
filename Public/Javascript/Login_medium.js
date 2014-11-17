var px_m1 = 0;
var px_m2 = 0;
var px_m3 = 0;

if(($(window).width() >= 800) && ($(window).width() <= 1279)){
	px_m1 = 1250;
	px_m2 = 2500;
	px_m3 = 3500;
	movimiento(px_m1, px_m2, px_m3);
}

if(($(window).width() >= 768) && ($(window).width() <= 799)){
	px_m1 = 900;
	px_m2 = 1925;
	px_m3 = 2860;
	movimiento(px_m1, px_m2, px_m3);
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
