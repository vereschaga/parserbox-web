var plugin = {

    hosts: {
        'www.qdoba.com': true,
        'qdoba.myguestaccount.com': true,
    },

    getStartingUrl: function (params) {
        return "https://qdoba.myguestaccount.com/guest/account-balance";
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

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a[href *= "logout=1"]').get(0).click();
        });
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href *= "logout=1"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form.loginForm:visible').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp($('span[code = "cardNumberAppend"]').last().text(), /\s*:\s*([^<]+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Card) != 'undefined')
            && (account.properties.Card != '')
            && (number == account.properties.Card));
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form.loginForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[id = "inputUsername"]').val(params.account.login);
            form.find('input[id = "inputPassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[id = "loginFormSubmitButton"]').click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('#lift__noticesContainer___error:visible');
        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
            return;
        }
        plugin.loginComplete();
    },

    loginComplete: function () {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};