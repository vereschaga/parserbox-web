var plugin = {
    flightStatus: {
        url: 'http://www.tam.com.br/b2c/vgn/v/index.jsp?vgnextoid=e14f31a804a77310VgnVCM1000009508020aRCRD',
        match: /\d+/i,

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                var form = $('form[name = "pontualidade_voo"]');
                browserAPI.log("waiting... " + start);
                if (form.length > 0) {
                    browserAPI.log("submit form");
                    form.find('input[name = "flightNumber"]').val(params.flightNumber);

                    $('li:contains("TAM (JJ)")').click();

                    clearInterval(start);
                    // date
                    var dateVisible = $.format.date(api.getDepDate(), 'dd/MM/yy');
                    browserAPI.log("Date: " + dateVisible);
                    form.find('input[name = "date"]').val(dateVisible);

                    var date = $.format.date(api.getDepDate(), 'yyyy-M-d');
                    browserAPI.log("Date: " + date);
                    form.find('input[name = "departureDate"]').val(date);

                    api.setNextStep('finish', function () {
                        api.eval("onSubmit()");
                    });
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("can't find form");
                }
                counter++;
            }, 500);
        },

        finish: function () {
            browserAPI.log("finish");
            var error = $('div.msgerrog:visible');
            if (error.length > 0)
                api.error(error.text().trim());
            else
                api.complete();
        }
    }
};