var plugin = {
    autologin: {

        getStartingUrl: function (params) {
            if (params.account.login2 == 'Italy')
                return 'https://m.sephora.it/index.html#!account/view/';
            if (params.account.login2 == 'Spain')
                return 'https://m.sephora.es/index.html#!account/view/';
            return 'https://m.sephora.com/account';
        },

        loadLoginForm: function(params) {
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
                var isLoggedIn = plugin.autologin.isLoggedIn(params.account);
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.autologin.isSameAccount(params.account))
                            provider.complete();
                        else
                            plugin.autologin.logout(params);
                    }
                    else
                        plugin.autologin.login(params);
                }// if (isLoggedIn !== null)
                if (isLoggedIn === null && counter > 10) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }// if (isLoggedIn === null && counter > 10)
                counter++;
            }, 500);
        },

        isLoggedIn: function (account) {
            browserAPI.log("isLoggedIn");
            switch (account.login2) {
                case 'Italy':
                case 'Spain':
                    if ($('section#login > form:visible').length > 0) {
                        browserAPI.log("not LoggedIn");
                        return false;
                    }
                    if ($('section.logout a:visible').length > 0) {
                        browserAPI.log("LoggedIn");
                        return true;
                    }
                    break;
                default:
                    if ($('form[data-comp *= "SignInForm"]:visible').length > 0) {
                        browserAPI.log("not LoggedIn");
                        return false;
                    }
                    if ($('h1:contains("Account Information"):visible').length > 0
                        && $('input#signin_username:visible').length == 0) {
                        browserAPI.log("LoggedIn");
                        return true;
                    }
                    break;
            }
            return null;
        },

        isSameAccount: function (account) {
            browserAPI.log("isSameAccount");
            var name;
            switch (account.login2) {
                case 'Italy':
                case 'Spain':
                    name = util.filter($('div.client-name').text());
                    break;
                default:
                    name = util.filter($('div > span:contains("Name")').parent().next('div[class^="css-"]').text());
                    break;
            }
            browserAPI.log("name: " + name);
            return ((typeof (account.properties) !== 'undefined')
                && (typeof (account.properties.Name) !== 'undefined')
                && (account.properties.Name !== '')
                && name
                && (name.toLowerCase() === account.properties.Name.toLowerCase()));
        },

        logout: function (params) {
            browserAPI.log("logout");
            switch (params.account.login2) {
                case 'Italy':
                case 'Spain':
                    provider.setNextStep('loadLoginForm', function () {
                        $('section.logout a:visible').get(0).click();
                    });
                    break;
                default:
                    $.post('https://m.sephora.com/api/auth/logout', function (data) {
                        provider.setNextStep('start', function () {
                            document.location.href = plugin.autologin.getStartingUrl(params);
                        });
                    }).fail(function () {
                        provider.setError(util.errorMessages.unknownLoginState);
                    });
                    break;
            }
        },

        login: function (params) {
            browserAPI.log("login");
            var form;
            switch (params.account.login2) {
                case 'Italy':
                case 'Spain':
                    form = $('section#login > form:visible');
                    if (form.length > 0) {
                        browserAPI.log("submitting saved credentials");
                        form.find('input#email').val(params.account.login);
                        form.find('input#password').val(params.account.password);
                        provider.setNextStep('checkLoginErrors', function () {
                            form.find('input[type="submit"]').click();
                        });
                    }
                    else
                        provider.setError(util.errorMessages.loginFormNotFound);
                    break;
                default:
                    form = $('form[data-comp *= "SignInForm"]:visible');
                    if (form.length > 0) {
                        browserAPI.log("submitting saved credentials");
                        //form.find('input#signin_username').val(params.account.login);
                        //form.find('input#signin_password').val(params.account.password);
                        // var username = form.find('input#signin_username');
                        // username.val(params.account.login);
                        // util.sendEvent(username.get(0), 'input');
                        //
                        // var password = form.find('input#signin_password');
                        // password.val(params.account.password);
                        // util.sendEvent(password.get(0), 'input');

                        provider.eval(
                            "var FindReact = function (dom) {" +
                            "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                            "        return dom[key];" +
                            "    }" +
                            "    return null;" +
                            "};" +
                            "FindReact($('input#signin_username').get(0)).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});"
                            + "FindReact($('input#signin_password]').get(0)).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
                        );

                        provider.setNextStep('checkLoginErrors', function () {
                            setTimeout(function () {
                                form.find('button[type="submit"]:contains("Continue")').click();
                                plugin.autologin.checkLoginErrors();
                            }, 1000);
                        });
                    }
                    else
                        provider.setError(util.errorMessages.loginFormNotFound);
                    break;
            }
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var counter = 0;
            var checkLoginErrors = setInterval(function () {
                var error = $('p[data-at="sign_in_error"]');
                if (error.length > 0 && util.trim(error.text()) !== '') {
                    clearInterval(checkLoginErrors);
                    provider.setError(util.trim(error.text()));
                }
                if (counter > 10) {
                    clearInterval(checkLoginErrors);
                    provider.complete();
                }
                counter++;
            }, 300);
        }
    }
};