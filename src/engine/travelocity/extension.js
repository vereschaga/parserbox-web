var plugin = {
    hosts: {'www.travelocity.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.travelocity.com/login';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },
	
	start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
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
        if ($('a[href *= logout]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[name = "loginForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },
	
	isSameAccount: function (account) {
		browserAPI.log("isSameAccount");
        const name = $('div.heading-container h3').text();
        browserAPI.log("name: " + name);
        return (typeof(account.properties) != 'undefined')
           && (typeof(account.properties.Name) != 'undefined')
           && (account.properties.Name !== '')
           && account.properties.Name.toLowerCase().indexOf(name.toLowerCase()) !== -1;
	},
	
    logout: function () {
        browserAPI.log("logout");
		provider.setNextStep('loadLoginForm', function () {
			document.location.href = 'https://www.travelocity.com/user/logout?';
		});
    },

	login: function (params) {
		browserAPI.log("login");
		const form = $('form[name = "loginForm"]');

		if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return
		}

        browserAPI.log("submitting saved credentials");
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
            "triggerInput('input[id = \"loginFormEmailInput\"]', '" + params.account.login + "');\n" +
            "triggerInput('input[id = \"loginFormPasswordInput\"]', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors');
        form.find('#loginFormSubmitButton').get(0).click();
        setTimeout(function() {
            plugin.checkLoginErrors(params);
        }, 30000);
	},

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.uitk-field-message-error:visible, div.uitk-error-summary h3:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('itLoginComplete', function () {
                document.location.href = 'https://www.travelocity.com/trips';
            });
            return;
        }

        provider.complete();
    },

    /*
    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            const link = $('#utility-link a:contains("Trip")');// for more deep need tripNumber

            if (link.length === 0) {
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }

            provider.setNextStep('itLoginComplete', function(){
                link.get(0).click();
            });
        }, 2000);
    },
    */

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};