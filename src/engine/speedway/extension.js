var plugin = {

    hosts: {'www.speedway.com': true, 'login.speedway.com': true},

    getStartingUrl: function (params) {
		return 'https://www.speedway.com/myaccount/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
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
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)

            // provider bug fix
            if ($('p:contains("Something\'s gone wrong on our end."):visible').length) {
                clearInterval(start);
                provider.setNextStep('loadLoginForm', function () {
                    document.location.href = 'https://www.speedway.com/logout';
                });
                return;
            }

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
        if ($('#loginForm:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= logout]').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('span.card_number').text(), /Card\s*#\s*([^<]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && number
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= logout]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[id = "loginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            util.setInputValue(form.find('input[name = "Email"]'), params.account.login);
            util.setInputValue(form.find('input[name = "Passcode"]'), params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[type = "submit"]').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 10000)
            });
        }
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div#validationSummary:visible');
        if (errors.length > 0 && util.filter(errors.text()) != '')
            provider.setError(errors.text());
        else {
            if ($('#loading:visible').length) {
                provider.setNextStep('loginComplete', function () {
                    document.location.href = 'https://www.speedway.com';
                });
                return;
            }
            plugin.loginComplete(params);
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};