var plugin = {
    hosts: {
        'sj.se'    : true,
        'www.sj.se': true
    },

    getStartingUrl: function (params) {
        return 'https://www.sj.se/en/my-page.html#/';
    },

    start: function (params) {
        browserAPI.log('start');
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
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 20)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log('isLoggedIn');
        if ($('button:contains("Log out"):visible').length) {
            browserAPI.log('isLoggedInd: true');
            return true;
        }
        if ($('button:contains("Log in"):visible').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        if (undefined != typeof account.properties.Name) {
            var namePart = account.properties.Name.split(' ');
            var name = $('span:first-child', '.shows-sj-user-nav').text().trim();
            return (name == namePart[0]);
        }
        return false;
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('start', function () {
            $('span:contains("Log out")').click();
        });
    },

    login: function (params) {
        browserAPI.log('login');
        if (
            typeof (params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.sj.se/en/find-change-booking.html';
            });
            return;
        }

        const form = $('form[id = "mfa-login-form"]');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        // reactjs
        provider.eval(
            "function triggerInput(selector, enteredValue) {\n" +
            "      let input = document.querySelector(selector);\n" +
            "      input.dispatchEvent(new Event('focus'));\n" +
            "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
            "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
            "      nativeInputValueSetter.call(input, enteredValue);\n" +
            "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
            "      input.dispatchEvent(inputEvent);\n" +
            "}\n" +
            "triggerInput('input#login-username', '" + params.account.login + "');\n" +
            "triggerInput('input#login-password', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function () {
            $('button[data-testid="loginButton"').click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 4000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        const error = $('p.alert-text:visible');

        if (error.length) {
            provider.setError(error.text());
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (
            typeof (params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId > 0
        ) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.sj.se/en/my-page/booked-trips.html#/';
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        setTimeout(function () {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('b:contains("Booking Number ' + confNo + '")');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form[name = "orderSearch_form"]');
        if (form.length > 0) {
            provider.eval(
                "var scope = angular.element( document.querySelector('[name=\"orderSearch_form\"]')).scope();"
                + "scope.$apply(function(){"
                + "scope.orderNumber = '" + properties.ConfNo + "';"
                + "scope.phoneEmail = '" + properties.Email + "';"
                + "});"
            );
            form.find('input[name = "orderNumber"]').val(properties.ConfNo);
            form.find('input[name = "phoneEmail"]').val(properties.Email);
            provider.setNextStep('itLoginComplete', function () {
                form.find('button[ng-click *= "submitForm"]').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
