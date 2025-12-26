var plugin = {

    hosts: {'www.splendia.com': true, 'splendia.com': true},

    getStartingUrl: function (params) {
        return 'https://www.splendia.com/en/club/profile/';
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn())
            if (plugin.isSameAccount(params.account)) {
                document.location.href = "https://www.splendia.com/en/club/my-splendia";
                provider.complete();
            }
            else
                plugin.logout(params.account.login2);
        else
            plugin.loadLoginForm(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#form-signin').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        browserAPI.log("Can't determine login state");
        provider.setError(["Can't determine login state", util.errorCodes.providerError]);
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var email = $('#email').attr('value');
        browserAPI.log("email: " + email);
        return ((typeof(account.login) != 'undefined')
            && (account.login != '')
            && (email == account.login));
    },

    logout: function (region) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm');
        document.location.href = 'https://www.splendia.com/auth/logout';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login');
        if (document.location.href == 'https://www.splendia.com/en/login/')
            plugin.login(params);
        else
            document.location.href = 'https://www.splendia.com/en/login/';
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#form-signin');
        if (form.length > 0) {
            form.find('input#email').val(params.account.login);
            form.find('input#password').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            // refs #11326
            util.sendEvent(form.find('input#email').get(0), 'input');
            util.sendEvent(form.find('input#password').get(0), 'input');
            form.find("button").click();
            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 2000)
        }
        else {
            browserAPI.log('Login form not found');
            provider.setError(['Login form not found', util.errorCodes.providerError]);
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var error = $('message div.alert');
        if (error.length > 0)
            provider.setError(error.text());
        else
            provider.complete();
    }
}