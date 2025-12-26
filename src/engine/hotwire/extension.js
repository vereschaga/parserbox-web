var plugin = {
    keepTabOpen: true,
    hosts: {'www.hotwire.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.hotwire.com/checkout/#!/account/myaccount/myinfo';
    },

    /*deprecated*/
    startFromChase: function(params) {
        plugin.loadLoginForm(params);
    },

    /*deprecated*/
    fromCashback: function (params) {
        browserAPI.log("fromCashback");
        plugin.loadLoginForm(params);
    },

    // for Cashback auto-login
    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var timeout = 5;
        var loginState = setInterval(function() {
            browserAPI.log("waiting... " + counter + "/" + timeout);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(loginState);
                browserAPI.log('logged in =' + isLoggedIn);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
                    plugin.login(params);
            }
            if (counter >= timeout) {
                clearInterval(loginState);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter += 1;
        }, 1000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('div.avatar').length)
            return true;
        if ($('form[name = "forms.signInForm"]').length)
            return false;
        return null;
    },

    isSameAccount: function (params) {
        browserAPI.log("isSameAccount");
        // for debug only
        // browserAPI.log("account: " + JSON.stringify(account));
        var email = $('p.email-address').text();
        browserAPI.log("email: " + email);
        return (
            params.account.login === email
        );
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function() {
            var link = $('a[ng-click = "doLogout()"]');
            if (link.length)
                link.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "forms.signInForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            var loginInput = form.find('input[name = "login"]');
            var passwordInput = form.find('input[name = "password"]');
            loginInput.val(params.account.login);
            passwordInput.val(params.account.password);
            util.sendEvent(loginInput.get(0), 'input');
            util.sendEvent(passwordInput.get(0), 'input');

            var submit = form.find('button[type = "submit"]')
            provider.setNextStep('checkLoginErrors', function() {
                submit.get(0).click();
            });
            plugin.checkLoginErrors(params);
        } else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        util.waitFor({
            selector: 'div.avatar',
            success: function() {
                plugin.loginComplete(params);
            },
            fail: function() {
                var errors = $('div.hw-alert-error');
                if (errors.length > 0)
                    provider.setError(errors.text());
                else
                    plugin.loginComplete(params);
            }
        });
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.hotwire.com/checkout/account/mytrips/upcoming';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('button.btn[data-bdd="more-detail"]');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function () {
                    document.location.href = 'https://www.hotwire.com/checkout/hotel/tripdetails/' + confNo + '/?fromAccount=true';
                });
            } else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 3000);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};
