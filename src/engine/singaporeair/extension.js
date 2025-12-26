var plugin = {

    hosts: {'www.singaporeair.com': true},

    getStartingUrl: function (params) {
        return "https://www.singaporeair.com/krisflyer/account-summary/elite";
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
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
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('div[class *= "UserPanel__LoggedInUser"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#kfLoginForm').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp($('#__NEXT_DATA__').text(), /kfNumber.?\":.?\"([^\\\"]+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber !== '')
            && (number === account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.singaporeair.com/logOut.form?firstPageURL=';
        });
    },

    loadLoginForm: function (params) {
        provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl(params);
    },

    login: function (params) {
        if (
            typeof(params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary');
            document.location.href = "https://www.singaporeair.com/en_UK/us/home#/managebooking";
            return;
        }

        browserAPI.log("login");
        const form = $('form#kfLoginForm');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "kfNumber"]').val(params.account.login);
        form.find('input[name = "pin"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('input[id = "login"]').click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 10000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        let errors = $('p.text-error > span:visible');
        if (errors.length === 0)
            errors = $('div.alert__message:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                var url = "https://www.singaporeair.com/en_UK/ppsclub-krisflyer/bookings/upcoming-flights/";
                var link = $('a[href *= "bookings/upcoming-flights/"]:visible');
                if (link.length > 0)
                    url = "https://www.singaporeair.com" + link.attr('href');
                document.location.href = url;
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        var counter = 0;
        var confNo = params.account.properties.confirmationNumber;
        // for debug only
        browserAPI.log("confNo: " + JSON.stringify(params.account.properties.confirmationNumber));
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('form:has(input[value *= "' + confNo + '"]) > button');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.click();
                });
            }// if (link.length > 0)
            if (counter > 20) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log('getConfNoItinerary');
        var properties = params.account.properties.confFields;
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(params.account.properties.confFields));
        var form = $('form#MBActionForm');
        if (form.length > 0) {
            var loginInput = form.find('input[name = "bookingReferenceBR"]');
            var nameInput = form.find('input[name = "last_familyNameBR"]');
            loginInput.val(properties.ConfNo);
            nameInput.val(properties.LastName);

            util.sendEvent(loginInput.get(0), 'input');
            util.sendEvent(nameInput.get(0), 'input');
            util.sendEvent(loginInput.get(0), 'blur');
            util.sendEvent(nameInput.get(0), 'blur');
            provider.setNextStep('itLoginComplete', function() {
                form.find('button[type = "submit"]').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function (params) {
        provider.complete();
    }
};
