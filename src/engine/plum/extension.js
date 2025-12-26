var plugin = {

    hosts: {
        'shop.chapters.indigo.ca': true,
        'www.indigo.ca'          : true,
        'auth.indigo.ca'         : true,
    },

    getStartingUrl: function (params) {
        return 'https://shop.chapters.indigo.ca/Loyalty/myRecommendations.aspx?Section=home&Lang=en';
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
                        plugin.loginComplete(params);
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
        if ($('a[data-logout-path *= "logout"]:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form.auth0-lock-widget').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('div:has(span:contains(" NUMBER:")) + div.my-rewards-tile__table-value').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber !== '')
            && (number === account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[data-logout-path *= "logout"]:visible').get(0).click();
        });
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form.auth0-lock-widget');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // form.find('input[name = "email"]').val(params.account.login);
        // form.find('input[name = "password"]').val(params.account.password);

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
            "triggerInput('input[name = \"password\"]', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function () {
            form.find('button[aria-label="Sign In"]').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 3000)
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        const errors = $('.auth0-global-message-error:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    }
}