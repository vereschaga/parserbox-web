var plugin = {

    hosts: {'www.vitaminshoppe.com': true},

    getStartingUrl: function (params) {
        return 'https://www.vitaminshoppe.com/s/myAccount/myAccount.jsp';
    },

    /*deprecated*/
    startFromChase: function(params) {
        plugin.loadLoginForm(params);
    },

    /*deprecated*/
    fromCashback: function (params) {
        browserAPI.log("fromCashback");
        plugin.loadLoginForm(params);
    },

    // for Cashback auto-login
    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[id = "vs_registerLoginForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Sign Out")').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        var name = $('span:contains("Name:") + span').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        document.location.href = 'https://www.vitaminshoppe.com' + $('a:contains("Sign Out")').attr('href');
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[id = "vs_registerLoginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "vs_registerLoginPassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('input[name = "/atg/userprofiling/ProfileFormHandler.login"]').click();
        }
        else {
            browserAPI.log("Login form not found");
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.error-master div.error-master');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        if (typeof(params.account.fromPartner) == 'string') {
            setTimeout(provider.close, 1000);
        }
        provider.complete();
    }
}