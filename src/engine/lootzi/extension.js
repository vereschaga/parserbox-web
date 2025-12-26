var plugin = {


    hosts: {'lootzi.com': true},

    getStartingUrl: function(params){
        return 'http://lootzi.com/account/';
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
        if( $('#txtSignInEmail').length > 0){
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
        var email = $('#account h2').text();
        browserAPI.log("email: " + email);
        return ((typeof(account.properties) != 'undefined')
            && (email == account.login));
    },

    logout: function(){
        browserAPI.log("Logout");
        if($.browser.msie){
            provider.setNextStep('loadLoginForm');
        }
        document.location.href = 'http://lootzi.com/account/logout/';
    },

    loadLoginForm: function(){
        provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl();
    },

    login: function(params){
        browserAPI.log("login");
        var login = $('#txtSignInEmail');
        if(login.length > 0){
            browserAPI.log("submitting saved credentials");
            login.val(params.account.login);
            $('#txtSignInPassword').val(params.account.password);
            provider.setNextStep('checkLoginErrors');

            if($.browser.msie){
                var div = document.getElementsByTagName('div')[0];
                div.innerHTML = div.innerHTML + "<SCRIPT DEFER>jQuery.noConflict();</SCRIPT>";
                $('input[name = "username"]').val(params.account.login);
                $('input[name = "password"]').val(params.account.password);
                $('input[name = "signin"]').click();
            }
            else{
                var submit = document.createElement('script');
                submit.type = 'text/javascript';
                submit.innerHTML = "attemptSignIn();";
                document.head.appendChild(submit);
            }
        }
        else
            provider.setError('code 1');
    },

    checkLoginErrors: function(){
        if($('a[href *=logout]').text())
            provider.complete();
        else
            provider.setError("Unknown error");
    }
}