var plugin = {

    hosts: {'/\\w+\\.makemytrip\\.com/': true},

    getStartingUrl: function (params) {
        return 'https://supportz.makemytrip.com/Mima/BookingSummary/';
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
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
                    plugin.redirectToLogin(params);
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
        if ($('span.chUserInfoName').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('input#username').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return false;
    },

    logout: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            $('span.chUserInfoName').click();
            util.waitFor({
                selector: 'a[data-cy = "userMenuLogout"]',
                success: function (elem) {
                    provider.setNextStep('redirectToLogin', function () {
                        elem.get(0).click();
                    });
                },
                fail: function () {
                },
            });
        });
    },

    redirectToLogin: function (params) {
        browserAPI.log("redirectToLogin");
        provider.setNextStep('login', function() {
            document.location.href = 'https://supportz.makemytrip.com/login';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('div#userLoginSection');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#ch_login_email').val(params.account.login);
            form.find('input#ch_login_password').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button#ch_login_btn').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    loginOld: function (params) {
        browserAPI.log("login");
        var emailInput = $('input#mobileOrEmailId');
        if (emailInput.length > 0) {
            browserAPI.log("submitting email");
            emailInput.val(params.account.login);
            // refs #11326
            if (emailInput.length > 0)
                util.sendEvent(emailInput.get(0), 'input');
            setTimeout(function() {
                var nextButton = $('input[value = "NEXT"]');
                nextButton.get(0).click();
                util.waitFor({
                    selector: 'input[name = "password"]',
                    success: function(elem) {
                        elem.val(params.account.password);
                        if (elem.length > 0)
                            util.sendEvent(elem.get(0), 'input');
                        browserAPI.log("submitting password");
                        provider.setNextStep('checkLoginErrors', function() {
                            var loginButton = $('input[value = "LOGIN"]');
                            loginButton.get(0).click();
                        });
                    },
                    fail: function() {
                        provider.setError(util.errorMessages.loginFormNotFound);
                    },
                });
            }, 2000);
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    loginStep2Old: function (params) {
        browserAPI.log("login");
        var form = $('form.login-form-container:has(#emailId)');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            var emailInput = form.find('input[id = "emailId"]');
            var passwordInput = form.find('input[id = "password"]');
            emailInput.val(params.account.login);
            passwordInput.val(params.account.password);
            // refs #11326
            if (emailInput.length > 0)
                util.sendEvent(emailInput.get(0), 'input');
            if (passwordInput.length > 0)
                util.sendEvent(passwordInput.get(0), 'input');
            provider.setNextStep('checkLoginErrors', function(){
                form.find('span:contains("Login")').click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrorsOld: function (params) {
        browserAPI.log("checkLoginErrors");
        var error = $('div.error_inline:visible:eq(0)');
        if (error.length > 0 && util.filter(error.text()) !== '')
            provider.setError(util.filter(error.text()));
        else
            plugin.loginComplete(params);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var error = $('div.ch-error-msg:visible:eq(0)');
        if (error.length > 0 && util.filter(error.text()) !== '')
            provider.setError(util.filter(error.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        if (typeof(params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId > 0
        ) {
            provider.setNextStep('itLoginComplete', function() {
                document.location.href = 'https://supportz.makemytrip.com/Mima/BookingSummary/';
            });
            return;
        }
        plugin.itLoginComplete(params);
    },

    toItinerariesOld: function (params) {
        browserAPI.log('toItineraries');
        var counter = 0;
        var confNo = params.account.properties.confirmationNumber;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('li:has(p:contains("' + confNo + '"))');
            if (link.length > 0) {
                clearInterval(toItineraries);
                link.click();
                plugin.itLoginComplete(params);
            }// if (link.length > 0)
            if (counter > 10) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    itLoginComplete: function (params) {
        browserAPI.log('Complete');
        provider.complete();
    }

}
