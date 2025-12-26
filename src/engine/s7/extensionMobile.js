var plugin = {
    flightStatus:{
	url: 'about:blank',
    match: /^(?:S7)?\d+/i,

	start: function () {
        api.setNextStep('finish', function(){
            var date = api.getDepDate();
            var dateStr = ('0' + date.getDate()).slice(-2) + ('0' + (date.getMonth() + 1)).slice(-2)+(date.getFullYear() + '').slice(-2);
            window.location.href = 'http://myb.s7.ru/getFleetPageFlight.action?request_locale=en&date=' + dateStr + '&flight=' + params.flightNumber.replace(/S7/gi);
        });
	},

	finish: function () {
        if($('.timing').length > 0)
		    api.complete();
        else{
            api.error($('.error').text().trim());
        }
	}
    }
};