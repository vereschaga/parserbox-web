var plugin = {


    hosts: {'ww3.dotres.com': true},

    getStartingUrl: function(params){
        return 'https://ww3.dotres.com/meridia?posid=99G7';
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
        if( $('form#frmLogin').length > 0){
            browserAPI.log("not LoggedIn");
            return false;
        }
        if($('a[href *=logout]').text()){
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
        var number = plugin.findRegExp(/Frequent\s*Flyer\s*#\s*:\s*([\d]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function(){
        browserAPI.log("Logout");

        var logout = document.createElement('script');
        logout.type = 'text/javascript';
        logout.innerHTML = "wsClickTrack(this);";
        document.head.appendChild(logout);
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('form#frmLogin');
        if (form.length == 0)
            form = $('form[name = "frmLogin"]');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "accountID"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');

            var login = document.createElement('script');
            login.type = 'text/javascript';
            login.innerHTML = "cookieAndSubmit();";
            document.head.appendChild(login);
        }
        else
            provider.setError('code 1');
    },

    checkLoginErrors: function(){
        var errors = $(".errorLogin");
        if(errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    findRegExp: function(regExp, required){
        var matches = regExp.exec( $('p:contains(Frequent Flyer #)').text() );
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