var plugin = {

    hosts: {
        'qmiles.qatarairways.com': true,
        'secure.qmiles.com': true,
        'booking.qatarairways.com': true,
        'www.qatarairways.com': true,
        'cpm.qatarairways.com': true
    },
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return "https://www.qatarairways.com/en/homepage.html";
    },

    start: function (params) {
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.loadLoginForm(params);
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
        if ($('.login-block-text >p:contains("Your status:"):visible').length > 0 ||
            $('a[onclick="logout()"]:contains("Logout")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('a[id^="loginMenuHeader"]:visible, #header-loginlink-redirect:visible').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp($('#membershipnumber').text(), /^(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm',function () {
            document.location.href = 'https://www.qatarairways.com/qr/Logout?logOut=logOut';
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://booking.qatarairways.com/nsp/views/retrievePnr.xhtml";
            });
            //plugin.getConfNoItinerary(params);
            return;
        }
        provider.setNextStep('login',function () {
            document.location.href = 'https://www.qatarairways.com/en/Privilege-Club/loginpage.html?resource=/content/global/en/homepage.html';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#j-login-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "j_username"]').val(params.account.login);
            form.find('input[name = "j_password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function() {
                    var captcha = util.findRegExp( form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]:visible').attr('src'), /k=([^&]+)/i);
                    browserAPI.log("waiting captcha -> " + captcha);
                    if (captcha && captcha.length > 0) {
                        provider.reCaptchaMessage();
                        browserAPI.log("waiting...");
                        var counter = 0;
                        var login = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            var errors = $('#errorId:visible');
                            if (errors.length > 0) {
                                clearInterval(login);
                                provider.setError(errors.text(), true);
                            }// if (errors.length > 0)
                            if (counter > 160) {
                                clearInterval(login);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }
                            counter++;
                        }, 500);
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        var button = $('input[value = "Log in"]');
                        if (/android/gi.test(navigator.userAgent.toLowerCase()))
                            button.click();
                        else
                            button.get(0).click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 10000);
                    }
                }, 1000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('p#errorId:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            setTimeout(function () {
                provider.setNextStep('toItineraries', function () {
                    document.location.href = 'https://www.qatarairways.com/en/Privilege-Club/postLogin/dashboardqrpcuser/my-trips.html';
                });
            }, 2000);
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('a.enhanceMyFlight_Link[href *= "'+ confNo +'"]');
            if (link.length > 0) {
                clearInterval(start);
                provider.setNextStep('itLoginComplete', function () {
                    link.attr('target', '_self').get(0).click();
                });
                return;
            }
            if (counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }
            counter++;
        }, 1000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var form = $('#searchPNRForm');
        if (form.length) {
            var properties = params.account.properties.confFields;
            setTimeout(function () {
                // form.find('input[aria-label="booking reference"]').val(properties.LastName);
                // form.find('input[aria-label="last name"]').val(properties.ConfNo);
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
                    "triggerInput('#searchPNRForm input#pnrValue', '" + properties.ConfNo + "');\n" +
                    "triggerInput('#searchPNRForm input#pnrlastname', '" + properties.LastName + "');"
                );
                provider.setNextStep('itLoginComplete', function () {
                    form.find('#searchPNRBtn').get(0).click();
                });
            }, 200);
        } else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};
