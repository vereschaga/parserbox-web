var plugin = {

    hosts: {'www.maxandermas.com': true, 'iframe.punchh.com': true},

    getStartingUrl: function (params) {
        return 'https://iframe.punchh.com/customers/sign_in.iframe?slug=maxnermas';
    },

    redirectProfile: function (params) {
        provider.setNextStep('start', function () {
            document.location.href = 'https://iframe.punchh.com/customers/edit.iframe?slug=maxnermas';
        });
    },

    redirectLogin: function (params) {
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
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
                    clearInterval(start);
                    // Redirecting to a profile to find out which user is authorized
                    if ('https://iframe.punchh.com/whitelabel/maxnermas' === window.location.href) {
                        plugin.redirectProfile(params);
                        return;
                    }
                    if (plugin.isSameAccount(params.account))
                        plugin.checkLoginErrors();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form.new_user:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "/sign_out"]').length > 0 || $('iframe#advanced_iframe').contents().find('a[href *= "/sign_out"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var login = $('#user_email').val();
        browserAPI.log("login: " + login);
        return typeof account.login !== 'undefined' && login === account.login+1;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('redirectLogin', function () {
            document.location.href = 'https://iframe.punchh.com/customers/sign_out.iframe?slug=maxnermas';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form.new_user');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('#user_email').val(params.account.login);
            form.find('#user_password').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#invisible-recaptcha').get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('.alert-message');
        if (errors.length > 0 && errors.text().indexOf('Signed in successfully.') === -1 && errors.text().indexOf('You are already signed in.') === -1)
            provider.setError(errors.text());
        else
            provider.setNextStep('loginComplete', function () {
                document.location.href = 'https://www.maxandermas.com/rewards/';
            });
    },

    loginComplete: function () {
        browserAPI.log("checkLoginErrors");
        var scrollTop = $("#advanced_iframe");
        if (scrollTop.length > 0) {
            $('html, body').animate({
                scrollTop: scrollTop.offset().top
            }, 1000);
        }
        provider.complete();
    }
};

