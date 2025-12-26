var plugin = {


    hosts: {'www.citibank.com': true, 'www.accountonline.com': true},

    getStartingUrl: function (params) {
        return 'https://www.citibank.com/us/cards/srs/index.jsp';
    },

    start: function (params) {
        setTimeout(function() {
            if (plugin.isLoggedIn()) {
                if (plugin.isSameAccount(params.account))
                    provider.complete();
                else
                    plugin.logout();
            }
            else
                plugin.login(params);
        }, 1000)
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#heroLoginSignOn form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a.logout').attr('class')) {
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
        var name = plugin.findRegExp( $('span.welcome_msg').text(), /,([^<]+)/i );
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm');
        if ($('a[href *= Logout]').length > 0)
            $('a[href *= Logout]').click();
        else
            if ($('a.logout').length > 0)
            $('a.logout').get(0).click();
    },

    loadLoginForm: function(){
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl();
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#heroLoginSignOn form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "USERNAME"]').val(params.account.login);
            form.find('input[name = "PASSWORD"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('a.signOn').get(0).click();
        }
        else
            provider.setError('code 1');
    },

    checkLoginErrors: function () {
        var errors = $('.err-new');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    findRegExp: function(elem ,regExp, required){
        var matches = regExp.exec( elem );
        if(matches){
            console.log('matched regexp: ' + regExp);
            result = matches[1];
        }
        else{
            console.log('failed regexp: ' + regExp);
            if(required)
                console.log('regexp not found');
            else
                result = null;
        }
        return util.trim(result);
    }
}