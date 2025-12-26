var plugin = {

    hosts: {
        'www.smiles.com.br': true,
        'www.smiles.com.ar': true,
        'login.smiles.com.br': true,
    },

    getStartingUrl: function (params) {
        if (params.account.login2 === 'Argentina') {
            return 'https://www.smiles.com.ar/';
        }
		return 'https://www.smiles.com.br/home';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else {
                    if (params.account.login2 === 'Argentina') {
                        plugin.login(params);
                        return;
                    }
                    provider.setNextStep('login', function () {
                        document.location.href = 'https://www.smiles.com.br/login';
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if (params.account.login2 === 'Argentina') {
            var enter = $('.header__brand .box-signin button.btn-outline-light:visible');
            if (enter.length > 0) {
                enter.get(0).click();
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('.logout:visible').length > 0 || (provider.isMobile && $('div.name:visible div.member-id-dyna').length > 0)) {
                browserAPI.log("LoggedIn");
                return true;
            }
            return null;
        }

        var entrar = $('a#smls-hf-btn_toEnter');
        if (entrar.length > 0) {
            entrar.get(0).click();
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.logout:visible').length > 0 || (provider.isMobile && $('div.name:visible div.member-id-dyna').length > 0)) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.filter($('div.member-id-dyna:eq(0)').text());
        if (!number)
            number = util.filter($('span:contains("Número Smiles")').next('span').text());
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && number && number != ''
            && (number == account.properties.AccountNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            var logout = $('.logout, a[href="/logout"]');
            if (logout.length)
                logout.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function() {
            let form;
            if (params.account.login2 === 'Argentina') {
                form = $('.modal-body form.frm-default');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");

                    // reactjs
                    provider.eval(
                        "var FindReact = function (dom) {" +
                        "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                        "        return dom[key];" +
                        "    }" +
                        "    return null;" +
                        "};" +
                        "FindReact(document.getElementById('memberid')).onChange({target:{id: 'memberid', value:'" + params.account.login + "'}, preventDefault:function(){}});" +
                        "FindReact(document.getElementById('password')).onChange({target:{id: 'password', value:'" + params.account.password + "'}, preventDefault:function(){}});"
                    );

                    provider.setNextStep('checkLoginErrors', function () {
                        if ($('div.recaptcha:visible').length > 0) {
                            provider.reCaptchaMessage();
                            waitingArgentina();

                            function waitingArgentina() {
                                browserAPI.log("waiting...");
                                let counter = 0;
                                let login = setInterval(function () {
                                    browserAPI.log("waiting... " + counter);
                                    if ($('p#login_errorMessage:visible, .alert-danger.alert:visible, div.response-modal > h4:visible, div.input-error > label:visible').length > 0) {
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
                            return;
                        }

                        form.find('button[type="submit"]').get(0).click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 5000)
                    });
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            form = $('div.main-content:has(input#identifier)');
            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }
            browserAPI.log("submitting saved credentials");
            // reactjs
            provider.eval(
                "var FindReact = function (dom) {" +
                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                "        return dom[key];" +
                "    }" +
                "    return null;" +
                "};" +
                "FindReact(document.getElementById('identifier')).onChange({target:{id: 'identifier', value:'" + params.account.login + "'}, preventDefault:function(){}});"
            );

            setTimeout(function() {
                browserAPI.log("click 'Continuar'");
                form.find('button[text="Continuar"]').click();

                setTimeout(function() {
                    // reactjs
                    provider.eval(
                        "var FindReact = function (dom) {" +
                        "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                        "        return dom[key];" +
                        "    }" +
                        "    return null;" +
                        "};" +
                        "FindReact(document.getElementById('password')).onChange({target:{id: 'password', value:'" + params.account.password + "'}, preventDefault:function(){}});"
                    );

                    provider.setNextStep('checkLoginErrors', function () {
                        let captcha = $('div.recaptcha:visible');
                        if (captcha && captcha.length > 0) {
                            provider.reCaptchaMessage();
                            $('#awFader').remove();
                            provider.setTimeout(function () {
                                waiting();
                            }, 0);
                        }// if (captcha && captcha.length > 0)
                        else {
                            browserAPI.log("captcha is not found");
                            $('button[text="Entrar"]').click();
                            provider.setTimeout(function () {
                                waiting();
                            }, 0);
                        }
                    });
                }, 500)
            }, 1000)
        }, 1000);

        function waiting() {
            browserAPI.log("waiting...");
            let counter = 0;
            let login = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                let error = $('div.response-modal > h4:visible, div.input-error > label:visible');
                if (error.length > 0 && util.filter(error.text()) !== '') {
                    clearInterval(login);
                    provider.setError(util.filter(error.text()));
                }// if (error.length > 0 && util.filter(error.text()) !== '')
                // refs #14909
                if (counter > 120) {
                    clearInterval(login);
                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                }
                counter++;
            }, 500);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('p#login_errorMessage:visible, .alert-danger.alert:visible, div.response-modal > h4:visible, div.input-error > label:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('itLoginComplete', function () {
                var url = 'https://www.smiles.com.br/meus-voos';
                if (params.account.login2 === 'Argentina') {
                    url = 'https://www.smiles.com.ar/myaccount/my-flights';
                }
                document.location.href = url;
            });
            return;
        }
        if (params.account.login2 === 'Argentina') {
            provider.setNextStep('itLoginComplete', function () {
                document.location.href = 'https://www.smiles.com.ar/myaccount';
            });
        } else
            plugin.itLoginComplete(params);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
