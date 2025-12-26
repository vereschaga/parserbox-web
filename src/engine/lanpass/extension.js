var plugin = {

    hosts: {
        'www.latam.com': true,
        'login.latam.com': true,
        'www.latamairlines.com': true,
        'accounts.latamairlines.com': true,
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return "https://www.latamairlines.com/us/en";
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        $('#lnk-sign-in, #header__profile__lnk-sign-in')[0].click();
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
                    provider.setNextStep('login', function () {
                        plugin.loadLoginForm()
                    });
                    // plugin.login(params);
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
        if ($('#header__profile-dropdown span').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#lnk-sign-in, #header__profile__lnk-sign-in').length > 0 || $('form#form').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = $('#header__profile-dropdown span').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && account.properties.Name !== ''
            && name !== ''
            && account.properties.Name.toLowerCase().indexOf(name.toLowerCase()) !== -1);
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
             $('a[href="/en-us/logout"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId === 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://www.latamairlines.com/us/en/my-trips";
            });
            return;
        }

        const form = $('form#form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
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
            "triggerInput('input[id = \"form-input--alias\"]', '" + params.account.login + "');"
        );
        let btn = $('#primary-button');
        if (btn.length)
            btn.get(0).click();
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        util.waitFor({
            selector: 'input#form-input--password:visible',
            success: function (emailInput) {
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
                    "triggerInput('input[id = \"form-input--password\"]', '" + params.account.password + "');"
                );
                provider.setNextStep('checkLoginErrors', function () {
                    $('#primary-button').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000)
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.loginFormNotFound);
            },
            timeout: 10
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('span.Mui-error:visible:eq(0), #form-alert--error:visible p');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('itLoginComplete', function () {
                document.location.href = 'https://www.latamairlines.com/us/en/my-trips';
            });
            return;
        }

        plugin.itLoginComplete(params);
    },

    /*toItineraries: function(params) {
        browserAPI.log('toItineraries');
        var counter = 0;
        var confNo = params.account.properties.confirmationNumber;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('a[href *= "' + confNo + '"]:visible');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
            }
            if (counter > 30) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },*/

    getConfNoItinerary: function(params) {
        browserAPI.log('getConfNoItinerary');
        const properties = params.account.properties.confFields;
        let counter = 0;
        let getConfNoItinerary = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            const form = $('input#code:visible');
            if (form.length > 0) {
                clearInterval(getConfNoItinerary);
                browserAPI.log("submitting saved properties");
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
                    "triggerInput('input[id = \"code\"]', '" + properties.ConfNo + "');\n" +
                    "triggerInput('input[id = \"lastname\"]', '" + properties.LastName + "');"
                );

                provider.setNextStep('itLoginComplete', function () {
                    $('button#submit-search-code').click();
                    setTimeout(function () {
                        plugin.itLoginComplete(params);
                    }, 5000)
                });
            }
            if (counter > 30) {
                clearInterval(getConfNoItinerary);
                provider.setError(util.errorMessages.itineraryFormNotFound);
            }
            counter++;
        }, 500);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};