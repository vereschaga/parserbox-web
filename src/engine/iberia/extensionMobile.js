var plugin = {
    flightStatus: {
        url: 'http://www.iberia.mobi/',
        match: /^(?:IB)?\d+/i,

        start: function () {
            browserAPI.log("start");
            if ((/Idioma/i).test($('.language').text()))
                this.selectLang();
            else
                this.goToPage();
        },

        selectLang: function () {
            browserAPI.log("selectLang");
            api.setNextStep('goToPage', function () {
                window.location.href = '/mobi/languages.do?language=en';
            });
        },

        goToPage: function () {
            browserAPI.log("goToPage");
            api.setNextStep('checkStatus', function () {
                window.location.href = '/mobi/obsmenu.do?menuId=MOBILEINFLLE';
            });
        },

        checkStatus: function () {
            browserAPI.log("checkStatus");
            var input = $('input[name=flightNo]');
            var form = input.parents('form[name *= InfoLleForm]:eq(0)');
            if (form.length == 1 && input.length == 1) {
                var flightNumber = params.flightNumber.replace(/IB/gi, '');
                browserAPI.log("flightNumber: " + flightNumber);
                input.val(flightNumber);

                var date = $.format.date(api.getDepDate(), 'yyyyMMdd') + '0000';
                console.log("Date: " + date + " / " + api.getDepDate());

                $('.optionGroup-a [name="fecha"]').val(date);
                api.setNextStep('finish', function () {
                    form.submit();
                });
            }
        },

        finish: function () {
            browserAPI.log("finish");
            if ($('.info-flight').length > 0) {
                api.complete();
            } else {
                api.error($('.error').text().trim());
            }
        }
    }
};