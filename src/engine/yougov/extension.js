var plugin = {

    hosts: {
        'today.yougov.com': true,
        'labs.yougov.co.uk': true,
        'yougov.co.uk': true,
        'yougov.de': true,
        'id.yougov.com': true,
        'account.yougov.com': true,
        'ca.yougov.com': true,
        'mena.yougov.com': true,
    },

    getLink: function (params) {
        let link;
        switch (params.account.login2) {
            case 'USA':
                link = 'account.yougov.com/us-en/account';
                break;
            case 'Germany':
                link = 'yougov.de';
                break;
            case 'Canada':
                link = 'ca.yougov.com/ca-en/account';
                break;
            case 'ID':
                link = 'account.yougov.com/id-en/account';
                break;
            case 'Lebanon':
                link = 'account.yougov.com/lb-en/account';
                break;
            case 'MENA':
                link = 'mena.yougov.com/en';
                break;
            case 'UK':
            default:
                // link = 'yougov.co.uk';
                link = 'account.yougov.com/gb-en/account';
                break;
        }

        return 'https://' + link;
    },

    getStartingUrl: function (params) {
        if (['USA', 'UK', 'MENA', 'Canada', 'Lebanon', 'ID'].indexOf(params.account.login2) === -1) {
            return plugin.getLink(params);
        }

        return plugin.getLink(params) + '/account/login/';
    },

    start: function (params) {
        browserAPI.log("start");
        setTimeout(function () {
            if (plugin.isLoggedIn(params)) {
                if (plugin.isSameAccount(params.account))
                    provider.complete();
                else
                    plugin.logout(params);
            } else
                plugin.login(params);
        }, 1000)
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        browserAPI.log("Region => " + params.account.login2);
        if (
            $('input[ng-model = "user.email"]:visible').length > 0
            || $('input[name = "emailInput"]').length > 0
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href*="logout"], a:contains("Logout")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("Region => " + account.login2);
        const name = $('#id_pii_name_first, #id_pii_name_fullname').val();
        browserAPI.log("Name => " + name);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Name) !== 'undefined')
                && (account.properties.Name !== '')
                && name
                && (0 === account.properties.Name.toLowerCase().indexOf(name.toLowerCase())));
    },

    logout: function (params) {
        browserAPI.log("logout");
        browserAPI.log("Region => " + params.account.login2);
        provider.setNextStep('loadLoginForm', function () {
            if (['USA', 'UK', 'Canada', 'Lebanon', 'ID'].indexOf(params.account.login2) === -1) {

                if (params.account.login2 === 'MENA') {
                    document.location.href = plugin.getLink(params) + '/account/logout/';
                    return;
                }

                document.location.href = plugin.getLink(params) + '/logout';
                return;
            }
            // if (params.account.login2 === 'Canada') {
            //     $('a[href *= "logout"]:visible').get(0).click();
            //     return;
            // }
            document.location.href = plugin.getLink(params) + '/logout/';
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        browserAPI.log("Region => " + params.account.login2);
        let email;

        if (['USA', 'UK', 'Canada', 'Lebanon', 'ID'].indexOf(params.account.login2) !== -1) {

            email = $('input[name = "emailInput"]');
            if (email.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }
            browserAPI.log("submitting saved credentials");
            email.val(params.account.login);
            util.sendEvent(email.get(0), 'input');

            provider.setNextStep('checkLoginErrors', function () {
                browserAPI.log("click");
                $('button.prl-btn').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });

            return;
        }

        email = $('input[ng-model = "user.email"]');
        if (email.length > 0) {
            browserAPI.log("submitting saved credentials");
            email.val(params.account.login);
            $('input[ng-model = "user.password"]').val(params.account.password);

            var href = plugin.getLink(params) + '/account/';
            provider.setNextStep('checkLoginErrors', function () {
                if (params.account.login2 === 'Canada') {
                    provider.eval(
                        "var scope = angular.element(document.querySelector('form')).scope();"
                        + "scope.user.email ='" + params.account.login + "';"
                        + "scope.user.password ='" + params.account.password + "';"
                    );
                    setTimeout(function () {
                        $('button:contains("Sign in with e-mail")').click();
                    },500);
                }else {
                    // angularjs
                    provider.eval('var scope = angular.element(".login-question-form").scope();' +
                        'scope.login.login({' +
                        'email:"' + params.account.login + '", ' +
                        'password:"' + params.account.password + '"' +
                        '}).then(function(response){' +
                        'if (typeof (response.show_captcha) == "undefined" && !response.error) ' +
                        '    document.location.href = "' + href + '"; ;},' +
                        'function(fail) { console.log(fail);})'
                    );// else if (response.error && typeof (response.message) != "undefined")
                }
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }// if (email.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div[ng-if="loginErrorMessage"]:eq(0):visible');
        if (errors.length === 0) {
            errors = $('p.email__error:eq(0):visible');
        }
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    }

};