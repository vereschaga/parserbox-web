var plugin = {
    // keepTabOpen: true,
    hosts: {
        'melia.com'     : true,
        'www.melia.com' : true,
        'www1.melia.com': true,
        'www3.melia.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.melia.com/login';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else {
                    $('#access').click();
                    plugin.login(params);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#access').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const name = $('p[class *= "user-name"]').text();
        browserAPI.log("name: " + name);
        return (
            (typeof(account.properties) !== 'undefined') &&
            (typeof(account.properties.Name) !== 'undefined') &&
            (account.properties.Name !== '') &&
            name &&
            name.toLowerCase() === account.properties.Name.toLowerCase()
        );
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= "logout"]').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form:has(input#user)');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('#user').val(params.account.login);
        form.find('#login').click();

        setTimeout(function () {
            $('#password').val(params.account.password);
            $('#login').click();

            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    plugin.checkLoginErrors(params)
                }, 7000);
            });
        }, 2000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('p[class *= "error"]:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.melia.com/en/meliarewards/my-bookings/upcoming-reservations';
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        setTimeout(function () {
            const confNo = params.account.properties.confirmationNumber;
            const link = $('*[class *= "c-hotel-card"]:has(dd:contains("' + confNo + '")) button:contains("See detail")');

            if (link.length === 0) {
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }// if (link.length === 0)

            provider.setNextStep('itLoginComplete', function () {
                link.click();
            });
        }, 2000);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
