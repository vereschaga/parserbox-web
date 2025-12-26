var plugin = {


    hosts: {'www.tripalertz.com': true},

    getStartingUrl: function(params){
        return 'http://www.tripalertz.com/login';
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
        if($('a[href *=logout]').text()){
            browserAPI.log("LoggedIn");
            return true;
        }
        if( $('#login_form').length > 0){
            browserAPI.log("not LoggedIn");
            return false;
        }
        browserAPI.log("can't determine");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function(account){
        return false;
    },

    logout: function(){
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm');
        document.location.href = 'http://www.tripalertz.com/ajax/logout/';
    },

    loadLoginForm: function(){
        provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl();
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('#login_form');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "data[User][username]"]').val(params.account.login);
            form.find('input[name = "data[User][password]"]').val(params.account.password);
            form.submit();
            provider.complete();
        }
        else
            provider.setError('code 1');
    }
}