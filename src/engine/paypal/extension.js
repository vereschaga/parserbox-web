var plugin = {

    hosts: {'www.paypal.com': true},

    getStartingUrl: function (params) {
		return 'https://www.paypal.com/signin/';//?country.x=US&locale.x=en_US
    },

    start: function (params) {
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout(params);
        }
        else
            plugin.login(params);
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[name = "login"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= logout]').attr('href')) {
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("can't determine login state");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = plugin.findRegExp( $('p:contains("Hi again,")').text(), /,\s*([^\!<]+)/i );
        if (!name)
            name = plugin.findRegExp( $('h2:contains("Welcome,")').text(), /,\s*([^\!<]+)/i );
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm');
        document.location.href = 'https://www.paypal.com/myaccount/logout';
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "login_email"]').val(params.account.login);
            form.find('input[name = "login_password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('button[name = "btnLogin"]').click();
            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 5000);
        }
        else {
            browserAPI.log("Login form not found");
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function (params) {
        var errors = $('p[role = "alert"]');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    findRegExp: function (elem, regExp, required) {
        var matches = regExp.exec(elem);
        if (matches) {
            browserAPI.log('matched regexp: ' + regExp);
            result = util.trim(matches[1]);
        }
        else {
            browserAPI.log('failed regexp: ' + regExp);
            if (required)
                browserAPI.log('regexp not found');
            else
                result = null;
        }
        return result;
    }

}
