var plugin = {
    // keepTabOpen: true,//todo
    hosts: {'www.longos.com': true},

    getStartingUrl: function (params) {
        return 'https://www.longos.com/my-account/cards-and-points';
    },

    getFocusTab: function (account, params) {
        return true;
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));

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
                        plugin.logout(params);
                }
                else {
                    const singIn = $('a[href*="/user-login"]');
                    if (singIn.length) {
                        provider.setNextStep('login', function () {
                            browserAPI.log("click 'singIn'");
                            singIn.get(0).click();
                            setTimeout(function () {
                                browserAPI.log("force call login");
                                plugin.login(params);
                            }, 1000);
                        });
                        return;
                    }
                    plugin.login(params);
                }
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
        if ($('form[class *= "sign-in__input-container"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (
            $('button[aria-controls="logout"]:visible').length > 0
            || $('span.barcode-number:eq(0):visible').length > 0
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp($('span.barcode-number:eq(0)').text(), /(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            // const logout = $('button[aria-controls="logout"]:visible');
            // if (logout.length) {
            //     logout.get(0).click();
            //     setTimeout(function () {
                    document.location.href = 'https://www.longos.com/logout';
            //     }, 3000);
            // }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form[class *= "sign-in__input-container"]:visible');
        if (form.length === 0) {
            provider.logBody("lastPage");
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        provider.logBody("loginPage");
        browserAPI.log("submitting saved credentials");
        // form.find('input[name=mail-example-com]').val(params.account.login);
        // form.find('input[name=password]').val(params.account.password);

        // angularjs 10
        provider.eval(
            "function triggerInput(enteredName, enteredValue) {\n" +
            "      const input = document.querySelector(enteredName);\n" +
            "      var createEvent = function(name) {\n" +
            "            var event = document.createEvent('Event');\n" +
            "            event.initEvent(name, true, true);\n" +
            "            return event;\n" +
            "      }\n" +
            "      input.dispatchEvent(createEvent('focus'));\n" +
            "      input.value = enteredValue;\n" +
            "      input.dispatchEvent(createEvent('change'));\n" +
            "      input.dispatchEvent(createEvent('input'));\n" +
            "      input.dispatchEvent(createEvent('blur'));\n" +
            "}\n" +
            "triggerInput('input[name = \"email\"]', '" + params.account.login + "');\n" +
            "triggerInput('input[name = \"password\"]', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function () {
            setTimeout(function () {
                document.querySelector('.sign-in__btn-container button.primary').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 10000);
            }, 2000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('p.text-error:visible:eq(0), div.sign-in__error:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            let message = util.filter(errors.text());
            browserAPI.log("error: " + message);

            if (
                message.indexOf('The email and/or password entered does not match our records.') !== -1
                || message.indexOf('Please enter a valid email address.') !== -1
                || message.indexOf('Your password is at least 8 characters.') !== -1
            ) {
                provider.setError([message, util.errorCodes.invalidPassword], true);
            }
            // provider.setError(errors.text());
            provider.complete();
            return;
        }

        plugin.loginComplete(params);

        // provider.setNextStep('loginComplete', function () {
        //     provider.logBody("checkLoginErrorsPage");
        //     document.location.href = plugin.getStartingUrl(params);
        // });
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        // parse account
        if (params.autologin) {
            provider.complete();
            return;
        }
        setTimeout(function () {
            plugin.parse(params);
        }, 500);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("parse");
        provider.updateAccountMessage();
        var data = {};

        var balance = util.findRegExp($('div[class*="customer-sidebar-module--account-sidebar-points--"]').text(), /^([\d.,\-\s]+)\s*Points/);
        if (balance) {
            data.Balance = balance;
            browserAPI.log("Balance: " + data.balance);
        } else
            browserAPI.log("Balance not found");

        var number = util.findRegExp($('div[class*="tyr-cardbox-desktop-module--tyr-cardbox-desktop--cardnumber--"]').text(), /^([\d.,\-\s]+)$/);
        if (number) {
            data.Number = number;
            browserAPI.log("Number: " + data.Number);
        } else
            browserAPI.log("Number not found");

        provider.saveProperties(data);
        provider.complete();
    }
};