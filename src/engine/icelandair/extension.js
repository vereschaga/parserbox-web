var plugin = {
    hosts: {
        'www.icelandair.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.icelandair.com/profile/';
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

    isLoggedIn: function (params) {
        browserAPI.log('isLoggedIn');
        if ($('span:contains("Saga Club number") + span:visible').length) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        if ($('button div:contains("Frequent flyer"):visible').length > 0
            // Mobile
            || $('button[aria-label="Mobile menu"]:visible').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        const number = util.filter($('span:contains("Saga Club number") + span').text());
        browserAPI.log("number: " + number);
        return (typeof (account.properties) != 'undefined'
            && typeof (account.properties.Number) != 'undefined'
            && account.properties.Number !== ''
            && number !== ''
            && number === account.properties.Number);
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('start', function () {
            let menu, logout;
            if (provider.isMobile) {
                menu = $('button[aria-label="Mobile menu"]:visible');
                if (menu.length > 0) {
                    menu.get(0).click();
                    logout = $('#mobile-user_menu_logout');
                    if (logout.length) {
                        logout.get(0).click();
                        setTimeout(function () {
                            plugin.start(params);
                        }, 4000);
                    }
                }
            } else {
                menu = $('span[role="button"] > div[class*="avatar_letters"]');
                if (menu.length) {
                    menu.get(0).click();
                    logout = $('button:contains("Log out")');
                    if (logout.length) {
                        logout.trigger('click');
                        setTimeout(function () {
                            plugin.start(params);
                        }, 4000);
                    }
                }
            }
        });
    },

    login: function (params) {
        browserAPI.log('login');
        let counter = 0;
        let login = setInterval(function () {
            let menu, loginForm;
            if (provider.isMobile) {
                menu = $('button[aria-label="Mobile menu"]:visible');
                if (menu.length > 0) {
                    menu.get(0).click();
                    loginForm = $('button:contains("Log in")');
                    if (loginForm.length) {
                        loginForm.get(0).click();
                        loginForm = $('button:contains("Saga Club ID")');
                        if (loginForm.length)
                            loginForm.get(0).click();
                    }
                }
            } else {
                menu = $('button div:contains("Frequent flyer"):visible');
                if (menu.length > 0) {
                    menu.get(0).click();
                    loginForm = $('button:contains("Log in with Saga Club")');
                    if (loginForm.length) {
                        browserAPI.log("Log in with Saga Club ID");
                        loginForm.get(0).click();
                    }
                }
            }
            const form = $('form:has(#input_username):visible');

            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                // form.find('#input_username').val(params.account.login);
                // form.find('#input_password').val(params.account.password);
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
                    "triggerInput('input[id = \"input_username\"]', '" + params.account.login + "');\n" +
                    "triggerInput('input[id = \"input_password\"]', '" + params.account.password + "');"
                );

                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        var captcha = form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]:visible');
                        if (captcha.length > 0) {
                            browserAPI.log('captcha found');
                            provider.reCaptchaMessage();
                            $('#awFader').hide();
                            var counterCaptcha = 0;
                            var loginInterval = setInterval(function () {
                                browserAPI.log("waiting... " + counterCaptcha);
                                if (counterCaptcha > 120) {
                                    clearInterval(loginInterval);
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                }// if (counter > 120)
                                counterCaptcha++;
                            }, 1000);
                            form.find('button#submit_login').click(function () {
                                clearInterval(loginInterval);
                                setTimeout(function () {
                                    plugin.checkLoginErrors(params);
                                }, 5000);
                            });
                        }// if (captcha.length > 0)
                        else {
                            browserAPI.log("captcha is not found");
                            form.find('button#submit_login').click();
                        }
                    }, 2000);
                });
            }
            if (counter > 5) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        const error = $('#input_password_error:visible');

        if (error.length && util.filter(error.text()) !== '') {
            provider.setError(error.text());
            return;
        }

        provider.setNextStep('loginComplete', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        provider.complete();
    }

};
