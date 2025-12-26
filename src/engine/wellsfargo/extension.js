var plugin = {

    hosts: {
        'online.wellsfargo.com': true,
        'mywellsfargorewards.com': true,
        'connect.secure.wellsfargo.com': true,
        'www.mywellsfargorewards.com': true,
        'gofarrewards.wf.com': true,
        'www.wellsfargo.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.wellsfargo.com/';
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[id = "frmSignon"]').length > 0) {
            browserAPI.log("not LoggedIn");
            if (provider.isMobile && $('#signOnham').length > 0) {
                $('#signOnham').click();
            }
            return false;
        }
        if ($('a[href *= logout]').attr('href')) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        return false;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://connect.secure.wellsfargo.com/auth/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[id = "frmSignon"]');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        form.find('input[id = "userid"]').val(params.account.login);
        form.find('input[id = "password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('input[name = "btnSignon"], input[value = "Sign On"]').get(0).click();
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        setTimeout(function() {
            var errors = $('div[class = "alert"]:visible');
            if (errors.length > 0)
                provider.setError(errors.text());
            else
                provider.complete();
        }, 2000)
    }

};