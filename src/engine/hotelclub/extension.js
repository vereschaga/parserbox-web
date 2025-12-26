var plugin = {

    hosts: {'www.hotelclub.com': true},
    //cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        plugin.loadLoginForm(params);
    },

    getStartingUrl: function (params) {
        return 'https://www.hotelclub.com/trips/current';
    },

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
        if ($('#content form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=logout]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("Can't determine login state");
        provider.setError(["Can't determine login state", util.errorCodes.providerError]);
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.trim($('span.userName').text());
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (0 === account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm');
        document.location.href = 'https://www.hotelclub.com/account/logout';
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#content form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find("input[name = 'models['userName'].userName']").val(params.account.login);
            form.find("input[name = 'models['loginPasswordInput'].password']").val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('input[name = "_eventId_submit"]').click();
        }
        else {
            browserAPI.log("Login form not found");
            provider.setError(['Login form not found', util.errorCodes.providerError]);
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function () {
        var errors = $('p.error');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params){
        if(typeof(params.account.fromPartner) == 'string'){
            setTimeout(provider.close, 1000);
        }
        provider.complete();
    }
}