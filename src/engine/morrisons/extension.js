var plugin = {

    hosts: {
        'my.morrisons.com': true,
        'www.morrisons.com': true,
        'your.morrisons.com': true,
        'groceries.morrisons.com': true,
        'auth.morrisons.com': true,
        'more.morrisons.com': true
    },

    getStartingUrl: function (params) {
        return 'https://my.morrisons.com/more/#/preferences-wizard/initial';
    },

    loadLoginForm: function (params) {
        return provider.setNextStep('login', function () {
            document.location.href = 'https://more.morrisons.com/login';
        });
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
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.loadLoginForm(params);
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
        if ($("a[href*='https://more.morrisons.com/login']").length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($("a[href*='/tabs/account'], a[href*='https://more.morrisons.com/']:contains('Account'):eq(0)").length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return false;
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('logout2', function () {
            document.location.href = 'https://more.morrisons.com/tabs/account';
        });
    },
    logout2: function (params) {
        browserAPI.log('logout2');
        provider.setNextStep('loadLoginForm', function () {
            let logout = $("ion-item:contains('Log out')");
            if (logout.length) {
                logout.get(0).click();
            }
        });
    },

    login: function (params) {
        browserAPI.log('login');
        var form = $('form');
        if (form.length) {
            browserAPI.log("submitting saved credentials");
            $('input[name="username"]', form).val(params.account.login);
            $('input[name="password"]', form).val(params.account.password);
            util.sendEvent($('input[name="username"]', form).get(0), 'input');
            util.sendEvent($('input[name="password"]', form).get(0), 'input');
            provider.setNextStep('checkLoginErrors', function () {
                $('ion-button:contains("Log in")', form).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        var error = $('.toast-message:visible');
        if (error.length && '' != util.trim(error.text()))
            provider.setError(error.text());
        else
            provider.complete();
    }

};