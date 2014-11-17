var px_s1 = 0;
var px_s2 = 0;
var px_s3 = 0;

if($(window).width() == 640){
	px_s1 = 550;
	px_s2 = 1250;
	px_s3 = 1800;
	movimiento(px_s1, px_s2, px_s3);
}

if(($(window).width() >= 550) && ($(window).width() <= 767) && ($(window).width() != 640)){
	px_s1 = 510;
	px_s2 = 1254;
	px_s3 = 1800;
	movimiento(px_s1, px_s2, px_s3);
}

if(($(window).width() >= 360) && ($(window).width() <= 549)){
	px_s1 = 275;
	px_s2 = 960;
	px_s3 = 1580;
	movimiento(px_s1, px_s2, px_s3);
}

if(($(window).width() >= 320) && ($(window).width() <= 359)){
	px_s1 = 230;
	px_s2 = 740;
	px_s3 = 1255;
	movimiento(px_s1, px_s2, px_s3);
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