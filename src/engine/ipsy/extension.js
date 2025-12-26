var plugin = {

    hosts: {
        'www.ipsy.com': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.ipsy.com/';
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

        let btn = $('#login-splash-button');
        let form = $('form[data-e2e-selector="login-splash-form"]');
        if (btn.length > 0 && form.length === 0) {
            btn.get(0).click();
            browserAPI.log("probably not LoggedIn, clicked button to show login form");
            return null;
        }

        if (form.length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('a[href^="/account"]').length) {
            document.location.href = 'https://www.ipsy.com/account/general#/profile';
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let description = document.getElementsByClassName('account-description-field-text');
        let username = description[0].textContent;
        let email = description[1].textContent;
        browserAPI.log("username: " + username);
        browserAPI.log("email: " + email);
        return (username && username.toLowerCase() === account.login.toLowerCase()
                || email && email.toLowerCase() === account.login.toLowerCase());
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('body').append("<form name='formToPostLogoutRequest' method='POST' action='https://www.ipsy.com/logout'></form>");
            document.forms.formToPostLogoutRequest.submit();
        });
    },

    login: function (params) {
        browserAPI.log("login");

        let loginInput = $('#id-username');

        if (loginInput.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        loginInput = loginInput.val(params.account.login).get(0);
        loginInput.dispatchEvent(new Event('input', {bubbles: true}));
        loginInput.dispatchEvent(new Event('change', {bubbles: true}));
        loginInput.dispatchEvent(new Event('blur', {bubbles: true}));
        let pwdInput = $('#id-password').val(params.account.password).get(0);
        pwdInput.dispatchEvent(new Event('input', {bubbles: true}));
        pwdInput.dispatchEvent(new Event('change', {bubbles: true}));
        pwdInput.dispatchEvent(new Event('blur', {bubbles: true}));
        provider.setNextStep('checkLoginErrors', function () {
            let btn = $('button[data-is-disabled="false"]');
            if (btn.length) btn.get(0).click();
            else setTimeout(function () { $('button[data-is-disabled="false"]').get(0).click(); }, 1000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div[type="error"]:visible');

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