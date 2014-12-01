$(document).ready(function(){
	$('#regPassword').keyup(function(){
		var len = $('#regPassword').val().length;
		//$('.medidor').text(len);
		if(len==0){
			$('.medidor').text('');
			$('.medidor').removeClass('red orange green');
		}

		else if(len<=5){
			$('.medidor').text('Débil');
			$('.medidor').addClass('red');
			$('.medidor').removeClass('orange green');
		}
		else if(len<=10){
			$('.medidor').text('Media');
			$('.medidor').addClass('orange');
			$('.medidor').removeClass('red green');
		}
		else{
			$('.medidor').text('Fuerte');
			$('.medidor').addClass('green');
			$('.medidor').removeClass('red orange');
		}
	});
});