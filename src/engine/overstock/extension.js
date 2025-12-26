var plugin = {

    hosts: {'www.overstock.com': true},
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.overstock.com/myaccount?myacckey=clubo_rewards';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
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
                        plugin.loginComplete(params);
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
        var loggedIn = $('a[class *= "UserDropDowns_iconContainer_"][href ="/myaccount"]');
        if (provider.isMobile) {
            loggedIn = $("a#profile-menu-item");
        }
        if (loggedIn.length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#login-form').length > 0 || $('form#loginForm').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return false;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.overstock.com/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#login-form');
        if (form.length === 0) {
            form = $('form#loginForm');
        }
        var idEmail = form.find('input[name *= "email"]').attr('id');
        var idPassword = form.find('input[name *= "password"]').attr('id');

        browserAPI.log(idEmail);
        if (form.length > 0
            && typeof (idEmail) != "undefined"
            && idEmail !== null
            && typeof (idPassword) != "undefined"
            && idPassword !== null) {
            browserAPI.log("submitting saved credentials");

            provider.eval(
                "var setValue = function (id, value) {" +
                "let input = document.querySelector('input[id = ' + id + ']');" +
                "let lastValue = input.value;" +
                "input.value = value;" +
                "let event = new Event('input', { bubbles: true });" +
                "event.simulated = true;" +
                "let tracker = input._valueTracker;" +
                "if (tracker) {" +
                "   tracker.setValue(lastValue);" +
                "}" +
                "input.dispatchEvent(event);" +
                "};" +
                "setValue('" + idEmail + "', '" + params.account.login + "');" +
                "setValue('" + idPassword + "', '" + params.account.password + "');"
            );
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    form.find('button[type = "submit"]').click();
                }, 1000);
            });
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.page-errors div.danger:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
}