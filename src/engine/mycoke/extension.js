var plugin = {

    hosts: {
        'www.mycokerewards.com': true,
        'us.coca-cola.com': true,
        'coca-cola.com': true,
        'login.us.coca-cola.com': true,
    },

    getStartingUrl: function(params) {
        return 'https://us.coca-cola.com';
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
                    plugin.loadLoginForm(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    isLoggedIn: function(params) {
        browserAPI.log("step isLoggedIn");
        if (!$('#loginRegWrapper.authenticated').length) {
            browserAPI.log("isLoggedIn = false");
            return false;
        }
        if ($('#loginRegWrapper.authenticated').length) {
            browserAPI.log("isLoggedIn = true");
            return true;
        }
        return null;
    },

    isSameAccount: function(account) {
        browserAPI.log('isSameAccount');
        browserAPI.log('isSameAccount = false');
        return false;
    },

    logout: function(params) {
        browserAPI.log('step logout');
        provider.setNextStep('startRedirect', function() {
            $('a#capture_signout_link').get(0).click();
        });
    },

    startRedirect: function(params) {
        browserAPI.log('step startRedirect');
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl();
        });
    },

    loadLoginForm: function(params) {
        browserAPI.log('step loadLoginForm');
        provider.setNextStep('login', function() {
            $('a:contains("Sign In")').get(0).click();
        });
        util.waitFor({
            selector: 'input#localAccountForm:visible',
            success: function() {
                plugin.login(params);
            },
            fail: function() {
                provider.setError(util.errorMessages.loginFormNotFound);
            },
            timeout: 10
        });
    },

    login: function(params) {
        browserAPI.log("step login");
        let form = $('#localAccountForm');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        form.find('input#signInName').val(params.account.login);
        form.find('input#password').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function() {
            form.find('button#next').get(0).click();
            plugin.checkLoginErrors(params);
        });
    },

    checkLoginErrors: function(params) {
        browserAPI.log('step checkLoginErrors');
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let errors = $('div.error:visible');
            if (errors.length && util.filter(errors.text()) !== '') {
                clearInterval(start);
                provider.setError(util.filter(errors.text()));
                return;
            }
            if (counter > 10) {
                clearInterval(start);
                provider.complete();
                return;
            }
            counter++;
        }, 500);
    }
};
