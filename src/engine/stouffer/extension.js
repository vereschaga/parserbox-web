var plugin = {


    hosts: {'dinnerclub.stouffers.com': true, 'www.stouffers.com': true},

    getStartingUrl: function (params) {
        return 'https://dinnerclub.stouffers.com/account';
    },

    start: function (params) {
        if (document.location.href != 'https://www.stouffers.com/user.aspx' &&
            document.location.href != 'https://dinnerclub.stouffers.com/account') {
            document.location.href = 'https://www.stouffers.com/user.aspx';
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
        if ($('form[name = login_form]').length > 0 || $('form#form1').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Log out")').text()) {
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
        var matches = /Welcome\s*([^<\.]+)/.exec($('h2#welcome').text() );
        var name = '';
        if (typeof(matches[1]) != 'undefined')
            name = util.trim(matches[1]);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        $('a:contains("Log out")').get(0).click();
    },

    login: function (params) {
        browserAPI.log("login");
//        var form = $('form[name = login_form]');
//        if (form.length > 0) {
//            browserAPI.log("submitting saved credentials");
//            form.find('input[name = "email"]').val(params.account.login);
//            form.find('input[name = "password"]').val(params.account.password);
//            provider.setNextStep('checkLoginErrors');
//            form.submit();
//        }
        var form = $('form#form1');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolder1$ctl00$UserPage_2$txtUserName"]').val(params.account.login);
            form.find('input[name = "ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolder1$ctl00$UserPage_2$txtPassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('input[name = "ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolder1$ctl00$UserPage_2$Login"]').click();
        }
        else
            provider.setError('code 1');
    },

    checkLoginErrors: function () {
        var errors = $('span.error');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}