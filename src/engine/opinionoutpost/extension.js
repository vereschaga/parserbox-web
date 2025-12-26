var plugin = {

    hosts: {'www.opinionoutpost.com': true},

    getStartingUrl: function (params) {
        return 'https://www.opinionoutpost.com/login';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null && counter > 2) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
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

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a:contains("Logout")').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('div.loginForm').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const name = $('span.header-accountInfo-account-name > span').text();
        browserAPI.log("name: " + name);
        return ((typeof (account.properties) !== 'undefined')
            && (typeof (account.properties.Name) !== 'undefined')
            && (account.properties.Name !== '')
            && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('checkLoginErrors');
        $('a[ng-click="authHeader.logout()"]:eq(0)').click();
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('div.loginForm');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // form.find('input[name = "username"]').val(params.account.login);
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
            "triggerInput('input[id = \"email\"]', '" + params.account.login + "');\n" +
            "triggerInput('input[id = \"password\"]', '" + params.account.password + "');"
        );
        provider.setNextStep('checkLoginErrors');
        form.find('button:contains("Sign in")').click();
        setTimeout(function () {
            plugin.checkLoginErrors(params);
        }, 7000)
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.alert:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    }
}