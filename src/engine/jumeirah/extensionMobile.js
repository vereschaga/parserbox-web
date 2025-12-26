var plugin = {
    autologin: {

        url: 'https://www.jumeirah.com/en/',

        start: function () {
            browserAPI.log('start');
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.autologin.isLoggedIn();
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.autologin.isSameAccount(params.account))
                            provider.complete();
                        else
                            plugin.autologin.logout();
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

        logout: function () {
            browserAPI.log('logout');
            api.setNextStep('loadLoginForm', function () {
                $('#sirius-login-overlay').get(0).click();
            });
        },

        loadLoginForm: function () {
            browserAPI.log('loadLoginForm');
            api.setNextStep('login', function () {
                document.location.href = plugin.autologin.url;
            });
        },

        isSameAccount: function () {
            browserAPI.log('isSameAccount');
            return (typeof(params.properties) != 'undefined' &&
                typeof(params.properties.Number) != 'undefined' &&
                params.properties.Number != '' &&
                $('p:contains("' + params.properties.AccountNumber + '")').length);
        },

        isLoggedIn: function () {
            browserAPI.log('isLoggedIn');
            if ($('form.LoginForm').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            if ($('#sirius-login-overlay').length > 0) {
                browserAPI.log('logged in');
                return true;
            }
            return null;
        },

        login: function () {
            browserAPI.log('login');
            $('a.icon-login').get(0).click();
            var form = $('form.LoginForm');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                // $('input[id = "siriusId"]', form).val(params.login);
                // $('input[id = "Password"]', form).val(params.pass);

                // angularjs
                provider.eval("var scope = angular.element(document.querySelector('form.LoginForm')).scope();"
                    + "scope.$apply(function(){scope.LoginData.siriusId = '" + params.account.login + "';"
                    + "scope.LoginData.Password = '" + params.account.password + "';"
                    + "scope.LoginUser();"
                    + "});");

                provider.setNextStep('checkLoginErrors', function () {
                    $('div.submit button').click();
                    setTimeout(function () {
                        var recaptchaFrame = $('iframe[title *= "challenge"]:visible').length;
                        if (recaptchaFrame.length) {
                            provider.reCaptchaMessage();
                            provider.setNextStep('checkLoginErrors', function () {
                                browserAPI.log("captcha entered by user");
                                checkErrors();
                            });
                        }// if (recaptchaFrame.length)
                        else
                            checkErrors();
                    }, 1000);
                });

                function checkErrors() {
                    var counter = 0;
                    var loginProcess = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        var error = $('p.error:visible');
                        if ((error.length > 0 && util.trim(error.text()) != '') || counter > 120) {
                            clearInterval(loginProcess);
                            plugin.autologin.checkLoginErrors();
                            return;
                        }// if (error.length > 0 || counter > 120)
                        counter++;
                    }, 500);
                }
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        },

        checkLoginErrors: function () {
            browserAPI.log('checkLoginErrors');
            var $error = $('p.error:visible');
            if ($error.length && '' != util.trim($error.text())) {
                api.error(util.trim($error.text()));
            }
            else
                this.finish();
        },

        finish: function () {
            browserAPI.log('finish');
            api.complete();
        }

    }
};
