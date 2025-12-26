var plugin = {

    hosts: {'www.flyporter.com': true, 'www1.flyporter.com': true},

    getStartingUrl: function (params) {
        return 'https://www.flyporter.com/Modify-Booking/Bookings?culture=en-US';
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
        if ($('#formLoginViporter').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Sign Out")')) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('input[value = "Sign Out"]').attr('value')) {
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
        var number = util.findRegExp($('#marketing_touts').text(), /Member\s*Number\s*:\s*(\d+\s*\d*\s*\d*)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('login');
        document.location.href = 'https://www.flyporter.com//Login/Sign-Out?culture=en-US';
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#formLoginViporter');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "VIPorterNumberOrEmailOrUsername"]').val(params.account.login);
            form.find('input[name = "Password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.submit();
        }
        else {
            browserAPI.log('Login form not found');
            provider.setError(['Login form not found', util.errorCodes.providerError]);
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function () {
        var errors = $('div.errorMessage');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}
