var plugin = {

    hosts: {'www.ghadiscovery.com': true},

    getStartingUrl: function (params) {
        return "https://www.ghadiscovery.com/member/dashboard";
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('div.w-full form').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('span:contains("Member nº")').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp($('span:contains("Member nº")').text(), /\º\s*([\d]+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && number
            && number === account.properties.Number);
    },

    logout: function (params) {
        browserAPI.log("logout");
        $('a:contains("Log out")').get(0).click();
        setTimeout(function() {
            plugin.start(params);
        }, 3000);
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('div.w-full form');

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
            "triggerInput('input.tid-inputUserName', '" + params.account.login + "');\n" +
            "triggerInput('input.tid-inputUserPass', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function () {
            form.find('button.tid-signInButton').click();
            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 7000);
        });
    },

    checkLoginErrors:function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('span.text-danger:visible');

        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    }
};
