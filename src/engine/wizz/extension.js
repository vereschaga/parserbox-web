var plugin = {
    //keepTabOpen: true,
    hosts : {
        'wizzair.com'      : true,
        'be.wizzair.com'   : true,
        'book.wizzair.com' : true
    },

    getStartingUrl: function (params) {
        return 'https://wizzair.com/#/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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
        var isLogged = $('button:contains("Sign in"):visible').length;
        if (isLogged) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (!isLogged.length && $('a:contains("Sign out")').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return false;
        // var number = util.findRegExp( $('li:contains("ThankYou Account")').text(), /Account\s*([^<]+)/i);
        // browserAPI.log("number: " + number);
        // return ((typeof(account.properties) != 'undefined')
        // && (typeof(account.properties.AccountNumber) != 'undefined')
        // && (account.properties.AccountNumber != '')
        // && (number == account.properties.AccountNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a:contains("Sign out")').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                $('button:contains("Check-in & Bookings")').click();
                setTimeout(function () {
                    plugin.getConfNoItinerary(params);
                }, 1000);
            });
            return;
        }

        $('button:contains("Sign in"):visible').click();
        setTimeout(function () {
            var form = $('form[name="login-form"]');
            if (form.length) {
                form.find('input[name = "email"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);
                // reactjs
                provider.eval(
                    "function doEvent( obj, event ) {"
                    + "var event = new Event( event, {target: obj, bubbles: true} );"
                    + "return obj ? obj.dispatchEvent(event) : false;"
                    + "};"
                    + "var el = document.querySelector('input[name = \"email\"]'); el.value = \"" + params.account.login + "\"; doEvent(el, 'input' );"
                    + "var el = document.querySelector('input[name = \"password\"]'); el.value = \"" + params.account.password + "\"; doEvent(el, 'input' );"
                );

                setTimeout(function () {
                    $('button[type="submit"]', form).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000);
                }, 500);
            } else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var $error = $('.error-notice__title', 'form[name="login-form"]');
        if ($error.length && $error.is(':visible') && '' != $error.text().trim())
            provider.setError($error.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                var name = params.account.properties.Name.split(' ');
                name = name[name.length - 1];
                document.location.href = 'https://wizzair.com/en-gb/itinerary#' + params.account.properties.confirmationNumber + '/' + name.toUpperCase();
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        var attempt = 0,
            checkItineraries = setInterval(function () {
                if ($('div:contains("' + params.account.properties.confirmationNumber + '")').length) {
                    provider.complete();
                    clearInterval(checkItineraries);
                } else if (++attempt > 5 || $('.error-notice span:contains("NotFound")').length) {
                    clearInterval(checkItineraries);
                    provider.setError(['Itinerary not found', util.errorCodes.providerError]);
                }
            }, 1000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form[name="find-booking-form"]');
        if (form.length > 0) {
            // form.find('input[name="pnr"]').val(properties.ConfNo);
            // form.find('input[name="lastName"]').val(properties.LastName);

            // reactjs
            provider.eval(
                "function doEvent( obj, event ) {"
                + "var event = new Event( event, {target: obj, bubbles: true} );"
                + "return obj ? obj.dispatchEvent(event) : false;"
                + "};"
                + "var el = document.querySelector('input[name = \"pnr\"]'); el.value = \"" + properties.ConfNo + "\"; doEvent(el, 'input' );"
                + "var el = document.querySelector('input[name = \"lastName\"]'); el.value = \"" + properties.lastName + "\"; doEvent(el, 'input' );"
            );
            provider.setNextStep('itLoginComplete', function() {
                form.find('button[type="submit"]').click();
            });

        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};