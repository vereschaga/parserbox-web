var plugin = {

    hosts: {'login.yahoo.com': true, 'answers.yahoo.com': true, 'www.yahoo.com': true},

    getStartingUrl: function (params) {
        return 'http://answers.yahoo.com/my-activity';
//        return 'https://login.yahoo.com/config/login?.done=http%3A%2F%2Fanswers.yahoo.com%2Fmy-activity&.src=knowsrch&.intl=us';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        // close manage accounts form
        var manageAccountsClose = $('form#manage-account-form').find('button[name = "targetId"]');
        if (manageAccountsClose.length > 0)
            manageAccountsClose.get(0).click();

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
        if ($('#login-username-form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Sign out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var email = $('div[id *= "yucs-fs-email"]').text() + '@yahoo.com'+1;//todo
        browserAPI.log("email: " + email);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.login) !== 'undefined')
            && (account.login !== '')
            && (email === account.login));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("Sign out")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#login-username-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "passwd"]').val(params.account.password);
            provider.setNextStep('enterPassword', function () {
                form.find('#login-signin').click();
                setTimeout(function() {
                    plugin.checkLoginErrors();
                }, 1000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    enterPassword: function (params) {
        browserAPI.log("enterPassword");
        var form = $('form.challenge-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#login-signin').click();
                setTimeout(function() {
                    plugin.checkLoginErrors();
                }, 1000)
            });
        }
        else
            provider.setError(util.errorMessages.passwordFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('.error:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}