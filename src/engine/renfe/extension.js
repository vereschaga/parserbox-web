var plugin = {

    hosts: {'fidelizacion.renfe.com': true, 'venta.renfe.com': true},

    getStartingUrl: function (params) {
        return 'https://venta.renfe.com/vol/masRenfe.do';
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");

        if (
            -1 < document.location.href.indexOf('https://venta.renfe.com/vol/home.do')
        ) {
            plugin.loadLoginForm(params);
            return;
        }

        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete();
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
        if ($('form[name="loginForm"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a#salir').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $('input#numTarjetaJovenHidden').val();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && (typeof(number) != 'undefined')
            && (number == account.properties.CardNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a#salir').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name="loginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('#num_tarjeta').val(params.account.login);
            form.find('#pass-login').val(params.account.password);

            provider.setNextStep('checkLoginErrors', function () {
                form.find('#loginButtonId, #login').get(0).click();
                setTimeout(function () {
                    var captcha = $('div[style *= "visibility: visible;"]:has(iframe[title *= "recaptcha"])');
                    if (captcha.length > 0) {
                        provider.reCaptchaMessage();
                        var counter = 0;
                        var captchaInterval = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            if ($('div#myModalBody:visible').length > 0) {
                                clearInterval(captchaInterval);
                                plugin.checkLoginErrors(params);
                            }
                            if (counter > 160) {
                                clearInterval(captchaInterval);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }
                            counter++;
                        }, 500);
                    } else {
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 7000)
                    }
                }, 2000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div#myModalBody:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
        }
        else {
            plugin.loginComplete();
        }
    },

    loginComplete: function () {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};
