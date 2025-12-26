var plugin = {

    hosts: {'gomastercard-online.gomastercard.com.au': true},

    getStartingUrl: function (params) {
        return 'https://gomastercard-online.gomastercard.com.au/wps/myportal/gomc';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function() {
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
        if ($('#login-submit').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a.logout-link').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.findRegExp( $('span.weclomeMessage').text(), /Welcome\s*back\s*([^<\.]+)/i);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function() {
            $('a.logout-link').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form.s_loginforms');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "USER"]').val(params.account.login);
            form.find('input[name = "PASSWORD"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[name = "SUBMIT"]').click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('span.errors:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}