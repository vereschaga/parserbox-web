
var plugin = {

    hosts: {
        'www.budget.com': true,
        "budget.com": true,
        "www.budget-russia.ru": true,
        "pluto.budgetinternational.com": true
    },
    clearCache: true,

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.budget.com/en/loyalty-profile/fastbreak/dashboard/profile';
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

        if ($('form[name="loginForm"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('p:contains("Username")').length > 0 && $('button:contains("Log Out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
		return null;
	},

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        // browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('span[ng-if = "vm.customerData.preferred"]:first').parent().text(), /(\w+)\s*$/ );
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('button:contains("Log Out")').get()[0].click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId === 0   ) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://www.budget.com/en/reservation/view-modify-cancel';
            });
            return;
        }

		var form = $('form[name="loginForm"]');
		if (form.length > 0) {
			util.sendEvent(form.find('input[name = "username"]').val(params.account.login).get()[0], 'input');
			util.sendEvent(form.find('input[name = "password"]').val(params.account.password).get()[0], 'input');
			var captcha = $('div.g-recaptcha:not([data-size="invisible"])');
			if (captcha.length > 0) {
				provider.reCaptchaMessage();
				util.waitFor({
					selector: 'input[name="recaptcha"][value!=""]',
					success: function(){
						plugin.submitLogin(params, form);
					},
					fail: function(){
						provider.setError(util.errorMessages.captchaErrorMessage, true);
					},
					timeout: 120
				});
			} else
				plugin.submitLogin(params, form);
		} else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form[name = "VMCForm"]');
        if (form.length > 0) {
            var confInput = form.find('input[name = "vm.lookupModel.confirmationNumber"]').val(properties.ConfNo);
            util.sendEvent(confInput.get(0), 'input');
            var nameInput = form.find('input[name = "vm.lookupModel.lastName"]').val(properties.LastName);
            if (nameInput.length > 0) {
                util.sendEvent(nameInput.get(0), 'input');
            }
            provider.setNextStep('itLoginComplete', function() {
                form.find('button[ng-click *= "vm.CNValidation.submit(VMCForm);"]').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId > 0    ) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.budget.com/en/loyalty-profile/fastbreak/dashboard/my-activity/upcoming-reservations';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log('toItineraries');
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('span:contains("'+ confNo +'")').closest('div.rental-left-content').find('a:contains("View Details")');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function(){
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
    },

	submitLogin: function(params, form) {
		provider.setNextStep('checkLoginErrors', function () {
			form.find('.btn-submit').get(0).click();
			setTimeout(function(){
				plugin.checkLoginErrors(params);
			}, 5000);
		});
	},

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('.mainErrorText:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    }
};
