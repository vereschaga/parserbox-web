var plugin = {

    hosts: {'www.nationalcar.com': true, '/\\w+\\.nationalcar\\.com/': true},

    getStartingUrl: function (params) {
        return 'https://www.nationalcar.com/en/profile.html';
    },

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
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
        browserAPI.log("isLoggedIn");
        if ($('p.profile__tier-status-account-id').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form.sign-in-form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp($('p.profile__tier-status-account-id').text(), /\d+/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number) != '')
            && (number.trim() == account.properties.Number);
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('#login a').get(0).click();
            setTimeout(function () {
                $('div.profile-nav-modal__sign-out button').get(0).click();
            }, 2000);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://www.nationalcar.com/en/reserve/view-modify-cancel.html";
            });
            return;
        }
        var form = $('form.sign-in-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // form.find('input[id *= "text-"]').val(params.account.login);
            // util.sendEvent(form.find('input[id *= "text-"]').get(0), 'input');
            // form.find('input[id *= "password-"]').val(params.account.password);
            // util.sendEvent(form.find('input[id *= "password-"]').get(0), 'input');

            // reactjs
            provider.eval(
                "var FindReact = function (dom) {" +
                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                "        return dom[key];" +
                "    }" +
                "    return null;" +
                "};" +
                "FindReact(document.querySelector('input[id *= \"text\"]')).onChange({currentTarget:{value:'" + params.account.login + "'}, isDefaultPrevented:function(){}});" +
                "FindReact(document.querySelector('input[id *= \"password-\"]')).onChange({currentTarget:{value:'" + params.account.password + "'}, isDefaultPrevented:function(){}});"
            );

            setTimeout(function () {
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button.btn[type *= "submit"]').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 5000);
                });
            }, 1000);
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.error-description:visible');
        if (errors.length > 0 && util.filter(errors.text()) != '')
            provider.setError(util.filter(errors.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.nationalcar.com/en/profile.html#/upcoming-trips';
            });
        }
        else
            provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var counter = 0;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('strong:contains("' + confNo + '")').parents('div.trip-summary').find('button[type="button"]:eq(0)');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
            }// if (isLoggedIn === null && counter > 10)
            if (counter > 15) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('div.find-reservation-form form');
        if (form.length > 0) {
            form.find('input[id *= "findReservation.firstName-"]').val(properties.FirstName);
            util.sendEvent(form.find('input[id *= "findReservation.firstName-"]').get(0), 'input');
            form.find('input[id *= "findReservation.lastName-"]').val(properties.LastName);
            util.sendEvent(form.find('input[id *= "findReservation.lastName-"]').get(0), 'input');
            form.find('input[id *= "findReservation.confirmationNumber-"]').val(properties.ConfNo);
            util.sendEvent(form.find('input[id *= "findReservation.confirmationNumber-"]').get(0), 'input');
            provider.setNextStep('itLoginComplete', function () {
                form.find('button.btn[type *= "submit"]').get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
