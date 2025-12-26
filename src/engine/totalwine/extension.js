var plugin = {

    hosts: {
        'www.totalwine.com': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.totalwine.com/login';
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
        $('.QSIWebResponsive').remove(); // removing pop-up survey

        if ($('div[data-test=signIn] > form:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('span[class^=accountHomeMemberNumber]').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let split = $('span[class^=accountHomeMemberNumber]').text().split(' ');
        let number = split[split.length - 1];
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Number) != 'undefined')
                && (account.properties.Number !== '')
                && number
                && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.totalwine.com/logout';
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.totalwine.com/login';
        });
    },

    login: function (params) {
        browserAPI.log("login");

        let form = $('div[data-test=signIn] > form:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "emailAddress"]').val(params.account.login).get(0).dispatchEvent(new Event('blur'));
        form.find('input[name = "password"]').val(params.account.password).get(0).dispatchEvent(new Event('blur'));
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button[data-at=signin-submit-button]').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params)
            }, 10000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div[class*=ErrorMsg]:visible');

        if (errors.length > 0 && util.filter(errors.text()).length > 1) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        errors = $('a[class*=errorLink]:visible').parent();
        if (errors.length > 0 && util.filter(errors.text()).length > 1) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};