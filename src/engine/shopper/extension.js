var plugin = {


    hosts: {'www.shopperdiscountsandrewards.com': true},

    getStartingUrl: function(params){
        return 'https://www.shopperdiscountsandrewards.com/Home/Default.rails';
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
        if( $('#frmLogin').length > 0){
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
        var number = plugin.findRegExp(/Member\s*#\s*:\s*([^<]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Member) != 'undefined')
            && (account.properties.Member != '')
            && (number == account.properties.Member));
    },

    logout: function(){
        browserAPI.log("Logout");
        document.location.href = 'http://www.shopperdiscountsandrewards.com/Membership/Logout.rails';
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('#frmLogin');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "emailAddress"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('input[name = "imgLogin"]').click();
        }
        else
            provider.setError('code 1');
    },

    checkLoginErrors: function(){
        var errors = $('div:contains("Please Try Again")');
        if(errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    findRegExp: function(regExp, required){
        var matches = regExp.exec( $('#toprightinfo').html() );
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