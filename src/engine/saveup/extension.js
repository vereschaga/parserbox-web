var plugin = {



    hosts: {'saveup.com': true, 'www.saveup.com': true},

    getStartingUrl: function (params) {
        return 'https://www.saveup.com/users/sign_in';
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn())
            if (plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout();
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form.sign_in').length > 0) {
            browserAPI.log("LoggedIn, form found");
            return false;
        }
        if ($('a.sign_out').length > 0) {
            browserAPI.log("LoggedIn, sign out found");
            return true;
        }
        browserAPI.log("can't determine");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = $('a.user');
        return ((typeof(account.properties.Name) != 'undefined') &&
                (account.properties.Name != '') &&
                (typeof(name) != 'undefined' &&
                 name.text().toLowerCase().indexOf(account.properties.Name.toLowerCase()) != -1));
    },

    logout: function () {
        browserAPI.log("logout");
        $('a.sign_out').get(0).click();
        provider.setNextStep('loadLoginForm');
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form.sign_in');
        if (form.length > 0) {
            form.find('input#user_email').val(params.account.login);
            form.find('input#user_password').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.submit();
        }
        else {
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl(params);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var error = $('.note:visible');
        if (error.length > 0) {
            provider.setError(error.test());
        }
        else
            provider.complete(params);
    }
}