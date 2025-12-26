var plugin = {
    clearCache: true,

    autologin: {
        url: 'https://m.lq.com/en/la-quinta-returns/lq-returns-sign-in.html#/sign-in',

        start: function () {
            browserAPI.log("start");
            setTimeout(function(){
                if (plugin.autologin.isLoggedIn()) {
                    if (plugin.autologin.isSameAccount())
                        plugin.autologin.finish();
                    else
                        plugin.autologin.logout();
                } else
                    plugin.autologin.login();
            }, 2000);
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('span:contains("logout"):visible').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[name="signinFormNg"]').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            provider.setError(util.errorMessages.unknownLoginState);
        },

        login: function () {
            browserAPI.log("login");
            var counter = 0;
            var login = setInterval(function () {
                var form = $('form[name="signinFormNg"]');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name="userId"]').val(params.login);
                    form.find('input[name="userLastName"]').val(params.login2);
                    form.find('input[name="password"]').val(params.pass);
                    util.sendEvent(form.find('input[name="userId"]').get(0), 'input');
                    util.sendEvent(form.find('input[name="userLastName"]').get(0), 'input');
                    util.sendEvent(form.find('input[name="password"]').get(0), 'input');
                    clearInterval(login);

                    api.setNextStep('checkLoginErrors', function () {
                        $('button[type="submit"]', form).trigger('click');
                        setTimeout(function(){
                            plugin.autologin.checkLoginErrors();
                        }, 2500);
                    });
                }
                if (counter > 10) {
                    clearInterval(login);
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
                counter++;
            }, 500);
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return false;
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var counter = 0;
            var checkLoginErrors = setInterval(function () {
                browserAPI.log("waiting... " + checkLoginErrors);
                var error = $('div.alert-container .alert > div');
                if (error.length > 0 && '' != util.trim(error.text())) {
                    clearInterval(checkLoginErrors);
                    api.error(error.text().trim());
                }
                if (counter > 3) {
                    clearInterval(checkLoginErrors);
                    plugin.autologin.finish();
                }
                counter++;
            }, 500);
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('start', function () {
                $('span:contains("logout")').parent().click();
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};
