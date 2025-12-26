var plugin = {

    hosts: {'www.koreanair.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return "https://www.koreanair.com/my-mileage/overview";
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout();
                } else
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
        if ($('span:contains("Available mileage"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('div.login').parent('form').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = $('ul.mileage-image__list li.-num').text();
        browserAPI.log("number: " + number.replace(/ /g, ''));
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber !== '')
            && number
            && (number.replace(/ /g, '') === account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('button:contains("Log out")').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('div.login').parent('form');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        let emailInput = form.find('ke-text-input[formcontrolname = "userId"] input');
        emailInput.val(params.account.login);
        util.sendEvent(emailInput.get(0), 'input');
        let passwordInput = form.find('ke-password-input[formcontrolname = "password"] input');
        passwordInput.val(params.account.password);
        util.sendEvent(passwordInput.get(0), 'input');
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button.login__submit-act').click();

            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.alert:visible, p[id *= "error-message-"]:visible');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            return provider.setNextStep('itLoginComplete', function () {
                document.location.href = 'https://www.koreanair.com/reservation/list';
            });
        }

        provider.complete();
    },

    /*
    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        setTimeout(function () {
            let confNo = params.account.properties.confirmationNumber;
            let link = $('a[href*="reservationCode"][href*="' + confNo + '"]');
            if (link.length === 0) {
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }
            link.get(0).click();
            provider.setNextStep('itLoginComplete');
        }, 2000)
    },
    */

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};