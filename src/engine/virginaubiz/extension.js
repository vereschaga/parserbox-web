var plugin = {

    hosts: {
        'business.velocityfrequentflyer.com': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://business.velocityfrequentflyer.com/#/corporate/overview';
    },

    start: function (params) {
        browserAPI.log("start");
        let isLoggedIn = plugin.isLoggedIn(params);
        if (isLoggedIn === null) {
            provider.setNextStep('preLoad', function () {
            });
        } else {
            plugin.handleLogin(params);
        }
    },

    preLoad: function (params) {
        browserAPI.log("preLoad");
        let isLoggedIn = plugin.isLoggedIn(params);

        if (isLoggedIn === null) {
            provider.setNextStep('start', function () {
            });
        } else {
            plugin.handleLogin(params);
        }
    },

    handleLogin: function (params) {
        browserAPI.log("handleLogin");
        let counter = 0;
        let start = setInterval(async function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (await plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);

                } else
                    plugin.login(params);

                return;
            }// if (isLoggedIn !== null)

            if (isLoggedIn === null && counter > 15) {

                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }

            counter++;
        }, 1000);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");

        if (document.getElementsByClassName('form-signin').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('.name2:visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: async function (account) {
        browserAPI.log("isSameAccount");
        let number;
        let counter = 0;
        do {
            if (counter === 10) break;
            number = $('.digital-card .number').text();
            if (number.length === 0) {
                browserAPI.log("waiting... " + counter);
                await new Promise(res => setTimeout(res, 500));
            }
            counter++;
        }
        while (number.length === 0)
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Number) != 'undefined')
                && (account.properties.Number !== '')
                && number
                && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('.dropdown-menu a[data-test=logouttest]').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            provider.setTimeout(function () {
                document.getElementById('0').click();
            }, 4000);
        });
    },

    login: function (params) {
        browserAPI.log("login");

        let form = $('.form-signin');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");

        // reactjs
        provider.eval(
            "function triggerInput(selector, enteredValue) {\n" +
            "      let input = document.querySelector(selector);\n" +
            "      input.dispatchEvent(new Event('focus'));\n" +
            "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
            "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
            "      nativeInputValueSetter.call(input, enteredValue);\n" +
            "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
            "      input.dispatchEvent(inputEvent);\n" +
            "}\n" +
            "triggerInput('#id_user_id', '" + params.account.login + "');\n" +
            "triggerInput('#id-psw', '" + params.account.password + "');"
        );

        return provider.setNextStep('preCheckLoginErrors', function () {
            document.getElementById('id-login-btn').click();
        });
    },

    preCheckLoginErrors: function (params) {
        browserAPI.log("preCheckLoginErrors");

        return provider.setNextStep('checkLoginErrors', function () {
            setTimeout(function () {
                plugin.preCheckLoginErrors(params);
            }, 10000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('form .alert');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },
};