var plugin = {

    hosts: {
        'www.westernunion.com': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.westernunion.com/us/en/web/user/login?Route=MYWUSITE&RouteType=REDIRECT';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");

        if ($('input#txtKey').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('a#wu-mobile-login-button').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('a#user-logout-link').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.findRegExp($('li:contains("Account #")').text(), /Account\s*#\s*([^<]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Number) != 'undefined')
                && (account.properties.Number !== '')
                && number
                && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.westernunion.com/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");

        let form = $('form.ng-pristine');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        let login = form.find('input#txtEmailAddr').val(params.account.login).get(0);
        login.dispatchEvent(new Event('input'));
        login.dispatchEvent(new Event('blur'));
        let pwd = form.find('input#txtKey').val(params.account.password).get(0);
        pwd.dispatchEvent(new Event('input'));
        pwd.dispatchEvent(new Event('blur'));
        provider.setNextStep('checkLoginErrors', function () {
            const btn = form.find('button#button-continue');
            btn.get(0).click();

            setTimeout(function () {
                browserAPI.log("click one more time");
                btn.get(0).click();
            }, 2000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('span#notification-message:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },
};