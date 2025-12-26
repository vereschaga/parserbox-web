var plugin = {

    hosts: {
        'sindbad.omanair.com': true,
        'omanair.com': true,
        'www.omanair.com': true,
        'bookings.omanair.com': true
    },

    getStartingUrl: function (params) {
        return 'https://sindbad.omanair.com/SindbadProd/memberHome';
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
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
        if ($('form[name = loginForm]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=logout]').attr('href')) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('h4._user_name + p:first').text().trim();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && (number == account.properties.CardNumber));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://sindbad.omanair.com/SindbadProd/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId == 0   ) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = 'https://bookings.omanair.com/dx/WYDX/#/home?tabIndex=1';
            });
            return;
        }

        var form = $('form[name = loginForm]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            var login = params.account.login;
            login = login.replace(/WY/i, '');
            // browserAPI.log("Login " + login);
            form.find('input[name = "sindbadno"]').val(login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function() {
                form.find('input[name = "Login"]').click();
            });
        }
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'form.retrieve-pnr',
            success: function (form) {
                form.find('input.confirmation-pnr-input').val(properties.ConfNo);
                form.find('input.lastname-input').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function () {
                    $('button#search').get(0).click();
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            },
            timeout: 10
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.validation:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else {
            document.location.href = plugin.getStartingUrl(params);
            setTimeout(function() {
                plugin.loginComplete(params);
            }, 2000);
        }
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId > 0    ) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://sindbad.omanair.com/SindbadProd/futureBookings';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            $('input[name = "radioButton"][items *= "' + confNo + '"]:first').click();
            var link = $('a[value = "View Booking Details"]');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function(){
                    provider.eval("var windowOpen = window.open; window.open = function(url) { windowOpen(url, '_self'); }");
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
}