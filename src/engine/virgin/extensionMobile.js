var plugin = {
    flightStatus: {
        url: 'http://www.virgin-atlantic.com/us/en/travel-information/flight-status.html',
        match: /^(?:VS)?\d+/i,

        start: function () {
            browserAPI.log("start");
            this.selectLang();
            this.selectPage();
            this.checkFlightStatus();
        },

        selectLang: function () {
            browserAPI.log("selectLang");
            var langSelect = $('[name=home_region]');
            if(langSelect.length == 1){
                langSelect.val('us');
                api.setNextStep('selectPage', function(){
                    HTMLFormElement.prototype.submit.call(document.forms[0]);
                });
            }
        },

        selectPage: function(){
            var statusLink = $('a:has(#flightstatus)');
            if(statusLink.length == 1){
                api.setNextStep('checkFlightStatus', function(){
                    window.location.href = statusLink.attr('href');
                });
            }
        },

        checkFlightStatus: function(){
            browserAPI.log("checkFlightStatus");
            var form = $('form#flightstatussearch');
            if (form.length > 0) {
                // fill search form
                var flightNumber = $('option[value*="' + params.flightNumber + '"]');
                if (flightNumber.length > 0)
                    form.find('select[name = "flightNumber"]').val( flightNumber.val() );
                else
                    form.find('select#route').val($('option[value*="' + params.depCode + '-' + params.arrCode + '"]').val());
                // date
                var depDateElem = $('option[value*="' + $.format.date(api.getDepDate(), 'yyyy-MM-dd') + '"]');
                if (depDateElem.length == 1) {
                    form.find('select[name = "date"]').val( depDateElem.val() );
                    api.setNextStep('checkErrors', function(){
                        //HTMLFormElement.prototype.submit.call(document.forms[0]);
                        form.find('button[type = "submit"]').click();
                    });
                }else{
                    api.errorDate();
                }
            }
        },

        checkErrors: function(){
            var title = $('.headline');
            if(title.length == 1 && title.text().match(/Flight Information/i) && !window.location.href.match(/details\.jsp/i)){
                // switch to reload friendly url
                api.setNextStep('success', function(){
                    window.location.href = 'http://mobile.virginatlantic.com/flightstatus/details.jsp;' + window.location.href.split(';')[1];
                });
            }else{
                api.error($('.error').text());
            }
        },

        success: function () {
            api.complete();
        }
    },

    autologin: {
        url: 'http://mobile.virginatlantic.com/index.jsp',

        start: function () {
            browserAPI.log("start");
            var start = setInterval(function () {
                var btn = $('a#login');
                if (btn.length > 0) {
                    if ($('a#login:contains("Log in")').length > 0) {
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
            if ($('a#login:contains("Log out")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('a#login:contains("Log in")').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return false;
            //return (typeof(params.properties) != 'undefined' &&
            //    typeof(params.properties.Number) != 'undefined' &&
            //    params.properties.Number != '' &&
            //    $('label:contains("'+ params.properties.Number +'")').length > 0);
        },

        login: function(){
            browserAPI.log("login");
            var login = setInterval(function () {
                var form = $('form[name *= "flyClubLogin"]');
                browserAPI.log("waiting...");
                if (form.length > 0) {
                    if (form.length > 0) {
                        browserAPI.log("submitting saved credentials");

                        form.find('input[name = "login_uname"]').val(params.login);
                        form.find('input[name = "login_pwd"]').val(params.pass);
                        api.setNextStep('checkLoginErrors', function(){
                            form.find('button[type = "submit"]').click();
                        });
                    } else {
                        browserAPI.log("can't find login form");
                        api.error("can't find login form");
                    }
                    clearInterval(login);
                }
            }, 500);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var errors = $('span.errorMessage');
            if (errors.length > 1)
                api.error(errors.text().trim());
            else
                this.finish();
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('start', function () {
                $('a#login:contains("Log out")').get(0).click();
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};