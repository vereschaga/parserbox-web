var plugin = {

    hosts: {'www.payback.de': true, 'www.payback.in': true, 'occssl.payback.pl': true, 'www2.payback.pl': true, 'www.payback.pl': true, 'www.payback.it': true, 'www.payback.mx' : true},
    hideOnStart: false, // todo
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),
    // alwaysSendLogs: true, // todo
    keepTabOpen: true,

    getStartingUrl: function(params){
        switch (params.account.login3) {
            case 'India':
                return 'https://www.payback.in/login';
            case 'Poland':
                return 'https://www.payback.pl/logowanie';
            case 'Italy':
                return 'https://www.payback.it/';
            case 'Mexico':
                return 'https://www.payback.mx/mi-monedero';
            default:
                return 'https://www.payback.de/';
        }
    },

    getFocusTab: function(account, params){
        return true;
    },

    start: function(params){
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params);
            // Germany - incapsula workaround
            var frame = $('iframe[src *= "_Incapsula_Resource"]:visible, div.g-recaptcha:visible');
            if (frame.length > 0 && ['GermanyNew', 'Germany', '', null].indexOf(params.account.login3) !== -1) {
                clearInterval(start);
                if (provider.isMobile) {
                    provider.command('show', function () {
                        provider.reCaptchaMessage();
                        //todo
                        frame.contents().find('form#captcha-form').bind('submit', function (event) {
                            provider.command('hide', function () {
                                provider.setNextStep('loadLoginForm', function(){
                                    browserAPI.log("captcha entered by user");
                                });
                            });
                        });
                    });
                }// if (provider.isMobile)
                else {
                    browserAPI.log("waiting...");
                    provider.setNextStep('start', function () {
                        provider.reCaptchaMessage();
                        var incapsulaCounter = 0;
                        var incapsula = setInterval(function () {
                            browserAPI.log("waiting... " + incapsulaCounter);
                            if (incapsulaCounter > 120) {
                                clearInterval(incapsula);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }
                            incapsulaCounter++;
                        }, 500);
                    });
                }
                return;
            }// if (frame.length > 0 && ['GermanyNew', 'Germany', '', null].indexOf(params.account.login3) === -1)
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else {
                    console.log('location: ' + document.location.href +', Region: ' + params.account.login3);
                    if (document.location.href !== 'https://www.payback.de/login' &&
                        (params.account.login3 === null || params.account.login3 === 'Germany' || params.account.login3 === 'GermanyNew'))
                        provider.setNextStep('start', function () {
                            document.location.href = 'https://www.payback.de/login';
                        });
                    else
                        plugin.login(params);
                }


            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.logBody("lastPage");
                // provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 1000);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        switch (params.account.login3) {
            case 'India':
                browserAPI.log("Region => India");
                if ($('span.pb-user-name').text() != '') {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                if ($('form#pb-login_form:visible').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                break;
            case 'Italy':
                browserAPI.log("Region => Italy");
                if ($('a[href *= "Logout"]').length > 0) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                if ($('form[name *= "Login"]').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                break;
            case 'Poland':
                browserAPI.log("Region => Poland");
                if ($('div#login_module_1:visible').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                if ($('a[href *= "Logout"]').length > 0) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                break;
            case 'Mexico':
                browserAPI.log("Region => Mexico");
                if ($('span:contains("Cerrar Sesión")').length) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                if ($('form[name="Login"]').length) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                break;
            default:
                browserAPI.log("Region => Germany");
                if ($('a[href*=logout]').attr('href')) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                //if ($('#pbLogin').attr('id') && $('#loginFormClassicDobZip').length == 0)
                //    document.location.href = 'http://www.payback.de/pb/id/312142/';
                if (!$('.header-element--welcome-msg > strong').length) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                break;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name;
        switch (params.account.login3) {
            case 'India':
                browserAPI.log("Region => India");
                name = util.findRegExp( $('span.pb-user-name').text(), /Hi,\s*([^<!]+)/i);
                browserAPI.log("name: " + name);
                return ((typeof(account.properties) != 'undefined')
                    && (typeof(account.properties.Name) != 'undefined')
                    && (account.properties.Name != '')
                    && name
                    && (name.toLowerCase() == account.properties.Name.toLowerCase()));
            case 'Italy':
                browserAPI.log("Region => Italy");
                name = util.findRegExp( $('h1:contains("Ciao")').text(), /Ciao\s*([^<!]+)/);
                browserAPI.log("name: " + name);
                return ((typeof(account.properties) != 'undefined')
                    && (typeof(account.properties.Name) != 'undefined')
                    && (account.properties.Name != '')
                    && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
            case 'Poland':
                browserAPI.log("Region => Poland");
                var number = $('span.numer').eq(0).text();
                browserAPI.log("number: " + number);
                return ((typeof(account.properties) != 'undefined')
                    && (typeof(account.properties.Number) != 'undefined')
                    && (account.properties.Number != '')
                    && (number == account.properties.Number));
            case 'Mexico':
                browserAPI.log("Region => Mexico");
                name = $('span.pb-account-details__card-holder-name:eq(1)').text();
                return ((typeof(account.properties) != 'undefined')
                    && (typeof(account.properties.Name) != 'undefined')
                    && (account.properties.Name != '')
                    && (name.toLowerCase() == account.properties.Name.toLowerCase()));
            default:
                browserAPI.log("Region => Germany");
                name = util.trim($('.header-element--welcome-msg > strong').text());
                browserAPI.log("name: " + name);
                return ((typeof(account.properties) != 'undefined')
                    && (typeof(account.properties.Name) != 'undefined')
                    && (account.properties.Name != '')
                    && (name.toLowerCase() == account.properties.Name.toLowerCase()));
        }
    },

    logout: function(params){
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            switch (params.account.login3) {
                case 'India':
                    browserAPI.log("Region => India");
                    $('a:contains("Logout")').get(0).click();
                    break;
                case 'Italy':
                    browserAPI.log("Region => Italy");
                    $('a[href *= "Logout"]').get(0).click();
                    break;
                case 'Poland':
                    browserAPI.log("Region => Poland");
                    $('a[href *= "Logout"]').get(0).click();
                    break;
                case 'Mexico':
                    $('a[href*="action=Logout"]').get(0).click();
                    break;
                default:
                    browserAPI.log("Region => Germany");
                    var logout = $('a[href*=logout]');
                    if (logout.length > 0)
                        logout.get(0).click();
                    break;
            }
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function(params){
        browserAPI.log("login");
        if(params.account.login3 === 'Germany' && !params.account.password.match(/^\d+$/)) {
            browserAPI.log(params.account.login3);
            params.account.login3 = 'GermanyNew';
        }

        var form;
        switch (params.account.login3) {
            case 'India':
                browserAPI.log("Region => India");
                form = $('form#pb-login_form:visible');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "card_number"]').val(params.account.login);
                    form.find('input[name = "pin_number"]').val(params.account.password);
                    // IE
                    if (!!navigator.userAgent.match(/Trident\/\d\./)) {
                        provider.eval('jQuery.noConflict()');
                    }
                    provider.setNextStep('checkLoginErrors', function () {
                        setTimeout(function () {
                            var captcha = form.find('div.g-recaptcha:visible');
                            if (captcha.length > 0) {
                                provider.reCaptchaMessage();
                                $('#awFader').remove();
                                var counter = 0;
                                var login = setInterval(function () {
                                    browserAPI.log("waiting... " + counter);
                                    var errors = $('div.error:visible, div#errorPopupBody:visible');
                                    if (errors.length > 0) {
                                        clearInterval(login);
                                        plugin.checkLoginErrors(params);
                                    }
                                    if (counter > 120) {
                                        clearInterval(login);
                                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                                    }
                                    counter++;
                                }, 500);
                            }// if (captcha.length > 0)
                            else {
                                browserAPI.log("captcha is not found");
                                form.find('.pb-login-submit').click();

                                setTimeout(function() {
                                    plugin.checkLoginErrors(params);
                                }, 5000)
                            }
                        }, 2000);
                    });
                }
                else {
                    form = $('form#weekLoginForm:visible');
                    if (form.length > 0) {
                        browserAPI.log("submitting saved credentials");
                        form.find('input[name = "weakphonenumber"]').val(params.account.login);
                        // IE
                        if (!!navigator.userAgent.match(/Trident\/\d\./)) {
                            provider.eval('jQuery.noConflict()');
                        }
                        provider.setNextStep('checkLoginErrors', function () {
                            provider.eval("weakLoginValidation()");

                            setTimeout(function() {
                                plugin.checkLoginErrors(params);
                            }, 5000)
                        });
                    }
                    else
                        provider.setError(util.errorMessages.loginFormNotFound);
                }
                break;
            case 'Italy':
                browserAPI.log("Region => Italy");

                $('a#login_btn_large').get(0).click();

                form = $('form[name *= "Login"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "alias"]').val(params.account.login);
                    form.find('input[name = "secret"]').val(params.account.password);
                    provider.setNextStep('checkLoginErrors', function () {
                        setTimeout(function () {
                            var captcha = form.find('div.g-recaptcha:visible');
                            if (captcha.length > 0) {
                                provider.reCaptchaMessage();
                                $('#awFader').remove();
                                var counter = 0;
                                var login = setInterval(function () {
                                    browserAPI.log("waiting... " + counter);
                                    if (counter > 80) {
                                        clearInterval(login);
                                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                                    }
                                    counter++;
                                }, 500);
                            }// if (captcha.length > 0)
                            else {
                                browserAPI.log("captcha is not found");
                                form.find('input[name *= "loginButton-"], input[name *= "login-button-"]').click();
                            }
                        }, 2000)
                    });
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
            case 'Poland':
                browserAPI.log("Region => Poland");
                form = $('div#login_module_1:visible');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "alias"]').val(params.account.login);

                    var date = params.account.login2.split(/[\/\.\-]/i);
                    form.find('input[class *= "js__input-dob-day"]').val(date[0]);
                    form.find('input[class *= "js__input-dob-month"]').val(date[1]);
                    form.find('input[class *= "js__input-dob-year"]').val(date[2]);
                    form.find('input[name = "dob"]').val(date[0] + '.' + date[1] + '.' + date[2]);

                    var pass = params.account.password.replace(/\-?\s?/ig, '');
                    form.find('input[class *= "js__poland-postcode-first-block"]').val(pass[0] + pass[1]);
                    form.find('input[class *= "js__poland-postcode-second-block"]').val(pass[2] + pass[3] + pass[4]);
                    form.find('input[name = "zip"]').val(pass[0] + pass[1] + '-' + pass[2] + pass[3] + pass[4]);
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('input[value="Zaloguj się"]').click();
                        setTimeout(function() {
                            plugin.checkLoginErrors(params);
                        }, 1500);
                    });
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
            case 'Mexico':
                form = $('form[name="Login"], div.pb-login');
                if (form.length) {
                    browserAPI.log("submitting saved credentials");
                    $('input[name="alias"]').val(params.account.login);
                    $('input[name="secret"]').val(params.account.password);
                    var $recaptchaFrame = $('iframe[src*="//www.google.com/recaptcha/api2/"]:visible');
                    if ($recaptchaFrame.length) {
                        provider.reCaptchaMessage();
                        var captchaAttempt = 0;
                        var captchaInterval = setInterval(function () {
                            browserAPI.log('login: wait[' + captchaAttempt + ']');
                            var $error = $('p.pb-alert-content__message');
                            if ($error.length && '' != util.trim($error.text())) {
                                clearInterval(captchaInterval);
                                provider.setError($error.text(), true);
                            }
                            if (++captchaAttempt > 60) {
                                clearInterval(captchaInterval);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }
                        }, 500);
                    } else {
                        provider.setNextStep('checkLoginErrors', function () {
                            $('input[type="submit"]', form).trigger('click');
                        });
                    }
                }// if (form.length)
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;

            case 'GermanyNew':
                browserAPI.log("Region => Germany - New Form");
                form = $('form[name="loginForm"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input#aliasInputSecure').val(params.account.login);
                    form.find('input#passwordInput').val(params.account.password);
                    provider.setNextStep('loginGermanyPwd', function () {
                        form.find('#loginSubmitButtonSecure').click();
                    });
                }
                else if ($('pbc-login').length) {
                    browserAPI.log("submitting saved credentials");
                    let loginShadowRoot = document.querySelector("pbc-login").shadowRoot;
                    let identificationShadowRoot = loginShadowRoot.querySelector("pbc-login-identification").shadowRoot;
                    identificationShadowRoot.querySelector('pbc-input-text input').value = params.account.login;
                    identificationShadowRoot.querySelector("pbc-button").shadowRoot.querySelector('button').click();

                    var counter = 0;
                    var login = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        let loginShadowRoot = document.querySelector("pbc-login").shadowRoot;
                        let identificationShadowRoot = loginShadowRoot.querySelector("pbc-login-password");
                        if (identificationShadowRoot) {
                            clearInterval(login);
                            provider.setNextStep('loginGermanyPwd', function () {
                                let loginShadowRoot = document.querySelector("pbc-login").shadowRoot;
                                let identificationShadowRoot = loginShadowRoot.querySelector("pbc-login-password").shadowRoot;
                                identificationShadowRoot.querySelector('pbc-input-password input').value = params.account.password;
                                identificationShadowRoot.querySelector("pbc-button").shadowRoot.querySelector('button').click();
                            });
                        }
                        var captcha = $('iframe[src*="https://www.google.com/recaptcha/api2/bframe"]:visible');
                        if (counter < 1 && captcha.length > 0) {
                            provider.reCaptchaMessage();
                            $('#awFader').remove();
                        }
                        if (counter > 90 && captcha.length > 0) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                        }
                        counter++;
                    }, 1000);
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);

                break;
            default:
                browserAPI.log("Region => Germany");
                form = $('form[name=loginForm]');

                if (form.length > 0) {
                    if($('input[name="login-method"]').val() !== 'dobzip')
                        $('#toggleSecureLogin').click();

                    browserAPI.log("submitting saved credentials");
                    form.find('input#cardnumberInputClassicDobZip').val(params.account.login);
                    var date = params.account.login2.replace(/\/?\.?\-?/ig, '');
                    date = date.split(/(\d{2})(\d{2})(\d{4})/i);

                    form.find('input[name = "dobDayName"]').val(date[1]);
                    form.find('input[name = "dobMonthName"]').val(date[2]);
                    form.find('input[name = "dobYearName"]').val(date[3]);

                    form.find('input[name = "zipName"]').val(params.account.password);
                    provider.setNextStep('loginGermanyZip', function () {
                        form.find('#loginSubmitButtonSecure').click();
                    });
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
        }
    },

    loginGermanyPwd: function (params) {
        browserAPI.log("loginGermanyPwd");
        var form = $('form[name=loginForm]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#aliasInputSecure').val(params.account.login);
            form.find('input#passwordInput').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#loginSubmitButtonSecure').click();
                var errors = $('.pb-notification__msg--error:visible');
                if (errors.length > 0 && util.filter(errors.text()) != '') {
                    var errorText = util.filter(errors.text());
                    if (plugin.captchaWorkaround(params, errorText)) {
                        return false;
                    }
                }
            });
        }
        else
            plugin.checkLoginErrors(params);
    },

    captchaWorkaround: function (params, errorText) {
        browserAPI.log("captchaWorkaround");
        // captcha
        if (!/Bitte Login-Daten prüfen. Schutz mit Google reCAPTCHA ist aktiviert/gi.test(errorText)) {
            return false;
        }
        if (provider.isMobile) {
            provider.command('show', function () {
                provider.reCaptchaMessage();
                var form = $('form[name = "loginForm"]');
                form.find('input#passwordInput').val(params.account.password);
                form.bind('submit', function (event) {
                    provider.command('hide', function () {
                        provider.setNextStep('checkLoginErrors', function () {
                            browserAPI.log("captcha entered by user");
                        });
                    });
                });
            });
        }// if (provider.isMobile)
        else {
            browserAPI.log("waiting...");
            provider.setNextStep('checkLoginErrors', function () {
                provider.reCaptchaMessage();
                var incapsulaCounter = 0;
                var incapsula = setInterval(function () {
                    browserAPI.log("waiting... " + incapsulaCounter);
                    if (incapsulaCounter > 120) {
                        clearInterval(incapsula);
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }
                    incapsulaCounter++;
                }, 500);
            });
        }

        return true;
    },

    loginGermanyZip: function (params) {
        browserAPI.log("loginGermanyZip");
        var form = $('form[name=loginForm]');
        if (form.length > 0) {
            if($('input[name="login-method"]').val() !== 'dobzip')
                $('#toggleSecureLogin').click();

            browserAPI.log("submitting saved credentials");
            form.find('input#cardnumberInputClassicDobZip').val(params.account.login);
            var date = params.account.login2.replace(/\/?\.?\-?/ig, '');
            date = date.split(/(\d{2})(\d{2})(\d{4})/i);

            form.find('input[name = "dobDayName"]').val(date[1]);
            form.find('input[name = "dobMonthName"]').val(date[2]);
            form.find('input[name = "dobYearName"]').val(date[3]);

            form.find('input[name = "zipName"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#loginSubmitButtonSecure').click();
            });
        }
        else
            plugin.checkLoginErrors(params);
    },

    checkLoginErrors: function(params){
        browserAPI.log("checkLoginErrors");
        var errors;
        switch (params.account.login3) {
            case 'India':
                browserAPI.log("Region => India");
                errors = $('div.error:visible');
                if (errors.length == 0)
                    errors = $('div#errorPopupBody:visible');
                if (errors.length > 0)
                    provider.setError(errors.text());
                else
                    provider.complete();
                break;
            case 'Italy':
                browserAPI.log("Region => Italy");
                errors = $('p.pb-alert-content__message:visible');
                if (errors.length > 0)
                    provider.setError(errors.text());
                else
                    provider.complete();
                break;
            case 'Poland':
                browserAPI.log("Region => Poland");
                errors = $('div.pb-alert-content__message:visible');
                if (errors.length > 0)
                    provider.setError(errors.text());
                else
                    provider.complete();
                break;
            case 'Mexico':
                errors = $('p.pb-alert-content__message');
                if (errors.length > 0 && util.trim(errors.text()) != '')
                    provider.setError(util.trim(errors.text()));
                else
                    provider.complete();
                break;
            default:
                if ($('a[href*=logout]').attr('href')) {
                    provider.setNextStep('loginComplete', function () {
                        var unixtime = Math.round(new Date().getTime() / 1000);
                        document.location.href = 'https://www.payback.de/' + '?t=' + unixtime;
                    });
                }
                else {
                    errors = $('.pb-notification__msg--error:visible');
                    if (errors.length > 0 && util.filter(errors.text()) != '') {
                        var errorText = util.filter(errors.text());

                        // captcha
                        if (plugin.captchaWorkaround(params, errorText)) {
                            return false;
                        }

                        if (/(?:Ihr Konto wurde zu Ihrer Sicherheit gesperrt!|Ihr Konto wurde deaktiviert\.|Das Konto ist deaktiviert\.)/gi.test(errorText)) {
                            provider.setError([errorText, util.errorCodes.lockout], true);
                            return;
                        }
                        if (/Leider stehen Ihnen die PAYBACK Services aktuell nicht zur Verfügung/gi.test(errorText)) {
                            provider.setError([errorText, util.errorCodes.providerError], true);
                            return;
                        }
                        provider.setError(errorText, true);
                    }// if (errors.length > 0 && util.filter(errors.text()) != '')
                    else
                        plugin.loginComplete(params);
                }
                break;
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        browserAPI.log('Current URL: ' + document.location.href);
        provider.logBody('loginComplete');

        util.waitFor({
            selector: 'h1:contains("2-Schritt-Verifizierung"):visible',
            success: function () {
                if (params.autologin) {
                    provider.setError(['It seems that PAYBACK needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your PAYBACK authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                } else {
                    provider.setError(['It seems that PAYBACK needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your PAYBACK authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                }
            },
            fail: function () {
                if (params.autologin) {
                    browserAPI.log(">>> Only autologin");
                    provider.complete();
                    return;
                }
                util.waitFor({
                    selector: 'p.welcome-msg > a, div.header-element--welcome-msg > a > strong',
                    success: function () {
                        plugin.parse(params);
                    },
                    timeout: 15
                });
            },
            timeout: 5
        });

    },

    parse: function (params) {
        browserAPI.log("parse");
        browserAPI.log('Current URL: ' + document.location.href);
        if (!provider.isMobile)
            provider.updateAccountMessage();
        var data = {};
        // Balance - Current Points
        var balance = $('p.welcome-msg > a');
        if (balance.length === 0) {
            browserAPI.log("Balance, second selector");
            balance = $('div.header-element--welcome-msg > a > strong');
        }
        if (balance.length > 0) {
            balance = util.findRegExp(balance.text(), /([\d\.\,]+)/i);
            browserAPI.log("Balance: " + balance);
            data.Balance = util.trim(balance);
        }
        else
            browserAPI.log("Balance is not found");
        // Name
        var name = $('p.welcome-msg > strong');
        if (name.length === 0) {
            browserAPI.log("Name, second selector");
            name = $('div.header-element--welcome-msg > strong');
        }
        if (name.length > 0) {
            name = util.beautifulName( util.filter(name.text()) );
            browserAPI.log("Name: " + name );
            data.Name = name;
        }
        else
            browserAPI.log("Name not found");

        params.account.properties = data;
//        console.log(params.account.properties);
        provider.saveProperties(params.account.properties);
        browserAPI.log(">>> complete");
        provider.complete();
    }

};