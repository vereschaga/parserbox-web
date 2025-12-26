var plugin = {

    hosts: {'azimut.flysmartavia.com': true, 'www.flysmartavia.ru': true},

    getStartingUrl: function (params) {
        return 'https://azimut.flysmartavia.com/frame/login/';
    },

    start: function (params) {
        browserAPI.log("function start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.finalRedirect();
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
        browserAPI.log("function isLoggedIn");
        var form = $('form[name = "f1"]');
        if (form.length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("function isSameAccount");
        var number = util.findRegExp( $('.card > div + div').text(), /(\w+)/i);
        browserAPI.log("number: " + number);
        return (
            (typeof(account.properties) !== 'undefined') &&
            (typeof(account.properties.CardNumber) !== 'undefined') &&
            (account.properties.CardNumber !== '') &&
            (number === account.properties.CardNumber)
        );
    },

    logout: function () {
        browserAPI.log("function logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://azimut.flysmartavia.com/frame/logout/';
        });
    },

    login: function (params) {
        browserAPI.log("function login");
        var form = $('form[name = "f1"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.submit();
            });
        } else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("function checkLoginErrors");
        var errors = $('.error');
        if (errors.length > 0)
            provider.setError(errors.text().trim());
        else {
            plugin.finalRedirect(params);
        }
    },

    finalRedirect: function (params) {
        browserAPI.log("function finalRedirect");
        provider.setNextStep('loginComplete', function () {
            document.location.href = 'https://www.flysmartavia.com/en/miles/lk/';
        });
    },

    loginComplete: function(params) {
        browserAPI.log("function loginComplete");
        provider.complete();
    }

};
