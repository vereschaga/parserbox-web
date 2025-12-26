var plugin = {
	autologin: {

        getStartingUrl: function (params) {
            return "http://m.audiencerewards.com/login.cfm";
        },

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.autologin.isLoggedIn();
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.autologin.isSameAccount())
                            provider.complete();
                        else
                            plugin.autologin.logout();
                    }
                    else
                        plugin.autologin.login();
                }// if (isLoggedIn !== null)
                if (isLoggedIn === null && counter > 10) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }// if (isLoggedIn === null && counter > 10)
                counter++;
            }, 500);
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('#frmLogin').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('#memberinfoname').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            return null;
        },

        login: function () {
            browserAPI.log("login");
            var form = $('#frmLogin');
            var button = $('#btn-login');
            if (form.length == 1 && button.length == 1) {
                browserAPI.log("submitting saved credentials");
                form.find('#Email').val(params.login);
                form.find('#Password').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    button.click();
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Number) !== 'undefined')
                && ($('#memberinfonumber:contains("' + params.properties.Number + '")').length > 0)
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('.alert');
            if (error.length > 0)
                api.error(error.text().trim());
            else
                this.finish();
        },

        loadLoginForm: function () {
            browserAPI.log("Logout");
            api.setNextStep('start', function () {
                document.location.href = plugin.autologin.getStartingUrl();
            })
        },

        logout: function () {
            browserAPI.log("Logout");
            api.setNextStep('loadLoginForm', function () {
                document.location.href = $('a[href *= "logout.cfm"]').attr('href');
            });
        },

        finish: function () {
            api.complete();
        }
	}
};