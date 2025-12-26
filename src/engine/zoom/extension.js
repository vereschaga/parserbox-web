var plugin = {

    hosts : {
        'grabpoints.com'         : true,
        'members.grabpoints.com' : true
    },

    getStartingUrl : function (params) {
        return 'https://members.grabpoints.com/#/home';
    },

    start: function (params) {
        browserAPI.log('start');
        var ua = util.detectBrowser();
        if (false == ua && navigator.userAgent.match(/Trident\//i))
            ua = ['MSIE', 0, 0];
        provider.setNextStep('startLogin', function () {
            if ('MSIE' == ua[0]) {
                provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
                document.location.href = plugin.getStartingUrl(params);
            } else
                provider.eval("angular.reloadWithDebugInfo();");
        });
        plugin.timeStartLogin = setTimeout(function () {
            plugin.startLogin(params);
        }, 3000);
    },

    startLogin: function (params) {
        browserAPI.log('startLogin');
        if ('undefined' != typeof plugin.timeStartLogin)
            clearTimeout(plugin.timeStartLogin);
        if ($('form.landing-auth-form').length)
            provider.setNextStep('startLogin', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        setTimeout(function () {
            if (plugin.isLoggedIn()) {
                if (plugin.isSameAccount(params.account))
                    provider.complete();
                else
                    plugin.logout(params);
            } else
                plugin.login(params);
        }, 2000);
    },

    isLoggedIn : function () {
        browserAPI.log('isLoggedIn');
        if ($('a span:contains("Sign-out")').length) {
            browserAPI.log('isLoggedInd: true');
            return true;
        }
        if (0 == $('a span:contains("Sign-out")').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function(account) {
        browserAPI.log('isSameAccount');
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var nameParts = [];
        $('.user-preview-name div').each(function () {
            nameParts.push($(this).text());
        });
        var name = nameParts.join(' ');
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout : function (params) {
        browserAPI.log('logout');
        provider.eval("document.querySelector('a[href=\"#/log-out\"]').click()");
        setTimeout(function () {
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }, 2000);
    },

    login : function (params) {
        browserAPI.log('login');
        $form = $('form[name="loginForm"]');
        if ($form.length) {
            $('#email').val(params.account.login);
            $('#password').val(params.account.password);

            provider.eval(
                "var scope = angular.element(document.querySelector('[name=\"loginForm\"]')).scope();"
                + "scope.loginForm.email.$viewValue = '" + params.account.login + "';"
                + "scope.loginForm.email.$render();"
                + "scope.loginForm.password.$viewValue = '" + params.account.password + "';"
                + "scope.loginForm.password.$render();"
                + "scope.loginForm.$valid = true;"
            );

            $('button[type="submit"]', $form).click();
            setTimeout(function () {
                plugin.checkLoginErrors();
            }, 1500);
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors : function () {
        browserAPI.log('checkLoginErrors');
        var $error = $('.error-msg', 'form[name="loginForm"]');
        if ($error.length && '' != $error.text().trim())
            provider.setError($error.text());
        else
            provider.complete();
    }

};
