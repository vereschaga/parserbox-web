var plugin = {


    hosts: {'www.surveyspot.com': true},

    getStartingUrl: function(params){
        return 'http://www.surveyspot.com/Secured/My-Dashboard';
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
        if ($('#apslogout').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if( $('#form1').length > 0){
            browserAPI.log("not LoggedIn");
            return false;
        }
        browserAPI.log("can't determine");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function(account){
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = plugin.findRegExp(/Welcome\s*([^<\,]+)/i);
        browserAPI.log("number: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function(){
        browserAPI.log("Logout");
        if($.browser.msie){
            browserAPI.log("ie version");
            var div = document.getElementsByTagName('div')[0];
            div.innerHTML = div.innerHTML + "<SCRIPT DEFER>jQuery.noConflict(); document.getElementById('apslogout').click();</SCRIPT>";
        }
        else
            document.getElementById('apslogout').click();
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('#form1');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');

            if($.browser.msie){
                browserAPI.log("ie version");
                var div = document.getElementsByTagName('div')[0];
                div.innerHTML = div.innerHTML + "<SCRIPT DEFER>jQuery.noConflict(); document.getElementById('apslogin').click();</SCRIPT>";
            }
            else
                document.getElementById('apslogin').click();
        }
        else
            provider.setError('code 1');
    },

    checkLoginErrors: function(){
        var errors = $("b:contains(Sign In Error)");
        if(errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    findRegExp: function(regExp, required){
        var matches = regExp.exec( $('h1:contains("Welcome")').text() );
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