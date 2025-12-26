var plugin = {

    hosts: {'www.virginamerica.com': true},

    cashbackLink: '',
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

	getStartingUrl: function(params){
		return 'https://www.virginamerica.com/elevate-frequent-flyer';
	},

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var loading = $('span:contains("Loading"):visible');
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null && loading.length == 0) {
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
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 20)
            counter++;
        }, 500);
	},

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if ($('a[title*=Sign]').attr('title') == 'Sign In' || $('input[name = email]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("SIGN OUT")').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function(account){
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('dd[bo-text = "landingPage.user.elevateId"]').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("SIGN OUT")').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
			provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://www.virginamerica.com/view-itinerary.html";
            });
			return;
		}
        var form = $('form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");

            $('input[name = email]').val(params.account.login).blur();
            $('input[name = password]').val(params.account.password).blur();
            provider.setNextStep('checkLoginErrors');
            //form.find('button:contains("Sign in")').get(0).click();

            // click "Not you?"
            $('a:contains("Not you?")').get(0).click();

            setTimeout(function() {
                // angularjs
                provider.eval('var scope = angular.element(".form__input:eq(0)").scope(); ' +
                    'scope.input.validateValue("' + params.account.login + '");' +
                    'scope.input.value = "' + params.account.login + '";' +
                    'scope.input.isValid = true;' +
                    'var scope = angular.element(".form__input:eq(1)").scope();' +
                    'scope.input.validateValue("' + params.account.password + '");' +
                    'scope.input.value = "' + params.account.password + '";' +
                    'scope.input.isValid = true;' +
                    'var scope = angular.element(".log-in-form__header").scope(); ' +
                    'scope.elevateLoginForm.doLogin();'
                );
            }, 2000);
            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 4000)
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

	checkLoginErrors: function(params) {
		var errors = $('td.errorLabel');
		if (errors.length > 0) {
			var error = errors.text();
			if (error.indexOf("Invalid User ID and/or Password") !== -1)
				provider.setError('Invalid User ID and/or Password');
			else
				provider.setError(error);
		}
		else
			plugin.loginComplete(params);
	},

	loginComplete: function(params) {
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
			provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.virginamerica.com/manage-itinerary';
            });
			return;
		}
		provider.complete();
	},

	toItineraries: function(params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var counter = 0;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var loading = $('span:contains("Loading"):visible');
            if (loading.length == 0 || counter > 15) {
                clearInterval(toItineraries);
                if ($('header:contains(' + confNo + ')').length == 0) {
                    if ($('div[data-bo-text="accordion.content.pnr"]:contains(' + confNo + ')').length == 0)
                        provider.setError(util.errorMessages.itineraryNotFound);
                    else
                        plugin.itLoginComplete(params);
                    return;
                }
                // angularjs
                provider.eval('var scope = angular.element("header:contains(' + confNo + ')").scope(); ' +
                    'scope.accordion.toggleAccordion();'
                );
                plugin.itLoginComplete(params);
            }// if (loading.length == 0 || counter > 15)
            counter++;
        }, 500);
		//var link = $('a[href*="showCkInHome(\'' + confNo +'\'"]');
		//if (link.length > 0) {
		//	var func = /(showCkInHome.*),showWaitImage/.exec(link.attr('href'));
		//	if (func) {
		//		provider.setNextStep('itLoginComplete');
		//		provider.eval(func[1] + '; showWaitImage();');
		//		return;
		//	}
		//}
		//if (typeof(params.account.properties.confFields) == 'object')
		//	plugin.getConfNoItinerary(params);
		//else
		//	provider.setError('Itinerary not found');
	},

	getConfNoItinerary: function(params) {
        browserAPI.log("getConfNoItinerary");
        var form = $('form.log-in-form__form:eq(1)');
        if (form.length > 0) {
            var properties = params.account.properties.confFields;
            // angularjs
            provider.eval('var scope = angular.element(".form__input[name = \'lastName\']").scope(); ' +
                'scope.input.validateValue("' + properties.LastName + '");' +
                'scope.input.value = "' + properties.LastName + '";' +
                'scope.input.isValid = true;' +
                'var scope = angular.element(".form__input[name = \'ticketNumber\']").scope();' +
                'scope.input.validateValue("' + properties.ConfNo + '");' +
                'scope.input.value = "' + properties.ConfNo + '";' +
                'scope.input.isValid = true;' +
                'var scope = angular.element("#elevate-log-in-form-submit").scope();' +
                'scope.pnrLogin.findPNR();'
            );
            setTimeout(function() {
                plugin.itLoginComplete(params);
            }, 3000)
        }
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
	},

	itLoginComplete: function(params) {
		provider.complete();
	}

}
