var plugin = {

    hosts: {
        'altituderewards.com': true,
        'altituderewards.com.au': true,
        'banking.westpac.com.au': true,
    },

    getStartingUrl: function (params) {
        return 'https://banking.westpac.com.au/wbc/banking/handler?TAM_OP=login&segment=personal&logout=false';
    },

    start: function (params) {
        browserAPI.log("function start");
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
        if ($('#login').length > 0) {
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
        return false;
    },

    logout: function (params) {
        browserAPI.log("function logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://altituderewards.com.au/public/logout.aspx';//todo
        });
    },

    login: function (params) {
        browserAPI.log("function login");
        let form = $('#login');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        form.find('input[name = "fakeusername"]').val(params.account.login);
        form.find('input[name = "password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            $('#signin').click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 10000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("function checkLoginErrors");
        let errors = $('div.alert-error:visible div');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }
        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("function loginComplete");
        provider.complete();
    }

};
