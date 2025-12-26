var plugin = {

    hosts : {
        'lacompagnie.com'     : true,
        'www.lacompagnie.com' : true
    },

    getStartingUrl : function (params) {
        return 'https://www.lacompagnie.com/en/member/profile/';
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

    isLoggedIn : function () {
        browserAPI.log('isLoggedIn');
        if ($('form[name="signPageForm"]').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if ($('#loginHeaderDropdown:visible').length) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('li:contains("Member number")').text(), /:\s*(\w+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.MemberNumber) !== 'undefined')
            && number
            && (account.properties.MemberNumber != '')
            && (number == account.properties.MemberNumber));
    },

    logout : function () {
        browserAPI.log('logout');
        provider.setNextStep('start', function () {
            $('button:contains("Log me out")').click();
        });
    },

    login : function (params) {
        browserAPI.log('login');
        var form = $('form[name="signPageForm"]');
        if (form.length) {
            $('input[name="clientNumber"]', form).val(params.account.login);
            $('input[name="password"]', form).val(params.account.password);
            // angularjs
            provider.eval("" +
                "var scope = angular.element(document.querySelector('form[name=\"signPageForm\"]')).scope();"
                + "scope.clientNumber = '" + params.account.login + "';"
                + "scope.password = '" + params.account.password + "';"
                //+ "scope.ctrl.submitForm(scope.signPageForm, true);"
            );
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    browserAPI.log('login: submit');
                    form.submit();
                }, 1000);
            });

        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors : function () {
        browserAPI.log('checkLoginErrors');
        var $errors = $('.alert.ng-scope.alert--flag p');
        if ($errors.length)
            provider.setError($errors.text());
        else {
            provider.setNextStep('complete', function () {
                document.location.href = 'https://www.lacompagnie.com/en/member/home/';
            });
        }
    },

    complete: function () {
        browserAPI.log('complete');
        provider.complete();
    }

};