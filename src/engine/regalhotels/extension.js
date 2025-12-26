var plugin = {
    // keepTabOpen: true,
    hosts: {'www.loyalty.regalhotel.com': true},

    getStartingUrl: function (params) {
        return 'https://www.loyalty.regalhotel.com/Promotions/Article.aspx?ID=82&lang=en-US';
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
        browserAPI.log("isLoggedIn");
        if ($('form[name = "aspnetForm"] #ctl00_btnLogin:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "/Membership/LogOut.aspx"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('#ctl00_lblCardNo').text(), /\s*(R.+)\s*/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && (number == account.properties.CardNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.loyalty.regalhotel.com/Membership/LogOut.aspx?logout=1';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "aspnetForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "ctl00$tbUserName"]').val(params.account.login);
            form.find('input[name = "ctl00$tbUserPassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#ctl00_btnLogin').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div#myAlertLay:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }

};
