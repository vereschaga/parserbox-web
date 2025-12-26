var plugin = {

    hosts: {
        'www.enrich.malaysiaairlines.com': true,
        'www.malaysiaairlines.com': true,
        'member.malaysiaairlines.com': true
    },

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.malaysiaairlines.com/my/en/enrich-portal/home/summary.html';
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

    loginCheck: function(params) {
        browserAPI.log("loginCheck");
        if (plugin.isSameAccount(params.account)) {
            provider.complete();
            return;
        }

        plugin.logout(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('div#api[data-name="SelfAsserted"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('div.card--number:visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('div.card--enrichnumber').text();
        browserAPI.log("number: " + number);
            return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber !== '')
            && number
            && (number === account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[id = "logoutLink"]').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.malaysiaairlines.com/my/en/enrich-portal/home/summary.html';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('div#api[data-name="SelfAsserted"]:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name="Sign in name"]').val(params.account.login);
        form.find('input[name="Password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button#next').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 10000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.error:visible');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    }

};