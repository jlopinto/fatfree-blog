$(function(){
	
	var modals = {};
	
	modals.yesno = $('#yesno');
	
	modals.yesno.find('.btn-primary').eq(0).on('click',function(e){
		
		e.preventDefault();
		
		var self = $(this), href = self.attr('href');
		
		modals.yesno.modal('hide');
		
		window.setTimeout(function(){
			self.attr('href', 'unset');
			window.location = href;	
		}, $.fx.speeds._default);
			
	});
	
	modals.yesno.on('hidden', function(){
		
		var self = $(this);
		var yesBtn;
		
		yesBtn = self.find('.btn-primary').eq(0);
		self.find('.modal-label').eq(0).empty();
	});
	
	$('[data-toggle="yesno"]').on('click', function(e){
		
		var self = $(this);
		modals.yesno.modal('show');
		modals.yesno.find('.btn-primary').eq(0).attr('href', self.attr('href'));
		modals.yesno.find('.modal-label').eq(0).text(self.attr('data-question'));
		
		return false;
	});
})