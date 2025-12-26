var plugin = {
    autologin: {
        url: 'https://m.hyatt.com/mt/www.hyatt.com/un_myaccount',

        start: function () {
            browserAPI.log("start");
            var start = setInterval(function () {
                var btn = $('a:contains("SIGN IN")');
                if ($('#unLogoutBtn:not([style *= "display:none"])').find('form[action *= "logout"]').length > 0 || btn.length > 0) {
                    if (btn.length > 0) {
                        api.setNextStep('start2', function(){
                            btn.get(0).click();
                        });
                    }
                    clearInterval(start);
                    plugin.autologin.start2();
                }
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
            if ($('a:contains("SIGN IN")').length > 0) {
                api.eval("utils.showhideLoginMenu();");
                browserAPI.log('not logged in');
                return false;
            }
            if ($('#unLogoutBtn:not([style *= "display:none"])').find('form[action *= "logout"]').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        login: function () {
            browserAPI.log("login");
            // open menu
            $('div#unHeader').find('a:has(img[alt = menu_icon])').get(0).click();
            // click "SIGN IN"
            $('a span:contains("SIGN IN")').get(0).click();
            // log in
            var form = $('#login_form');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "un_user"]').val(params.login);
                form.find('input[name = "un_psw"]').val(params.pass);
                api.setNextStep('checkLoginErrors', function() {
                    form.submit();
                    setTimeout(function() {
                        plugin.autologin.checkLoginErrors();
                    }, 2000)
                });
            } else {
                browserAPI.log("can't find login form");
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return (typeof(params.properties) != 'undefined' &&
                typeof(params.properties.Number) != 'undefined' &&
                params.properties.Number != '' &&
                $('div.un_upper:contains("'+ params.properties.Number +'")').length > 0);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('div[class = "un_error margT"]');
            if (error.length > 0 && error.text().length > 5)
                api.error(error.text());
            else {
                api.setNextStep('finish', function(){
                    setTimeout(function() {
                        document.location.reload();
                    }, 2000)
                });
            }
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('toLoginPage', function () {
                $('form[action *= "logout"]').find('input[name *= "un_jtt_logout"]').get(0).click();
            });
        },

        toLoginPage: function(){
            api.setNextStep('login', function(){
                document.location.href = plugin.autologin.url;
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};