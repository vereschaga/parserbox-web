var plugin = {

    hosts: {
        'www.totalrewards.com'   : true,
        'totalrewards.com'       : true,
        "www.caesars.com"        : true,
        "www.harrahslasvegas.com": true,
        "www.harrahsresort.com"  : true,
        "www.harrahs.com"        : true
    },

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.caesars.com/myrewards/profile/#myrewards';
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
        browserAPI.log("isLoggedIn");
        if ($('form[action *= "login"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Sign Out")').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

	isSameAccount: function(account) {
        browserAPI.log("isSameAccount");
        let number = $('div.trnum span').text();
        // mobile
        if (provider.isMobile) {
            number = $('li:contains("Rewards #:") span').text();
        }
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.AccountNumber) !== 'undefined')
            && (account.properties.AccountNumber !== '')
            && number
            && (number === params.account.properties.AccountNumber));
	},

    logout: function(){
        browserAPI.log("logout");
        provider.setNextStep('start');
        $('a:contains("Sign Out")').get(0).click();
    },

	login: function(params){
        browserAPI.log("login");
		let form = $('form[action *= "login"]:visible');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        form.find('input[name = "userID"]').val(params.account.login);
        form.find('input[name = "userPassword"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            $('button:contains("SIGN IN")').removeAttr('disabled');
            form.submit();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

	checkLoginErrors: function(params) {
        browserAPI.log("checkLoginErrors");
		let errors = $('div#errorMsg span:visible, div[class *= "index-module_form-error"]:visible:eq(0)');

		if (errors.length > 0 && util.filter(errors.text()) !== '') {
			provider.setError(util.filter(errors.text()));
		    return;
        }

        plugin.loginComplete(params);
	},

    loginComplete: function(params) {
        browserAPI.log("loginComplete");

        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId > 0
        ) {
            document.location.href = 'https://www.caesars.com/myrewards/profile/#reservations';
            setTimeout(function() {
                plugin.toItineraries(params);
            }, 2000);
            return;
        }

        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('span.confirmation:contains("'+ confNo +'")').closest('div.billing-information').nextAll('button[type = "submit"]').first();
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function() {
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
    }

};
