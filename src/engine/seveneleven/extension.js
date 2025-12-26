var plugin = {

    hosts: {'stage.slurpee.com': true, 'www.slurpee.com': true},

    getStartingUrl: function(params){
        // refs #4448
        if ($.browser.msie)
            provider.complete();
        return 'https://stage.slurpee.com/Users/Account.aspx';
    },

    start: function(params){
        setTimeout(function() {
            if(plugin.isLoggedIn()){
                if(plugin.isSameAccount(params.account))
                    provider.complete();
                else
                    plugin.logout();
            }
            else
                plugin.login(params);
        }, 3000)
    },

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if (document.location.href == 'http://stage.slurpee.com/Default.aspx'
            || document.location.href == 'https://stage.slurpee.com/Default.aspx?ReturnUrl=%2fUsers%2fAccount.aspx'){
            browserAPI.log("not LoggedIn");
            return false;
        }
        if($('div:contains("Sign Out")').text() ){
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
        throw "Can't determine login state";
    },

    isSameAccount: function(account){
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.findRegExp( $('#profile').find('div.greeting:contains("Hello,")').text(), /,([^<]+)/i );
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function(){
        browserAPI.log("Logout");
        $('#menubox').find('div[class *="signout"]').click();
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('#aspnetForm');
        if (form.length == 0)
            form = $('form[name = "aspnetForm"]');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "signin_email_address"]').val(params.account.login);
            form.find('input[name = "signin_password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                $('#modalbox').find('div[class *="signin button"]').click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function(){
        var errors = $("b:contains(Sign In Error)");
        if(errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}