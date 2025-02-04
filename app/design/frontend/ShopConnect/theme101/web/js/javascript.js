


//search js

  $("#btnsearch").click(function(){
  $(".input_bar").toggleClass("header-hide");
  $('.input_bar').css('height' , '90px')		;
 $("#search_header").toggleClass("show")
 	
 $("#closed").toggleClass("show")
		
  	
  });
	
	$("#closed").click(function(){
		$('.input_bar').css('height' , 0);
		
		 $("#search_header").toggleClass("show")
 	
 $("#closed").toggleClass("show")
	});


//    
//acordin js footer
$(function() {
		var Accordion = function(el, multiple) {
				this.el = el || {};
				this.multiple = multiple || false;

				var links = this.el.find('.article-title');
				links.on('click', {
						el: this.el,
						multiple: this.multiple
				}, this.dropdown)
		}

		Accordion.prototype.dropdown = function(e) {
				var $el = e.data.el;
				$this = $(this),
						$next = $this.next();

				$next.slideToggle();
				$this.parent().toggleClass('open');

				if (!e.data.multiple) {
						$el.find('.accordion-content').not($next).slideUp().parent().removeClass('open');
				};
		}
		var accordion = new Accordion($('.accordion-container'), false);
});

		
$(document).on('click', function (event) {
  if (!$(event.target).closest('#accordion').length) {
    $this.parent().toggleClass('open');
  }
});


//listing filter js

$(".find_match_btn").click(function(){
  $(".match_filter_detail").slideToggle("slow", function() {
  $(".find_match_btn").toggleClass("arrow_up")
  });

	
});


//header language + socail js

	 $(".language_list").click(function(){
     $(".drop_down").toggle();
		 
	 });
	
	




	 $(".share_icon").click(function(){
     $(".drop_down1").toggle();
		 
	 });
	



$('#check-tick').click(function(){
   $('.read_paragraph').slideToggle(1000);                
                      
});
