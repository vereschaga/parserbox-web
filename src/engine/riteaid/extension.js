var plugin = {
    hosts: {'www.riteaid.com': true},

    getStartingUrl: function (params) {
        return 'https://www.riteaid.com/login';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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
                        plugin.loginComplete(params);
                    else
                        plugin.logout();
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

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#login-email-address').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[data-logout-api-uri *= ".ralogout.json"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp( $('span:contains("Rite Aid Rewards #")').text(), /\#\s*([\d]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && number
            && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            provider.eval("$('div.right-rail-logout-template-wrapper > a').click()");
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const login = $("#login-email-address");

        if (login.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return false;
        }

        browserAPI.log("submitting saved credentials");
        provider.eval("$('#login-email-address').val('" + params.account.login + "').trigger('input')");
        provider.setNextStep('checkLoginErrors');
        $("button#email-continue-button").get(0).click();


        util.waitFor({
            selector: '#login-user-password:visible',
            timeout: 5,
            success: function () {
                provider.eval("$('#login-user-password').val('" + params.account.password + "').trigger('input')");
                $("button#pwd-submit-button").get(0).click();

                util.waitFor({
                    selector: 'div.inlineErrorMsg:visible, div#login-error-text-message:visible',
                    timeout: 7,
                    success: function () {
                        plugin.checkLoginErrors(params);
                    }
                });
            },
            fail: function () {
                if ($('div.inlineErrorMsg:visible, div#login-error-text-message:visible').length) {
                    plugin.checkLoginErrors(params);
                }
            },
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.inlineErrorMsg:visible, div#login-error-text-message:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};