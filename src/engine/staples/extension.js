var plugin = {

	hosts: {
	    'www.staples.com': true,
        'www.staplesrewardscenter.com': true,
        'login.staples.com': true,
        'rewards.staples.com': true,
		'www.staplesdividends.ca': true,
        'print.staples.com': true,
        '.staples.com': true,
    },

    clearCache: true,

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

	getStartingUrl: function(params) {
        return 'https://www.staples.com/gus/sdc/profileinfo/account/v2/registeredUser';
	},

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

	start: function(params) {
        browserAPI.log("start");
		if (plugin.isLoggedIn(params)) {
			if (plugin.isSameAccount(params.account))
				plugin.loginComplete(params);
			else
				plugin.logout(params.account.login2);
		}// if (plugin.isLoggedIn())
		else {
            provider.setNextStep('login', function () {
                document.location.href = "https://www.staples.com/office/supplies/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051";
            });
        }
	},

	isLoggedIn: function(params){
		browserAPI.log("isLoggedIn");
		// USA
		if (
            $('div.LoginCom__forgotUsernameWrapper').length > 0
            || $('div[data-testid="dotcom_loginform_username"]').length > 0
            || $('span.stp--account-name:contains("Sign In")').length > 0
            || (provider.isMobile && $('div[id="Sign In"]:visible').text().trim() == 'Sign In')
        ) {
			browserAPI.log("not LoggedIn");
			return false;
		}
		if (
            $('div.MyAccount__profileField').length
            || (provider.isMobile && $('div[id="account"]:visible').text().trim() == 'Account')
        ) {
			browserAPI.log("LoggedIn");
			return true;
		}
        provider.setError(util.errorMessages.unknownLoginState);
	},

	isSameAccount: function(account) {
        browserAPI.log("isSameAccount");
        let username = util.filter($('#userName_profile_card').text());
        let email = util.filter($('#email_profile_card').text());
		browserAPI.log("username: " + username);
		browserAPI.log("email: " + email);
		return ((typeof(account.properties) != 'undefined')
			&& (
                (username && username.toLowerCase() === account.login.toLowerCase())
                || (email.toLowerCase() === account.login.toLowerCase())
            ));
	},

    logout: function (params) {
		browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.staples.com/office/supplies/StaplesLogoff?langId=-1&storeId=10001&catalogId=10051';
        });
	},

	login: function(params){
		browserAPI.log("login");
        let form = $('div.LoginCom__forgotUsernameWrapper, div[data-testid="dotcom_loginform_username"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // form.find('input[name = "username"]').val(params.account.login);
        // form.find('input[name = "password"]').val(params.account.password);

        // reactjs
        provider.eval(
            "var FindReact = function (dom) {" +
            "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
            "        return dom[key];" +
            "    }" +
            "    return null;" +
            "};" +
            "FindReact(document.querySelector('input[name = \"username\"], input[id = \"loginUsername\"]')).onChange({currentTarget:{value:'" + params.account.login + "'}, preventDefault:function(){}, stopPropagation:function(){}});" +
            "FindReact(document.querySelector('input[name = \"password\"], input[name = \"loginPassword\"]')).onChange({currentTarget:{value:'" + params.account.password + "'}, preventDefault:function(){}, stopPropagation:function(){}});"
        );

        provider.setNextStep('checkLoginErrors', function () {
            // form.find('#loginBtn').get(0).click();
            provider.eval('document.getElementById(\'loginBtn\').click()');
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000);
        });
	},

	checkLoginErrors: function(params) {
        browserAPI.log("checkLoginErrors");
        var error = $('.login__sparq_errorBlock:visible');

		if (error.length > 0) {
            provider.setError(error.text());
            return;
        }

		plugin.loginComplete(params);
	},

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
		provider.complete();
	}

};
