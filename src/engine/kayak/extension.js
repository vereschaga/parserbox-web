var plugin = {

    hosts: {'www.kayak.com': true},

    getStartingUrl: function (params) {
        return 'https://www.kayak.com/profile/account';
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
                        provider.complete();
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
        browserAPI.log("isLoggedIn - debug");
        if ($('.formContainer form[role="form"]').length > 0 || $('button:contains("Continue with email"):visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button span:contains("Sign out")').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var email = util.trim($('.keel-container .inspectlet-sensitive').text());
        browserAPI.log("email: " + email);
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.Email) !== 'undefined'
            && account.properties.Name != ''
            && email.indexOf(account.properties.Email) !== -1;
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('button span:contains("Sign out")').click();
        });

    },

    loadLoginForm: function(params) {
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let btn = $('button:contains("Continue with email"):visible');
        if (btn.length)
            btn.get(0).click();
        setTimeout(function () {
            var form = $('#main');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[placeholder="Email address"]').val(params.account.login);
                util.sendEvent(form.find('input[placeholder="Email address"]').get(0), 'input');

                //form.find('input[name = "passwd"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button[role="button"]:contains("Continue")').get(0).click();
                    setTimeout(function () {
                        plugin.complete(params);
                    }, 3000);
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 1000);
    },

    checkLoginErrors: function (params) {
        var errors = $('#div[id*="-error"]:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else if (document.location.href.indexOf('/login?redir=') !== -1)
            provider.setNextStep('complete', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        else
            provider.complete();
    },

    complete: function (params) {
        provider.complete();
    }
};