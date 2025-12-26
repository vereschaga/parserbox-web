var plugin = {
    hosts: {
        'www.scandichotels.com': true,
        'login.scandichotels.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.scandichotels.com/scandic-friends/my-profile';
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
                    if (plugin.isSameAccount(params.account)) {
                        plugin.loginComplete(params);
                    } else {
                        plugin.logout(params);
                    }
                } else {
                    plugin.loadLoginForm(params);
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
        if ($('a:contains("Log in")').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('input[value = "Log out"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("start");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('dt.my-pages-hero-area__membership-section__info__membership-label + dd:eq(0)').text();
        browserAPI.log("number: " + number);
        return (
            (typeof account.properties != 'undefined')
            && (typeof account.properties.AccountNumber != 'undefined')
            && account.properties.AccountNumber != ''
            && number == account.properties.AccountNumber
        );
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            $('input[value = "Log out"]').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("login");
        var jsLogin = $('#js-login-modal');
        if (jsLogin.length > 0) {
            provider.setNextStep('login', function () {
                document.location.href = jsLogin.attr('data-js-login');
            });
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    login: function (params) {
        browserAPI.log("login");
        if (
            (typeof params.account.itineraryAutologin == "boolean")
            && params.account.itineraryAutologin
            && params.account.accountId == 0
        ) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.scandichotels.com/hotelreservation/get-booking';
            });
            return;
        }

        setTimeout(function () {
            var form = $('form#authenticate-login-form');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "userName"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button[type = "submit"]').get(0).click();
                    setTimeout(function() {
                        plugin.checkLoginErrors(params);
                    }, 5000);
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 1000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form[name = "getBookingForm"]');
        if (form.length > 0) {
            form.find('input[name = "BookingId"]').val(properties.ConfNo);
            form.find('input[name = "LastName"]').val(properties.LastName);
            provider.setNextStep('itLoginComplete', function () {
                form.find('button[type = "submit"]').click();
            });
        } else {
            provider.setError(util.errorMessages.itineraryFormNotFound);
        }
    },

    checkLoginErrors: function () {
		if (
            document.location.href == "https://login.scandichotels.com/authn/authenticate/scandic/migrate"
            && $('#migrate-not-now-button').length > 0
        ) {
			$('#migrate-not-now-button').get(0).click();
			return;
		}
        browserAPI.log("checkLoginErrors");
        var errors = $('.bubble-errors .alert:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '') {
            provider.setError(errors.text());
        } else {
            plugin.loginComplete(params);
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (/profile/.test(document.location.href)) {
            plugin.toProfile(params);
            return;
        }
        provider.setNextStep('toProfile', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    toProfile: function (params) {
        browserAPI.log("toProfile");
        if (
            (typeof params.account.itineraryAutologin == "boolean")
            && params.account.itineraryAutologin
            && params.account.accountId > 0
        ) {
            plugin.toItineraries(params);
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        setTimeout(function () {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('div.hotel-stays__list__item__booking-id:contains("' + confNo + '")').next('a');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
            } else {
                provider.setError(util.errorMessages.itineraryNotFound);
            }
        }, 2000);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};
