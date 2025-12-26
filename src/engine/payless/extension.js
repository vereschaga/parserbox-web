var plugin = {

    hosts: {
        'paylesscar.com': true,
        'www.paylesscar.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.paylesscar.com/en/profile/dashboard/profile';
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log('start');
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout();
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log('isLoggedIn');
        if ($('button[ng-click="vm.getLogout()"]:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('.login form#loginForm').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        if ('undefined' !== typeof account.properties && 'undefined' !== typeof account.properties.PerksID)
            return $('h3:contains("' + account.properties.PerksID + '")').length;

        return false;
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('startFromCashback', function () {
            $('button[ng-click="vm.getLogout()"]').click();
        });
    },

    login: function (params) {
        browserAPI.log('login');
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItineraryPreload', function() {
                document.location.href = 'https://www.paylesscar.com/en/reservation/view-modify-cancel';
            });
            return;
        }
        var form = $('.login form#loginForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            var captcha = form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]:visible');
            if (captcha.length > 0) {
                provider.setNextStep('submitLoginForm', function () {
                    // angularjs
                    // provider.eval("angular.reloadWithDebugInfo();");
                    provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
                    var unixtime = Math.round(new Date().getTime() / 1000);
                    document.location.href = 'https://www.paylesscar.com/en/profile/login?t=' + unixtime;
                });
            }// if (captcha && captcha.length > 0)
            else {
                browserAPI.log("captcha is not found");
                $('input#username', form).val(params.account.login);
                $('input#password', form).val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    $('#res-login-profile', form).trigger('click');
                });
            }
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    submitLoginForm: function (params) {
        browserAPI.log("submitLoginForm");
        var form = $('.login form#loginForm');
        if (form.length > 0) {
            $('input#username', form).val(params.account.login);
            $('input#password', form).val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                browserAPI.log("submitting saved credentials");
                provider.reCaptchaMessage();
                // angularjs
                provider.eval("" +
                    "var scope = angular.element(document.querySelector('#loginModal form[name=\"loginForm\"]')).scope();"
                    + "scope.vm.loginModel.uName = '" + params.account.login + "';"
                    + "scope.vm.loginModel.password = '" + params.account.password + "';"
                );
                var submitButton = $('#loginModal form#loginForm').find('#res-login-profile');
                var fakeButton = submitButton.clone();
                form.append(fakeButton);
                form.find('#res-login-profile').hide();
                fakeButton.show();
                fakeButton.unbind('click mousedown mouseup tap tapend');
                fakeButton.bind('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    browserAPI.log("captcha entered by user");
                    provider.setNextStep('checkLoginErrors', function () {
                        submitButton.get(0).click();
                    });
                });
                browserAPI.log("waiting...");
                var counter = 0;
                var login = setInterval(function () {
                    browserAPI.log("waiting... " + counter);
                    if (counter > 120) {
                        clearInterval(login);
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }
                    counter++;
                }, 500);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        var error = $('span.mainErrorText:visible');
        if (error.length && '' !== util.trim(error.text()))
            provider.setError(error.text());
        else
            provider.complete();
    },

    getConfNoItineraryPreload: function (params) {
        browserAPI.log("getConfNoItineraryPreload");
        provider.setNextStep('getConfNoItinerary', function () {
            // angularjs
            // provider.eval("angular.reloadWithDebugInfo();");
            provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
            var unixtime = Math.round(new Date().getTime() / 1000);
            document.location.href = 'https://www.paylesscar.com/en/reservation/view-modify-cancel?t=' + unixtime;
        });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form#res-viewModifyForm');
        if (form.length > 0) {
            provider.setNextStep('itLoginComplete', function() {
                provider.eval("" +
                    "var scope = angular.element(document.querySelector('form[id=\"res-viewModifyForm\"]')).scope();"
                    + "scope.vm.lookupModel.confirmationNumber = '" + properties.ConfNo + "';"
                    + "scope.vm.lookupModel.lastName = '" + properties.LastName + "';"
                    + "scope.vm.CNValidation.validationSuccess();"
                );
                provider.setTimeout(function() {
                    var error = $('span.mainErrorText:visible');
                    if (error.length > 0)
                        provider.setError([error.text(), util.errorCodes.providerError]);
                }, 7000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
