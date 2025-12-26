var plugin = {

    hosts: {'www.mrrebates.com': true},

    getStartingUrl: function (params) {
        return 'https://www.mrrebates.com';
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
                    plugin.loadLoginForm(params);
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
        if ($('a[href="/logout.asp"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('a[href="/login.asp"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const ulogin = $('div.login-info').text();
        browserAPI.log("ulogin: " + ulogin);
        return ((typeof(account.properties) != 'undefined')
            && ulogin
            && (ulogin.indexOf(account.login) + 1) > 0);
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'http://www.mrrebates.com/logout.asp';
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = "https://www.mrrebates.com/login.asp";
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form[name="theForm"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        form.find('input[name="t_email_address"]').val(params.account.login);
        form.find('input[name="t_password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            if (form.find('button.g-recaptcha:visible').length > 0) {
                form.find('button.g-recaptcha:visible').click();
                waiting();

                function waiting() {
                    browserAPI.log("waiting...");
                    let counter = 0;
                    let login = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        if (counter === 10) {
                            provider.reCaptchaMessage();
                        }
                        if (counter > 120) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                        }
                        counter++;
                    }, 500);
                }
                return;
            }
            form.submit();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('b:contains("Email Address Not Registered at Mr. Rebates")');
        if (errors.length === 0)
            errors = $('b:contains("Password Does Not Match Email Address on File")');
        if (errors.length === 0)
            errors = $('font[color="red"]:visible');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },
};