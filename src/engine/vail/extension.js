var plugin = {

    hosts: {'www.snow.com': true},

    getStartingUrl: function (params) {
        return 'https://www.snow.com/account/my-account.aspx';
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
        if ($('#returningCustomerForm_1').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('a#signOut').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('p.productname:eq(0)').text();
        browserAPI.log("name: " + name);
        return typeof account.properties !== 'undefined'
            && typeof account.properties.Name !== 'undefined'
            && account.properties.Name !== ''
            && util.trim(name) === account.properties.Name;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a#signOut:contains("Sign Out")').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function () {
            var form = $('#returningCustomerForm_3');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "UserName"]').val(params.account.login);
                form.find('input[name = "Password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button.accountLogin__cta').click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 5000);
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('.accountLogin__errorMessage:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }
};
