var plugin = {

    hosts: {'www.redcube.ru': true},

    getStartingUrl: function (params) {
        return 'https://www.redcube.ru/users/edit';
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
                        plugin.logout();
                }
                else
                    setTimeout(function () {
                        plugin.login(params);
                    }, 2000);
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
        if ($('a[href = "/users/logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        var loginForm = $('a:contains("Личный Кабинет")');
        if (loginForm.length > 0) {
            browserAPI.log("not logged in");
            // open login form
            loginForm.get(0).click();
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('h2:contains("Карта №")').text(), /№\s*([\d]+)/i);
        browserAPI.log("number: " + number);
            return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.CardNumber) !== 'undefined')
            && (account.properties.CardNumber !== '')
            && (number === account.properties.CardNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.redcube.ru/users/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#CheckPhone');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "phone"]').val(params.account.login);
            form.find('#auth-check-phone').get(0).click();
            setTimeout(function () {
                plugin.enterPassword(params);
            }, 2000);
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    enterPassword: function (params) {
        browserAPI.log("enterPassword");
        var form = $('form#CheckPassword:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#auth-login').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }// if (form.length > 0)
        else {
            var errors = $('div.message:visible');
            if (errors.length > 0)
                provider.setError(errors.text());
            else
                provider.setError(util.errorMessages.passwordFormNotFound);
        }
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.message:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }

}