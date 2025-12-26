var plugin = {

    hosts: {'www.lennys.com': true},

    getStartingUrl: function(params){
        return 'https://www.lennys.com/rewards/index.cfm';
    },

    start: function(params){
        browserAPI.log("start");
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
        if( $('form[name = "rewardsregistration"]').length > 0){
            browserAPI.log("not LoggedIn");
            return false;
        }
        if($('a[href *=logout]').text()){
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(["Can't determine login state", util.errorCodes.providerError]);
    },

    isSameAccount: function(account){
        return false;
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = plugin.findRegExp(/membership\s*number\s*is\s*([\d\s]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function(){
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm');
//        document.location.href = '';
    },

    loadLoginForm: function(){
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('form[name = "rewardsregistration"]');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            var cardNumber = params.account.login.replace(/\D/g, '');
            browserAPI.log("login: " + cardNumber);
            form.find('input[name = "cnum1"]').val(cardNumber.substr(0,4));
            form.find('input[name = "cnum2"]').val(cardNumber.substr(4,4));
            form.find('input[name = "email"]').val(params.account.login2);
            form.find('input[name = "phone"]').val(params.account.password);

            var ts = form.find('input[name = "formfield1234567893"]').attr("value").split(',');
            browserAPI.log("ts: " + ts[1]);
            ts[1] = ts[1] - 10;
            browserAPI.log("ts: " + form.find('input[name = "formfield1234567893"]').attr("value") );
            form.find('input[name = "formfield1234567893"]').val( ts.join(',') );
            browserAPI.log("ts: " + form.find('input[name = "formfield1234567893"]').attr("value") );
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[name = "submit"]').click();
            });
        }
        else
            provider.setError(['Login form not found', util.errorCodes.providerError]);
    },

    checkLoginErrors: function(){
        browserAPI.log("checkLoginErrors");
        var errors = $("li:contains('NO RECORD FOUND')");
        if(errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}