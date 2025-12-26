var plugin = {
    hosts: {'www.stashrewards.com': true},

    getStartingUrl: function (params) {
        return 'https://www.stashrewards.com/login';
    },

    redirectAccount: function (params) {
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.stashrewards.com/account';
        });
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn) {
                clearInterval(start);
                if (window.location.href === 'https://www.stashrewards.com' ||
                    window.location.href === 'https://www.stashrewards.com/login') {
                    plugin.redirectAccount(params);
                    return;
                }
                if (plugin.isSameAccount(params.account))
                    plugin.checkLoginErrors();
                else
                    plugin.logout(params);
            }
            else
                plugin.login(params);
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href ^= "/login"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href ^= "/logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // <p><strong>Stash Member ID:</strong> daletabu@yahoo.com</p>
        var login = util.trim($('p:contains("Stash Member ID:")').contents().filter(function(){
            return this.nodeType == 3;
        })[0].nodeValue);

        browserAPI.log("login: " + login);
        return typeof account.login !== 'undefined' && login === account.login;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.stashrewards.com/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#login-page-form');
        if (form.length > 0) {
            form.find('input[name = "email_address"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);

            provider.setNextStep('checkLoginErrors', function () {
                form.find('#login-form-submit').get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('.alert.alert-danger:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }

};

