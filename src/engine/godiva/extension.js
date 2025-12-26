var plugin = {

    hosts: {'www.godiva.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.godiva.com/sign-in';
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
        if ($('form[name = "login-form"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "Logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.filter($('dt:contains("Rewards Club No.") + dd:eq(0)').text())+1;
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.MembershipNumber) != 'undefined')
            && (account.properties.MembershipNumber != '')
            && (number == account.properties.MembershipNumber));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= "Logout"]:eq(0)').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "login-form"]');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        form.find('input[name *= "loginEmail"]').val(params.account.login);
        form.find('input[name = "loginPassword"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button.btn-block').click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.alert-danger:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};
