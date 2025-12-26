var plugin = {

    hosts: {'www.ozon.ru': true},

    getStartingUrl: function (params) {
        return 'https://www.ozon.ru/context/mypoints/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.ozon.ru/my/login/';
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
                        plugin.logout();
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
        if ($('a:contains("Войти по почте")').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('div.jsLogOff').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        return false;
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.ozon.ru/context/logoff';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        // open login form
        $('a:contains("Войти по почте")').get(0).click();
        // wait login form
        var counter = 0;
        var login = setInterval(function () {
            var form = $('div.login');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0 && form.find('span:contains("Пароль") + input:visible').length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                var loginFiled = form.find('span:contains("Заполните почту") + input, span:contains("Почта") + input');
                loginFiled.val(params.account.login);
                util.sendEvent(loginFiled.get(0), 'input');
                var passwordFiled = form.find('span:contains("Пароль") + input');
                passwordFiled.val(params.account.password);
                util.sendEvent(passwordFiled.get(0), 'input');
                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        form.find('button:contains("Войти")').get(0).click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 7000)
                    }, 500)
                });
            }// if (form.length > 0 && form.find('span:contains("Пароль") + input:visible').length > 0)
            if (counter > 10) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        var errors = $('label.m-error:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }

}