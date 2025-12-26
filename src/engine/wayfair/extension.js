var plugin = {

    hosts: {'www.wayfair.com': true},

    getStartingUrl: function (params) {
        return 'https://www.wayfair.com/session/secure/account/login.php';
    },

    fromCashback: function (params) {
        browserAPI.log("fromCashback");
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
        if ($('form.LoginCreate-loginForm').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=logout]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.findRegExp($('h2.MyAccountDropdown-title').text(), /Welcome\s*([^\!]+)/i);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function() {
            document.location.href = 'https://www.wayfair.com/session/secure/account/logout.php?logout=1';
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
        var form = $('form.LoginCreate-loginForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // reactjs
            provider.eval(
                "var FindReact = function (dom) {" +
                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                "        return dom[key];" +
                "    }" +
                "    return null;" +
                "};" +
                "FindReact(document.querySelector('input[name = \"email\"]')).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});"
            );
            form.find('button[type="submit"]:contains("Continue")').click();
            setTimeout(function () {
                provider.eval(
                    "var FindReact = function (dom) {" +
                    "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                    "        return dom[key];" +
                    "    }" +
                    "    return null;" +
                    "};" +
                    "FindReact(document.querySelector('input[name = \"password\"]')).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
                );
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button[type="submit"]:contains("Sign In")').click();
                });
            }, 1500);
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        var errors = $('p.pl-InputValidationText:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        provider.complete();
    }
};