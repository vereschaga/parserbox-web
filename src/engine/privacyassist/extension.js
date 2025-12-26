var plugin = {


    hosts: {'privacyassist.bankofamerica.com': true},

    getStartingUrl: function(params){
        return 'https://privacyassist.bankofamerica.com';
    },

    start: function(params){
        if(plugin.isLoggedIn()){
            if(plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if( $('form[name = "frmLogon"]').length > 0){
            browserAPI.log("not LoggedIn");
            return false;
        }
        if($('a[href *=Logout]').text()){
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("can't determine");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function(account){
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = plugin.findRegExp(/Welcome\s*([^<\,]+)/i);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function(){
        browserAPI.log("Logout");
        document.location.href = 'https://privacyassist.bankofamerica.com/pages/asp/Logout.asp';
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('form[name = "frmLogon"]');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "txtUserID"]').val(params.account.login);
            form.find('input[name = "txtPassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.submit();
        }
        else
            provider.setError('code 1');
    },

    checkLoginErrors: function(){
        var errors = $(":contains(Invalid)");
        if(errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    findRegExp: function(regExp, required){
        var matches = regExp.exec( $('span:contains("Welcome")').text() );
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