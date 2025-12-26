var plugin = {

    clearCache: true,
    hosts: {
        'www.velocityrewards.com.au': true,
        'virginaustralia.com': true,
        '/\\w+\\.virginaustralia\\.com/': true,
        '/\\w+\\.velocityfrequentflyer\\.com/': true
    },

	getStartingUrl: function(params) {
        return "https://experience.velocityfrequentflyer.com/my-velocity";
	},

    loadLoginForm: function (params) {
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
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 30) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
	},

	isLoggedIn: function() {
        browserAPI.log("isLoggedIn");
		if( util.trim($('.mv__src-pages-Dashboard-Dashboard_memberNo:visible').text()) !== '' ) {
			browserAPI.log("LoggedIn");
			return true;
		}
		if ($('form[name = "velocityForm"]').length > 0) {
			browserAPI.log('not logged in');
			return false;
		}
        return null;
	},

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
		// for debug only
		//browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('.mv__src-pages-Dashboard-Dashboard_memberNo').text(), /:\s*([^<]+)/i);
        number = number.replace(/\s*/g, '');
        browserAPI.log("number: " + number);
		return ((typeof(account.properties) != 'undefined')
			&& (typeof(account.properties.Number) != 'undefined')
			&& (account.properties.Number != '')
			&& (number == account.properties.Number));
	},

    logout: function (params) {
        browserAPI.log("logout");
		provider.setNextStep('loadLoginForm', function () {
		    /*
            if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin)
                document.location.href = 'https://fly.virginaustralia.com/SSW2010/VAVA/logout.html';
            else
            */
                document.location.href = 'https://accounts.velocityfrequentflyer.com/auth/realms/velocity/protocol/openid-connect/logout?redirect_uri=https%3A%2F%2Fwww.velocityfrequentflyer.com/content/sso/logout%3Fredirect_uri%3Dhttps%253A%252F%252Fexperience.velocityfrequentflyer.com';
        });
	},

    login: function (params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId == 0   ) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = "https://www.virginaustralia.com/au/en/beta/?screen=mytrips&&error=login_required#myTrips";
            });
            return;
        }
		var form = $('form[name = "velocityForm"]');
        if (form.length > 0) {
			browserAPI.log("submitting saved credentials");
			form.find('input[name = "username"]').val(params.account.login);
			form.find('input[name = "password"]').val(params.account.password);
			provider.setNextStep('checkLoginErrors', function () {
                $('#btnKCLogin').click();
            });
		}
		else
            provider.setError(util.errorMessages.loginFormNotFound);
	},

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
		var errors = $('div.form-alert:visible div.right-col');
		if (errors.length > 0)
			provider.setError(util.filter(errors.text()));
		else
			plugin.loginComplete(params);
	},

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId > 0    ) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.velocityfrequentflyer.com/content/MyAccount/MyBookings/';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('a[onclick*="openNotification"]:contains("' + confNo + '")');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function() {
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 3000);
    },

    getConfNoItinerary: function(params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form div[class^="src-components-ManageBooking-ManageBooking"]');
        if (form.length > 0) {
            form.find('input#pnr').val(properties.ConfNo);
            form.find('input#lastName').val(properties.LastName);
            util.sendEvent(form.find('input#pnr').get(0), 'input');
            util.sendEvent(form.find('input#lastName').get(0), 'input');
            util.sendEvent(form.find('button[type="submit"]').get(0), 'input');
            form.find('button[type="submit"]').get(0).click();
        }
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },


    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
