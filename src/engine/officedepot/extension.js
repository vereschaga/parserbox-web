var plugin = {

    hosts: {'www.officemaxperks.com': true, 'www.officedepot.com': true, 'www.officedepotrewards.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.officedepot.com/account/accountSummaryDisplay.do';
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
        if ($('h1:contains("My Account Overview")').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form.login-form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.filter($('p.membership-id').text());
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && number
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm');
        document.location.href = 'http://www.officedepot.com/account/logout.do';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl(params);
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form.login-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // form.find('input[name = "username"]').val(params.account.login);
            // form.find('input[name = "password"]').val(params.account.password);
            // reactjs
            provider.eval(
                "var FindReact = function (dom) {" +
                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                "        return dom[key];" +
                "    }" +
                "    return null;" +
                "};" +
                "FindReact(document.querySelector('input#login-username-container-login-username-input')).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});" +
                "FindReact(document.querySelector('input#login-password-container-login-password-input')).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
            );
            provider.setNextStep('checkLoginErrors');
            form.find('button[data-auid="LoginPage_OdButton_LoginBtn"]').click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        }
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.login-error:visible').find('div.od-callout-description');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};
