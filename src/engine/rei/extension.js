var plugin = {
    
    //keepTabOpen: true,
    hosts: {'www.rei.com': true},

    getStartingUrl: function(params){
		return 'https://www.rei.com/YourAccountInfoInView';
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
                    if (plugin.isSameAccount(params))
                        provider.complete();
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
		
		if ($('form#Logon').length > 0)
			return false;
		if ($('span:contains("Member number:")').length > 0)
			return true;
		
        return null;
    },

    isSameAccount: function (params) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
		var number = $('span:contains("Member number:")').text().match(/Member number: (\d+)/)[1];
        browserAPI.log("number: " + number);
        return ((typeof(params.account.properties) != 'undefined')
            && (typeof(params.account.properties.AccountNumber) != 'undefined')
            && (params.account.properties.AccountNumber != '')
            && (number == params.account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
			document.location.href = 'https://www.rei.com/Logoff?storeId=8000&URL=/YourAccountInfoOutView?storeId=8000';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#Logon');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
			form.find('input#logonId').val(params.account.login);
			form.find('input#password').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[type="submit"]').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
		var errors = $('p.alert-danger');
        if (errors.length > 0 && util.filter(util.trim(errors.text())) !== '')
            provider.setError(util.filter(util.trim(errors.text())));
        else
            provider.complete();
    }

}