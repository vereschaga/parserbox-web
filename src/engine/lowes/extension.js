var plugin = {

    hosts: {'www.lowes.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.lowes.com/mylowes/profile/mylowescard';
    },

    loadLoginForm: function (param) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(param);
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
        if ($('input[name = "user-password"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button[data-linkid="MyLowes"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // let number = $('div:has(h2:contains("MyLowe\'s Card Number"))').find('span.art-pref-manageMLC-textvalue-AccountNumber-0').text();
        let number = $('h4[color="text_primary"]:eq(0)').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && number
            && (number == account.properties.CardNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        $('button[data-linkid="MyLowes"]').get(0).click();
        provider.setNextStep('loadLoginForm', function () {
            var signOut = $('button[data-linkid="Sign Out"]');
            if (signOut.length > 0) {
                $('button[data-linkid="Sign Out"]').get(0).click();
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[method="post"]');
        if (form.length > 0) {
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
                "triggerInput('#email', '" + params.account.login + "');\n" +
                "triggerInput('#user-password', '" + params.account.password + "');"
            );
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button:contains("Sign In")').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $("div[role='alert']:visible").find("span:eq(0):visible");
        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
            return;
        }
        plugin.loginComplete();
    },

    loginComplete: function () {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};