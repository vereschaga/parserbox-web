var plugin = {

    hosts: {'upromise.force.com': true, 'www.upromise.com': true},

    getStartingUrl: function (params) {
        return 'https://www.upromise.com';
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
                        plugin.logout();
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

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[action = "openlogin"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.account-fullname').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = $('.account-fullname').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.Name) !== 'undefined')
            && (account.properties.Name !== '')
            && name
            && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[action = "logout"]').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let sign = $('a[action = "openlogin"]');
        if (sign.length) {
            sign.get(0).click();
        }
        setTimeout(function () {
            let form = $('#loginForm');
            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }
            browserAPI.log("submitting saved credentials");
            form.find('input[placeholder="Email"]').val(params.account.login);
            form.find('input[placeholder="Password"]').val(params.account.password);

            provider.setNextStep('checkLoginErrors', function () {
                browserAPI.log("click");
                form.find('input#regloginsubmit').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }, 1500);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.error:visible, div.alert-danger:visible');
        if (errors.length > 0 && util.filter(errors.text()) != '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    }

};
