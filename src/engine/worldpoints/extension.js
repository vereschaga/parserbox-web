var plugin = {

    hosts: {
        'www.heathrow.com': true,
        'heathrow.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.heathrow.com/rewards/home?login=Login%20Succcessful';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function(){
            document.location.href = plugin.getStartingUrl();
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
                } else
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
        if ($('#login-form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('div.card-value').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.filter($('div.card-value').text().replace(/\s/g, ''));
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && number
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a.logout-cta:eq(0)').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#login-form');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        params.account.login = params.account.login.replace(/\s/g, '');
        form.find('input[id = "username"]').val(params.account.login);
        form.find('input[id = "usercardnumber"]').val(params.account.login2);
        form.find('input[id = "userpassword"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button.login-button').click();

            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000)
        });
    },

    checkLoginErrors: function (params) {
        var errors = $('div.validation-error-message:visible');
        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
        }
        else
            provider.complete();
    }
};