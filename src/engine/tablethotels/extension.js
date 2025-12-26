
var plugin = {

    hosts: {'www.tablethotels.com': true},

    getStartingUrl: function (params) {
        return 'https://www.tablethotels.com/account/Bookings';
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#user-signin-form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= logout]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('p.accountname').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        document.location.href = 'https://www.tablethotels.com/logout';
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#user-signin-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            if ($.browser.msie) {
                browserAPI.log("ie version");
                var div = document.getElementById('paneoverlay');
                div.innerHTML = div.innerHTML + "<SCRIPT DEFER>jQuery.noConflict();</SCRIPT>";
            }
            form.find('button[type = "submit"]').click();
        }
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function () {
        var errors = $('p.form-field-error');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
};
