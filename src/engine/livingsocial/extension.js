var plugin = {

    hosts: {'login.livingsocial.com': true, 'www.livingsocial.com': true, 'livingsocial.com': true,
        'secure.livingsocial.co.uk': true, 'livingsocial.co.uk': true},

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        browserAPI.log("Region => " + params.account.login2);
        if (params.account.login2 === 'UK')
            return 'https://secure.livingsocial.co.uk/myaccount';
        else
            return "https://www.livingsocial.com/mybucks";
    },

    loadLoginForm: function (params) {
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
            var isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if (params.account.login2 === 'UK') {
            if ($('ng-form[name="signUpForm"]').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('a[href="/logout"]').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
        } else {
            if ($('form#login-form').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('a:contains("Sign Out")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return false;
    },

    logout: function () {
        browserAPI.log("logout");
        if (params.account.login2 === 'UK') {
            provider.setNextStep('loadLoginForm', function () {
                $('a[href="/logout"]').get(0).click();
            });
        } else {
            provider.setNextStep('start', function () {
                $('a:contains("Sign Out")').get(0).click();
            });
        }
    },

    login: function (params) {
        browserAPI.log("login");
        browserAPI.log("location " + document.location.href);
        var form;
        if (params.account.login2 === 'UK') {
            form = $('ng-form[name="signUpForm"]');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                // angularjs
                provider.setNextStep('passwordForm', function () {
                    provider.eval('var scope = angular.element(document.querySelector("ng-form[name = signUpForm]")).scope();' +
                        'scope.userInfo.email = "' + params.account.login + '";' +
                        'scope.vm.checkEmail(scope.userInfo.email);'
                    );
                    var counter = 0;
                    var loginProcess = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        var error = $('span.err-msg:visible');
                        if (error.length > 0 && util.trim(error.text()) !== '') {
                            clearInterval(loginProcess);
                            plugin.checkLoginErrors(params);
                        }
                        if (!provider.isMobile && counter > 1)
                            clearInterval(loginProcess);
                        if (provider.isMobile && $('ng-form[name="loginEmailForm"]').find('input[name = "password"]').length > 0) {
                            clearInterval(loginProcess);
                            plugin.passwordForm(params);
                        }
                        if (counter > 5) {
                            clearInterval(loginProcess);
                            if ($('h4:contains("create your password"):visible').length > 0)
                                provider.complete();
                            else
                                provider.setError(util.errorMessages.passwordFormNotFound);
                            return;
                        }// if (isLoggedIn === null && counter > 10)
                        counter++;
                    }, 500);
                });
            } else
                provider.setError(util.errorMessages.loginFormNotFound);

        } else {
            form = $('form#login-form');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "email"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    if (form.find('#login-recaptcha:visible').length > 0)
                        provider.complete();
                    else
                        form.find('.touch-submit-container >input[type="submit"]').get(0).click();
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    passwordForm: function (params) {
        browserAPI.log('passwordForm');
        var form = $('ng-form[name="loginEmailForm"]').find('input[name = "password"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            provider.setNextStep('checkLoginErrors', function () {
                provider.eval('var scope = angular.element(document.querySelector("ng-form[name = loginEmailForm]")).scope();' +
                    'scope.vm.password = "' + params.account.password + '";' +
                    'scope.vm.loginByEmail()'
                );
            });
        }
        else {
            if ($('h4:contains("create your password"):visible').length > 0)
                provider.complete();
            else
                provider.setError(util.errorMessages.passwordFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        var error;
        if (params.account.login2 === 'UK') {
            error = $('span.err-msg:visible');
            if (error.length > 0 && util.trim(error.text()) !== '')
                provider.setError(error.text());
            else
                plugin.loginComplete(params);
        } else {
            error = $('div.error:visible');
            if (error.length == 0)
                error = $('div.generic-error:visible');
            if (error.length > 0)
                provider.setError(error.text());
            else
                plugin.loginComplete(params);
        }
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        provider.complete();
    }

};
