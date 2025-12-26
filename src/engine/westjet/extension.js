var plugin = {
    //keepTabOpen: true,
    hosts: {
        'profile.westjet.com': true,
        'book.westjet.com': true,
        'www.westjet.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.westjet.com/en-ca/rewards/account-overview';
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
                else {
                    var delay = 0;

                    let signIn = $('#sign-in-button:visible, #profile-apps-sign-in-button button:visible')

                    if (signIn.length) {
                        delay = 2000;
                        signIn.click();
                    }

                    setTimeout(function () {
                        plugin.login(params);
                    }, delay);
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
        if ($
            ('form.f-wj-widget, main form:has(input[name = "emailOrWestJetId"]):visible, form.f-wj-widget:visible').length > 0
            || ($('form#sign-in-form').length > 0 && $('a.sign-in:visible').length > 0)
            || $('#sign-in-button:visible, #profile-apps-sign-in-button button:visible').length
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('dt:contains("WestJet Rewards ID:") + dd:eq(0), span[data-testid="westjet-id"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.trim($('dt:contains("WestJet Rewards ID:") + dd:eq(0), span[data-testid="westjet-id"]').text());
        browserAPI.log("number: " + number);
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.AccountNumber) !== 'undefined'
            && account.properties.AccountNumber !== ''
            && number === account.properties.AccountNumber;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            let signOut = $('button.sign-out-btn');

            if (signOut.length) {
                signOut.click();
            } else {
                let myAccount = $('button[name="sign-in-my-account-cta"]');
                myAccount.click();

                setTimeout(function () {
                    let signOut = $('button.sign-out-link');

                    if (signOut.length) {
                        signOut.click();
                    }
                }, 1500);
            }
        });
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.westjet.com/en-ca/manage';
            });
            return;
        }

        let  form = $('form#signInForm');
        if (form.length === 0)
            form = $('form.f-wj-widget:visible');
        if (form.length === 0)
            form = $('form#sign-in-form');
        if (form.length === 0)
            form = $('main form:has(input[name = "emailOrWestJetId"]):visible');

        if (form.length === 0)
            form = $('form[data-testid="sign-in-form"]:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "emailOrWestJetId"], input[name = "westjetId"]').val(params.account.login);
        form.find('input[name = "password"]').val(params.account.password);

        // vue.js
        // vue.js
        provider.eval(
            'function createNewEvent(eventName) {' +
            'var event;' +
            'if (typeof(Event) === "function") {' +
            '    event = new Event(eventName);' +
            '} else {' +
            '    event = document.createEvent("Event");' +
            '    event.initEvent(eventName, true, true);' +
            '}' +
            'return event;' +
            '}'+
            'var email = document.querySelector(\'input[name="westjetId"]\');' +
            'email.dispatchEvent(createNewEvent(\'input\'));' +
            'email.dispatchEvent(createNewEvent(\'change\'));' +
            'email.dispatchEvent(createNewEvent(\'keyup\'));' +
            'var pass = document.querySelector(\'input[name="password"]\');' +
            'pass.dispatchEvent(createNewEvent(\'input\'));' +
            'pass.dispatchEvent(createNewEvent(\'change\'));' +
            'pass.dispatchEvent(createNewEvent(\'keyup\'));'
        );

        form.find('#signInButton, input[value = "Sign in"], button[data-testid="submit-btn"]').get(0).click();

        provider.setNextStep('checkLoginErrors', function () {
            setTimeout(function () {
                let captcha = util.findRegExp(form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                if (captcha && captcha.length > 0) {
                    provider.reCaptchaMessage();
                    let counter = 0;
                    let login = setInterval(function () {
                        browserAPI.log("waiting captcha... " + counter);
                        if (counter > 120) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                            return;
                        } else {
                            let errors = $('#signInBox .error-login:visible, #password-error:visible');
                            if (errors.length > 0 && util.trim(errors.text()) !== '') {
                                clearInterval(login);
                                provider.setError(errors.text());
                                return;
                            }
                        }
                        counter++;
                    }, 1000);
                } else {
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 4000);
                }
            }, 2000);
        });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
             util.waitFor({
                selector: '.mt-lookup__form',
                success: function (form) {
                    var properties = params.account.properties.confFields;
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
                        "triggerInput('input#mt-lookup-pnr', '" + properties.ConfNo + "');\n" +
                        "triggerInput('input#mt-lookup-last-name', '" + properties.LastName + "');"
                    );
                    provider.setNextStep('itLoginComplete', function() {
                        form.find('#mt-lookup-btn').click();
                    });
                },
                fail: function () {
                    provider.setError(util.errorMessages.itineraryFormNotFound);
                }
            });
     },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('#signInBox .error-login:visible, #password-error:visible, small.error:visible, div.error-message:visible p');

        if (errors.length > 0 && util.trim(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId > 0
        ) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.westjet.com/en-ca/my-trips/index';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        plugin.itLoginComplete(params);
    }

};
