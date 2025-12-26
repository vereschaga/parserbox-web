var plugin = {
	autologin: {
        //url: "https://ssl.omnihotels.com/Omni?pagesrc=SG6&pagedst=SG6&Phoenix_state=clear",
        url : 'https://m.omnihotels.com/h5/index?pagedst=SI',

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                if ($('form.loginForm:visible').length || $('a:contains("Sign Out")').length) {
                    clearInterval(start);
                    plugin.autologin.start2();
                }

                if (++counter > 10) {
                    clearInterval(start);
                    api.error("Can't determine state");
                }
            }, 700);
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
            if ($('a:contains("Sign Out")').length) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form.loginForm').length) {
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
                var form = $('form.loginForm');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    $('#loginName').val(params.login);
                    $('#password').val(params.pass);
                    clearInterval(login);

                    api.setNextStep('checkLoginErrors', function () {
                        api.eval("document.getElementById('loginBTN').click();");
                    });
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
                && ($('span:contains("' + params.properties.Number + '")').length > 0);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var counter = 0;
            var checkLoginErrors = setInterval(function () {
                browserAPI.log("waiting... " + checkLoginErrors);
                var error = $('form[name = "navFormSI"]').find('p.has-error:not([style *= "display:none"])');
                if (error.length > 0) {
                    clearInterval(checkLoginErrors);
                    api.error(error.text().trim());
                }
                if (counter > 5) {
                    clearInterval(checkLoginErrors);
                    plugin.autologin.finish();
                }
                counter++;
            }, 500);
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('start', function () {
                document.location.href = $('a.btn2:contains("Sign Out")').attr('href');
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};
