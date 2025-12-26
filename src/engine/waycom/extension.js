var plugin = {

    hosts: {
        'www.way.com': true
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.way.com/login';
    },

    start: function (params) {
        browserAPI.log("start");
        // Wait while captcha resolved by user
        if ($('div[id = "recaptcha_widget"]').length > 0) {
            browserAPI.log("waiting while captcha resolved...");
            provider.reCaptchaMessage();
            provider.setNextStep('start', function () {
                var counter = 0;
                var captcha = setInterval(function () {
                    browserAPI.log("waiting while captcha resolved... " + counter);
                    if (counter > 180) {
                        clearInterval(captcha);
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }
                    if ($('div[id = "recaptcha_widget"]').length < 1) {
                        clearInterval(captcha);
                        browserAPI.log("captcha resolved!");
                    }
                    counter++;
                }, 1000);
            });
        } // if ($('div[id = "recaptcha_widget"]').length > 0)
        else {
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
        }
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form input[id*="userForm"]').length > 1) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.drdbox li:contains("Log Out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = $('div.dashboard-heading span').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('.drdbox li:contains("Log Out")').eq(0).trigger('click');
            if (provider.isMobile) {
                setTimeout(function () {
                    browserAPI.log('Force redirect');
                    plugin.loadLoginForm(params);
                }, 5000);
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form').has('input[id*="userForm"]:eq(1)');
        if (form.length === 1) {
            browserAPI.log("submitting saved credentials");

            const login = form.find('input[name="inputEmail"]');
            if (login.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }
            login.val(params.account.login);
            util.sendEvent(login.get(0), 'input');

            const pass = form.find('input[name="password"]');
            if (pass.length === 0) {
                provider.setError(util.errorMessages.passwordFormNotFound);
                return;
            }
            pass.val(params.account.password);
            util.sendEvent(pass.get(0), 'input');

            const buttons = form.find('button.newlogin_btn');
            if (buttons.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            provider.setNextStep("checkLoginErrors", function () {
                setTimeout(function () {
                    const captcha = form.find('img[id*="imgCaptcha"][src^="data:image/"]:visible').eq(0);
                    const captchaAnswer = form.find('input[id*="txtCaptcha"]').eq(0);

                    if (captcha.length > 0 && captchaAnswer.length > 0) {
                        provider.captchaMessageDesktop();
                    }

                    if (captcha.length > 0 && captchaAnswer.length > 0 && !provider.isMobile) {
                        browserAPI.log("waiting...");
                        const dataURL = captcha.attr('src');

                        browserAPI.send("awardwallet", "recognizeCaptcha", {
                            captcha: dataURL,
                            "extension": "png"
                        }, function (response) {
                            browserAPI.log(JSON.stringify(response));
                            if (response.success === true) {
                                browserAPI.log("Success: " + response.success);
                                captchaAnswer.val(response.recognized);
                                util.sendEvent(captchaAnswer.get(0), 'input');
                                buttons.eq(0).trigger('click');
                                setTimeout(function () {
                                    plugin.checkLoginErrors(params);
                                }, 7000);
                            }
                            if (response.success === false) {
                                browserAPI.log("Success: " + response.success);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }
                        });
                    } else {
                        browserAPI.log("captcha is not found");
                        buttons.eq(0).trigger('click');
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 7000);
                    }
                }, 1000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('p#loginEmail_userForm_pErrorLoginMessage');
        if (errors.length === 0 || util.filter(errors.text()) === '') {
            const form = $('form').has('input[id*="userForm"]:eq(1)');
            errors = form.find('.error');
        }
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }
        provider.complete();
    }

};