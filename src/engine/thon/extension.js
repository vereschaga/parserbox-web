var plugin = {

    hosts: {
        'me.thonhotels.no': true,
        'www.thonhotels.com': true,
        'login.olavthon.no': true,
    },

    getStartingUrl: function (params) {
        return 'https://me.thonhotels.no/en/';
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
        }, 1000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[action *= "/Login"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button.main-menu__item-link:contains("Log out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $('div.profile-box__id:eq(0)').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber !== '')
            && number
            && (number === account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            let logout = $('button.main-menu__item-link:contains("Log out")');
            if (logout.length)
                logout.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form[action *= "/Login"]:visible');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        form.find('input#Username').val(params.account.login);
        form.find('input#Password').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button[value="login"]').click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let error = $('div.alert-danger:visible');
        if (error.length > 0 && util.trim(error.text()) !== '') {
            provider.setError(util.trim(error.text()));
            return;
        }
        provider.complete();
    }

};