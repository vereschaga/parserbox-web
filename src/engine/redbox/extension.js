var plugin = {

    hosts: {
        'www.redbox.com': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.redbox.com/account/perksinfo';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");

        if ($('form[data-test-id=rb-registration-form]').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('span[data-test-id=rb-header__points]').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = $.cookie('xpcp');
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Number) != 'undefined')
                && (account.properties.Number !== '')
                && number
                && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.redbox.com/logout';
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.redbox.com/playpasslogin';
        });
    },

    login: function (params) {
        browserAPI.log("login");

        let form = $('form[data-test-id=rb-registration-form]:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('#userName').val(params.account.login).get(0).dispatchEvent(new Event('change'));
        form.find('#password').val(params.account.password).get(0).dispatchEvent(new Event('change'));
        provider.setNextStep('checkLoginErrors', function () {
            let btn = form.find('button[type=submit]').get(0);
            btn.click();
            setTimeout(function () {
                let dialog = $('.rb-popover-dialog');
                if (dialog.length > 0) {
                    dialog.remove();
                    btn.click();
                    $('.overlay').hide();
                }
            }, 1000);
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('p[data-test-id=rb-sign-in-error]');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },
};