var plugin = {
    hosts: {'www.westmarine.com': true},

    getStartingUrl: function (params) {
		return 'https://www.westmarine.com/on/demandware.store/Sites-WestMarine-Site/en_US/Loyalty-ShowCertificates';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
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
        const form = $('form[name = "login-form"]');
        if (form.length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= Logout]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.filter( $('span:contains("Member") + span').text());
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm');
        $('a[href *= Logout]').get(0).click();
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form[name = "login-form"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "loginEmail"]').val(params.account.login);
        form.find('input[name = "loginPassword"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors');
        form.find('button[type = "submit"]').click();
        setTimeout(function () {
            plugin.checkLoginErrors(params);
        }, 7000)
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div[class *= "alert-danger"]:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

}