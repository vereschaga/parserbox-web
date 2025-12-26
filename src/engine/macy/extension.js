var plugin = {

    hosts: {'www.macys.com': true, 'macys.com': true, 'www1.macys.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.macys.com/loyalty/starrewards?cm_sp=navigation-_-top_nav-_-star_rewards';
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

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = util.findRegExp($('#globalUserName'), /Hi, (.+)/i);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (typeof(name) != 'undefined')
            && (account.properties.Name.indexOf(name) !== -1));
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form#login-form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#globalHeaderSignout:visible, a[href *= "/myaccount/home"]').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.macys.com/signin/signout.ognc?cm_sp=navigation-_-top_nav-_-sign-out';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#login-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#email').val(params.account.login);
            form.find('input#pw-input').val(params.account.password);
            util.sendEvent(form.find('input#email').get(0), 'change');
            util.sendEvent(form.find('input#pw-input').get(0), 'change');
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#sign-in').get(0).click();
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
        var errors = $("div#ul-page-error:visible");
        if (errors.length == 0)
            errors = $('small.error_msg:eq(0):visible');
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
