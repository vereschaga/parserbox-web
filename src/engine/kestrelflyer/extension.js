var plugin = {

    hosts: {'www.airmauritius.com': true},

    getStartingUrl: function (params) {
        return 'https://www.airmauritius.com/miles-statement';
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
        if ($('form#form-login-dedicated:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "Logout"]:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.filter($('label:contains("FF Number")')[0].nextSibling.nodeValue);
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Number) != 'undefined')
                && (account.properties.Number !== '')
                && number
                && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a[href *= "Logout"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form#form-login-dedicated');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "Identity"]').val(params.account.login);
        form.find('input[name = "Password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('input[value = "Login"]').click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        let errors = $('div#modal_errors_user:visible');

        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    }
};