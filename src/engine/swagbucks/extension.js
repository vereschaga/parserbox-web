var plugin = {

    hosts: {'www.swagbucks.com': true},

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'http://www.swagbucks.com/p/login';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();

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
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#loginForm').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#sbLogOutCta, span:contains("Log Out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        return false;
        const name = $('p.accountname').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('#sbLogOutCta').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('#loginForm');

        if (form.length > 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "emailAddress"]').val(params.account.login);
        form.find('input[name = "password"]').val(params.account.password);
        setTimeout(function () {
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function() {
                    const captcha = form.find('section#sbCaptcha:visible');
                    if (captcha && captcha.length > 0) {
                        browserAPI.log("waiting...");
                        provider.reCaptchaMessage();
                        setTimeout(function() {
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                        }, 120000);
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        form.submit();
                    }
                }, 2000)
            });
        }, 1000)
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.divErLandingPage:visible, p#loginErrorMessage:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    }
};