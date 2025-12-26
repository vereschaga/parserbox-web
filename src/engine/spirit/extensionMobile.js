var plugin = {
    flightStatus:{
	url: 'http://www.spirit.com/FlightStatus.aspx',
    match: /^(?:NK)?\d+/i,
    reload: true,

	start: function () {
        var input = $('#FlightStatusBISearchControl_TextBoxFlightNumber');
        var form = $('#SkySales');
        var button = $('#FlightStatusBISearchControl_LinkButtonFindFlightNumber');
        if (input.length == 1 && form.length == 1){
            $('#trackByFlightTitle').click();
            $("input#searchBy").val("flight");
            input.val(params.flightNumber.replace(/NK/gi, ''));

            var depDateElem = $('.by_flight input[value*="' + $.format.date(api.getDepDate(), 'yyyyMMdd') + '"]');
            if(depDateElem.length == 1){
                depDateElem.click();
                api.setNextStep('finish', function(){
                    window.location.href = button.attr('href');
                });
                this.finish();
            }else{
                api.errorDate();
            }
        }else{
            api.error("Can't find login form");
        }
	},

	finish: function () {
        var counter = 0;
        var int = setInterval(function(){
            var error = $('.warning_msg p strong').text();
            if(error){
                clearInterval(int);
                api.error(error.trim());
            }
            if($('.standard').length > 0 && counter > 5){
                $('.more_info_area a').click();
                clearInterval(int);
                api.complete();
            }

            if(counter > 10){
                clearInterval(int);
                api.error();
            }
            counter++;
        }, 500);
	}
    }
};