var plugin = {

    hosts: {'www.petsmart.com': true},

    getStartingUrl: function (params) {
        return 'https://www.petsmart.com/on/demandware.store/Sites-PetSmart-Site/default/Account-Treats';
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
        if ($('a[href *= "Logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#signInForm').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = util.findRegExp($('span:contains("hi,")').text(), /,\s*([^<]+)/);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && account.properties.Name.toLowerCase().indexOf(name.toLowerCase()) !== -1);
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep("start", function () {
            $('a[href *= "Logout"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#signInForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[id = "login"]').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.login-errors:visible');
        if (errors.length == 0)
            errors = $('span.error:visible:eq(0)');
        if (errors.length > 0 && util.filter(errors.text()) != '')
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

}