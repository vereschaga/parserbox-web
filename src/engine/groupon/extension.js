var plugin = {

    hosts: {'www.groupon.com': true, 'www.groupon.co.uk': true, 'www.groupon.ca': true, 'www.facebook.com': true, 'www.groupon.com.au': true},
    
    cashbackLinkMobile : false,
    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params){
        switch (params.account.login2) {
            case 'UK':
                return 'https://www.groupon.co.uk/login';
                break;
            case 'Canada':
                return 'https://www.groupon.ca/login';
                break;
            case 'Australia':
                return 'https://www.groupon.com.au/login';
                break;
            case 'USA':
            default:
                return 'https://www.groupon.com/login';
                break;
        }
    },

    // for Cashback auto-login
    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        if (params.account.login3 == 'facebook')
            provider.setNextStep('loadFBLoginForm');
        else
            provider.setNextStep('login');
        if (document.location.href == plugin.getStartingUrl(params)) {
            return plugin.login(params);
        }
        document.location.href = plugin.getStartingUrl(params);
    },

    start: function(params){
        browserAPI.log("start");
        if (plugin.getStartingUrl(params).indexOf(document.location.host) == -1
            && !plugin.isLoggedIn(params.account.login2)) {
            provider.setNextStep('start');
            document.location.href = plugin.getStartingUrl(params);
            return;
        }
        var form = $('form.form_en_CA');
        if (form.length > 0) {
            provider.setNextStep('start');
            form.submit();
            return;
        }
        if (plugin.isLoggedIn(params.account.login2))
            plugin.logout(params.account.login2);
        else
            plugin.loadLoginForm(params);
        // it's better
//        if (plugin.isLoggedIn(params.account.login2)) {
//            if (plugin.isSameAccount(params.account))
//                provider.complete();
//            else
//                plugin.logout(params.account.login2);
//        }
//        else
//            plugin.login(params);
    },

//    isSameAccount: function(account){
//        // for debug only
//        //browserAPI.log("account: " + JSON.stringify(account));
//        var name = $('a.user-name').text();
//        browserAPI.log("name: " + name);
//        return ((typeof(account.properties) != 'undefined')
//            && (typeof(account.properties.Name) != 'undefined')
//            && (account.properties.Name != '')
//            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
//    },

    isLoggedIn: function(region){
        browserAPI.log("isLoggedIn");
        browserAPI.log("Region => " + region);
        if ($('a[href *= logout]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#master_form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('form[data-bhw = "LoginForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a.first[data-bhw = "SignIn"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('form[action*="/login"]').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        browserAPI.log("Can't determine login state");
        provider.setError(["Can't determine login state", util.errorCodes.providerError]);
        throw "Can't determine login state";
    },

    logout: function(region){
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm');
        switch (region) {
                case 'UK':
                document.location.href = 'https://www.groupon.co.uk/logout';
                break;
            case 'Canada':
                document.location.href = 'https://www.groupon.ca/logout';
                break;
            case 'Australia':
                document.location.href = 'https://www.groupon.com.au/logout';
                break;
            case 'USA':
            default:
                document.location.href = 'https://www.groupon.com/logout';
                break;
        }
    },

    isFacebook: function() {
        return ($('html#facebook').length > 0);
    },

    loadFBLoginForm: function(params) {
        if ($('div.facebook_login_button').length > 0)
            setTimeout(function(){ $('div.facebook_login_button').click();}, 3000);
        else
            setTimeout(function(){ document.getElementById('auth-fb-loginButton').click();}, 3000);
        provider.setNextStep('facebookLogin');
    },

    facebookLogin: function(params) {
        if (plugin.isFacebook()) {
            var form = $('form#login_form');
            if (form.length > 0) {
                provider.setNextStep('checkLoginErrorsFB');
                $('input#email').val(params.account.login);
                $('input#pass').val(params.account.password);
                form.submit();
            }
            else
                provider.setError('Autologin failed');
        }
        else
            if ($('input#session_email_address').length == 0 && $('input#email') == 0)
                //already signed in on facebook
                plugin.loginComplete(params);
    },

    checkLoginErrorsFB: function(params) {
        if (plugin.isFacebook()) {
            var errors = $('h2#standard_error');
            if (errors.length > 0) {
                provider.setError(errors.text());
                return;
            }
            var form = $('#uiserver_form');
            if (form.length > 0)
                form.submit();
        }
        plugin.loginComplete(params);
    },

    login: function(params){
        browserAPI.log("login");
        provider.setNextStep('checkLoginErrors');
        var form = $('form#master_form');
        if (form.length == 0)
            form = $('form[data-bhw = "LoginForm"]');
        0 == form.length ? form = $('form[action*="/login"]') : null;
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            if ($('#recaptcha_challenge_image:visible, iframe[src*="/recaptcha/"]:visible').length) {
                if (provider.isMobile) {
                    api.command('show', function () {
                        api.reCaptchaMessage();
                    });
                } else
                    provider.reCaptchaMessage();
            } else
                form.submit();
        }
        else {
            browserAPI.log("Login form not found");
            provider.setError(["Login form not found", util.errorCodes.providerError]);
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function(params) {
        var errors = $('li.error');
        if (errors.length > 0) {
            var errorMsg = $('li.error div').text();
            var caption = $('li.error div span.caption').text();
            provider.setError((errorMsg.substr(0, errorMsg.indexOf(caption)).trim()));
            return;
        }
        errors = $('div.notification.error:visible');
        if(!errors.length)
            errors = $('div.generic-error:visible');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }
        plugin.loginComplete(params);
    },

	loginComplete: function(params){
		if(typeof(params.account.fromPartner) == 'string'){
			setTimeout(provider.close, 1000);
		}
		provider.complete();
	}

};
