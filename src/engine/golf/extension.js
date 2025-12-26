var plugin = {

    hosts: {'www.golfgalaxy.com': true, 'www.mygolfgalaxy.com': true, 'sso.golfgalaxy.com': true,},

    getStartingUrl: function (params) {
        return 'https://www.golfgalaxy.com/MyAccount/AccountSummary';
    },

    loadLoginForm: function(){
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
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
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
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
        if ($('a[data-testid="sign-out"]:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[method="POST"] input[name="username"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.filter( $('.mat-card-subtitle:not(.member-name):visible').text() );
        browserAPI.log("number: " + number);
            return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && (number == account.properties.CardNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[data-testid="sign-out"]').get(0).click();
            if (provider.isMobile) {
                setTimeout(function () {
                    plugin.loadLoginForm(params);
                }, 2000);
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[method="POST"]');
        if (form.length > 0) {
            var login = form.find('input[name="username"]');
            var pass = form.find('input[name="password"]');
            login.val(params.account.login);
            pass.val(params.account.password);
            util.sendEvent(login.get(0), 'input');
            util.sendEvent(pass.get(0), 'input');
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[name="action"]').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('#login-validation-alert:visible');
        if (errors.length > 0 && util.filter(errors.text()) != "")
            provider.setError(util.filter(errors.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};