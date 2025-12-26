var plugin = {
    hosts: {
        'www.shangri-la.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.shangri-la.com/en/corporate/golden-circle/online-services/account-summary/';
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
        let authByNumber = $('span.shangrila-react-login-box-switch-icon-gc, span.p-login-switch-icon-gc');
        if (
            $('.shangrila-react-login-box-content > form:visible').length > 0
            || $('.p-login-container > div:visible').length > 0//mobile
            || authByNumber.length > 0
        ) {
            if (authByNumber.length) {
                authByNumber.click();
            }
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.user-info-layer div:contains("Sign Out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return typeof(account.properties) != 'undefined'
            && typeof(account.properties.Number) != 'undefined'
            && account.properties.Number !== ''
            && $('span:contains("'+account.properties.Number+'")').length;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            if ($('.opt-user-info').length) {
                $('.opt-user-info').get(0).click();
                $('.js-do-login-out').get(0).click();
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('div.p-login-container:visible, .shangrila-react-login-box-content > form:visible, .p-login-container > div:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("[react form]: submitting saved credentials");

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
            "triggerInput('input[name = \"gc\"], input[placeholder=\"Membership Number\"]', '" + params.account.login + "');\n" +
            "triggerInput('input[name = \"gc-password\"], input[placeholder=\"Password\"]', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function () {
            form.find('.shangrila-react-login-btn:visible, .content-login').get(0).click();
            setTimeout(function() {
                let captcha = $('script[src*="/js/geetest_verify.js"]');
                if (captcha && captcha.length > 0) {
                    browserAPI.log("waiting...");
                    let counter = 0;
                    let login = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        let errors = $('.shangrila-react-login-box-common-err:visible, .content-error:visible');

                        if (errors.length > 0) {
                            clearInterval(login);
                            provider.setError(errors.text(), true);
                            return;
                        }// if (errors.length > 0)

                        if (counter > 120) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                            return;
                        }
                        counter++;
                    }, 500);
                }// if (captcha.length > 0)
                else {
                    plugin.checkLoginErrors(params);
                }
            }, 2000);
        });
    },

    checkLoginErrors: function (params) {
        provider.hideFader();
        browserAPI.log("checkLoginErrors");
        const errors = $('.shangrila-react-login-box-common-err:visible, .content-error:visible');

        if (errors.length > 0 && util.trim(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.shangri-la.com/en/corporate/golden-circle/online-services/reservations-list/';
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            const confNo = params.account.properties.confirmationNumber;
            const link = $('a[href*="confirmationNo='+ confNo +'"]');

            if (link.length === 0) {
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }

            provider.setNextStep('itLoginComplete', function(){
                link.get(0).click();
            });
        }, 1000);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
