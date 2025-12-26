var plugin = {

    //clearCache: true,

    autologin: {
        getStartingUrl: function () {
            return 'https://www.makemytrip.com/pwa/flights/mmt-login';
        },

        start: function (params) {
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.autologin.isLoggedIn();
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.autologin.isSameAccount(params.account))
                            plugin.autologin.loginComplete(params);
                        else
                            plugin.autologin.logout(params);
                    }
                    else
                        plugin.autologin.login(params);
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
            if ($('li#logoutLi').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('div.signup__form').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            return null;
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return false;
            //return (typeof(params.properties) !== 'undefined')
            //    && (typeof(params.properties.Number) !== 'undefined')
            //    && ($('span:contains("' + params.properties.Number + '")').length > 0);
        },

        login: function (params) {
            browserAPI.log("login");
            var counter = 0;
            var login = setInterval(function () {
                var form = $('div.signup__form');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input#username').val(params.account.login);
                    form.find('input#password').val(params.account.password);

                    clearInterval(login);

                    provider.setNextStep('checkLoginErrors', function() {
                        form.find('a[onclick="loginSubmit()"]').get(0).click();
                    });
                }
                if (counter > 10) {
                    clearInterval(login);
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
                counter++;
            }, 500);
        },


        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var counter = 0;
            var checkLoginErrors = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var error = $('.error_label.RobotoRegular:visible');
                if (error.length > 0 && $.trim(error.text()) !== '') {
                    clearInterval(checkLoginErrors);
                    provider.setError($.trim(error.text()));
                }
                if (counter > 5) {
                    clearInterval(checkLoginErrors);
                    plugin.autologin.loginComplete();
                }
                counter++;
            }, 500);
        },

        logout: function () {
            browserAPI.log("logout");
            var logout = $('li#logoutLi');
            if (logout.length > 0) {
                logout.find('> a').get(0).click();
                provider.setNextStep('start', function() {
                    document.location.href = plugin.autologin.getStartingUrl(params);
                });
            } else
                provider.setError(util.errorMessages.unknownLoginState);
        },

        loginComplete: function (params) {
            browserAPI.log('Complete');
            provider.complete();
        }
    }
};
