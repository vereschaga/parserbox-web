var plugin = {

    hosts: {'oldchicago.com': true},

    getStartingUrl: function (params) {
        return 'https://oldchicago.com/account/rewards';
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
        if ($('form:has(input[id = "emailOrPhone"])').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return null;
        // var name = $('h1.user').text();
        // browserAPI.log("name: " + name);
        // return ((typeof(account.properties) != 'undefined')
        //     && (typeof(account.properties.Name) != 'undefined')
        //     && (account.properties.Name != '')
        //     && (name.toLowerCase() == account.properties.Name.toLowerCase() ));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            $('a[href *= "logout"]').get(0).click();
            setTimeout(function () {
                document.location.href = plugin.getStartingUrl(params);
            }, 1000)
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form:has(input[id = "emailOrPhone"])');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[id = "emailOrPhone"]').val(params.account.login);
            form.find('input[id = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                // reactjs
                provider.eval(
                    "var FindReact = function(dom) {"
                    + "for (var key in dom)"
                    + "if (0 == key.indexOf(\"__reactInternalInstance$\")) {"
                    + "var compInternals = dom[key]._currentElement;"
                    + "var compWrapper = compInternals._owner;"
                    + "var comp = compWrapper._instance;"
                    + "return comp;"
                    + "}"
                    + "return null;"
                    + "};"
                    + "var a = FindReact(document.getElementById('emailOrPhone'));"
                    + "a.setState({emailOrPhone: '" + params.account.login + "', password: '" + params.account.password + "'});"
                );
                form.find('div:contains("Login")').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div._1nye9ibkhAUMj_s8HZNelF:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}