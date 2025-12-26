var plugin = {

    hosts: {'www.audiencerewards.com': true},

    getStartingUrl: function (params) {
        return 'https://www.audiencerewards.com/member/dashboard';
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
        if ($('form#loginForm').length > 0) {
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
        var number = util.findRegExp( $('strong:contains("Your Account Number is:")').parent('div').text() , /:\s*([\d]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            $('a[href *= "logout"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#loginForm');
        browserAPI.log("waiting");
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.submit();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.alert-dismissable:visible');
        if (errors.length > 0) {
            var errorText = util.findRegExp(errors.text(), /\×Close\s*(.+)/);
            if (!errorText)
                errorText = errors.text();
            provider.setError(errorText);
        }// if (errors.length > 0)
        else
            provider.complete();
    }
}