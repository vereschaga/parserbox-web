var plugin = {

    hosts: {
        'www.thankyou.com'   : true,
        'online.citibank.com': true,
        'online.citi.com'    : true,
        'www.citi.com'       : true
    },
    clearCache: true,

    getStartingUrl: function (params) {
		return 'https://www.thankyou.com/';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            if ($('.sign-on > a').text().trim() == 'Sign On') {
                clearInterval(start);
                browserAPI.log("not LoggedIn");
                provider.setNextStep('login', function () {
                    window.location.href = 'https://www.citi.com/citi-partner/thankyou/login?userType=tyLogin&locale=en_US&TYNewUser=false&TYForgotUUID=false&TYMigration=&SAMLPostURL=https:%2F%2Fwww.thankyou.com%2F%2Fgateway2.htm&ErrorCode=&TYPostURL=https:%2F%2Fwww.thankyou.com%2F%2FtyLoginGateway.htm&cmp=null';
                });
                return;
            }
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
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[name = "tySignonForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= logout]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        // Citibank
        if ($('a.signOffBtn').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.findRegExp( $('li:contains("ThankYou Account")').text(), /Account\s*([^<]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number == account.properties.AccountNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            if (params.account.login2 == 'Citibank')
                $('a.signOffBtn').get(0).click();
            else
                document.location.href = 'https://www.thankyou.com/logout.jspx';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if ($('a.signOffBtn').length > 0) {
            plugin.logout(params);
            return false;
        }
        // the provider's website does not support IE
        if ($.browser.msie)
            provider.complete();
        setTimeout(function() {
            let form = $('form[name = "partnerLoginForm"]');
            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }
            browserAPI.log("submitting saved credentials");
            form.find('input[id = "username"]').val(params.account.login);
            form.find('input[id = "password"]').val(params.account.password);
            let value = 'thankYou';
            if (params.account.login2 === 'Citibank') {
                value = 'citiCards';
            } else if (params.account.login2 === 'Sears') {
                value = 'sears';
            }
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
            triggerInput('input[id = "password"]', '' + params.account.password );
            triggerInput('input[name = "IdStrHiddenInput"]', '' + value);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#signInBtn').click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }, 1000)
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.critical:visible span.strong, span.validation-message-danger:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    }

};