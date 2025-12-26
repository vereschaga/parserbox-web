var plugin = {


    hosts: {'www.target.com': true, 'targetpharmacyrewards.com': true},

    getStartingUrl: function(params){
        return 'https://targetpharmacyrewards.com';
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
        if(plugin.isLoggedIn())
            if(plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout();
        else
            plugin.login(params);
    },

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if( $('p#welcomeTxt').length > 0){
            browserAPI.log("LoggedIn");
            return true;
        }
        if( $('input#login_btnLogin').length > 0){
            browserAPI.log("not LoggedIn");
            return false;
        }
        browserAPI.log("can't determine");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function(account){
        return false;
    },

    logout: function(){
        browserAPI.log("logout");
        provider.setNextStep('login');
		document.location.href = 'https://targetpharmacyrewards.com/CP/logout.aspx';
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('form[name="CPForm"]');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            form.find('input[name="login$email"]').val(params.account.login);
            form.find('input[name="login$password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
			form.find('input#login_btnLogin').click();
        }
        else
            provider.setError('code 1');
    },

    checkLoginErrors: function(params){
		var errors = $('div#login_vsError ul li');
		if (errors.length > 0) {
			if (errors.text().indexOf('Your username and password are not recognized'))
				provider.setError('Invalid credentials');
			else
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