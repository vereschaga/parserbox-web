var plugin = {

    hosts: {'www.harrods.com': true, 'secure.harrods.com': true},

    getStartingUrl: function (params) {
        return 'https://www.harrods.com/en-gb/account';
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
        if ($('form[data-test="loginForm-container"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button[data-test="account-logout"]:visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        var number = $('span:contains("Rewards") + span').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && number
            && (number == account.properties.CardNumber));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            $('button[data-test="account-logout"]:visible').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[data-test="loginForm-container"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // form.find('input[name = "email"]').val(params.account.login);
            // form.find('input[name = "password"]').val(params.account.password);

            // reactjs
            provider.eval(
                "var setValue = function (id, value) {" +
                "let input = document.querySelector('input[id = ' + id + ']');" +
                "let lastValue = input.value;" +
                "input.value = value;" +
                "let event = new Event('input', { bubbles: true });" +
                "event.simulated = true;" +
                "let tracker = input._valueTracker;" +
                "if (tracker) {" +
                "   tracker.setValue(lastValue);" +
                "}" +
                "input.dispatchEvent(event);" +
                "};" +
                "setValue('loginForm-email', '" + params.account.login + "');" +
                "setValue('loginForm-password', '" + params.account.password + "');"
            );

            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[data-test="loginForm-submitButton"]').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        var errors = $('span[data-test="input-error"]:visible:eq(0), div[data-test="notification-alert"]:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }
};