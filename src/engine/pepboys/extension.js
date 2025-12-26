var plugin = {

    hosts: {
        'pepboys.com': true,
        'www.pepboys.com': true,
        'm.pepboys.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.pepboys.com/account/rewards';
    },

    start: function (params) {
        browserAPI.log('start');
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
        browserAPI.log('isLoggedIn');
        if ($('#headerLogoutLink,a[href="/logout"]').length) {
            browserAPI.log('LoggedIn');
            return true;
        }
        if ($('a#loginDropDownNavBar:visible').length) {
            browserAPI.log('not LoggedIn');
            return false;
        }
        return false;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        var number = util.findRegExp($('p:contains("Account")').text(), /#(\d+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && number
            && (number == account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.pepboys.com/logout';
        });
    },

    login: function (params) {
        browserAPI.log('login');

        provider.eval('$(\'a#loginDropDownNavBar\').click();');

        var $form = $('form[id = "loginForm"]');
        if ($form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return false;
        }
        browserAPI.log("submitting saved credentials");
        $('#inputEmail', $form).val(params.account.login);
        $('#inputPassword', $form).val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            $('button#login-form__login-button', $form).click();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        var $error = $('#js-addErrorSpan:visible');
        if ($error.length && util.filter($error.text()) !== '')
            provider.setError(util.filter($error.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        provider.complete();
    }

};
