var plugin = {
    
    hosts: {'www.flyuia.com': true, '/\\w+.flyuia.com/': true},

    getStartingUrl: function (params) {
        return 'https://new.flyuia.com/us/en/panorama-club/';
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
        if ($('#login').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return false;
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            document.location.href = 'http://www.flyuia.com/panorama-club/LogOut';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#login');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "Login"]').val(params.account.login);
            form.find('input[name = "Password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[type = "submit"]').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        var errors = $('div#response-modal h3#response-title');
        if (errors.length === 0)
            errors = $('div#qtip-2 >div:contains("Card number should contain 10 digits."):visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
};