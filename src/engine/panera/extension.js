var plugin = {

    hosts: {'www.panerabread.com': true},

    getStartingUrl: function (params) {
        return 'https://www.panerabread.com/en-us/home.html?mobile=true';
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        var name = $('span.welcomeUser:visible').text();
        if (name == '') {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (name != '') {
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('span.welcomeUser:visible').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            $('a#global-logout-link').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        // IE not working properly
        if (!!navigator.userAgent.match(/Trident\/\d\./)) {
            provider.eval('jQuery.noConflict()');
        }
        // open login form
        $('a#global-sign-in').get(0).click();
        // wait login form
        var counter = 0;
        var login = setInterval(function () {
            var form = $('#form_sign_in:visible');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "user_email"]').val(params.account.login);
                form.find('input[name = "user_password"]').val(params.account.password);
                document.getElementById('user_email').value = params.account.login;
                document.getElementById('user_password').value = params.account.password;

                clearInterval(login);

                provider.setNextStep('checkLoginErrors', function () {
                    $('button#join-now-primary').click();
                    setTimeout(function() {
                        plugin.checkLoginErrors();
                    }, 7000)
                });
            }
            if (counter > 30) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div#form_sign_in_msg:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
};