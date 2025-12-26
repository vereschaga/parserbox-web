var plugin = {

    hosts: {'/\\w+\\.virginmobileusa\\.com/': true},

	getStartingUrl: function(params){
		return 'https://myaccount.virginmobileusa.com/primary/my-account-home';
	},

    fromCashback: function (params) {
        browserAPI.log("fromCashback");
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
                        plugin.loginComplete(params);
                    else
                        plugin.logout();
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

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if ($('form#login-form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a#menu-header-signout, a#nav-mobile-logout').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function(account){
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.filter($('h3#header-greeting-username, p#para-header-bst-message2').text());
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a#menu-header-signout, a#nav-mobile-logout').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#login-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "login-PTN"]').val(params.account.login);
            provider.eval('window.vmuLogin.formatFieldValue($(\'input[name = "login-PTN"]\')[0])');
            form.find('input[name = login-pin]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                provider.eval('window.vmuLogin.validateLoginForm()');
                form.find('#login-enter-button').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 10000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

	checkLoginErrors: function(params) {
        browserAPI.log("checkLoginErrors");
		var errors = $('p#response-message:visible');
        if (errors.length === 0)
		    errors = $('span.validation_message:visible');
		if (errors.length > 0)
            provider.setError(errors.text());
		else
			plugin.loginComplete(params);
	},

	loginComplete: function(params) {
        browserAPI.log("loginComplete");
		provider.complete();
	}

}
