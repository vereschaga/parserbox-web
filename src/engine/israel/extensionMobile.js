var plugin = {
    flightStatus:{
	url: 'http://www.elal.co.il/ELAL/English/FlightInfo/FlightSchedule/FlightScheduleEng.html',
    match: /^(?:LY)?\d+/i,

	start: function () {
        var date = api.getDepDate();
        var fromSelect = $('#From');
        var toSelect = $('#To');
        if(toSelect.length == 1 && fromSelect.length == 1){
            fromSelect.val(params.depCode);
            toSelect.val(params.arrCode);
            (eval('ctl00_ContentPlaceHolder_PresentationModeContainer234_OnLineFlightSchedule_DatePickerUC1_RadDatePicker1')).SetDate(date);

            api.setNextStep('selectFlight', function(){
                $('#ctl00_ContentPlaceHolder_PresentationModeContainer234_OnLineFlightSchedule_spSend img').click();
                //flightScheduleClicked_Submit('http://booking.elal.co.il/newBooking/urlDirector.do', 'All fields are mandatory','English','ctl00_ContentPlaceHolder_PresentationModeContainer234_OnLineFlightSchedule_DatePickerUC1_RadDatePicker1');
            });
        }
	},

    selectFlight: function(){
        var error = $('.WDSErrorE');
        if(error.length > 0){
            api.error(error.text());
        }else{
            api.setNextStep('finish', function(){
                $('a:contains("current flight status")').get(0).click();
            });
        }
    },

	finish: function () {
        api.complete();
	}
    }
};