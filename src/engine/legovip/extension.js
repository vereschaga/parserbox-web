var plugin = {

    hosts: {
        'identity.lego.com': true,
        'shop.lego.com': true,
        'account.lego.com': true,
        'www.lego.com': true,
        'account2.lego.com': true,
        'rewards.lego.com': true
    },

    getStartingUrl: function (params) {
        return 'https://rewards.lego.com/account/?__locale__=en-us';
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
                        plugin.loginComplete();
                    else
                        plugin.logout(params);
                    return;
                }
                else {
                    provider.setNextStep('login', function () {
                        var age = $('button[class^="AgeGatestyles__StyledButton-"]:visible');
                        if (age.length)
                            age.get(0).click();
                        setTimeout(function () {
                            var vip = $('button[class^="VipBlockstyles__StyledButton-"]:visible');
                            if (vip.length)
                                vip.get(0).click();
                        }, 1000);
                    });
                    return;
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a.nav__iconlink--logout:visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if (
            $('form#loginform:visible').length > 0
            || $('button[class^="AgeGatestyles__StyledButton-"]:visible').length > 0
            || $('button[class^="VipBlockstyles__StyledButton-"]:visible').length > 0
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = $('.accountbar__user .accountbar__name').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && name
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a.nav__iconlink--logout:visible').get(0).click();
        });
    },

    /*logoutVip: function () {
        browserAPI.log("logoutVip");
        provider.setNextStep('loadLoginForm', function () {
            $('button[data-test="util-bar-account-dropdown"]').get(0).click();
            setTimeout(function () {
                $('button[data-test="legoid-logout-button"]').get(0).click();
            }, 2000);
        });
    },*/

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        setTimeout(function () {
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }, 2000);
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#loginform');
        if (form.length > 0) {
            setTimeout(function () {
                browserAPI.log("submitting saved credentials");

                // ff bug fix
                form.find('input[id = "username"]').val(params.account.login);
                util.sendEvent(form.find('input[id = "username"]').get(0), 'input');
                form.find('input[id = "password"]').val(params.account.password);
                util.sendEvent(form.find('input[id = "password"]').get(0), 'input');

                provider.eval(
                    "var FindReact = function (dom) {" +
                    "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                    "        return dom[key];" +
                    "    }" +
                    "    return null;" +
                    "};" +
                    "FindReact(document.querySelector('input[id = \"username\"]')).onChange({target:{name:'username', value:'" + params.account.login + "'}});" +
                    "FindReact(document.querySelector('input[id = \"username\"]')).onFocus();" +
                    "FindReact(document.querySelector('input[id = \"password\"]')).onChange({target:{name:'password', value:'" + params.account.password + "'}});"
                );

                provider.setNextStep('checkLoginErrors', function () {
                    form.find('#loginBtn, [data-testid="loginBtn"]').click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000);
                });
            }, 2000);
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const error = $('span.login-error:visible, div[data-testid="error"]:visible');

        if (error.length > 0 && util.trim(error.text()) !== '') {
            provider.setError(util.trim(error.text()));
            return;
        }

        provider.setNextStep('loginComplete', function () {
            document.location.href = 'https://rewards.lego.com/account?__locale__=en-us';
        });
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};