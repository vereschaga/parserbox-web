var plugin = {

    hosts: {'www.quidco.com': true, 'quidco.com': true},

    getStartingUrl: function (params) {
        return 'https://www.quidco.com/settings/general/';
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

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp($('script:contains("js_user_id")').text(), /js_user_id = '([^']+)',/ig);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number == account.properties.AccountNumber));
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form#sign-in-page-form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href*="/logout/"]:contains("Sign out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.quidco.com/logout';
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = "https://www.quidco.com/sign-in";
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#sign-in-page-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name="username"]').val(params.account.login);
            form.find('input[name="password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[name="login"]').get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $(".alert-text:visible");
        if (errors.length > 0 && !/You have successfully signed in/.test(errors.text()))
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }
}