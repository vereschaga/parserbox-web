var plugin = {
    flightStatus: {
        url: 'http://mobile.lufthansa.com/arrdep/arrdep.do',
        match: /^(?:LH)?\d+/i,

        start: function () {
            this.selectLang();
            this.closeAppInstallPopup();
            this.checkFlightStatus();
        },

        checkFlightStatus: function(){
            var input = $('#flightB');
            if(input.length == 1){
                var flightNumber = params.flightNumber.replace(/LH/gi, '');
                input.val(flightNumber);
                document.forms[1].flight.value = flightNumber;

                var dateInput = $('#fl_dateB');
                var depDateElem = dateInput.find('option[value*="' + $.format.date(api.getDepDate(), 'yyyyMMdd') + '"]');
                if(depDateElem.length == 1){
                    dateInput.val(depDateElem.val());
                    api.setNextStep('finish',function(){
                        $('form:has(#fl_dateB)').submit();
                    });
                }else{
                    api.errorDate();
                }
            }
        },

        selectLang: function(){
            var form = $('#corFormId');
            var countrySelect = $('#country');
            if(form.length == 1 && countrySelect.length == 1){
                countrySelect.val('US');
                api.setNextStep('start', function(){
                    form.submit();
                });
            }
        },

        closeAppInstallPopup: function(){
            var checkbox = $('#dontshow');
            var closeButon  = $('.overlay_closeicon');
            if(checkbox.length == 1 && closeButon.length == 1){
                checkbox.prop('checked', true);
                closeButon.click();
                api.setNextStep('checkFlightStatus', function(){
                    $('.flightstatus').click();
                });
            }
        },

        finish: function () {
            if($('.result').length > 0){
                api.complete();
            }else{
                api.error($('.feedback_neg').text());
            }
        }
    },

    autologin: {
        url: "https://mobile.lufthansa.com/rs/account-statement",
        
        start: function(){
            if (this.isLoggedIn())
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            else
                this.login();
        },

        login: function () {
            var form = $('form[action*="/rs/account-statement/login"]');
            var submitButton = form.find('button[type=submit]');
            if (submitButton.length == 1) {
                form.find('input[name="user"]').val(params.login);
               form.find('input[name="pass"]').val(params.pass);
                api.setNextStep('checkLoginErrors', function(){
                    submitButton.click();
                });

            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            return (typeof(params.properties) != 'undefined' &&
                typeof(params.properties.Number) != 'undefined' &&
                params.properties.Number != '' &&
            $.trim($('.content-page p').eq(0).clone().find('b').remove().end().text()).replace(/ /g,'') == params.properties.Number);
        },

        isLoggedIn: function () {
            if($('input[name="user"]').length > 0){
                return false;
            }
            if($('input[value*="Login"]').length > 0){
                return false;
            }
            if($('a#service_account').length > 0){
                return true;
            }
            if($('a[href*="logout"]').length > 0){
                return true;
            }

            api.error("can't determine login state");
            return false;
        },

        checkLoginErrors: function () {
            var error = $('.error');

            if (error.length > 0) {
                api.error($.trim(error.text()));
            } else {
                plugin.autologin.finish();
            }
        },

        logout: function () {
            api.setNextStep('toLoginPage', function () {
                $('a[href*="logout"]').get(0).click();
            });
        },

        toLoginPage: function(){
            var url = this.url;
            api.setNextStep('login', function(){
               document.location.href = url;
            });
        },

        finish: function () {
            api.complete();
        }
    }
};