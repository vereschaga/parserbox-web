var plugin = {


    hosts: {'www.tam.com.br': true},
    clearCache: true,

    getStartingUrl: function(params){
        return 'http://www.tam.com.br/';
    },

    isMobile: function(){
        return (typeof(api) !== 'undefined') && (typeof(api.getDepDate) === 'function') && (api.getDepDate() instanceof Date);
    },

    start: function(params) {
        browserAPI.log("start");
        // redirect from mobile version(no login)
        var fullsiteLink = $('.fullsite > a');
        if (document.location.href.indexOf('b2c/vgn/img/mobile/') !== -1 && fullsiteLink.length === 2) {
            if (plugin.isMobile()) {
                browserAPI.log("Mobile");
                api.setNextStep('start2', function () {
                    document.location.href = fullsiteLink.eq(0).attr('href');
                });
            } else {
                provider.setNextStep('start2');
                document.location.href = fullsiteLink.eq(0).attr('href');
            }
        }
        else
            plugin.start2(params);
    },

    start2: function(params) {
        browserAPI.log("start2");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + start);
            if ($('a.bt_ok').length > 0 || $('a[href*="/home/sair.jsp"]').length > 0) {
                clearInterval(start);
                if (plugin.isLoggedIn())
                    plugin.logout();
                else
                    plugin.login(params);
            }
            if (counter > 10) {
                clearInterval(start);
                api.error("Can't determine state");
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if ($('a.bt_ok').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('a[href*="/home/sair.jsp"]').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    logout: function(){
        browserAPI.log("logout");
        provider.setNextStep('start');
        document.location.href = 'http://www.tam.com.br/b2c/jsp/home/sair.jsp';
    },

    login: function(params){
        browserAPI.log("login");
        var form=$('#formLogin');
        if (form.length > 0) {
            form.find('#login').attr("value", params.account.login);
            form.find('#senha').attr("value", params.account.password);

            provider.setNextStep('checkLoginErrors');
            plugin.eval("linkLogin('ok')");
            setTimeout(function() {
                plugin.checkLoginErrors();
            }, 2000)
        }
        else {
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function () {
        console.log("checkLoginErrors");
        var errors = $('error');
        if (errors.length > 0)
            provider.setError(errors.text());
        else {
            provider.complete();
            // fucking IE not working properly
            if (!!navigator.userAgent.match(/Trident\/\d\./))
                document.location.href = plugin.getStartingUrl(params);
        }
    },

    eval: function(code){
        // workaround to absesnce of provider.eval in mobile app
        var time;
        // api check
        if (plugin.isMobile) {
            var script = document.createElement('script');
            script.type = 'text/javascript';
            script.text = code;
            document.body.appendChild(script);
        }else{
            provider.eval(code);
        }
    }
}
