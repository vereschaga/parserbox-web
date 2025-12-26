var plugin = {
    hosts: {
        'secure.opentable.com': true,
        'my.opentable.com': true,
        'www.opentable.com': true,
        'secure.opentable.co.uk': true,
        'my.opentable.co.uk': true,
        'www.opentable.co.uk': true,
        'secure.opentable.ca': true,
        'my.opentable.ca': true,
        'www.opentable.ca': true
    },

    getStartingUrl: function (params) {
        if (params.account.login2 === 'CA')
            return 'https://www.opentable.ca/my/Profile';
        else if (params.account.login2 === 'UK')
            return 'https://www.opentable.co.uk/my/Profile';

        return 'https://www.opentable.com/my/Profile';
    },

    getFocusTab: function (account, params) {
        return true;
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
                        provider.complete();
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
        }, 1000);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('button[data-test="continue-with-email-button"]:visible').length > 0
            || $('form input[data-test="email-input"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (
            $('a#no-global_nav_logout').length > 0
            || $('button:contains("Sign out"), a:contains("Account Details")').length > 0
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const name = $('[data-test="page-header"] > div > h1').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            if ($('a#no-global_nav_logout').length)
                $('a#no-global_nav_logout').get(0).click();
            else {
                $('[data-test="header-user-menu"]').click();
                setTimeout(function () {
                    $('button:contains("Sign out")').click();
                }, 500)
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const btnEmail = $('button[data-test="continue-with-email-button"]:visible');
        if (btnEmail.length > 0) {
            btnEmail.click();
        }

        setTimeout(function () {
            const form = $('form input[data-test="email-input"]').closest('form');

            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }// if (form.length > 0)

            browserAPI.log("submitting saved credentials");
            // form.find('input[id = "email"]').val(params.account.login);
            // util.sendEvent(form.find('input[id = "email"]').get(0), 'input');

            // reactjs
            provider.eval(
                "function triggerInput(enteredName, enteredValue) {\n" +
                "  const input = document.getElementById(enteredName);\n" +
                "  const lastValue = input.value;\n" +
                "  input.value = enteredValue;\n" +
                "  const event = new Event(\"input\", { bubbles: true });\n" +
                "  const tracker = input._valueTracker;\n" +
                "  if (tracker) {\n" +
                "    tracker.setValue(lastValue);\n" +
                "  }\n" +
                "  input.dispatchEvent(event);\n" +
                "}\n" +
                "triggerInput('email', '" + params.account.login + "');"
            );

            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[data-test="continue-button"]').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 4000)
            });
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.validation-summary-errors ul > li:eq(0):visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            plugin.toItineraries(params);
            return;
        }

        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        setTimeout(function () {
            const confNo = params.account.properties.confirmationNumber;
            const link = $('h3:contains("Upcoming Reservations")').closest('.upcoming-reservations').find('a[href *= "confnumber=' + confNo + '&"]');

            if (link.length === 0) {
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }

            provider.setNextStep('itLoginComplete', function () {
                link.get(0).click();
            });
        }, 2500);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
