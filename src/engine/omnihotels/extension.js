var plugin = {

	hosts: {'www.omnihotels.com': true, 'ssl.omnihotels.com': true, 'bookings.omnihotels.com': true},

	getStartingUrl: function(params) {
		return "https://bookings.omnihotels.com/membersarea/overview";
	},

	loadLoginForm: function(params) {
		browserAPI.log("loadLoginForm");
		provider.setNextStep('start', function () {
			document.location.href = plugin.getStartingUrl(params);
		});
	},

	start: function(params) {
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
                        plugin.logout();
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

	isLoggedIn: function(){
		browserAPI.log("isLoggedIn");
		if( $('.guest-info-details p:contains("Select Guest Member #"):visible').length > 0 ){
			browserAPI.log("LoggedIn");
			return true;
		}
		if ($('form[id="login-form"]:visible').length > 0) {
			browserAPI.log('not logged in');
			return false;
		}
		return null;
	},

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
		var number = util.findRegExp($('.guest-info-details p:contains("Select Guest Member #"):visible').text(), /Member #(\\w+)/);
		browserAPI.log("number: " + number);
		return (number
			&& (typeof(account.properties) != 'undefined')
			&& (typeof(account.properties.Number) != 'undefined')
			&& (account.properties.Number != '')
			&& (number[1] == account.properties.Number));
	},

    logout: function () {
        browserAPI.log("logout");
		provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://bookings.omnihotels.com/logout';
        });
	},

    login: function (params) {
        browserAPI.log("login");
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
			provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://bookings.omnihotels.com/retrieve";
            });
			return;
		}
		var form = $('form[id="login-form"]');
        if (form.length > 0) {
			browserAPI.log("submitting saved credentials");
			$('input[name = "email"]').val(params.account.login);
			$('input[name = "password"]').val(params.account.password);
			provider.setNextStep('checkLoginErrors', function () {
                form.submit();
            });
		}
		else
            provider.setError(util.errorMessages.loginFormNotFound);
	},

	checkLoginErrors: function(params){
        browserAPI.log("checkLoginErrors");
		if ($("b:contains('Please wait, validating user name and password')").length > 0) {
			provider.setNextStep('checkLoginErrors');
			return;
		}
		var errors = util.trim($('table#Table7 li').text());
		if (errors != "") {
			provider.setError(errors);
		}
		else {
			plugin.loginComplete(params);
		}
	},

	loginComplete: function(params) {
        browserAPI.log("loginComplete");
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
			provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://bookings.omnihotels.com/membersarea/reservations';
            });
			return;
		}
		provider.complete();
	},

	toItineraries: function(params) {
        browserAPI.log("toItineraries");
		var confNo = params.account.properties.confirmationNumber;
		var link = $('#retrieveReservationNumber-' + confNo).next('button:contains("view details")');
		if (link.length > 0) {
			provider.setNextStep('itLoginComplete', function () {
				link.get(0).click();
            });
		}
		else
            provider.setError(util.errorMessages.itineraryNotFound);
	},

	getConfNoItinerary: function(params) {
        browserAPI.log("getConfNoItinerary");
		var properties = params.account.properties.confFields;
        var form = $('form[name="retrieve_form"]');
		if (form.length > 0) {
            $('input[name = "confirmationNumber"]').val(properties.ConfNo);
			$('input[name = "lastNameOnBooking"]').val(properties.LastName);
			provider.setNextStep('itLoginComplete', function () {
                form.submit();
            });
		} else {
            provider.setError(util.errorMessages.itineraryFormNotFound);
        }
	},

	itLoginComplete: function(params) {
		provider.complete();
	}
}
