var plugin = {


    hosts: {'www.partnerplusbenefit.com': true},

    getStartingUrl: function (params) {
        return 'https://www.partnerplusbenefit.com/application/partner/postbox/showPostbox.do';
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    start: function (params) {
        // if need to choose a continent
        if ($('td:contains("Please choose your continent:")').text()) {
            provider.setNextStep('loadLoginForm');
            document.location.href = 'https://www.partnerplusbenefit.com/application/pages2/common/A090NewChooseLocalePage.jsp?urlParamLanguage=en&urlParamCountry=CH';
            return;
        }
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
        if ($('form[name = "common.loginForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=logout]').text()) {
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
        var number = plugin.findRegExp($('pre.userID').next().html(), /(..[0-9]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        document.location.href = 'https://www.partnerplusbenefit.com/application/module/common/logout.do';
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "common.loginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('input[name = "doLogin"]').click();
        }
        else {
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function () {
        var errors = $('.error');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
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