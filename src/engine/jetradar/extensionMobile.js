var plugin = {

	url: 'about:blank',
    match: /.*/i,

	start: function (pa) {
        api.setNextStep('finish', function(){
            var date = api.getDepDate();
            var dateStr = ('0' + date.getDate()).slice(-2) + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + date.getFullYear();

            window.location.href = "http://m.jetradar.com/searches/new/?" +
                "utf8=%E2%9C%93" +
                "&with_request=true" +
                "&marker=21477" +
                "&search[origin_iata]=" + params.depCode +
                "&search[origin_name]=" + params.depCode +
                "&search[destination_iata]=" + params.arrCode +
                "&search[destination_name]=" + params.arrCode +
                "&search[depart_date]=" + dateStr +
                "&search[one_way]=1" +
                "&search[adults]=1" +
                "&search[children]=0" +
                "&search[infants]=0" +
                "&search[trip_class]=0" +
                "&commit=Find";
        });
	},

    finish: function(){
        api.complete();
        $('#iphone_alert .window_close').click();

        // one-way, not working through GET-params
        $('#search_one_way').val(1);
        $('[type=submit]').click();
    }
};