var plugin = {

    hosts: {
        'www.redlion.com': true
    },

    getStartingUrl : function (params) {
        return 'https://www.redlion.com/login';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
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
			}// if (isLoggedIn !== null)
			if (isLoggedIn === null && counter > 20) {
				clearInterval(start);
				provider.setError(util.errorMessages.unknownLoginState);
				return;
			}// if (isLoggedIn === null && counter > 20)
			counter++;
		}, 500);
	},

    isLoggedIn : function () {
        browserAPI.log('isLoggedIn');
        if ($('#js-magpopup-signin:visible').length > 0) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if ($('p:contains("Member number:"):visible').length > 0) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        return null;
    },

    isSameAccount : function (account) {
        browserAPI.log('isSameAccount');
        var memberId = util.findRegExp($('p:contains("Member number:")').text(), /\:\s*(\d+)/i);
        browserAPI.log("number: " + memberId);
        return (
            'undefined' != typeof account.properties
            && 'undefined' != typeof account.properties.Number
            && '' != account.properties.Number
            && memberId == account.properties.Number
        );
    },

    logout : function () {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            $('#oktaToggleLogout').click();
        });
    },

    login : function (params) {
        browserAPI.log('login');
        var form = $('#js-magpopup-signin');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('#oktaLoginEmail').val(params.account.login);
            form.find('#oktaLoginPassword').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#oktaLoginSubmit').get()[0].click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors : function () {
        browserAPI.log('checkLoginErrors');
        var error = $('#oktaLoginMessages > p:visible');
        if (error.length && util.filter(error.text()) != '')
            provider.setError(error.text());
        else
            provider.complete();
    }

};
