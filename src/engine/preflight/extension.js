var plugin = {

    hosts: {'www.preflightairportparking.com': true},

    getStartingUrl: function (params) {
        return 'https://www.preflightairportparking.com/members/AccountInfo.aspx';
    },

    start: function(params) {
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
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 1000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form:has(input[placeholder="Enter account email"])').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('div.welcome-title').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const name = util.findRegExp( $('div.welcome-title').text(), /,\s*(.+)/ig);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        $('a:contains("Log out")').get(0).click();

        setTimeout(function () {
            plugin.start(params);
        }, 3000)
    },

    login: function (params) {
        browserAPI.log("login");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://www.preflightairportparking.com/Reservation-Edit.aspx';
            });
            return;
        }

        const form = $('form:has(input[placeholder="Enter account email"])');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // angularjs
        provider.eval(
            "function triggerInput(enteredName, enteredValue) {\n" +
            "      const input = document.querySelector(enteredName);\n" +
            "      var createEvent = function(name) {\n" +
            "            var event = document.createEvent('Event');\n" +
            "            event.initEvent(name, true, true);\n" +
            "            return event;\n" +
            "      }\n" +
            "      input.dispatchEvent(createEvent('focus'));\n" +
            "      input.value = enteredValue;\n" +
            "      input.dispatchEvent(createEvent('change'));\n" +
            "      input.dispatchEvent(createEvent('input'));\n" +
            "      input.dispatchEvent(createEvent('blur'));\n" +
            "}\n" +
            "triggerInput('input[formcontrolname=\"emailOrCardNum\"]', '" + params.account.login + "');\n" +
            "triggerInput('input[formcontrolname=\"password\"]', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function () {
            form.find('button:contains("Log In")').click();

            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.invalid-feedback:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.preflightairportparking.com/site/account/reservation-history';
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        setTimeout(function () {
            const confNo = params.account.properties.confirmationNumber;
            const link = $('td:contains("' + confNo + '")').next('td').find('a:contains("View Details")');

            if (link.length === 0) {
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }

            provider.setNextStep('itLoginComplete', function () {
                link.get(0).click();
            });
        }, 500);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        const properties = params.account.properties.confFields;
        const form = $('.main-div:has(input[formcontrolname="confirmationNum"])');

        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return;
        }

        // angularjs
        provider.eval(
            "function triggerInput(enteredName, enteredValue) {\n" +
            "      const input = document.querySelector(enteredName);\n" +
            "      var createEvent = function(name) {\n" +
            "            var event = document.createEvent('Event');\n" +
            "            event.initEvent(name, true, true);\n" +
            "            return event;\n" +
            "      }\n" +
            "      input.dispatchEvent(createEvent('focus'));\n" +
            "      input.value = enteredValue;\n" +
            "      input.dispatchEvent(createEvent('change'));\n" +
            "      input.dispatchEvent(createEvent('input'));\n" +
            "      input.dispatchEvent(createEvent('blur'));\n" +
            "}\n" +
            "triggerInput('input[formcontrolname=\"confirmationNum\"]', '" + properties.ConfNo + "');\n" +
            "triggerInput('input[formcontrolname=\"lastName\"]', '" + properties.LastName + "');"
        );

        provider.setNextStep('itLoginComplete', function() {
            form.find('button:contains("Find My Reservation")').click();
        });
    },
};