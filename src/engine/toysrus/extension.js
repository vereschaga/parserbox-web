var plugin = {


    hosts: {'rewardsrus.toysrus.com': true, 'www.toysrus.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params){
        return 'https://rewardsrus.toysrus.com/index.cfm/account#summary';
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

    start: function (params) {
        browserAPI.log("start");
        // cash back
        if (document.location.href.indexOf('shop') > 0) {
            provider.setNextStep('start');
            document.location.href = plugin.getStartingUrl(params);
            return;
        }
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout(params);
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[action = "/index.cfm/login/index"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= logout]').attr('href')) {
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
        var number = plugin.findRegExp( $('div:contains("Member #")').html(), /Member\s*#\s*([^<]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm');
        document.location.href = 'https://rewardsrus.toysrus.com/index.cfm/login/logout';
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[action = "/index.cfm/login/index"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
        	form.find("input[name = 'strAccountOrEmail']").val(params.account.login);
        	form.find("input[name = 'strPassword']").val(params.account.password);
			provider.setNextStep('checkLoginErrors');
			form.find('button[name = "submit"]').click();
        }
        else {
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function (params) {
        var errors = $("p.error");
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

	loginComplete: function(params){
        if (typeof(params.account.fromPartner) == 'string') {
			setTimeout(provider.close, 1000);
		}
		provider.complete();
	},

    findRegExp: function (elem, regExp, required) {
        var matches = regExp.exec(elem);
        if (matches) {
            browserAPI.log('matched regexp: ' + regExp);
            result = matches[1];
        }
        else {
            browserAPI.log('failed regexp: ' + regExp);
            if (required)
                browserAPI.log('regexp not found');
            else
                result = null;
        }
        return util.trim(result);
    }

}
