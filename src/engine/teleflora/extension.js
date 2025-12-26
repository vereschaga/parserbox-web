var plugin = {

    hosts: {'www.teleflora.com': true},

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.teleflora.com/rewards/rewards.jsp';
    },

    start: function (params) {
        // cash back
        if (document.location.href.indexOf('affiliate') > 0) {
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
            return;
        }
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
                        plugin.logout();
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
        if ($('a:contains("Log out"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[name = "loginfileForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('h1:contains("Welcome,") span').html();
        // header
        if (!name)
            name = $('a#accountTrigger div.is-loggedin').text();
        if (name)
            name = name.replace(/&nbsp;/ig, ' ');
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            var logout = $('a:contains("Log out"):visible');
            logout.get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "loginfileForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[id = "logInfileBtn"]').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 10000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div#warningBox:visible');
        if (errors.length == 0)
            errors = $('span.is-error:visible');
        if (errors.length > 0)
            provider.setError(util.filter(errors.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};