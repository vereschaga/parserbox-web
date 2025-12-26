var plugin = {

    hosts : {
        'apps.rotana.com'        : true,
        'rotanarewards.com'      : true,
        'www.rotanarewards.com'  : true,
        'reservations.rotana.com': true,
        'bookings.rotana.com'    : true,
    },

    getStartingUrl : function(params) {
        return 'https://bookings.rotana.com/en/myaccount/myaccount';
    },

    start : function(params) {
        browserAPI.log('start');
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

    isLoggedIn : function() {
        browserAPI.log("isLoggedIn");
        if ($('form#loginForm1').length || $('a:contains("login"):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Logout"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $("input[name='membershipno']").attr('value');
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.CardNumber) !== 'undefined')
            && (account.properties.CardNumber !== '')
            && number
            && (number === account.properties.CardNumber));

    },

    logout: function(params) {
        $('a:contains("Logout"):visible').attr("href");
        provider.setNextStep('start', function() {
            document.location.href = $('a:contains("Logout"):visible').attr("href");
        });
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {

            provider.setNextStep('toItineraries', function () {
                if (document.location.href !== plugin.getStartingUrl(params))
                    document.location.href = plugin.getStartingUrl(params);
            });

            return;
        }
        provider.complete();
    },

    login: function(params) {
        browserAPI.log('login');
        var form = $('form#loginForm1');
        if (form.length) {
            form.find('#email').val(params.account.login);
            form.find('#password').val(params.account.password);
            return provider.setNextStep('checkLoginErrors', function() {
                form.find('input[name = "submit"]').click();
                setTimeout(function() {
                    plugin.checkLoginErrors();
                }, 2500);
            });
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors : function() {
        browserAPI.log('checkLoginErrors');
        var error = $('#divfirstmsg,#divsecondmsg');
        if (error.length && '' !== util.trim(error.text()))
            provider.setError(error.text());
         else
            plugin.loginComplete(params);
    },

/*
    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        setTimeout(function () {
            var properties = params.account.properties.confFields;
            var form = $('form[name="change"]');
            if (form.length > 0) {
                form.find('#login_reservationno').val(properties.ConfNo);
                form.find('#login_email').val(properties.Email);
                provider.setNextStep('itLoginComplete', function () {
                    form.find('a.button[onclick *= "change.btnRetrieveBooking"]').get(0).click();
                    plugin.itLoginComplete(params);
                });
            }
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }, 1000);
    },
*/

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('a[href *= "myaccount/manageBooking/'+ confNo +'"]').attr("href");
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function(){
                    document.location.href = link;
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

};