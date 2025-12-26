var plugin = {

    hosts: {
        'www.marriott.com': true,
        'www.marriott.co.uk': true,
        'rewards.ritzcarlton.com': true,
        'ritzcarlton.com': true,
        'www.starwoodhotels.com': true
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.marriott.com/loyalty/myAccount/default.mi';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        if ($('h1:contains("For your security, your session has ended")').length > 0) {
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }
        // refs #8118
        if (document.location.href == 'https://www.marriott.com/signIn.mi'
            || document.location.href == 'https://www.marriott.com/sessionTimedOut.mi'
            || document.location.href == 'https://www.marriott.com/ritz/rewards/signOutConfirmation.mi'
            || document.location.href == 'https://www.marriott.com/default.mi'
            || document.location.href == 'https://www.marriott.com/logout.mi'
            || document.location.href.indexOf('https://www.marriott.com/marriott-hotels-resorts/travel.mi') !== -1
            || document.location.href.indexOf('affname') !== -1
        ) {
            provider.setNextStep('start', function () {
                document.location.href = 'https://www.marriott.com/loyalty/myAccount/activity.mi';
            });
            return;
        }
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    provider.setNextStep('checkNumber', function () {
                        document.location.href = "https://www.marriott.com/loyalty/myAccount/profile.mi";
                    });
                    return;
                }
                else {
                    plugin.clearAccount(params);
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('input[id *= "-email"]:visible, div[class *= "StyledSignInContainerDiv"] form:visible').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("logged in");            
            return true;
        }
        return null;
    },

    checkNumber: function (params) {
        browserAPI.log("checkNumber");
        var number = $('li > p:contains("Member Number") + div > span').text();
        browserAPI.log("number " + number);
        var account = params.account;
        var isSame = number
            && (account.login == number ||
            (typeof(account.properties) != 'undefined')
                && (typeof(account.properties.Number) != 'undefined')
                && (account.properties.Number != '')
                && (account.properties.Number == number));
        if (isSame)
            plugin.loginComplete(params);
        else
            plugin.logout(params);
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://marriott.com/aries-auth/logout.comp';
        });
    },

    clearAccount: function(params) {
        browserAPI.log("clearAccount");
        $('button#remember_me').click();
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },
        
    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.marriott.com/reservation/lookupReservation.mi';
            });
            return;
        }
        // wait login form
        var counter = 0;
        var login = setInterval(function () {
            var form = $('input[id *= "-email"]:visible').closest('form');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");

                /*
                form.find('input[id *= "-email"]').val(params.account.login);
                util.sendEvent(form.find('input[id *= "-email"]').get(0), 'input');
                form.find('input[id *= "-password"]').val(params.account.password);
                util.sendEvent(form.find('input[id *= "-password"]').get(0), 'input');
                */
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
                    "triggerInput('input[id *= \"-email\"]', '" + params.account.login + "');\n" +
                    "triggerInput('input[name = \"input-text-Password\"]', '" + params.account.password + "');\n"
                );
                /*form.find('input[id *= "input-text-Password"]').val(params.account.password)
                util.sendEvent(form.find('input[name = "input-text-Password"]').get(1), 'click');
                util.sendEvent(form.find('input[name = "input-text-Password"]').get(1), 'input');
                util.sendEvent(form.find('input[name = "input-text-Password"]').get(1), 'change');
                util.sendEvent(form.find('input[name = "input-text-Password"]').get(1), 'blur');
                util.sendEvent(form.find('input[name = "input-text-Password"]').get(1), 'focus');*/


                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        var submitButton = form.find('button.js-btn-submit, button[data-testid="sign-in-btn-submit"]');
                        // refs #15568
                        util.sendEvent(submitButton.get(0), 'click');
                        submitButton.click();
                    }, 1000);
                });
            }
            if (counter > 15) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        var errors = $('div.tile-error-messages:visible span');
        if (errors.length == 0)
            errors = $('div[data-component-name="errorMessages"]:visible span');
        if (errors.length > 0 && util.filter(errors.text()) != "")
            provider.setError(util.filter(errors.text()));
        else
            plugin.loginComplete(params);

        const providerError = $(":contains('We are unable to process your request at this time')");
        if (providerError.length > 0) {
            provider.setError(providerError.text());
            return;
        }
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.marriott.com/loyalty/findReservationList.mi';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        var link = $('a[href *= "findReservationDetail.mi?confirmationNumber=' + params.account.properties.confirmationNumber + '"');
        if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function () {
                link.get(0).click();
            });
        } else {
            if ($('div[class *= "rows"] h3:contains("' + params.account.properties.confirmationNumber + '") + div + div:contains("In Progress"):visible').length > 0) {
                browserAPI.log('Reservation In Progress');
                plugin.itLoginComplete(params);
            }
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }
    },

    getConfNoItinerary: function (params) {
        browserAPI.log('getConfNoItinerary');
        var properties = params.account.properties.confFields;
        var form = $('form[name = "reservationLookUpForm"][data-component-id]');
        if (form.length > 0) {
            form.find('input[name="confirmationNumber"]').val(properties.ConfNo);
            form.find('input[name="firstName"]').val(properties.FirstName);
            form.find('input[name="lastName"]').val(properties.LastName);
            var date = new Date(properties.CheckInDate);
            var month = date.getUTCMonth() + 1;
            month = month + '';
            if (month.length === 1)
                month = '0' + month;
            var day = date.getDate();
            day = day + '';
            if (day.length === 1)
                day = '0' + day;
            var checkInDate = date.getFullYear() + '-' + month + '-' + day;// YYYY-MM-DD
            form.find('input[name="checkInDate"]').val(checkInDate);
            provider.setNextStep('itLoginComplete', function () {
                form.find('#lookup-submit-btn').click();
            });
        }
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    }

};
