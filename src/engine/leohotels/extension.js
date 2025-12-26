var plugin = {

    hosts: {
        'leonardo-hotels.com'    : true,
        'www.leonardo-hotels.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.leonardo-hotels.com/leonardo-advantage-club/my-points';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null && counter > 1) {
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
        if ($('.logout').length) {
            browserAPI.log('isLoggedInd: true');
            return true;
        }
        if ($('.logout').length == 0) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        let name = util.findRegExp($('div:contains("Hello,")').text(), /Hello,\s*([^\!]+)/i);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (0 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function (params) {
        browserAPI.log('logout');
        $('.logout').get(0).click();

        setTimeout(function () {
            plugin.start(params);
        }, 2000);
    },

    login: function (params) {
        browserAPI.log('login');
        $('.advantage-club:first').click();
        setTimeout(function () {
            const login = $('#ngb-tab-0');

            if (login.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            login.get(0).click();

            // $('input[id *= "email_"]').val(params.account.login);
            // $('input[id *= "password_"]').val(params.account.password);

            // express.js
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
                "triggerInput('input[id *= \"email_\"]', '" + params.account.login + "');\n" +
                "triggerInput('input[id *= \"password_\"]', '" + params.account.password + "');"
            );

            $('.sign-in-form__button').get(0).click();

            setTimeout(function () {
                plugin.checkLoginErrors();
            }, 7000);
        }, 500);
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        const error = $('div.error .sn-content:visible, div.validation-message:visible');

        if (error.length && util.filter(error.text()) !== '') {
            provider.setError(util.filter(error.text()));
            return;
        }

        provider.complete();
    }

};
