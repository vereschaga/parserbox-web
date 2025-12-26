var plugin = {
    autologin: {
        url: "https://m.nationalcar.com/en_US/car-rental/home.html",

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + start);
                if ($('input[name = "username"][type = text]').parents('form[name = "loginForm"]:eq(1)').length > 0
                    || $('span.loyaltyNumber').length > 0) {
                    clearInterval(start);
                    plugin.autologin.start2();
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("Can't determine state");
                }
                counter++;
            }, 500);
        },

        start2: function () {
            browserAPI.log("start2");
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            }
            else
                this.login();
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('span.loyaltyNumber').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('input[name = "username"][type = text]').parents('form[name = "loginForm"]:eq(1)').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        login: function () {
            browserAPI.log("login");
            var counter = 0;
            var login = setInterval(function () {
                var form = $('input[name = "username"][type = text]').parents('form[name = "loginForm"]:eq(1)');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "username"]').val(params.login);
                    form.find('input[name = "password"]').val(params.pass);

                    clearInterval(login);

                    api.setNextStep('checkLoginErrors', function () {
                        form.find('a.submit').get(0).click();
                    });
                    setTimeout(function() {
                        plugin.autologin.checkLoginErrors();
                    }, 3000);
                }
                if (counter > 10) {
                    clearInterval(login);
                    browserAPI.log("can't find login form");
                    api.error("can't find login form");
                }
                counter++;
            }, 500);
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Number) !== 'undefined')
                && ($('span.loyaltyNumber:contains("' + params.properties.Number + '")').length > 0);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('p.error');
            if (error.length > 0)
                api.error(error.text().trim());
            else
                plugin.autologin.finish();
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('start', function () {
                document.location.href = $('a:contains("Sign Out")').attr('href');
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};