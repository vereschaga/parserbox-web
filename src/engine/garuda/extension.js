var plugin = {
    //keepTabOpen: true,
    hosts: {'www.garuda-indonesia.com': true},

    getStartingUrl: function (params) {
        return 'https://www.garuda-indonesia.com/garudamiles/en/my-account.page';
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
        if ($('#gff-form:visible, #gff-form-mobile:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#frontlgn_panel_user_name:visible, #frontlgn_panel_user_name_mobile:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp( $('div.GM-number').text(), /^\s*(\d+)\s+\|/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && (number == account.properties.CardNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            var logout = $('#member-logout').find('input[type="submit"]');
            if (logout.length)
                logout.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (provider.isMobile)
            plugin.loginMobile(params);
        else
            plugin.loginDesktop(params);
    },

    loginMobile: function (params) {
        browserAPI.log("loginMobile");
        var menu = $('#mobile-menu:visible');
        if (menu.length) {
            menu.get(0).click();
            menu = $('#gff-form-mobile:visible');
            if (menu.length)
                menu.get(0).click();
        }
        var form = $('form#member-login-form-mobile, div#member-login-form-mobile:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "logusername"]').val(params.account.login);
            form.find('input[name = "logpassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                var captcha = util.findRegExp( form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                if (captcha && captcha.length > 0) {
                    provider.reCaptchaMessage();
                    browserAPI.log("waiting...");
                    var counter = 0;
                    var waiting = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        if (counter > 120) {
                            clearInterval(waiting);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                            return;
                        }
                        if (util.trim(form.find('.g-recaptcha-response').val()) !== '') {
                            clearInterval(waiting);
                            form.submit();
                            //form.find('input[value="Login"]').get(0).click();
                            return;
                        }
                        counter++;
                    }, 1000);
                    form.find('input[value="Login"]').click(function () {
                        clearInterval(waiting);
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 5000);
                    });
                } else
                    form.submit();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    loginDesktop: function (params) {
        browserAPI.log("loginDesktop");
        var form = $('form#member-login-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            $('.megamenu-list').addClass('open');
            form.find('input[name = "logusername"]').val(params.account.login);
            form.find('input[name = "logpassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                var captcha = util.findRegExp( form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                if (captcha && captcha.length > 0) {
                    provider.reCaptchaMessage();
                    browserAPI.log("waiting...");
                    var counter = 0;
                    var waiting = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        if (counter > 120) {
                            clearInterval(waiting);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                            return;
                        }
                        if (util.trim(form.find('.g-recaptcha-response').val()) !== '') {
                            clearInterval(waiting);
                            form.find('#submit-login').get(0).click();
                            return;
                        }
                        counter++;
                    }, 1000);
                    form.find('#submit-login').click(function () {
                        clearInterval(waiting);
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 5000);
                    });
                } else
                    form.find('#submit-login').get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div#errorCopy');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }

};