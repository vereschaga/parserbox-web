var plugin = {

    hosts: {'www.millenniumhotels.com': true},

    getStartingUrl: function (params) {
        if (provider.isMobile)
            return 'https://www.millenniumhotels.com/en/my-millennium/sign-in/';
        return 'https://www.millenniumhotels.com/';
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
        if ($('.logged-out.show-user:visible, a:contains("Sign In")').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#opt-logout, span:contains("Sign out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return typeof(account.properties) !== 'undefined'
        && typeof(account.properties.MemberNumber) !== 'undefined'
        && account.properties.MemberNumber != ''
        && document.documentElement.textContent.indexOf("'MembershipID':'" + account.properties.MemberNumber + "'") !== -1;
    },

    logout: function () {
        browserAPI.log("logout");
        var step = 'start';
        if (provider.isMobile) {
            step = 'loadLoginForm';
        }
        provider.setNextStep(step, function () {
            $('#opt-logout, span:contains("Sign out")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var signIn = $('.logged-out.show-user:visible');
        if (signIn.length)
            signIn.get(0).click();
        // wait login form
        var counter = 0;
        var login = setInterval(function () {
            var form = $('form#login_form');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "username"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        var captcha = $('form#login_form img.captcha-img, img.captcha-img:visible');
                        //browserAPI.log("waiting captcha -> " + captcha.attr('src'));
                        if (captcha.length > 0) {
                            browserAPI.log("waiting...");

                            if (provider.isMobile) {
                                provider.reCaptchaMessage();
                                setTimeout(function() {
                                    plugin.checkLoginErrors(params);
                                }, 100000);
                                return;
                            }

                            provider.captchaMessageDesktop();
                            plugin.saveImage(captcha.attr('src'), form, params);
                        }// if (captcha.length > 0)
                        else {
                            browserAPI.log("captcha is not found");
                            $('#opt-sign-in').get(0).click();
                            setTimeout(function () {
                                plugin.checkLoginErrors();
                            }, 7000)
                        }
                    }, 2000)
                });
            }
            if (counter > 20) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    saveImage: function (url, form, params) {
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
                "extension": "png"
            }, function (response) {
                console.log(JSON.stringify(response));
                if (response.success === true) {
                    console.log("Success: " + response.success);
                    form.find('input[name = "captcha"]').val(response.recognized);

                    $('#opt-sign-in').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors();
                    }, 7000)
                }// if (response.success === true))
                else if (response.success === false) {
                    console.log("Success: " + response.success);
                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                }// if (response.success === false)
                else {
                    console.log("Fail: " + response);
                }
            });
        }
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('#error-msg:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else {
            provider.complete();
        }
    }

};