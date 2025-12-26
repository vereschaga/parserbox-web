var plugin = {
    flightStatus: {
        url: 'https://m.turkishairlines.com/#/flightStatus/searchFlights',
        match: /\d+/i,

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                var form = $('form[name = "searchByFlightNoForm"]:visible');
                browserAPI.log("waiting... " + start);
                if (form.length > 0) {
                    browserAPI.log("submit form");
                    // Flight Number
                    form.find('input[name = "flightNo"]').val(params.flightNumber);

                    clearInterval(start);

                    api.setNextStep('finish', function () {
                        // date
                        var date = $.format.date(api.getDepDate(), 'd MMM yyyy');
                        // angularjs
                        api.eval('var scope = angular.element("#searchByFlightNoForm").scope();' +
                            'scope.search.flightNo = "TK'+ params.flightNumber + '";' +
                            'scope.search.setDepartureDate("' + date + '");' +
                            'scope.searchFlight();'
                        );
                        setTimeout(function() {
                            plugin.flightStatus.finish();
                        }, 5000);
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
            if ($('div:contains("TK'+ params.flightNumber + '")').length > 0 || $('div:contains("TK'+ params.flightNumber.replace(/^0+/, '') + '")').length > 0)
                api.complete();
            else
                api.error("We can't find this flight");
        }
    },

    autologin: {

        url: "https://www.turkishairlines.com/en-us/miles-and-smiles/account/index.html",

        start: function (params) {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.autologin.isLoggedIn();
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.autologin.isSameAccount(params.account))
                            provider.complete();
                        else
                            plugin.autologin.logout(params);
                    }
                    else
                        plugin.autologin.login(params);
                }// if (isLoggedIn !== null)
                if (isLoggedIn === null && counter > 15) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }// if (isLoggedIn === null && counter > 10)
                counter++;
            }, 500);
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('form.signin-form, #schuldeMobileSigninButton').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('li >a:contains("My account"), h3:contains("Transfer official Miles")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            return null;
        },

        isSameAccount: function (account) {
            browserAPI.log("isSameAccount");
            // for debug only
            //browserAPI.log("account: " + JSON.stringify(account));
            return (typeof(account.properties) !== 'undefined')
                && (typeof(account.properties.AccountNumber) !== 'undefined')
                && (account.properties.AccountNumber != '')
                && $('h5 span:contains("'+account.properties.AccountNumber+'")').length;
        },

        logout: function () {
            browserAPI.log("logout");
            provider.setNextStep('start', function () {
                $('#signoutBTN').get(0).click();
            });
        },

        login: function (params) {
            browserAPI.log("login");
            util.waitFor({
                selector: 'form.signin-form:visible',
                success: function() {
                    form();
                },
                fail: function () {
                    $('#schuldeMobileSigninButton').get(0).click();
                    form();
                }
            });

            function form() {
                util.waitFor({
                    selector: 'form.signin-form:visible',
                    success: function() {
                        var form = $('form.signin-form');
                        if (form.length > 0) {
                            browserAPI.log("submitting saved credentials");
                            form.find('input#lbusername').val(params.account.login);
                            form.find('input#lbpassword').val(params.account.password);
                            provider.setNextStep('checkLoginErrors', function () {
                                form.find('a:contains("Sign in")').get(0).click();
                            });
                        }// if (form.length > 0)
                        else
                            provider.setError(util.errorMessages.loginFormNotFound);
                    },
                    fail: function() {
                        provider.setError(util.errorMessages.loginFormNotFound);
                    }
                });
            }
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var errors = $('#error-messageLightbox');
            if (errors.length > 0 && util.trim(errors.text()) !== '')
                provider.setError(errors.text());
            else if(document.location.href.indexOf('com/en-us/undefined') !== -1) {
                provider.setNextStep('finish', function () {
                    document.location.href = plugin.autologin.url;
                });
            } else
                provider.complete();
        },

        finish: function () {
            provider.complete();
        }
    }
};