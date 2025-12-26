var plugin = {

    hosts: {'www.onetwotrip.com': true},

    getStartingUrl: function (params) {
        return 'https://www.onetwotrip.com/en-gb/p/';
    },

	loadLoginForm: function () {
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
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
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#topMenu_logout').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#SocialAuth:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
		browserAPI.log("isSameAccount");
        return $('.email > div').text() == account.login;
    },

    logout: function () {
        browserAPI.log("logout");
		provider.setNextStep('loadLoginForm', function () {
			$('#topMenu_logout').get(0).click();
		});
    },
	
    login: function (params) {
		browserAPI.log("login");
		$('.menu-item.login').get(0).click();
        var form = $('form#SocialAuth');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
			form.find('input[name = "auth_email"]').val(params.account.login);
			form.find('input[name = "auth_pas"]').val(params.account.password);

            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    form.find('button[type = "submit"]:contains("Log In")').get(0).click();
                    var interval = setInterval(function () {
                        if ($('.asidePreloader:visible').length == 0) {
                            clearInterval(interval);
                            plugin.checkLoginErrors(params);
                        }
                    }, 100);
                }, 500);
            });
        }else
            provider.setError(util.errorMessages.loginFormNotFound);
    },
	
	checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('#SocialAuth .Error:visible');
        if (errors.length > 0){
            provider.setError(errors.text());
			
			// incorrect login/password
		} else if($('.field.error #input_auth_email, .field.error #input_auth_pas').length > 0){
			util.sendEvent($('.field.error #input_auth_email, .field.error #input_auth_pas').get(0), 'focus');
			setTimeout(function(){
				provider.setError($('.hint.error').text());
			}, 100);
		} else {
            plugin.loginComplete(params);
		}
    },
	
	loginComplete: function(params) {
        browserAPI.log("loginComplete");
		// if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
		// 	// provider.setNextStep('toItineraries', function() {
		// 	// 	//document.location.href = 'https://secure.onetwotrip.com/en-gb/p/';
		// 	// });
         //    plugin.toItineraries();
        //
         //    return;
		// }
		provider.complete();
	},
	
	toItineraries: function(params) {
		browserAPI.log("toItineraries");
		var confNo = params.account.properties.confirmationNumber;
		setTimeout(function(){
			var link = $('a.show_order:contains("'+ confNo +'")');
			if (link.length > 0) {
				link.removeAttr('target');
				provider.setNextStep('itLoginComplete', function(){
					link.get(0).click();
				});
			}
			else
				provider.setError(util.errorMessages.itineraryFormNotFound);
		}, 2000);
	},

	itLoginComplete: function(params) {
		browserAPI.log("itLoginComplete");
		provider.complete();
	}

	
};
