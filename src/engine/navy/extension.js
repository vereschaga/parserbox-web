var plugin = {

    hosts: {'www.navyfederal.org': true, 'my.navyfederal.org': true, 'myaccounts.navyfederal.org': true, 'sso-myaccounts.navyfederal.org': true, 'ssoauth.navyfederal.org': true, 'signon.navyfederal.org': true, 'strongauth.navyfederal.org': true},

    getStartingUrl: function (params) {
        return 'https://www.navyfederal.org/nfoaa-signin.php';
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
        var form = $('form[id = "Login"]');
        if (form.length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a#SingOutLnk').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = $('span.username').text().trim();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name == account.properties.Name));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a#SingOutLnk').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[id = "Login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "USER"]').val(params.account.login);
            form.find('input[name = "PASSWORD"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                var signIn = form.find('input[name = SignIn]');
                signIn.removeAttr('disabled');
                setTimeout(function () {
                    signIn.get(0).click();
                }, 1000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('p#SignOn-Error:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}