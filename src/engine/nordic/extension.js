var plugin = {
    hosts: {
        'www.strawberryhotels.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.strawberryhotels.com/my-page/';
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
        if ($('span:contains("Sign out")').length > 0) {
            browserAPI.log("Logged in");
            return true;
        }
        if ($('form.login-form:visible').length > 0) {
            browserAPI.log('Not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = $('button.member-number > p').text();
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.MemberNumber) != 'undefined')
                && (account.properties.MemberNumber !== '')
                && (number.length > 0)
                && (number === account.properties.MemberNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("Sign out")').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.strawberryhotels.com/nordic-choice-club/login?redirectUrl=/my-page';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form.login-form:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "username"]').val(params.account.login);
        form.find('input[name = "password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button:contains("Sign in")').get(0).click();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.sds-c-banner--error > p:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.strawberryhotels.com/my-bookings';
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        let confNo = params.account.properties.confirmationNumber;
        browserAPI.log("open #: " + confNo);
        let link = $('a[href *= "my-bookings/' + confNo + '"]');
        browserAPI.log("toItineraries: " + link.length);

        if (link.length === 0) {
            provider.setError(util.errorMessages.itineraryNotFound);
            return;
        }

        provider.setNextStep('itLoginComplete', function () {
            link.get(0).click();
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};
