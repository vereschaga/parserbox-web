var plugin = {

	hosts: {'book.princess.com': true},

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

	getStartingUrl: function(params) {
		return "https://book.princess.com/captaincircle/myPrincess.page";
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
		if ($('form[name="signin"]').length > 0) {
			browserAPI.log('not logged in');
			return false;
		}
        if ($('.guest-ccn-wrapper:visible').length > 0) {
			browserAPI.log("LoggedIn");
			return true;
		}
        return null;
	},

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
		// for debug only
		//browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( util.filter($('.guest-ccn-wrapper .guest-ccn').text()), /#\s*(\w+)/i);
		browserAPI.log("name: " + number);
		return ((typeof(account.properties) != 'undefined')
			&& (typeof(account.properties.Number) != 'undefined')
			&& (account.properties.Number != '')
            && number
			&& (number.toLowerCase() == account.properties.Number.toLowerCase()));
	},

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('login', function () {
            //provider.eval('clearSession("Y", "N");');
            setTimeout(function () {
                provider.eval('window.guestAuth.logout(!1);');
            }, 5500);
        });
	},

    login: function (params) {
		browserAPI.log("login");
		var form = $('form[name="signin"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            /*form.find('input[name = "loginId"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#signin-btn').removeClass('deactive');
                form.find('#signin-btn').click();
            });*/

            // reactjs
            provider.eval(
                "function triggerInput(selector, enteredValue) {\n" +
                "      let input = document.querySelector(selector);\n" +
                "      input.dispatchEvent(new Event('focus'));\n" +
                "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
                "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
                "      nativeInputValueSetter.call(input, enteredValue);\n" +
                "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
                "      input.dispatchEvent(inputEvent);\n" +
                "}\n" +
                "triggerInput('input[id = \"loginId\"]', '" + params.account.login + "');\n" +
                "triggerInput('input[id = \"password\"]', '" + params.account.password + "');"
            );
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#signin-btn').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
	},

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
		var errors = $('span.bodycopyboldred:contains("Your email address, or password cannot be found.")');
		if (errors.length > 0)
			provider.setError(errors.text());
		else {
			if ($('form[name="login"]').length > 0)
				provider.setError('Login failed');
			else
				plugin.loginComplete(params);
		}
	},

	loginComplete: function(params) {
        browserAPI.log("loginComplete");
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
			plugin.toItineraries(params);
			return;
		}
		provider.complete();
	},

	toItineraries: function(params) {
        browserAPI.log("toItineraries");
		var confNo = params.account.properties.confirmationNumber;
		var link = $('a[href *= "bookingId=' + confNo + '"]:eq(0)');
		if (link.length > 0) {
			provider.setNextStep('itLoginComplete', function () {
                link.get(0).click();
            });
		}
		else
            provider.setError(util.errorMessages.itineraryNotFound);
	},

	itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
		provider.complete();
	}
}