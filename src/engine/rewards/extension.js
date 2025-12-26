var plugin = {


    hosts: {'www.idine.com': true},

    getStartingUrl: function (params) {
        return 'http://www.idine.com/';
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
        if ($("a[href*=logout]").attr('href') == '/logout.htm') {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#loginform').length > 0 || $('div.loginButtonClosed').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        browserAPI.log("can't determine login state");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = plugin.findRegExp(/Account\s*#\s*:\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm');
        document.location.href = 'http://www.idine.com/logout.htm';
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login');
        document.location.href = 'https://www.idine.com/login.htm';
    },

    login: function (params) {
        if (document.location.href != 'https://www.idine.com/login.htm') {
            browserAPI.log("reload");
            provider.setNextStep('login');
            document.location.href = 'https://www.idine.com/login.htm';
            return false;
        }
        browserAPI.log("login");
        var form = $('#loginform');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "loginId"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.submit();
        }
        else {
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div#txt1');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    findRegExp: function (regExp, required) {
        var matches = regExp.exec($('#snapshot').text());
        if (matches) {
            console.log('matched regexp: ' + regExp);
            result = matches[1];
        }
        else {
            console.log('failed regexp: ' + regExp);
            if (required)
                console.log('regexp not found');
            else
                result = null;
        }
        return result;
    }
}
