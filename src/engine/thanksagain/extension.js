var plugin = {

    hosts: {
        'www.thanksagain.com': true,
        'sso.thanksagain.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.thanksagain.com/account#profile';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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
                    setTimeout(function () {
                        plugin.login(params);
                    }, 2000)
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
        if ($('form[id = "kc-form-login"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        let signIn = $('a:contains("Sign In"):visible');
        if (signIn.length > 0) {
            browserAPI.log("not LoggedIn");
            signIn.get(0).click();
            return false;
        }
        if ($('p:contains("Hi, "):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.trim($('input[name = "email"]').val());
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.login) != 'undefined')
            && (account.login !== '')
            && (number == account.login));
            // && (typeof(account.properties.Number) != 'undefined')
            // && (account.properties.Number != '')
            // && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://sso.thanksagain.com/auth/realms/thanksagain/protocol/openid-connect/logout?redirect_uri=https%3A%2F%2Fwww.thanksagain.com';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form[id = "kc-form-login"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");

        // angularjs 10
        function triggerInput(selector, enteredValue) {
            const input = document.querySelector(selector);
            var createEvent = function(name) {
                var event = document.createEvent('Event');
                event.initEvent(name, true, true);
                return event;
            };
            input.dispatchEvent(createEvent('focus'));
            input.value = enteredValue;
            input.dispatchEvent(createEvent('change'));
            input.dispatchEvent(createEvent('input'));
            input.dispatchEvent(createEvent('blur'));
        }
        triggerInput('input[id = "username"]', '' + params.account.login );
        triggerInput('input[name = "password"]', '' + params.account.password );

        provider.setNextStep('checkLoginErrors', function () {
            form.find('input[id = "kc-login"]').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 4000);
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        let errors = $('#input-error:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    }

};