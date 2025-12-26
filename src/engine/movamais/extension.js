var plugin = {

    hosts : {
        'movamais.com'     : true,
        'www.movamais.com' : true
    },

    getStartingUrl : function(params) {
        return 'http://movamais.com/#!/perfil';
    },

    start : function(params) {
        browserAPI.log('start');
        setTimeout(function() {
            if (plugin.isLoggedIn(params)) {
                if (plugin.isSameAccount(params))
                    plugin.finish();
                else
                    plugin.logout(params);
            } else
                plugin.loadLoginForm(params);
        }, 3000);
    },

    isLoggedIn : function(params) {
        browserAPI.log('isLoggedIn');
        if ($('header[ng-show="isLoggedIn"], div.user-profile').length) {
            browserAPI.log('LoggedIn');
            return true;
        }
        if (!$('header[ng-show="isLoggedIn"]').length) {
            browserAPI.log('not Logged In');
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount : function(params) {
        browserAPI.log('isSameAccount');
        if ('undefined' != typeof params.account.properties
            && 'undefined' != typeof params.account.properties.Name
            && $('h2:contains("' + params.account.properties.Name + '")').length) {
            browserAPI.log('sameAccount: true');
            return true;
        }
        browserAPI.log('sameAccount: false');
        return false;
    },

    logout : function(params) {
        browserAPI.log('logout');
        provider.setNextStep('login', function() {
            var $btn = $('button[ng-click="logout()"]');
            if ($btn.length)
                $btn.click();
            setTimeout(function() {
                document.location.href = 'http://movamais.com/login';
            }, 2000);
        });
    },

    loadLoginForm : function(params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('login', function() {
            document.location.href = 'http://movamais.com/login';
        });
    },

    login : function(params) {
        browserAPI.log('login');
        var $form = $('form[name="loginForm"]');
        if ($form.length) {
            provider.eval(
                "var scope = angular.element(document.querySelector('form[name=\"loginForm\"]')).scope();"
                + "scope.$apply(function(){"
                //+ "scope.loginForm.email.$valid = true;"
                + "scope.loginForm.email.$$rawModelValue = '" + params.account.login + "';"
                + "scope.loginForm.email.$modelValue = '" + params.account.login + "';"
                + "scope.loginForm.email.$viewValue = '" + params.account.login + "';"
                + "scope.loginForm.email.$setDirty();"
                + "scope.loginForm.email.$dirty = true;"
                + "scope.loginForm.email.$validate();"
                + "scope.loginForm.email.$render();"
                //+ "scope.loginForm.password.$valid = true;"
                + "scope.loginForm.password.$$rawModelValue = '" + params.account.password + "';"
                + "scope.loginForm.password.$modelValue = '" + params.account.password + "';"
                + "scope.loginForm.password.$viewValue = '" + params.account.password + "';"
                + "scope.loginForm.password.$setDirty();"
                + "scope.loginForm.password.$dirty = true;"
                + "scope.loginForm.password.$render();"
                + "scope.loginForm.password.$validate();"
                + "scope.loginForm.$setDirty();"
                + "scope.loginForm.$invalid = false;"
                + "});"
            );

            $('input[name="email"]', $form).val(params.account.login);
            $('input[name="password"]', $form).val(params.account.password);
            util.sendEvent($('input[name="email"]', $form).get(0), 'input');
            util.sendEvent($('input[name="password"]', $form).get(0), 'input');

            util.sendEvent($('input[name="email"]', $form).get(0), 'change');
            util.sendEvent($('input[name="password"]', $form).get(0), 'change');

            return provider.setNextStep('checkLoginErrors', function() {
                $('button[type="submit"]', $form).trigger('click');
                setTimeout(function() {
                    plugin.checkLoginErrors();
                }, 4000);
            });
        }
        provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors : function() {
        browserAPI.log('checkLoginErrors');
        var $error = $('.login__form--message-error:visible');
        if ($error.length && '' != util.trim($error.text())) {
            provider.setError($error.text());
        } else
            plugin.finish();
    },

    finish : function() {
        browserAPI.log('finish');
        provider.complete();
    }

};
