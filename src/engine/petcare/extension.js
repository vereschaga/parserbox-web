var plugin = {

    hosts: {'www.petcarerx.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.petcarerx.com/login/login_frame.aspx?redirUrl=http%3a%2f%2fwww.petcarerx.com%2fdefault.aspx';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    start: function (params) {
        if ($.browser.msie) {
            browserAPI.log("ie version");
            provider.setError('Unfortunately, your browser is not supported.');/*review*/
            throw "Unfortunately, your browser is not supported.";/*review*/
        }
        // why?
        if ($('a[data-tab = "login"]:contains("Sign In")').length > 0) {
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

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#form1').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[data-tab = "login"]:contains("Not You")').length > 0 || $('a#ctl17_logout_btn').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const name = $('p.accountname').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm');
        $('a#ctl17_logout_btn').get(0).click();
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('#form1');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "tbUsername"]').val(params.account.login);
        form.find('input[name = "tbPassword"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors');
        form.find('input[name = "btnSignIn"]').click();
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('#login-error-msg, p.alert-danger:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    }
}
