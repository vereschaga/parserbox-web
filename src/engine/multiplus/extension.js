var plugin = {

    hosts: {'www.pontosmultiplus.com.br': true},

    getStartingUrl: function (params) {
        return 'https://www.pontosmultiplus.com.br/portal/';
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
        if ($('#form-login').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[onclick="btnGoTo(\'sair\')"]:visible').length > 0
            || (provider.isMobile && $('a[href *= "logout"]').length > 0)) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.filter($('p:contains("Nº Cartão Fidelidade:") > span').text());
        browserAPI.log("number: " + number);
            return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CPF) != 'undefined')
            && (account.properties.CPF != '')
            && (number == account.properties.CPF));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            if (provider.isMobile)
                $('a[href *= "logout"]').get(0).click();
            else
                $('a[onclick="btnGoTo(\'sair\')"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#form-login');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "user"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button').click();
                setTimeout(function () {
                    waiting();
                }, 3000);
            });
            function waiting() {
                browserAPI.log("waiting...");
                var counter = 0;
                var login = setInterval(function () {
                    browserAPI.log("waiting... " + counter);
                    var success = $('a#sign-in:visible').length;
                    if (success.length > 0
                        || $('div.modal-login-error:visible h2.title').length > 0) {
                        clearInterval(login);
                        plugin.checkLoginErrors(params);
                    }
                    if (counter > 120) {
                        clearInterval(login);
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }
                    counter++;
                }, 500);
            }
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.modal-login-error:visible h2.title').text();
        if (errors.length > 0)
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }
};