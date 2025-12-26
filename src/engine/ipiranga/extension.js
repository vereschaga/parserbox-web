var plugin = {

    hosts: {
        'kmdevantagens.com.br': true,
        'www.kmdevantagens.com.br': true
    },

    getStartingUrl: function (params) {
        return 'https://www.kmdevantagens.com.br/wps/portal/Applications/MarketPlace/';
    },

    // loadLoginForm: function (params) {
    //     browserAPI.log('loadLoginForm');
    //     provider.setNextStep('start', function () {
    //         document.location.href = plugin.getStartingUrl(params);
    //     });
    // },

    start: function (params) {
        browserAPI.log("start");
        setTimeout(function () {
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
        }, 3000);
    },

    isLoggedIn: function () {
        browserAPI.log('isLoggedIn');
        if ($('a.sign-in:visible').length > 0) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if ($('a:contains("Sair")').length > 0) {
            browserAPI.log('isLoggedInd: true');
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('div.informations span[ng-if = "$ctrl.showName"] > strong:visible').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (name.toLowerCase() == account.properties.Name.split(' ')[0].toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('start', function () {
            $('a:contains("Sair")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log('login');
        $('a.sign-in:visible').get(0).click();
        setTimeout(function () {
            var form = $('form[name="loginForm"]');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                // form.find('input[name = "cpf"]').val(params.account.login);
                // form.find('input[name = "senha"]').val(params.account.password);
                // util.sendEvent(form.find('input[name = "cpf"]').get(0), 'input');
                // util.sendEvent(form.find('input[name = "senha"]').get(0), 'input');

                // angularjs
                provider.eval("var scope = angular.element(document.querySelector('form[name = \"loginForm\"]')).scope();" +
                    "scope.$apply(function(){" +
                    "scope.loginForm.cpf.$setViewValue('" + params.account.login + "');" +
                    "scope.loginForm.cpf.$render();" +
                    "scope.loginForm.senha.$setViewValue('"  + params.account.password +  "');" +
                    "scope.loginForm.senha.$render();" +
                    "});"
                );

                var $frameCaptcha = $('iframe[src*="/recaptcha/"]:visible');
                provider.setNextStep('checkLoginErrors', function () {
                    if ($frameCaptcha.length > 0) {
                        form.submit(function () {
                            setTimeout(function () {
                                plugin.checkLoginErrors();
                            }, 1500);
                        });
                        return provider.reCaptchaMessage();
                    } else {
                        provider.eval("processarLoginTopo();");
                        return setTimeout(function () {
                            plugin.checkLoginErrors();
                        }, 1500);
                    }
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 2000);
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        var error = $('div.alert:visible');
        if (error.length > 0 && util.filter(error.text()) != '')
            provider.setError(util.filter(error.text()));
        else
            plugin.loginComplete();
    },

    loginComplete: function () {
        browserAPI.log('loginComplete');
        provider.complete();
    }

};
