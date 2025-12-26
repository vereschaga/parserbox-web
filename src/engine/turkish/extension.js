var plugin = {

    hosts: {'www.turkishairlines.com': true},
    clearCache: (typeof(applicationPlatform) != 'undefined' && applicationPlatform == 'android') ? true : false,

    getStartingUrl: function (params) {
        return 'https://www.turkishairlines.com/en-us/index.html';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null && counter > 7) {
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
        var login = $('span:contains("SIGN IN"):visible, #mblSigninButton:visible');
        if (login.length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button:contains("SIGNOUT"):visible, #signoutBTNMobile:visible').length > 0 ||
            (provider.isMobile && $('span:contains("Sign out"):visible').length > 0)) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.filter($('span.userfullname').text());
        if (provider.isMobile)
            name = util.filter($('a[data-bind *= "pageParameters.greeting"]:visible').text());
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && name
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('button:contains("SIGNOUT"):visible, #signoutBTNMobile:visible').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin && params.account.accountId === 0) {
            plugin.getConfNoItinerary(params);
            return;
        }
        // open login form
        $('span:contains("SIGN IN"):visible, #mblSigninButton:visible').click();
        // wait login form
        var counter = 0;
        var loginInterval = setInterval(function () {
            var form = $('form[class*="MSLogin_msLoginForm"]');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(loginInterval);
                setTimeout(function () {
                    browserAPI.log("submitting saved credentials");
                    var login = params.account.login;
                    login = login.replace(/TK\s*/i, "");
                    browserAPI.log('login: ' + login);
                    browserAPI.log("submitting saved credentials");


                    $('#signInPreferencesButton').click();
                    setTimeout(function () {
                        switch (params.account.login2) {
                            default:
                            case '1':
                                $('#preferencesMemberNumber').click();
                                break;
                            case '2':
                                $('#preferencesMail').click();
                                break;
                            case '4':
                                $('#preferencesIdNumber').click();
                                break;
                        }

                        setTimeout(function () {
                            form.find('input[id = "tkNumber"]').val(login);
                            util.sendEvent(form.find('input[id = "tkNumber"]').get(0), 'input');

                            form.find('input[id = "msPassword"]').val(params.account.password);
                            util.sendEvent(form.find('input[id = "msPassword"]').get(0), 'input');

                            provider.setNextStep('checkLoginErrors', function () {
                                form.find('button#msLoginButton').click();
                                setTimeout(function () {
                                    plugin.checkLoginErrors(params);
                                }, 7000)
                            });
                        }, 500);
                    }, 500);
                }, 500)
            }
            if (counter > 10) {
                clearInterval(loginInterval);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $("p#error-messageHeader:visible, span#errormessage:visible");
        if (provider.isMobile)
            errors = $('#error-messageLightbox:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.turkishairlines.com/en-us/miles-and-smiles/account/index.html#flights';
            });
            return;
        }
        else
            plugin.itLoginComplete(params);
    },

    toItineraries: function(params) {
        browserAPI.log('toItineraries');
        var counter = 0;
        var confNo = params.account.properties.confirmationNumber;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('div#flightbooking div:has(h4:contains("' + confNo + '")) + div a:contains("Manage this booking"):eq(0)');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
            }// if (link.length > 0)
            if (counter > 30) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    itLoginComplete: function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");

        $('a[aria-label="Check-in / Manage booking"]').get(0).click();

        var properties = params.account.properties.confFields;
        var counter = 0;
        var getConfNoItinerary = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var form = $('#bookerManageTab');
            if (form.length > 0) {
                clearInterval(getConfNoItinerary);
                form.find('input[id = "ticketNo"]').val(properties.ConfNo);
                provider.eval("$('#ticketNo').change();");
                form.find('input[id = "surname"]').val(properties.LastName).change();
                provider.eval("$('#surname').change();");
                provider.setNextStep('submitTicketNumber', function () {
                    form.find('a[data-bind *= "openManageBooking"]').get(0).click();
                    setTimeout(function () {
                        plugin.submitTicketNumber(params);
                    }, 5000);
                });
            }// if (form.length > 0)
            if (counter > 20) {
                clearInterval(getConfNoItinerary);
                provider.setError(util.errorMessages.itineraryFormNotFound);
                return;
            }// if (counter > 20)
            counter++;
        }, 500);
    },

    submitTicketNumber: function (params) {
        browserAPI.log('submitTicketNumber');

        util.waitFor({
            selector: 'span:contains("Search for passengers")',
            success: function(elem) {
                provider.setNextStep('itLoginComplete', function () {
                    elem.click();
                });
            },
            fail: function () {
                provider.itLoginComplete(params);
            },
            timeout: 5
        });
    }

};
