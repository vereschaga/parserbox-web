var plugin = {

    hosts: {
        'activejunky.com': true,
        'www.activejunky.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.activejunky.com';
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
        browserAPI.log('isLoggedIn');
        if ($('a[href="/login"]:visible').length > 0) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if ($('span.truncate').length > 0) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        const name = $('span.truncate').text()
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && name
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('start', function () {
            // $('a[href="/members/sign_out"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log('login');
        $('a[href="/login"]:visible').get(0).click();

        setTimeout(function () {
            const form = $('form:has(input[name="email"])');
            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return false;
            }

            browserAPI.log("submitting saved credentials");
            // form.find('input[name = "email"]').val(params.account.login);
            // form.find('input[name = "password"]').val(params.account.login);
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
                "triggerInput('input[name = \"email\"]', '" + params.account.login + "');\n" +
                "triggerInput('input[name = \"password\"]', '" + params.account.pass + "');\n"
            );

            provider.setNextStep('checkLoginErrors', function () {
                form.find('button:contains("Sign In")').click();
                setTimeout(function () {
                    plugin.checkLoginErrors();
                }, 5000);
            });
        }, 2000);
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        var $errors = $('.signInError', '#authenticationForm');

        if ($errors.length && '' != $errors.text().trim()) {
            provider.setError($errors.text());
            return;
        }

        provider.complete();
    }

};