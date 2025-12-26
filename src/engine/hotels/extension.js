var plugin = {

    hosts: {'www.hotels.com': true, '/\\w+\\.hotels\\.com/': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        plugin.loadLoginForm(params);
    },

    getStartingUrl: function (params) {
        return 'https://www.hotels.com/login';
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
        if ($('form[name="loginEmailForm"]:visible').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('a[href *= signout]').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp($('span#membership-number-value').text(), /(\d+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= signout]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (
            typeof(params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://www.hotels.com/profile/findbookings.html';
            });
            return;
        }

        let form = $('form[name="loginEmailForm"]:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return
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
            "triggerInput('input[id = \"loginFormEmailInput\"], input[id = \"loginFormEmailInput\"]', '" + params.account.login + "');\n"
        );

        form.find('#loginFormSubmitButton').get(0).click();
        util.waitFor({
            selector: '#passwordButton:visible',
            success: function(item){
                item.get(0).click();
                setTimeout(function () {
                    let form = $('form[name="enterPasswordForm"]:visible');
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
                        "triggerInput('input[id = \"enterPasswordFormPasswordInput\"]', '" + params.account.password + "');\n"
                    );
                    form.find('#enterPasswordFormSubmitButton').get(0).click();
                    provider.setNextStep('checkLoginErrors');
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 10000);
                }, 1000);
            },
            fail: function(){
                plugin.checkLoginErrors(params);
            },
            timeout: 10
        });



    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.uitk-field-message-error:visible, div.uitk-error-summary h3:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.hotels.com/trips';
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log('toItineraries');
        var confNo = params.account.properties.confirmationNumber;
        var link = $('a.trip-link[href *= "' + confNo + '"]');
        if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function() {
                link.get(0).click();
                return;
            });
        } else {
            plugin.itLoginComplete(params);
        }
    },

    itLoginComplete: function (params) {
        provider.complete();
    }

};