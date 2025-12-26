var plugin = {

    hosts: {
        'www.usbank.com': true,
        'onlinebanking.usbank.com': true
    },

    getStartingUrl: function (params) {
        return 'https://onlinebanking.usbank.com/Auth/Login';
    },

    loadLoginForm: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function f() {
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
                        provider.complete();
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
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#ReactLoginWidgetApp').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= Logout]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        return false;
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://onlinebanking.usbank.com/Auth/LogoutConfirmation';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('#ReactLoginWidgetApp');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // form.find('input[name = "personalId"]').val(params.account.login);
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
                "triggerInput('input[name=\"Username\"]', '" + params.account.login + "');\n" +
                "triggerInput('input[name=\"Password\"]', '" + params.account.password + "');"
            );

            provider.setNextStep('checkLoginErrors', function () {
                form.find('#login-button-continue').click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 10000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        let errors = $('p.error-text__error:visible, h2#top-error-msg-single-notification--text:visible');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    }
};