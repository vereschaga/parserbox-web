var plugin = {


    hosts: {'points.plink.com': true, 'www.plink.com': true},

    getStartingUrl: function (params) {
        return 'https://points.plink.com/account';
    },

    start: function (params) {
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
        var form = $('form[id = "organic-sign-in-form"]');
        if (form.length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a.logout').attr('href')) {
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
        var name = $('h2.bold').text().trim();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name == account.properties.Name));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start');
        $('a.logout').get(0).click();
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[id = "organic-sign-in-form"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "user_session[email]"]').val(params.account.login);
            form.find('input[name = "user_session[password]"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('button[name = button]').get(0).click();
        }
        else
            provider.setError("can't find login form");
    },

    checkLoginErrors: function () {
        var errors = $('div#errorCopy');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}
