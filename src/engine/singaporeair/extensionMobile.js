var plugin = {
    flightStatus:{
        url: 'https://mobile.singaporeair.com/plnext/SQmobile2/displayFlightSearchPage.action?COUNTRY_SITE=US&LANGUAGE=GB&SITE=SQSQCUST',
        match: /^(?:SQ)?\d+/i,

        start: function () {
            if(plugin.autologin.selectLang()){
                return;
            }
            var inputNumber = $('#txtFlightNumber');
            var inputDepCode = $('#txtCity');
            var form = $('#flightStatus');
            if (inputNumber.length == 1 && form.length == 1){
                inputNumber.val(params.flightNumber.replace(/SQ/gi, ''));
                inputDepCode.val(params.depCode);

                var depDateElem = $('option[value*="' + $.format.date(api.getDepDate(), 'yyyy-MM-dd') + '"]');
                if(depDateElem.length == 1){
                    $('#cmbDate').val(depDateElem.val());
                    api.setNextStep('finish', function(){
                        form.submit();
                    });
                }else{
                    api.errorDate();
                }
            }
        },

        finish: function () {
            var error = $('#dvServerError');
            if(error.length == 1)
                api.error(error.text().trim());
            else
                api.complete();
        }
    },

    autologin: {
        url:      "http://mobile.singaporeair.com/plnext/SQmobile2/MSQHome.action?COUNTRY_SITE=US&LANGUAGE=GB&SITE=SQSQCUST",
        loginUrl: "http://mobile.singaporeair.com/plnext/SQmobile2/MKFAccountLogin.action?COUNTRY_SITE=US&LANGUAGE=GB&SITE=SQSQCUST",

        selectLang: function (){
            browserAPI.log('selectLang');
            if($('#MCLangForm').length > 0){
                $('#COUNTRY').val('US');
                $('#LANGUAGE').val('GB');
                api.setNextStep('start', function (){
                    document.location.href = $('.buttonDirection a[href *= "validateCountry"]').attr('href');
                });
                return true;
            }
            return false;
        },

        start: function (){
            browserAPI.log('start');
            if(plugin.autologin.selectLang()){
                return;
            }
            var openLogin = $('#navKrisflyer > a');
            if (openLogin.length > 0) {
                provider.setNextStep('start', function(){
                    document.location.href = openLogin[0].href;
                });
            }
            if (this.isLoggedIn())
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            else
                this.toLoginPage();
        },

        isLoggedIn: function () {
            browserAPI.log('isLoggedIn');
            if($('#kflogin').length > 0){
                return false;
            }

            if($('.buttonDirection.floatR a:contains("Login")').length > 0){
                return false;
            }

            if($('.bannercustom').length > 0){
                return false;
            }

            if($('.kf_logout').length > 0){
                return true;
            }

            if($('.kfmiles').length > 0){
                return true;
            }

            if ($('button[onclick *= "logout.action"]').length > 0) {
                return true;
            }

            throw "can't determine login state";
        },

        login: function () {
            browserAPI.log('login');
            var form = $('#kflogin');
            var button = $('.buttonDirection.floatR a:contains("Login")');
            if(form.length == 1 && button.length == 1){
                form.find('#KF_NUMBER').val(params.login);
                form.find('#PIN').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    button.click();
                });
            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            browserAPI.log('isSameAccount');
            var nameEl = $('.kfusern22ame');
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Name) !== 'undefined')
                && (nameEl.length > 0)
                && (nameEl.text().toLowerCase().indexOf(params.properties.Name.toLowerCase()) !== -1)
        },

        checkLoginErrors: function () {
            browserAPI.log('checkLoginErrors');
            var error = $('#dvLoginError');
            if(error.length > 0){
                api.error(error.text().trim());
            } else {
                plugin.autologin.finish();
            }
        },

        logout: function (){
            browserAPI.log('logout');
            api.setNextStep('toLoginPage', function () {
                var logout = $('.kf_logout');
                if (logout.length > 0) {
                    document.location.href = logout.attr('href');
                } else {
                    document.location.href = 'https://mobile.singaporeair.com/plnext/SQmobile2/logout.action?COUNTRY_SITE=US&LANGUAGE=GB&SITE=SQSQCUST';
                }
            });
        },

        toLoginPage: function (){
            browserAPI.log('toLoginPage');
            api.setNextStep('login', function (){
                document.location.href = plugin.autologin.loginUrl;
            });
        },

        finish: function () {
            browserAPI.log('finish');
            api.complete();
        }
    }
};
