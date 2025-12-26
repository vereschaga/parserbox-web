var plugin = {

    hosts: {'customer.licard.ru': true},

    getStartingUrl: function (params) {
        return 'https://customer.licard.ru/#/';
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
        if ($('form[name = "form"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "logout"]:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('.li-my-card__number').text(), /\s+(\w+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && (number == account.properties.CardNumber));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            $('a[href *= "/auth/logout"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "form"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "login"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);

            // refs #11326
            util.sendEvent(form.find('input[name = "login"]').get(0), 'input');
            util.sendEvent(form.find('input[name = "password"]').get(0), 'input');

            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    var captcha = form.find('img[src*="data:image/jpeg;base64"]:visible');
                    if (captcha.length > 0) {
                        captcha.attr('id', 'captcha_image');
                        provider.captchaMessageDesktop();
                        plugin.saveImage(captcha, form);
                    } else {
                        // form.find('input[type = "submit"]').get(0).click();
                        // setTimeout(function () {
                        //     plugin.checkLoginErrors();
                        // }, 5000);
                    }
                }, 2000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    saveImage: function (captcha, form) {
        var captchaDiv = document.createElement('div');
        captchaDiv.id = 'captchaDiv';
        document.body.appendChild(captchaDiv);

        var canvas = document.createElement('CANVAS'),
            ctx = canvas.getContext('2d'),
            img = document.getElementById(captcha.attr('id'));

        canvas.height = img.height;
        canvas.width = img.width;
        ctx.drawImage(img, 0, 0);
        var dataURL = canvas.toDataURL('image/png');

        browserAPI.log("dataURL: " + dataURL);
        // recognize captcha
        browserAPI.send("awardwallet", "recognizeCaptcha", {
            captcha: dataURL,
            "extension": "png"
        }, function (response) {
            browserAPI.log(JSON.stringify(response));
            if (response.success === true) {
                browserAPI.log("Success: " + response.success);
                form.find('input[name="captcha"]').val(response.recognized);
                util.sendEvent(form.find('input[name="captcha"]').get(0), 'input');
                form.find('input[type = "submit"]').get(0).click();
            }// if (response.success === true))
            if (response.success === false) {
                console.log("Success: " + response.success);
                provider.setError(util.errorMessages.captchaErrorMessage, true);
            }// if (response.success === false)
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('[class = "error ng-active"]');
        if (errors.length > 0 && util.trim(errors.text()) != "")
            provider.setError(util.trim(errors.text()));
        else
            provider.complete();
    }
};