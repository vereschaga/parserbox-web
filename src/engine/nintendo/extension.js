var plugin = {


    hosts: {'club.nintendo.com': true},

    getStartingUrl: function(params){
        return 'https://club.nintendo.com/home.do';
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
        if( $('#login-form').length > 0){
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
        var acc = $('div[class =account-head] h3').text();
        browserAPI.log("Account: " + acc);
        return ((typeof(account.login) != 'undefined')
            && (account.login != '')
            && (acc.toLowerCase() == account.login.toLowerCase()));
    },

    logout: function(){
        browserAPI.log("Logout");
        document.location.href = 'https://club.nintendo.com/logout.do';
    },

    login: function(params){
        browserAPI.log("login");

        var loadForm = document.createElement('script');
        loadForm.type = 'text/javascript';
        loadForm.innerHTML = "void(0);";
        document.head.appendChild(loadForm);

        $('a:contains("Member Sign In")').click();
        provider.complete();
        var form = $('#login-form');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            form.attr('action', '/members/Account/LogOn');
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            $('#login-submit').click();

            provider.setNextStep('checkLoginErrors');
        }
        else
            provider.setError('code 1');
    },

    checkLoginErrors: function(){
        var errors = $('span[class = "error-message"]');
        if(errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}