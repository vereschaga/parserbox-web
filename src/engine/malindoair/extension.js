var plugin = {
    hosts: {'www.malindomiles.com': true},

    getStartingUrl: function (params) {
        return 'https://www.malindomiles.com/inspirenetz/app/customers/';
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
                        plugin.finish();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[name="frmLoginForm"]').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a >span#logout').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return typeof account.properties !== 'undefined'
            && typeof account.properties.AccountNumber !== 'undefined'
            && account.properties.AccountNumber !== ''
            && $('p.tier-id:contains("' + account.properties.AccountNumber + '")').length;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a >span#logout').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name="frmLoginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('#username').val(params.account.login);
            form.find('#password').val(params.account.password);


            // angularjs
            provider.eval("var scope = angular.element(document.querySelector('form[name=\"frmLoginForm\"]')).scope();"
            +"scope.frmLoginData = {mobile: '"+params.account.login+"', password: '"+params.account.password+"'};"

            +"scope.frmLoginForm.$dirty = true;"
            +"scope.frmLoginForm.$invalid = false;"
            +"scope.frmLoginForm.$valid = true;"
            +"scope.frmLoginForm.$pristine = false;"

            +"scope.frmLoginForm.username.$modelValue = '"+params.account.login+"';"
            +"scope.frmLoginForm.username.$viewValue = '"+params.account.login+"';"
            +"scope.frmLoginForm.username.$dirty = true;"
            +"scope.frmLoginForm.username.$invalid = false;"
            +"scope.frmLoginForm.username.$valid = true;"
            +"scope.frmLoginForm.username.$pristine = false;"

            +"scope.frmLoginForm.password.$modelValue = '"+params.account.password+"';"
            +"scope.frmLoginForm.password.$viewValue = '"+params.account.password+"';"
            +"scope.frmLoginForm.password.$dirty = true;"
            +"scope.frmLoginForm.password.$invalid = false;"
            +"scope.frmLoginForm.password.$valid = true;"
            +"scope.frmLoginForm.password.$pristine = false;");

            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[src="img/home/forward-login.png"]').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors();
                }, 5000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        if($('h4:contains("RESET PASSWORD")').length)
            provider.setError(["Malindo Air (Malindo Miles) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);

        /*if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.finish();*/
    },

    finish: function () {
        provider.setNextStep('complete', function () {
            var account = $('#lnkAccSum');
            if(account.length)
                account.get(0).click();
        });
    },

    complete: function () {
        provider.complete();
    }

};