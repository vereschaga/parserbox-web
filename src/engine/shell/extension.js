var plugin = {
    //keepTabOpen: true,
    hosts: {
        'www.shellsmart.com': true,
        'clientes.disagrupo.es': true,
        'www.tarjetashellclubsmart.es': true,
        'login.consumer.shell.com': true,
        'www.shelldriversclub.co.uk': true,
        'www.goplus.shell.com': true,
        'www.clubsmart.shell.bg': true,
        'www.clubsmart.shell.hu': true,
    },

    getStartingUrl: function (params) {
        browserAPI.log("getStartingUrl");
        var lang = plugin.lang(params);
        if (lang === 'en-en')
            return 'https://www.goplus.shell.com/en-gb/sso/login/start?mode=LOGIN';
        if (lang === 'bg-bg') {
            return 'https://www.clubsmart.shell.bg/sso/login/start';
        }
        if (['cz-cs', 'pl-pl', 'sk-sk', 'hu-hu'].indexOf(lang) !== -1)
            return 'https://www.shellsmart.com/smart/account/manage_cards?site=' + lang;
        else if (lang === 'es-es')
            return 'https://clientes.disagrupo.es/sso/login?ReturnUrl=http%3a%2f%2fwww.tarjetashellclubsmart.es%2f'; // Not Valid Accounts
        else
            return 'https://www.shelldriversclub.com/smart/account/manage_cards?site=' + lang;
    },

    lang: function (params) {
        browserAPI.log("lang");
        if (params.account.login2 === 'http://www.tarjetashellclubsmart.es') {
            return 'es-es';
        }
        var expr = new RegExp("site=(.+)", "i");
        if (m = params.account.login2.match(expr))
            return m[1];
        return null;
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
        if ($('#logout_link').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if (
            $('form#ssoSignInForm input[type="submit"]').length > 0 || // en
            ($('#signInEmailAddress').length && $('#currentPassword').length) || // en,de, new
            $('#login_or_register_text_part_1').length > 0 || // hu
            $('form[action="/sso/login"]').length > 0 || // es
            $('form#mobile_login_form').length > 0 // mobile
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var accountNumber = !isNaN(account.login) ? account.login : account.properties.AccountNumber;
        var card = $('#card_no:contains(' + accountNumber + ')').text();
        browserAPI.log("card: " + card);

        return typeof accountNumber !== 'undefined'
            && accountNumber !== ''
            && accountNumber == card;
    },

    logout: function (params) {
        browserAPI.log("logout");
        var lang = plugin.lang(params);
        provider.setNextStep('start', function () {
            if (lang === 'en-en')
                document.location.href = 'https://www.shelldriversclub.co.uk/smart/user/LogOut.html?site=en-en';
            else
                document.location.href = $('#logout_link').attr('href');
        });
    },

    loadLoginForm: function (params) {
        var lang = plugin.lang(params);
        if (lang === 'cz-cs' || lang === 'pl-pl' || lang === 'sk-sk')
            provider.setNextStep('login', function () {
                $('form#ssoSignInForm input[type="submit"]').get(0).click();
            });
        else if (lang === 'hu-hu') {
            provider.setNextStep('login', function () {
                $('#login_or_register_text_part_1').get(0).click();
            });
        }
        else
            plugin.login(params);
    },

    login: function (params) {
        browserAPI.log("login");
        var lang = plugin.lang(params);
        if (lang === 'en-en' || lang === 'cz-cs' || lang === 'pl-pl' || lang === 'sk-sk' || lang === 'hu-hu' || lang === 'de-de') {
            var login = $('#menu_label_login_button:visible');
            var loginSubmit = $('#login_button');
            if (login.length && loginSubmit.length) {
                provider.setNextStep('start', function () {
                    loginSubmit.get(0).click();
                });
                return;
            }
            plugin.loginEn(params);
        }

        else if (lang === 'es-es')
            plugin.loginEs(params);
        else
            plugin.loginDefault(params);
    },

    loginEn: function (params) {
        browserAPI.log("loginEn");
        var counter = 0;
        var loginEn = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            if ($('#signInEmailAddress').length && $('#currentPassword').length) {
                clearInterval(loginEn);
                provider.setNextStep('checkLoginErrorsEn', function () {
                    // reactjs
                    provider.eval(`
                        function triggerInput(inputId, newValue) {
                            let input = document.getElementById(inputId);
                            let lastValue = input.value;
                            input.value = newValue;
                            let event = new Event('input', { bubbles: true });
                            let tracker = input._valueTracker;
                            if (tracker) tracker.setValue(lastValue);
                            input.dispatchEvent(event);
                        }
                        triggerInput('signInEmailAddress', '${params.account.login}');
                        triggerInput('currentPassword', '${params.account.password}');
                        document.getElementById('submit_wizard_form').click();
                    `);
                    setTimeout(function () {
                        plugin.checkLoginErrorsEn(params);
                    }, 1000);
                });
            }// if ($('#signInEmailAddress').length && $('#currentPassword').length)
            else if (counter > 15) {
                clearInterval(loginEn);
                provider.setError(util.errorMessages.loginFormNotFound, true);
            }
            counter++;
        }, 500);
    },

    loginDefault: function (params) {
        browserAPI.log("loginDefault");
        var form = $('form#login_page_form, form#mobile_login_form');
        if (form.length > 0) {
            form.find('input#user_name, input#mobile_card_number_input, input#card_number_input').val(params.account.login);
            form.find('input#password, input#mobile_password_input, input#password_input').val(params.account.password);

            var captcha = form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]:visible');
            if (captcha.length > 0) {
                provider.reCaptchaMessage();
                browserAPI.log("waiting...");
                var counter = 0;
                var login = setInterval(function () {
                    browserAPI.log("waiting... " + counter);
                    if (counter > 120) {
                        clearInterval(login);
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                        return;
                    }
                    counter++;
                }, 1000);

                provider.setNextStep('checkLoginErrorsDefault', function () {
                    $('input#submit').click(function () {
                    });
                    setTimeout(function () {
                        plugin.checkLoginErrorsDefault();
                    }, 5000);
                });
            } else
                provider.setNextStep('checkLoginErrorsDefault', function () {
                    form.find('input#submit').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrorsDefault();
                    }, 5000);
                });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    loginEs: function (params) {
        browserAPI.log("loginEs");
        var form = $('form[action="/sso/login"]');
        if (form.length > 0) {
            form.find('input[name="UserName"]').val(params.account.login);
            form.find('input[name="Password"]').val(params.account.password);

            provider.setNextStep('checkLoginErrorsEs', function () {
                form.find('button[type="submit"]').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrorsEs(params);
                }, 5000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrorsEn: function () {
        browserAPI.log("checkLoginErrorsEn");
        var counter = 0;
        var checkLoginErrorsEn = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var error = $('div:contains("The username and password don’t match. Please try again."):visible');
            if (error.length == 0)
                error = $('p:contains("Account locked"):visible');
            if (error.length == 0)
                error = $('span:contains("The credentials are invalid, please try again"):visible');
            if (error.length == 0)
                error = $('span:contains("A hitelesítés érvénytelen, kérjük próbálkozzon később. "):visible');
            if (error.length == 0) {
                if (document.location.href.indexOf('/securityUpdateOverview') !== -1) {
                    clearInterval(checkLoginErrorsEn);
                    provider.setError([$('.loadedContent h1:visible').text(), util.errorCodes.providerError], true);
                    return false;
                }
            }
            if (error.length > 0 && util.filter(error.text()) !== '') {
                clearInterval(checkLoginErrorsEn);
                provider.setError(error.text());
            }
            if (counter > 7 || $('#point_amount:visible').length > 0) {
                clearInterval(checkLoginErrorsEn);
                provider.complete();
            }
            counter++;
        }, 1000);
    },

    checkLoginErrorsDefault: function () {
        browserAPI.log("checkLoginErrorsDefault");
        var error = $('div#system_message:visible');
        if (error.length > 0 && util.trim(error.text()) !== '')
            provider.setError(error.text());
        else
            provider.complete();
    },

    checkLoginErrorsEs: function () {
        browserAPI.log("checkLoginErrorsEs");
        var error = $('.field-validation-error:visible');
        if (error.length > 0 && util.trim(error.text()) !== '')
            provider.setError(error.text());
        else
            provider.complete();
    }

};