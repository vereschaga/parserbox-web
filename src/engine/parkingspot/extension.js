var plugin = {

    hosts: {'www.theparkingspot.com': true},

    getStartingUrl: function (params) {
        return 'https://www.theparkingspot.com/account#/dashboard';
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
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('span:contains("Log Out"):visible, a:contains("Log Out"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('a:contains("Sign In"):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        const number = util.findRegExp( $('.account__header__user__membership:contains("Card number:")').find('span').text(), /^(\d+)$/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.MemberID) != 'undefined')
            && (account.properties.MemberID != '')
            && (number == account.properties.MemberID));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
             $('span:contains("Log Out"):visible, a:contains("Log Out"):visible').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        // open login form
        $('a:contains("Sign In"):visible').get(0).click();
        const form = $('form input[formcontrolname="login"]').closest('form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[formcontrolname="login"]').val(params.account.login);
        form.find('input[formcontrolname="password"]').val(params.account.password);
        // refs #11326
        util.sendEvent(form.find('input[formcontrolname="login"]').get(0), 'input');
        util.sendEvent(form.find('input[formcontrolname="password"]').get(0), 'input');
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button[type="submit"]:contains("Sign In")').click();
            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 3000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.tps-error-block:visible');

        if (errors.length === 0) {
            errors = $('form div.color-error:visible');
        }

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.theparkingspot.com/account#/my-reservations';
                setTimeout(function() {
                    plugin.toItineraries(params);
                }, 1000)
            });
            return;
        }

        plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        provider.setNextStep('itLoginComplete', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },


    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        setTimeout(function () {
            const confNo = params.account.properties.confirmationNumber;
            const link = $('a[href*="' + confNo + '"]:contains("View details")');

            if (link.length === 0) {
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }// if (link.length === 0)

            provider.setNextStep('itLoginComplete', function () {
                link.get(0).click();
            });
        }, 2000);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};