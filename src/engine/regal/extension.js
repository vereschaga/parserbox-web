var plugin = {

    hosts: {
        'www.regmovies.com': true,
        'experience.regmovies.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.regmovies.com/crown-club#/account/login';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = 'https://experience.regmovies.com/login';
        });
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
                    if (plugin.isSameAccount(params.account)) {
                        provider.setNextStep('loginComplete', function () {
                            document.location.href = 'https://experience.regmovies.com/account';
                        });
                    }
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
        browserAPI.log("isLoggedIn");
        if ($('form.login:visible').length > 0
            || $('a:contains("Log in"):visible').length > 0
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (
            $('button:contains("Logout"):visible').length > 0
            || $('p.account_overview-email:visible').length > 0//mobile
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let email = $('p.account_overview-email:visible').text();
        browserAPI.log("email: " + email);
            return ((typeof(account.properties) != 'undefined')
            && email
            && (email.toLowerCase() === account.login.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('button:contains("Logout"):visible').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form.login');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        // form.find('input[name = "username"]').val(params.account.login);
        // form.find('input[name = "password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
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
                "triggerInput('input[name = \"username\"]', '" + params.account.login + "');\n" +
                "triggerInput('input[name = \"password\"]', '" + params.account.password + "');"
            );
            form.find('button.button-default').click();
            setTimeout(function () {
                var captcha = $('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]:visible');
                if (captcha.length > 0) {
                    provider.reCaptchaMessage();
                    let counter = 0;
                    let captchaInterval = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        if ($('p.account_overview-email:visible').length || $('p.error_wrapper:visible').length) {
                            clearInterval(captchaInterval);
                            plugin.checkLoginErrors(params);
                        }
                        if (counter > 160) {
                            clearInterval(captchaInterval);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                        }
                        counter++;
                    }, 500);
                } else {
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 5000);
                }
            }, 2000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var counter = 0;
        var checkLoginErrors = setInterval(function () {
            const error = $('p.error_wrapper:visible');
            if (error.length > 0 && util.trim(error.text()) !== '') {
                clearInterval(checkLoginErrors);
                provider.setError(util.trim(error.text()));
            }
            if (counter > 10) {
                clearInterval(checkLoginErrors);
                if (document.location.href === 'https://experience.regmovies.com/account')
                    plugin.loginComplete(params);
                else
                    provider.setNextStep('loginComplete', function () {
                        document.location.href = 'https://experience.regmovies.com/account';
                    });
            }
            counter++;
        }, 500);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};