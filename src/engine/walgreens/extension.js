var plugin = {

    hosts: {
        'www.walgreens.com': true,
        'shop.walgreens.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.walgreens.com/youraccount/default.jsp';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log('start');
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout();
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
        browserAPI.log('isLoggedIn');
        if ($('form > div[name = "username-form"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Sign Out")').length > 0 || $('a:contains("sign out"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("Sign Out"), a:contains("sign out")').get(0).click();
        });
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        let number = util.findRegExp( $('strong:contains("Membership #")').parent('div').text(), /#\s*([^<]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number === account.properties.AccountNumber));
    },

    login: function (params) {
        browserAPI.log('login');
        let form = $('form > div[name = "username-form"]');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }// if (form.length === 0)
        browserAPI.log("submitting saved credentials");
        // // angularjs 10
        // if (provider.isMobile) {
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
            triggerInput('#user_name', '' + params.account.login );
            triggerInput('#user_password', '' + params.account.password);
        // } else {
        //     provider.eval(
        //         "function triggerInput(enteredName, enteredValue) {\n" +
        //         "      const input = document.querySelector(enteredName);\n" +
        //         "      var createEvent = function(name) {\n" +
        //         "            var event = document.createEvent('Event');\n" +
        //         "            event.initEvent(name, true, true);\n" +
        //         "            return event;\n" +
        //         "      }\n" +
        //         "      input.dispatchEvent(createEvent('focus'));\n" +
        //         "      input.value = enteredValue;\n" +
        //         "      input.dispatchEvent(createEvent('change'));\n" +
        //         "      input.dispatchEvent(createEvent('input'));\n" +
        //         "      input.dispatchEvent(createEvent('blur'));\n" +
        //         "};\n" +
        //         "triggerInput('#user_name', '" + params.account.login + "');\n" +
        //         "triggerInput('#user_password', '" + params.account.password + "');"
        //     );
        // }
        provider.setNextStep('checkLoginErrors', function () {
            setTimeout(function() {
                $('button#submit_btn').get(0).click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 5000);
            }, 1000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        let errors = $('#error_msg:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        provider.complete();
    }
};
