var plugin = {

    hosts: {'www.thinkgeek.com': true},
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    getStartingUrl: function (params) {
        return 'https://www.thinkgeek.com/brain/account/login.cgi';
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
        provider.isMobile ? setTimeout(function(){ plugin.startWait(params) }, 2500) : plugin.startWait(params);
    },
    startWait: function(params) {
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
        if ($('li#loggingin').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Log Out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("Can't determine login state");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('login');
        document.location.href = 'https://www.thinkgeek.com/brain/account/login.cgi?a=lo';
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var email = $('span:contains("' + account.login + '")');
        return (email.length > 0);
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[action = "https://www.thinkgeek.com/brain/account/login.cgi"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name="un"]').val(params.account.login);
            form.find('input[name="pass"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('input[type="submit"]').get(0).click();
        }
        else {
            browserAPI.log("Login form not found");
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function (params) {
        var errors = $('p.error');
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
