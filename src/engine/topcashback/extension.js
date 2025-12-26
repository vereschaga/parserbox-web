var plugin = {

    hosts: {
        'www.topcashback.com'  : true,
        'www.topcashback.co.uk': true,
        'cn.topcashback.com'   : true,
        'www.topcashback.de'   : true
    },

    getDomain: function (account) {
        let domain = 'co.uk';
        if (account.login2 === 'USA')
            domain = 'com';
        if (account.login2 === 'Germany')
            domain = 'de';

        return domain;
    },

    getStartingUrl: function (params) {
        var domain = plugin.getDomain(params.account);
        return  "https://www.topcashback." + domain + "/";
    },

    start: function (params) {
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);
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

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return false;
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href *= logout], a[href *= "abmelden"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('a[href *= logon], a:contains("SIGN-IN"):visible, a:contains("Anmelden"):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            let url;
            switch (params.account.login2) {
                case "Germany":
                    url = 'https://www.topcashback.de/abmelden';
                    break;
                case "USA":
                    url = 'https://www.topcashback.com/logout';
                    break;
                default:
                    url = 'https://www.topcashback.co.uk/logout';
                    break;
            }

            document.location.href = url;
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            const domain = plugin.getDomain(params.account);
            if (domain === 'de') {
                document.location.href = "https://www.topcashback." + domain +  "/keine-anmeldung?PageRequested=https%3a%2f%2fwww.topcashback." + domain + "/konto/auszahlungen/";
                return;
            }
            document.location.href = "https://www.topcashback." + domain + "/logon?PageRequested=https://www.topcashback." + domain +"/account/overview";
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "aspnetForm"]');
        if (form.length == 0)
            form = $('#aspnetForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "ctl00$GeckoOneColPrimary$LoginRefactor1$txtEmail"]').val(params.account.login);
            form.find('input[name = "ctl00$GeckoOneColPrimary$LoginRefactor1$txtPassword"]').val(params.account.password);

            form.find('input[name = "ctl00$GeckoOneColPrimary$Login$txtEmail"]').val(params.account.login);
            form.find('input[name = "ctl00$GeckoOneColPrimary$Login$txtPassword"]').val(params.account.password);

            form.find('input[name = "ctl00$GeckoOneColPrimary$Login$txtEmail"]').val(params.account.login);
            form.find('input[name = "ctl00$GeckoOneColPrimary$Login$loginPasswordInput"]').val(params.account.password);

            // Germany
            form.find('input[name = "ctl00$GeckoOneColPrimary$LoginV2$txtEmail"], input[name = "ctl00$mainContent$LoginMobile$emailInput"]').val(params.account.login);
            form.find('input[name = "ctl00$GeckoOneColPrimary$LoginV2$loginPasswordInput"], input[name = "ctl00$mainContent$LoginMobile$passwordInput"]').val(params.account.password);

            // mobile
            form.find('input[name = "ctl00$mainContent$Login$txtEmail"]').val(params.account.login);
            form.find('input[name = "ctl00$mainContent$Login$txtPassword"]').val(params.account.password);

            provider.setNextStep('checkLoginErrors', function () {
                // captcha recognize
                setTimeout(function() {
                    var captcha = form.find('img[src *= "CaptchaImage"], img[src *= "BotDetectCaptcha"]');
                    //browserAPI.log("waiting captcha -> " + captcha.attr('src'));
                    if (captcha.length > 0) {
                        provider.captchaMessageDesktop();
                        browserAPI.log("waiting...");
                        var dataURL = captcha.attr('src');

                        plugin.saveImage('https://' + document.location.host + '/' + captcha.attr('src'), form);
                    }// if (captcha.length > 0)
                    else {
                        if (form.find('#ctl00_GeckoOneColPrimary_Login_pnlLoginArea > div.g-recaptcha:visible').length
                            || form.find('#ctl00_GeckoOneColPrimary_Login_pnlLoginArea div.grecaptcha-badge:visible').length
                            || form.find('div.AlignCenterContainer script[src^="https://www.google.com/recaptcha/api/challenge"]').length) {
                            provider.reCaptchaMessage();
                            $('#Loginbtn').click();
                            setTimeout(function() {
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }, 1000*120);
                        }
                        else {
                            browserAPI.log("captcha is not found");
                            form.find('input[name = "ctl00$GeckoOneColPrimary$LoginRefactor1$Loginbtn"], input[name = "ctl00$GeckoOneColPrimary$Login$Loginbtn"], input[name = "ctl00$GeckoOneColPrimary$LoginRefactor$Loginbtn"], button[name = "ctl00$mainContent$Login$btnLogin"], button[name = "ctl00$GeckoOneColPrimary$LoginV2$Loginbtn"], button[name = "ctl00$mainContent$LoginMobile$btnLogin"]').get(0).click();
                        }
                    }
                }, 1000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    saveImage: function (url, form) {
        var img = document.createElement("img");
        img.src = url;
        img.onload = function () {
            var key = encodeURIComponent(url),
                canvas = document.createElement("canvas");

            canvas.width = img.width;
            canvas.height = img.height;
            var ctx = canvas.getContext("2d");
            ctx.drawImage(img, 0, 0);
            //localStorage.setItem(key, canvas.toDataURL("image/png"));
            var dataURL= canvas.toDataURL("image/png");
            browserAPI.log("dataURL: " + dataURL);
            // recognize captcha
            browserAPI.send("awardwallet", "recognizeCaptcha", {
                captcha: dataURL,
                "extension": "jpg"
            }, function (response) {
                browserAPI.log(JSON.stringify(response));
                if (response.success === true) {
                    browserAPI.log("Success: " + response.success);
                    form.find('input[name = "ctl00$GeckoOneColPrimary$Login$CaptchaControl1"]').val(response.recognized);
                    form.find('input[name = "ctl00$GeckoOneColPrimary$Login$CaptchaHandler$CaptchaControl1"]').val(response.recognized);
                    form.find('input[name = "ctl00$GeckoOneColPrimary$Login$CaptchaHandler$CaptchaCodeTextBox"]').val(response.recognized);
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('input[name = "ctl00$GeckoOneColPrimary$LoginRefactor1$Loginbtn"], input[name = "ctl00$GeckoOneColPrimary$Login$Loginbtn"], input[name = "ctl00$GeckoOneColPrimary$LoginRefactor$Loginbtn"]').get(0).click();
                    });
                }// if (response.success === true))
                if (response.success === false) {
                    browserAPI.log("Success: " + response.success);
                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                }// if (response.success === false)
            });
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('span[id *= "lblLoginFailedMemberNotEnabled"]:visible, span.error:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};