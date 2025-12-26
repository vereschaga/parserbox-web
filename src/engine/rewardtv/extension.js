var plugin = {


    hosts: {'www.rewardtv.com': true},

    getStartingUrl: function(params){
        return 'http://www.rewardtv.com/welcome/sampleGames.sdo';
    },

    start: function(params){
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout(params);
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if( $('#standardLoginForm').length > 0){
            browserAPI.log("not LoggedIn");
            return false;
        }
        if( $('#loginForm').length > 0){
            browserAPI.log("not LoggedIn");
            return false;
        }
        if($('a[href *=SignoutApi]').text()){
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
        var email = $('#top_nav_super_user').text();
        browserAPI.log("email: " + email);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.login) != 'undefined')
            && (email == account.login));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        setTimeout(function() {
            browserAPI.log("execute script");
            provider.eval("doSignoutApi();");
        }, 1000);
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('#standardLoginForm');
        if (form.length == 0)
            form = $('form[name = "standardLoginForm"]');
        if (form.length == 0)
            form = $('#loginForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "userName"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            form.find('input[name = "submitSignIn"]').click();
            $('#btnAcctLogin').click();
            plugin.checkLoginErrors();
        }
        else {
            browserAPI.log("can't find login form");
            provider.setError("can't find login form");
        }
    },

    checkLoginErrors: function(){
        var errors = $("#standardLoginFormErrors");
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    findRegExp: function(regExp, required){
        var matches = regExp.exec( $('p.membership_number').text() );
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