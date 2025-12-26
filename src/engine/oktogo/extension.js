var plugin = {

    hosts: {'oktogo.ru': true, 'account.oktogo.ru': true},

    getStartingUrl: function (params) {
        return 'https://oktogo.ru';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log('start');
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout(params);
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('div.bonus-points:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        } else {
            browserAPI.log("Not LoggedIn");
            return false;
        }
        // provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        return false;
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
//        var number = plugin.findRegExp( , /Account\s*([^<]+)/i);
//        browserAPI.log("number: " + number);
//        return ((typeof(account.properties) != 'undefined')
//            && (typeof(account.properties.AccountNumber) != 'undefined')
//            && (account.properties.AccountNumber != '')
//            && (number == account.properties.AccountNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('*[class ~= "js-login-control-button"]').click();
            $('*[class = "js-client-logout"]').get(0).click();
            setTimeout(function () {
                plugin.loadLoginForm(params);
            }, 3000)
        });
    },

    login: function (params) {
        browserAPI.log("login");
        $('*[class ~= "b-page-header-login_link-link-block"]').get(0).click();
        setTimeout(function() {
            provider.setNextStep('loginForm', function () {
                document.location.href = $('iframe.fancybox-iframe').attr('src');
            });
        }, 2000)
    },

    loginForm: function () {
        browserAPI.log('loginForm');
        var form = $('form[name = "loginform"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // form.find('input[name = "email"]').focus();
            // form.find('input[name = "email"]').val(params.account.login);
            // form.find('input[name = "password"]').focus();
            // form.find('input[name = "password"]').val(params.account.password);
            // angularjs
            var inputCode = (
                'var scope = angular.element(document.querySelector("form[name = loginform]")).scope();'
                // + "scope.$apply(function(){"
                + "scope.model.forms.login.email = '" + params.account.login + "';"
                + "scope.loginform.email.$viewValue = '" + params.account.login + "';"
                + "scope.loginform.email.$render();"
                + "scope.model.forms.login.password = '" + params.account.password + "';"
                + "scope.loginform.password.$viewValue = '" + params.account.password + "';"
                + "scope.loginform.password.$render();"
                + "scope.loginform.$valid = true;"
                // + "});"
            );
            provider.eval(inputCode);

            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[type = "submit"]').get(0).click();
            });
            setTimeout(function () {
                plugin.checkLoginErrors();
            }, 5000)
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        var errors = $('div[errors *= ".$error"]:visible');
        if (errors.length == 0)
            errors = $('div.b-login-form-error:visible > strong');
        if (errors.length > 0)
            provider.setError(errors.text());
        else {
            document.location.href = 'https://oktogo.ru/my';
            provider.complete();
        }
    }

}