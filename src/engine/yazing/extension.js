var plugin = {

    hosts: {
        'yazing.com': true,
        'www.yazing.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.yazing.com';
    },

    start: function (params) {
        browserAPI.log('start');
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
                }
                else {
                    provider.setNextStep('login', function () {
                        $('a:contains("Login")').get(0).click();
                    });
                }
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
        if ($('a:contains("Login")').length > 0) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if ($('a:contains("Log Out")', '.nav').length) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        const username = util.filter($('ul#w3', '.nav').prev().text());
        browserAPI.log('username: ' + username);
        if (
            username !== ''
            && username.toLowerCase() === account.login.toLowerCase()
        ) {
            return true;
        }

        return false;
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('start', function () {
            $("#logoutForm").submit();
        });
    },

    login: function (params) {
        browserAPI.log('login');
        const form = $('#loginform');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        $('input[name="LoginForm[username]"]', form).val(params.account.login);
        $('input[name="LoginForm[password]"]', form).val(params.account.password);
        return provider.setNextStep('checkLoginErrors', function () {
            $('#login-button').click();
            setTimeout(function () {
                plugin.checkLoginErrors();
            }, 2000);
        });
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        const errors = $('#alert-message-login h4');

        if (errors.length && util.trim(errors.text()) !== '') {
            provider.setError(util.trim(errors.text()));
            return;
        }

        provider.complete();
    }

};