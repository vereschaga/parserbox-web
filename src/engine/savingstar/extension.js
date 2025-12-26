var plugin = {

    hosts: {
        'www.coupons.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.coupons.com/';
    },

    getFocusTab: function (account, params) {
        return true;
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

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if (
            $('a#nav-signup:visible').length > 0
            || (provider.isMobile && $('#nav-bar-signin').length > 0)
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Hi, ")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let email = util.findRegExp($('a:contains("Hi, ")').text().trim(), /,\s*([^<]+)/);
        browserAPI.log(">>> email: " + email);
        return (
            (typeof (account) !== 'undefined')
            && (typeof (account.login) !== 'undefined')
            && email
            && (account.login !== '')
            && (email.toLowerCase() === account.login.toLowerCase())
        );
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.coupons.com/sign-out/';
        });
    },

    loadLoginForm: function (params) {
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");

        if (provider.isMobile) {
            $('#nav-bar-signin').get(0).click();
        } else {
            provider.eval("document.querySelector(\'#nav-signin-prof > a\').click()");
        }

        setTimeout(function () {
            let form = $('div.signin-signup-form form');
            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }
            browserAPI.log("submitting saved credentials");
            form.find('input[id = "signin-email"]').val(params.account.login);
            form.find('input[id = "signin-password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#couponscom-brandpage-signin').trigger('click');
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }, 1000)
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('p.errmsg:visible, p.error-message:visible');
        if (errors.length > 0 && util.filter(errors.text()) != '') {
            provider.setError(util.filter(errors.text()));
            return;
        }// if (errors.length > 0)
        plugin.loginComplete(params);
    }

};
