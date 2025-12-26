var plugin = {

    hosts: {'www.brilliantbylangham.com': true},

    getStartingUrl: function (params) {
        return 'https://www.brilliantbylangham.com/en/login';
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
        if ($('#login-form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        // if ($('#memberTopBanner1_lblHelloLabel:visible').length > 0) {//todo
        //     browserAPI.log("LoggedIn");
        //     return true;
        // }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('.memberInfo .userNum').eq(0).text();//todo
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) !== 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            // const logout = $('#header1_btnLogout');//todo
            // if (logout.length)
            //     logout.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('#login-form:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // form.find('input#login_id').val(params.account.login);
        // form.find('input#password').val(params.account.password);

        // data-value="email"
        // data-value="memberId"
        let loginMethod = 'memberId';

        if (/@/.test(params.account.login)) {
            loginMethod = 'email'
        }

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
            "triggerInput('input[class *= \"MuiSelect-nativeInput\"]', '" + loginMethod + "');" +
            "triggerInput('input[id = \"login_id\"]', '" + params.account.login + "');" +
            "triggerInput('input[id = \"password\"]', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function () {
            form.find('.submitbutton').click();

            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000)
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");

        const error = $('.error:visible, p.Mui-error:visible');

        if (error.length > 0 && util.trim(error.text()) !== '') {
            provider.setError(error.text());
            return;
        }

        provider.complete();
    }

};
