var plugin = {

    hosts: {'www.harrispollonline.com': true, 'www.harrisrewards.com': true, 'harrisrewards.com': true, 'survey1.hi-epanel.com': true},

    getStartingUrl: function (params) {
        switch (params.account.login2) {
            case 'Germany':
                return 'https://survey1.hi-epanel.com/index.php?languageID=1';
                break;
            case 'USA':
            default:
                return 'https://www.harrispollonline.com/#login';
                break;
        }
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params.account.login2);
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

    isLoggedIn: function (region) {
        browserAPI.log("isLoggedIn");
        browserAPI.log("Region => " + region);
        if (region == 'Germany') {
            if ($('a[href *= "Logout"]').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[name = "loginBoxForm"]').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
        }
        else {
            if ($('a:contains("Logout"):visible').length) {
                browserAPI.log("LoggedIn");
                return true;
            }
            var form = $('div[id *= "login-"][role = "form"]');
            if (form.length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('span.user-name').text().trim();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('span:contains("Logout"):visible').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        setTimeout(function () {
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }, 2000)
    },

    login: function (params) {
        browserAPI.log("login");
        var form;
        switch (params.account.login2) {
            case 'Germany':
                form = $('form[name = "loginBoxForm"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "user"]').val(params.account.login);
                    form.find('input[name = "pass"]').val(params.account.password);
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('button[type = "submit"]').get(0).click();
                    });
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
            case 'USA':
            default:
                form = $('div[id *= "login-"][role = "form"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "UserID"]').val(params.account.login);
                    form.find('input[name = "Pwd"]').val(params.account.password);
                    util.sendEvent(form.find('input[name = "UserID"]').get(0), 'input');
                    util.sendEvent(form.find('input[name = "Pwd"]').get(0), 'input');
                    provider.setNextStep('checkLoginErrors', function () {
                        setTimeout(function () {
                            form.find('span:contains("Sign In")').get(0).click();
                            setTimeout(function () {
                                plugin.checkLoginErrors(params);
                            }, 7000);
                        }, 2000);
                    });

                    // setTimeout(function () {
                    //     var captcha = form.find('tr[id = "dnn_ctr4677_Login_Login_DNN_trCaptcha2"] img');
                    //     //browserAPI.log("waiting captcha -> " + captcha.attr('src'));
                    //     //browserAPI.log("waiting captcha -> " + 'https://www.harrispollonline.com' + captcha.attr('src'));
                    //     provider.captchaMessageDesktop();
                    //     if (captcha.length > 0) {
                    //         browserAPI.log("waiting...");
                    //         plugin.saveImage('https://www.harrispollonline.com/' + captcha.attr('src'), form);
                    //     }// if (captcha.length > 0)
                    //     else
                    //         browserAPI.log("captcha is not found");
                    // }, 2000);
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
        }
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
            var dataURL= canvas.toDataURL("image/png");
            browserAPI.log("dataURL: " + dataURL);
            // recognize captcha
            browserAPI.send("awardwallet", "recognizeCaptcha", {
                captcha: dataURL,
                "extension": "png",
                postDataExtended: {"regsense": 1}
            }, function (response) {
                console.log(JSON.stringify(response));
                if (response.success === true) {
                    console.log("Success: " + response.success);
                    form.find('input[name = "dnn$ctr4677$Login$Login_DNN$ctlCaptcha"]').val(response.recognized);

                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('input[name = "dnn$ctr4677$Login$Login_DNN$cmdLogin"]').get(0).click();
                    });
                }// if (response.success === true))
                if (response.success === false) {
                    console.log("Success: " + response.success);
                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                }// if (response.success === false)
            });
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors;
        switch (params.account.login2) {
            case 'Germany':
                errors = $('div.error_wrapper:visible');
                break;
            case 'USA':
            default:
                errors = $('label[style *= "color: red;"]:visible');
                break;
        }
        if (errors.length > 0 && util.filter(errors.text()) != '')
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }
};