var plugin = {
	
    hosts: {'www.landrysselect.com': true},

    getStartingUrl: function (params) {
        return 'https://www.landrysselect.com/summary/';
    },
	
	gotoStart: function (params) {
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
        if ($('form[action = "/login/Login/"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a#logout').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $('.login-side-bar p:first').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('gotoStart', function () {
            $('a#logout').get()[0].click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[action = "/login/Login/"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "LoginPostbackData.Email"]').val(params.account.login);
            form.find('input[name = "LoginPostbackData.Password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input#loginButton').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('.field-validation-error:visible, .validation-summary-errors li:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }

}