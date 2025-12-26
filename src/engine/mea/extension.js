var plugin = {

    hosts: {'www.mea.com.lb': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.mea.com.lb/english/my-cedar-miles/my-account';
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
        if ($('#myAccountForm:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (
            $('li.loggedIn:not([class *= "displayNone"]) a[href *= "logout"]').length > 0
            || (provider.isMobile && $('div.cardHolder > div:eq(1)').length > 0)
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $('div.cardHolder > div:eq(1)').text();
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && number
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= "logout"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#myAccountForm');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        form.find('#MembershipNumber').val(params.account.login);
        form.find('#LoginPassword').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            // form.find('#myAccountSubmitButton').trigger('click');
            provider.eval("$('#myAccountSubmitButton').click()");
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000)
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('label#signInErrorMsg:visible');
        if (errors.length > 0)
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }

};
