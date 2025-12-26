var plugin = {


    hosts: {'www.officemaxperks.com': true, 'www.officemax.com': true, 'www.officedepot.com': true},

    getStartingUrl: function (params) {
        return 'https://www.officemaxperks.com/Home.aspx';
    },

    startFromChase: function (params) {
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    fromCashback: function (params) {
        browserAPI.log("fromCashback");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    start: function (params) {
        // cash back
        if (document.location.href.indexOf('affcode') > 0) {
            provider.setNextStep('start');
            document.location.href = plugin.getStartingUrl(params);
            return;
        }
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
        if ($('a:contains("Logout")').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#aspnetForm').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        browserAPI.log("can't determine");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = plugin.findRegExp(/ Member\s*ID\s*:\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        provider.setNextStep('login');
        var logout = document.createElement('script');
        logout.type = 'text/javascript';
        logout.innerHTML = "__doPostBack('ctl00$ctl10$lbLogout','');";
        document.head.appendChild(logout);
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#aspnetForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "ctl00$ContentPlaceHolderLeftPanel$txtLoginField"]').val(params.account.login);
            form.find('input[name = "ctl00$ContentPlaceHolderLeftPanel$txtPassField"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('input[name = "ctl00$ContentPlaceHolderLeftPanel$imgSubmit"]').click();
        }
        else {
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function (params) {
        var errors = $('.errorMsg');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    findRegExp: function (regExp, required) {
        var matches = regExp.exec($('#memberID').text());
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
    },

    loginComplete: function (params) {
        if (typeof(params.account.fromPartner) == 'string') {
            setTimeout(provider.close, 1000);
        }
        provider.complete();
    }

}