var plugin = {

    hosts: {'www.imutual.co.uk': true},

    getStartingUrl: function (params) {
        return "https://www.imutual.co.uk/statement";
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.LoginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[name="login"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    // here error: cookie function is undefined (by jQuery)
    isSameAccount: function (account) {
        var userId = 0;//$.cookie("user_id");
        browserAPI.log("User's id: " + userId
                +" |  properties.SiteUserId: " + account.properties.AccountNumber);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.AccountNumber) !== 'undefined')
            && (account.properties.AccountNumber != '')
            && (userId == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start');
        document.location.href = "http://www.imutual.co.uk/logout?url=%2Fstatement";
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('input[name = "submit"]').trigger('click');
        }
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },
    
    LoginComplete: function(params) {
        provider.complete();
    },
    
    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('*[id = "tab-login"] > *:contains("Incorrect login details, please try again")');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }

};