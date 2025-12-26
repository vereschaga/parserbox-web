var plugin = {


    hosts: {'www.restaurant.com': true},

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    getStartingUrl: function (params) {
        return 'https://www.restaurant.com/account/mycertificates';
    },

    fromCashback: function (params) {
        browserAPI.log("fromCashback");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    start: function (params) {
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
        if ($('#signInForm').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=Logout]').text()) {
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
        var name = $('li.account span.first').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm');
        document.location.href = 'https://www.restaurant.com/Account/Logout';
    },

    loadLoginForm: function(){
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl();
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#signInForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            document.getElementById('signIn').click();
            plugin.checkLoginErrors(params);
        }
        else {
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function (params) {
        var errors = $('div[class = "globalMessagingWrapper error"]');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params){
        if (typeof(params.account.fromPartner) == 'string') {
            // don't reopen page
            var info = { message: 'warning', reopen: false, style: 'none'};
            browserAPI.send("awardwallet", "info", info);
        }
        provider.complete();
    }
}