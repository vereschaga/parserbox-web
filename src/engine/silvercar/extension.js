var plugin = {

    hosts: {
        'www.silvercar.com'   : true,
        'www.audiondemand.com': true,
        'app.silvercar.com'   : true,
        'app.audiondemand.com': true
    },

    getStartingUrl: function (params) {
        return 'https://app.audiondemand.com/account';
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
            let isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
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
    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('input#text-field__password:visible').length === 1) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (
            $('p:contains("Log out")').length === 1
            || provider.isMobile && $('h2.username-or-email-text > p').length// mpbile
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        let name;
        if (provider.isMobile) {
             name = util.trim($('h2.username-or-email-text > p').text());
        }else {
             name = util.trim($('span.account-drop-down').text());
        }
        browserAPI.log("Name: " + name);

        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && name
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            if (provider.isMobile) {
                $('button[aria-label="menu"]').click();

                $('.username-email-dropdown > button').click();

                $('h2:contains("Log out")').click();

                return;
            }
            $('p:contains("Log out")').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // form.find('input#text-field__email').val(params.account.login);
        // form.find('input#text-field__password').val(params.account.password);

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
            "triggerInput('input[id = \"text-field__email\"]', '" + params.account.login + "');\n" +
            "triggerInput('input[id = \"text-field__password\"]', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function () {
            util.waitFor({
                selector: 'button.login-button[type = submit]',
                timeout: 5,
                success: function () {
                    provider.eval("document.querySelector('button.login-button[type = submit]').click();");
                    util.waitFor({
                        selector: 'div.http-form-status.error',
                        timeout: 5,
                        success: function () {
                            plugin.checkLoginErrors(params);
                        },
                        fail: function () {
                            plugin.checkLoginErrors(params);
                        }
                    });
                },
                fail: function () {
                    plugin.checkLoginErrors(params);
                }
            });
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $("p[id*='-helper-text']");
        if (errors.length === 0){
            errors = $("div.http-form-status.error");
        }

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    },

    loginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

};