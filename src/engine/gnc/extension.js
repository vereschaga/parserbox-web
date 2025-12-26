var plugin = {

    hosts: {
        'www.gnc.com': true,
        'login-register.gnc.com': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.gnc.com/account-rewards';
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

        if ($('form.login-form:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('li.sign-out:visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let email = $('#sfmcUserEmail').val();
        browserAPI.log("email: " + email);
        return ((typeof (account.login) != 'undefined')
                && (account.login !== '')
                && (email !== '')
                && (email.toLowerCase() === account.login.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('li.sign-out').find('a').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            $('#sso_link').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");

        let form = $('.login-form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name="username"]').val(params.account.login);
        util.sendEvent(form.find('input[name="username"]').get(0), 'click');
        util.sendEvent(form.find('input[name="username"]').get(0), 'input');
        util.sendEvent(form.find('input[name="username"]').get(0), 'blur');
        util.sendEvent(form.find('input[name="username"]').get(0), 'change');

        form.find('input[name="password"]').val(params.account.password);
        util.sendEvent(form.find('input[name="password"]').get(0), 'click');
        util.sendEvent(form.find('input[name="password"]').get(0), 'input');
        util.sendEvent(form.find('input[name="password"]').get(0), 'blur');
        util.sendEvent(form.find('input[name="password"]').get(0), 'change');
        provider.setNextStep('checkLoginErrors', function () {
            form.find('.button-primary').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('.username-warning-txt:visible');

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