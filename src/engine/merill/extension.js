var plugin = {


    hosts: {'card.ml.com': true, 'olui2.fs.ml.com': true, 'etui.fs.ml.com': true, 'www.managerewardsonline.com': true},

    getStartingUrl: function (params) {
//        return 'https://card.ml.com/RWDapp/ml/home';
        return 'https://olui2.fs.ml.com/ClientFederation/LoginWidget.aspx?pid=BE0777B6C4AF43beB3BE5DBC7EF1F904&format=html&size=compact&enrollNow=true&target=https%3A%2F%2Fcard.ml.com%2FRWDapp%2Fml%2Fhome';
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
        if ($('input[name = "ctl00$ctl00$ctl00$cphNestedUtility$cphStage$widgetContent$validateUserControl$ctl00$txtUserid"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=logoff]').text()) {
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
        var name = plugin.findRegExp( $('div.toolsMessage').text(), /Welcome\s*,\s*([^<]+)/i);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
//        provider.setNextStep('loadLoginForm');
//        document.location.href = 'https://www.usaa.com/inet/ent_logon/Logoff?wa_ref=pri_auth_nav_logoff';
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl(params);
    },

    login: function (params) {
        browserAPI.log("login");
        // we support two cards: MLOL and MyMerrill
//        if (params.account.login2 == "mymerrill" || params.account.login2 == "mlol")
//            $('#loginOptionSelect').find('option[value = mlol]').attr("selected", "selected");
//        else
//            provider.complete();

        var form = $('#aspnetForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "ctl00$ctl00$ctl00$cphNestedUtility$cphStage$widgetContent$validateUserControl$ctl00$txtUserid"]').val(params.account.login);
            form.find('input[name = "ctl00$ctl00$ctl00$cphNestedUtility$cphStage$widgetContent$validateUserControl$ctl00$btnValidate"]').click();
//            provider.setNextStep('enterPin');
            plugin.enterPin(params);
        }
        else
            provider.setError('code 1');
    },

    enterPin: function (params) {
        browserAPI.log("enterPin");
        var form = $('#aspnetForm');
        if (form.length > 0) {
            form.find('input[name = "ctl00$ctl00$ctl00$cphNestedUtility$cphStage$widgetContent$validateUserControl$ctl00$txtPassword"]').val(params.account.password);
            form.find('input[name = "ctl00$ctl00$ctl00$cphNestedUtility$cphStage$widgetContent$validateUserControl$ctl00$txtUserid"]').val(params.account.password);
            form.find('input[name = "ctl00$ctl00$ctl00$cphNestedUtility$cphStage$widgetContent$validateUserControl$ctl00$btnContinue"]').click();
            button.click();
            provider.setNextStep('checkLoginErrors');
        }
        else
            provider.setError('enterPin');
    },

    checkLoginErrors: function () {
        var errors = $('#messageLoginErrorLabel');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    findRegExp: function (elem, regExp, required) {
        var matches = regExp.exec( elem );
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
        return util.trim(result);
    }
}