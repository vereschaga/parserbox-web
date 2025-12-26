var plugin = {
    flightStatus: {
        url: 'https://mobile.emirates.com/english/plan_book/flight_status/flightSearch.xhtml',
        match: /^(?:EK)?\d+/i,

        start: function(){
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                var form = $('form#instantSearchForm');
                browserAPI.log("waiting... " + start);
                if (form.length > 0) {
                    form.find('input[name = "flightNumber"]').val(params.flightNumber.replace(/EK/gi, ''));
                    browserAPI.log("submit form");
                    clearInterval(start);
                    api.setNextStep('finish', function () {
                        form.find('input#fls_fltSrch_instantSearchBtn').click();
                    });
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("can't find form");
                }
                counter++;
            }, 500);
        },

        finish: function() {
            browserAPI.log("finish");
            var errors = $('div.error-container');
            if (errors.length > 0)
                api.error(errors.text().trim());
            else
                api.complete();
        }
    },

    autologin: {
        url: "https://mobile.emirates.com/english/myaccountDetails.xhtml",

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + start);
                if ($('form[name = "LoginForm"]').length > 0 || $('a[id *= logOut]').length > 0) {
                    clearInterval(start);
                    plugin.autologin.start2();
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("Can't determine state");
                }
                counter++;
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
            if ($('a[id *= logOut]').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[name = "LoginForm"]').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        login: function () {
            browserAPI.log("login");
            var counter = 0;
            var login = setInterval(function () {
                var form = $('form[name = "LoginForm"]');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "userName"]').val(params.login);
                    form.find('input[name = "password"]').val(params.pass);

                    clearInterval(login);

                    api.setNextStep('checkLoginErrors', function () {
                        form.find('input[id = "myAc_login_loginButton"]').click();
                    });
                }
                if (counter > 10) {
                    clearInterval(login);
                    browserAPI.log("can't find login form");
                    api.error("can't find login form");
                }
                counter++;
            }, 500);
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            var number = $('span#skywardsNumber').text();
            browserAPI.log(params.properties.SkywardsNo);
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.SkywardsNo) !== 'undefined')
                && ($('p:contains("' + params.properties.SkywardsNo + '")').length > 0);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var counter = 0;
            var checkLoginErrors = setInterval(function () {
                browserAPI.log("waiting... " + checkLoginErrors);
                var error = $('div.error-container');
                if (error.length > 0) {
                    clearInterval(checkLoginErrors);
                    api.error(error.text().trim());
                }
                if (counter > 2) {
                    clearInterval(checkLoginErrors);
                    plugin.autologin.loadLoginForm('finish');
                }
                counter++;
            }, 500);
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('loadLoginForm', function () {
                document.location.href = 'https://mobile.emirates.com/english/myAccountLogout.xhtml';
            });
        },

        loadLoginForm: function(next) {
            if (next != 'finish')
                next = 'start';

            api.setNextStep(next, function () {
                document.location.href = plugin.autologin.url;
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};