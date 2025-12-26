var plugin = {

    hosts: {
        'login.gamestop.com': true,
        'www.poweruprewards.com': true,
        'www.gamestop.com': true,
        'm.gamestop.com': true
    },
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.gamestop.com/account/';
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
        if ($('a[href *= "logout"]:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[name = "login-form"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.filter($('div.rewards-membership-id > span').text());
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.PowerUpRewardsId) != 'undefined')
            && (account.properties.PowerUpRewardsId != '')
            && number
            && (number == account.properties.PowerUpRewardsId));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= "logout"]:visible').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "login-form"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "loginEmail"]').val(params.account.login);
            form.find('input[name = "loginPassword"]').val(params.account.password);
            return provider.setNextStep('checkLoginErrors', function () {
                form.find('button[type="submit"]').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.alert-danger:visible, div.invalid-feedback:visible');
        if (errors.length > 0 && util.filter(errors.text()) != '')
            provider.setError(util.filter(errors.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};
