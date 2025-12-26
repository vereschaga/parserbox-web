var plugin = {

    flightStatus: {
        url: 'https://mobile.hawaiianairlines.com/',
        match: /^(?:HA)?\d+/i,
        reload: true,

        start: function () {
            this.goToStatusPage();
            this.submitForm();
            this.finish();
        },

        goToStatusPage: function(){
            var lb = setInterval(function () {
                var linkButton = $("#btnFlightStsevent_");
                if (linkButton.length == 1) {
                    linkButton.click();
                    clearInterval(lb);
                }
            }, 500);
        },

        submitForm: function(){
            var fn = setInterval(function () {
                var input = $("#txtFlightNo");
                if (input.length == 1) {
                    clearInterval(fn);
                    input.val(params.flightNumber.replace(/HA/i, ''));

                    var dateInput = $('#comboDate');
                    var depDateElem = dateInput.find('option:contains("' + $.format.date(api.getDepDate(), 'MM/dd/yyyy') + '")');
                    if(depDateElem.length == 1){
                        dateInput.val(depDateElem.val());
                        api.setNextStep('finish', function(){
                            $('#btnFindFlightevent_').click();
                        });
                    }else{
                        api.errorDate();
                    }
                }
            }, 500);
        },

        finish: function () {
            var counter = 0;
            var lbl = setInterval(function () {
                var label = $('#frmFlightStatusDetails\\.lblFlightNo');
                if (label.length == 1) {
                    api.complete();
                    clearInterval(lbl);
                }
                if(counter > 10){
                    api.error('unable to find flight');
                    clearInterval(lbl);
                }
                counter++;
            }, 500);
        }
    },

    autologin: {
        cashbackLinkMobile : false,
        getStartingUrl: function (params) {
            return 'https://mobile.hawaiianairlines.com/';
        },

        start: function () {
            browserAPI.log("start");
            var start = setInterval(function () {
                var btn = $('a:contains("Sign In")');
                if ($('form[action *= "/Account/Login"]').length > 0
                    || $('a:contains("Sign Out")').length > 0 || btn.length > 0) {
                    if (btn.length > 0) {
                        api.setNextStep('start2', function(){
                            btn.get(0).click();
                        });
                    }
                    clearInterval(start);
                    plugin.autologin.start2();
                }
            }, 500);
        },

        start2: function () {
            browserAPI.log("start2");
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            }
            else
                this.login();
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('form[action *= "/Account/Login"]').length > 0 || $('a:contains("Sign In")').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            if ($('a:contains("Sign Out")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return (typeof(params.properties) != 'undefined' &&
                typeof(params.properties.AccountNumber) != 'undefined' &&
                params.properties.AccountNumber != '' &&
                $('p:contains("'+ params.properties.AccountNumber.replace(/\s*/ig, '') +'")').length > 0);
        },

        login: function () {
            browserAPI.log("login");
            var login = setInterval(function () {
                var form = $('form[action *= "/Account/Login"]');
                browserAPI.log("waiting...");
                if (form.length > 0) {
                    if (form.length > 0) {
                        browserAPI.log("submitting saved credentials");
                        form.find('input[name = "UserName"]').val(params.login);
                        form.find('input[name = "Password"]').val(params.pass);

                        api.setNextStep('checkLoginErrors', function () {
                            form.find('input[id = "submit"]').click();
                        });

                        setTimeout(function() {
                            plugin.autologin.checkLoginErrors();
                        }, 2000)
                    } else {
                        browserAPI.log("can't find login form");
                        api.error("can't find login form");
                    }
                    clearInterval(login);
                }
            }, 500);
        },

        logout: function () {
            browserAPI.log("logout");
            $('a:contains("Sign Out")').get(0).click();
            var logout = setInterval(function () {
                if ($('a:contains("Sign In")').length > 0) {
                    clearInterval(logout);
                    plugin.autologin.start();
                }
            }, 500);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('div.errorTxt');
            if (error.length > 0 && error.text() != "")
                api.error(error.text());
            else
                this.finish();
        },

        finish: function(){
            api.complete();
        }
    }
};
