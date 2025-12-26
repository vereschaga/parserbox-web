var plugin = {

    hosts: {
        'www.lufthansa.com': true,
        'book.lufthansa.com': true,
        'www.miles-and-more.com': true,
        'book.miles-and-more.com': true,
        'lufthansa.miles-and-more.com': true,
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.lufthansa.com/us/en/account-statement';
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
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null && counter > 3) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout();
                }
                else {
                    // open login form
                    let openLoginForm = $('a:contains("Log in"):visible');

                    if (openLoginForm.length) {
                        provider.setNextStep('login', function () {
                            openLoginForm.get(0).click();
                        });
                    } else {
                        plugin.login(params);
                    }
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('button:has(span:contains("Log in")):visible, a:contains("Log in"):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#lh-loginModule-name:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp($('span:contains("Mam Number is ")').text(), /is\s+(\d+)/);
        browserAPI.log("Number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('logoutMam', function () {
            document.location.href = 'https://www.lufthansa.com/sso/logout';
            /*var profile = $('button.btn-login.btn-profile');
            if (profile.length) {
                profile.get(0).click();
                setTimeout(function () {
                    let logout = $('.modal-body button:contains("Logout")');
                    if (logout.length) {
                        logout.get(0).click();
                    }
                }, 500);
            }*/
        });
    },

    logoutMam: function () {
        browserAPI.log("logoutMam");
        provider.setNextStep('logoutMamStart', function () {
            document.location.href = 'https://www.miles-and-more.com/row/en/member.html';
        });
    },
    logoutMamStart: function () {
        browserAPI.log("logoutMamStart");
        provider.setNextStep('logoutMamEnd', function () {
            let logout = $('button.mainnavigation__logout');
            if (logout.length) {
                logout.get(0).click();
            }
        });
    },

    logoutMamEnd: function () {
        browserAPI.log("logoutMamEnd");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.lufthansa.com/se/en/homepage';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://www.lufthansa.com/de/en/login?deeplinkRedirect=true';
            });
            return;
        }
        // open login form
        let openLoginForm = $('button:has(span:contains("Log in")):visible');

        if (openLoginForm.length) {
            browserAPI.log("login " + openLoginForm.length);
            openLoginForm.get(0).click();
        }

        // wait login form
        let counter = 0;
        let login = setInterval(function () {
            let form = $('div[id *= "lufthansaId-section"]:visible, div[id *= "lufthansaID-section"]:visible');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");

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
                    "triggerInput('input[name = \"loginFormQuery.j_username\"]', '" + params.account.login + "');\n" +
                    "triggerInput('input[name = \"loginFormQuery.j_password\"]', '" + params.account.password + "');"
                );

                  provider.setNextStep('checkLoginErrors', function () {
                    form.find('button.btn-primary').click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 5000)
                });
            }

            let loginStepOne = $('input[name = "emailLoginStepOne"], input[name = "loginStepOne"]:visible');
            if (loginStepOne.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");

                /*
                if (!/@/.test(params.account.login)) {
                    browserAPI.log(">>> Switch to Service card number login");
                    $('button:contains("Service card number login")').click();

                    util.waitFor({
                        selector: 'input[name = "mamLoginStepOne"], input[name = "loginStepOne"]:visible',
                        success: function (elem) {
                            browserAPI.log("Service card number login");
                        },
                        timeout: 7
                    });
                }
                */

                loginStepOne = $('input[name = "mamLoginStepOne"]:visible, input[name = "emailLoginStepOne"]:visible, input[name = "loginStepOne"]:visible');
                browserAPI.log("set login");
                loginStepOne.val(params.account.login);
                loginStepOne = loginStepOne.get(0);
                util.sendEvent(loginStepOne, 'click');
                util.sendEvent(loginStepOne, 'blur');
                util.sendEvent(loginStepOne, 'change');
                util.sendEvent(loginStepOne, 'input');
                $('.travelid-login__continueButton:contains("Next"):visible').click();

                util.waitFor({
                    selector: '[name = "mamLoginStepTwoPassword"]:visible, [name = "emailLoginStepTwo"]:visible, [name = "mamLoginStepTwoPin"]:visible, [name = "loginStepTwoPassword"]:visible',
                    success: function (elem) {
                        browserAPI.log("set pass");
                        elem.val(params.account.password);
                        elem = elem.get(0);
                        util.sendEvent(elem, 'click');
                        util.sendEvent(elem, 'blur');
                        util.sendEvent(elem, 'change');
                        util.sendEvent(elem, 'input');


                        browserAPI.log("click by btn");
                        var button = $('.travelid-login__loginButton:contains("Log in"):visible').get(0);
                        util.sendEvent(button, 'click');
                        util.sendEvent(button, 'blur');

                        button.click()
                        provider.setNextStep('checkLoginErrors', function () {
                            setTimeout(function () {
                                plugin.checkLoginErrors(params);
                            }, 5000)
                        });
                    },
                    timeout: 7
                });
            }

            if (counter > 10) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.error:visible:eq(0) > div, p.travelid-form__elementValidationMessage:visible > span:not(:hidden):visible, .travelid-login__error:not(:hidden), p[class = "travelid-form__errorBoxContentItemText"]:visible');

        if (errors.length > 0 && util.filter(errors.html()) !== '') {
            provider.setError(util.filter(errors.text().replace('go to error', '')));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.lufthansa.com/deeplink/cockpit?country=de&language=en&layout=L';
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        const confNo = params.account.properties.confirmationNumber;
        util.waitFor({
            selector: 'button.logoutBtn',
            success: function() {
                setTimeout(function() {
                    let link = $('div:contains("Booking code: ' + confNo + '")').closest('pres-booking-card-header').next('div').find('span:contains("Flight details")');
                    if (link.length > 0) {
                        provider.setNextStep('checkItineraryMAM');
                        link.get(0).click();
                    }
                }, 4000);
            },
            fail: function() {
                provider.setError(util.errorMessages.itineraryNotFound);
            },
            timeout: 10
        });
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    checkItineraryMAM: function(params) {
        browserAPI.log("checkItineraryMAM");
        if (/miles-and-more\.com/.test(document.location.href)) {
            if (/my-bookings\.html/.test(document.location.href)) {
                provider.setNextStep('itLoginCompleteMAM', function () {
                    document.location.href = 'https://www.miles-and-more.com/row/en/account/my-bookings/my-bookings.html';
                });
                return;
            } else {
                provider.setNextStep('loginMAM', function() {
                    document.location.href = 'https://miles-and-more.com/de/en/static/login.html';
                });
            }
        } else {
            plugin.itLoginComplete(params);
        }
    },

    loginMAM: function (params) {
        browserAPI.log("loginMAM");
        $('div[data-form = "login__form--lufthansa"] button[type = "button"]').click();
        var form = $('input[name = "username-lufthansa"]').closest('form.login__form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username-lufthansa"]').val(params.account.login);
            form.find('input[name = "password-lufthansa"]').val(params.account.password);
            form.find('input[name = "logged-in"]').click();
            provider.setNextStep('checkLoginErrorsMAM', function () {
                form.find('button[type = "submit"]').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrorsMAM: function (params) {
        browserAPI.log("checkLoginErrorsMAM");
        var errors = $('p.login__errorText:visible');
        if (errors.length > 0 && util.filter(errors.html()) != '')
            provider.setError(errors.text().replace('go to error', ''));
        else
            plugin.loginCompleteMAM(params);
    },

    loginCompleteMAM: function (params) {
        browserAPI.log("loginCompleteMAM");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId > 0
        ) {
            provider.setNextStep('itLoginCompleteMAM', function () {
                document.location.href = 'https://www.miles-and-more.com/row/en/account/my-bookings/my-bookings.html';
            });
            return;
        }
        provider.complete();
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var btn = $('button:contains("Enter booking data")');
        if (btn.length) {
            btn.get(0).click();
        } else
            provider.setError(util.errorMessages.itineraryFormNotFound);
        setTimeout(function () {
            var form = $('.modal-body form:visible');
            if (form.length > 0) {
                form.find('input[name = "loginPNRFormQuery.j_bookingcode"]').val(properties.ConfNo);
                form.find('input[name = "loginPNRFormQuery.j_lastname"]').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function() {
                    form.find('button[type = "submit"]').get(0).click();
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }, 1000);



        setTimeout(function () {
            var form = $('.modal-body form:visible');
            if (form.length > 0) {
                //form.find('input[name = "loginPNRFormQuery.j_bookingcode"]').val(properties.ConfNo);
                //form.find('input[name = "loginPNRFormQuery.j_lastname"]').val(properties.LastName);
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
                    "triggerInput('input[name = \"loginPNRFormQuery.j_bookingcode\"]', '" + properties.ConfNo + "');\n" +
                    "triggerInput('input[name = \"loginPNRFormQuery.j_lastname\"]', '" + properties.LastName + "');"
                );

                provider.setNextStep('itLoginComplete', function() {
                    form.find('button[type = "submit"]').get(0).click();
                });            }
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }, 1000);
    },



    itLoginCompleteMAM: function (params) {
        browserAPI.log("itLoginCompleteMAM");
        provider.complete();
    }

};