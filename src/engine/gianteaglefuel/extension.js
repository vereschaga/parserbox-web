var plugin = {

    hosts: {
        'www.gianteagle.com': true,
        'account.gianteagle.com': true,
        'myprofile.accounts.gianteagle.com': true,
        'geb2c101.b2clogin.com': true,
        'https://shop.gianteagle.com/': true
    },

    getStartingUrl: function (params) {
        // https://shop.gianteagle.com/account/rewards
        return 'https://myprofile.accounts.gianteagle.com/perks?srcPage=ge';
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

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#cardNumber:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#localAccountForm:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('#cardNumber').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
           && (typeof(account.properties.Number) != 'undefined')
           && (account.properties.Number !== '')
           && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.gianteagle.com/api/sitecore/account/logout?returnURL=%2F';
        });
    },

    /*logout2: function () {
        browserAPI.log("Logout2");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://shop.gianteagle.com/logout';
        });
    },*/

    login: function (params) {
        browserAPI.log("login");
        var form = $('#localAccountForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#signInName').val(params.account.login);
            form.find('input#password').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button#next').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000);
            });
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        var errors = $("#signInNameError:visible");
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
};