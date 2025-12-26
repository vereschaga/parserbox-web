var plugin = {


    hosts: {'www.sportsauthorityleague.com': true, 'www.sportsauthority.com': true},

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    getStartingUrl: function(params){
        return 'https://www.sportsauthorityleague.com/RewardCertificates.aspx';
    },

	startFromChase: function(params) {
		provider.setNextStep('start');
		document.location.href = plugin.getStartingUrl(params);
	},

    fromCashback: function (params) {
        browserAPI.log("fromCashback");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    start: function(params){
		// starting url logs user out
        setTimeout(function() {
            if (plugin.isLoggedIn()) {
                if (plugin.isSameAccount(params.account))
                    plugin.loginComplete(params);
                else
                    plugin.logout();
            }
            else {
                // open popup
                $('li.menu-show-true a[onclick *= signIn]').get(0).click();
                plugin.login(params);
            }
        }, 1000)
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        var sigIn = $('li.menu-show-true:not([class *= hidden]) a[onclick *= signIn]');
        if (sigIn.length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (sigIn.length == 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("can't determine");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('#MainContent_accountInfo_lblCardNumber').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.eval("LogOut();")
    },

    login: function(params){
        browserAPI.log("login");
        setTimeout(function() {
            var form = $('iframe.fancybox-iframe').contents().find("form#PopupForm");
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "ctl00$MainContent$txt_Username"]').val(params.account.login.toUpperCase());
                form.find('input[name = "ctl00$MainContent$txt_Pass"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors');
                form.find('input[name = "ctl00$MainContent$btn_SignIn"]').get(0).click();
            }
            else {
                provider.setError('Login form not found');
                throw 'Login form not found';
            }
        }, 3000)
    },

    checkLoginErrors: function(params){
		var errors = $('div#login_vsError ul li');
		if (errors.length > 0) {
            provider.setError(errors.text());
		}
        else {
            plugin.loginComplete(params);
		}
    },

	loginComplete: function(params){
		if(typeof(params.account.fromPartner) == 'string'){
			setTimeout(provider.close, 1000);
		}
		provider.complete();
	}
}