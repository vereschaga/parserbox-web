var plugin = {

    hosts: {'malina.ru': true},

    getStartingUrl: function (params) {
        return 'https://malina.ru/msk/pp/';
    },

    months: {
        "ЯНВАРЬ"  : 1,
        "ФЕВРАЛЬ" : 2,
        "МАРТ"    : 3,
        "АПРЕЛЬ"  : 4,
        "МАЙ"     : 5,
        "ИЮНЬ"    : 6,
        "ИЮЛЬ"    : 7,
        "АВГУСТ"  : 8,
        "СЕНТЯБРЬ": 9,
        "ОКТЯБРЬ" : 10,
        "НОЯБРЬ"  : 11,
        "ДЕКАБРЬ" : 12
    },

    getFocusTab: function(account, params){
        return true;
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
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
        if ($('form[action *= "/pp/login/"]').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('a[href *=logout]').length > 0 || $('div.acc-exit').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('div:contains("Номер счета") + div.data').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.AccountNumber) !== 'undefined')
            && (account.properties.AccountNumber !== '')
            && (number === account.properties.AccountNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            var logout = $('div.acc-exit');
            if (logout.length > 0)
                logout.click();
            //document.location.href = 'http://malina.ru/msk/pp/logout/';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        // open popup
        var formLink = $('li.acc-login-form a');
        if (formLink.length > 0)
            formLink.get(0).click();

        var form = $('form[action *= "/pp/login/"]');
        if (form.length > 0) {
            browserAPI.log("Submitting credentials");
            form.find('input[name = "contact"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    var captcha = form.find('img.captcha');
                    //browserAPI.log("waiting captcha -> " + captcha.attr('src'));

                    provider.captchaMessageDesktop();
                    if (captcha.length > 0) {
                        browserAPI.log("waiting...");
                        plugin.saveImage('https://malina.ru' + captcha.attr('src'), form, params);
                    }// if (captcha.length > 0)
                    else
                        browserAPI.log("captcha is not found");
                }, 2000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
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
                    // lastpass gap     // refs #9411
                    form.find('input[name = "contact"]').val(params.account.login);
                    form.find('input[name = "password"]').val(params.account.password);
                    form.find('input[name = "captcha_1"]').val(response.recognized);

                    form.find('button').get(0).click();
                    setTimeout(function() {
                        plugin.checkLoginErrors(params);
                    }, 2000);
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

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        // retries
        var retry = $.cookie("malina.ru_aw_retry");
        // login failed
        if ($('div:contains("Вы ввели неверные символы.")').length > 0) {
            browserAPI.log("Login failed");
            if ((typeof(retry) === 'undefined' || retry === null) || retry < 3) {
                if (typeof(retry) === 'undefined' || retry === null)
                    retry = 0;
                provider.logBody("Login_failed_" + retry);
                retry++;
                $.cookie("malina.ru_aw_retry", retry, { expires: 0.01, path:'/', domain: '.malina.ru', secure: true });
                plugin.loadLoginForm(params);
                return;
            }
            else {
                provider.logBody("Login_failed_" + retry);
                provider.setError(util.errorMessages.captchaErrorMessage, true);
                return;
            }
        }// if ($('div:contains("Вы ввели неверные символы.")').length > 0)

        var errors = $('div.messenger');
        if (errors.length > 0)
            provider.setError(errors.text(), true);
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (params.autologin) {
            provider.complete();
            return;
        }
        plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        browserAPI.log('Current URL: ' + document.location.href);
        //// У Вас задан слишком простой пароль. Вы должны сменить его, чтобы продолжить.
        //var errors = $('li:contains("У Вас задан слишком простой пароль.")');
        //if (errors.length > 0)
        //    provider.setError(errors.text());

        if (document.location.href !== 'https://malina.ru/msk/pp/'){
            provider.setNextStep('parse', function () {
                document.location.href = 'https://malina.ru/msk/pp/';
            });
        }
        else
            plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("Retrieving balance");
        browserAPI.log('Current URL: ' + document.location.href);
        provider.updateAccountMessage();

        var data = {};
        // Balance - Баланс баллов
        var balance = $('div:contains("Баланс баллов") + div.data');
        balance = util.findRegExp(balance.text(), /([\d\.\,\s]+)/i);
        if (balance !== null && balance.length > 0) {
            balance = balance.replace(/\s/ig, '');
            browserAPI.log("Balance: " + balance);
            data.Balance = util.trim(balance);
        } else {
            browserAPI.log("Balance is not found");
            balance = util.findRegExp($('div.acc-points:has(img[src *= "points.png"])').text(), /([\d\.\,\s]+)/i);
            if (balance.length > 0) {
                balance = balance.replace(/\s/ig, '');
                browserAPI.log("Balance: " + balance);
                data.Balance = util.trim(balance);
            }
            else
                browserAPI.log("Balance from header not found");
        }
        // Номер счета
        var number = $('div:contains("Номер счета") + div.data').text();
        if (number.length > 0) {
            browserAPI.log("Номер счета: " + util.trim(number));
            data.AccountNumber = util.trim(number);
        } else
            browserAPI.log("Номер счета is not found");
        // Name
        var name = $('div:contains("Владелец счета") + div.data').text();
        if (name.length > 0) {
            name = util.beautifulName(util.filter(name));
            browserAPI.log("Name: " + name);
            data.Name = name;
        } else {
            browserAPI.log("Name is not found");
            name = $('li.acc-holder > a:has(i)').text();
            if (name.length > 0) {
                name = util.beautifulName(util.filter(name));
                browserAPI.log("Name: " + name);
                data.Name = name;
            }
            browserAPI.log("Name from header not found");
        }
        // Дата регистрации
        var memberSince = $('div:contains("Дата регистрации") + div.data').text();
        if (memberSince.length > 0) {
            browserAPI.log("Дата регистрации: " + util.trim(memberSince));
            data.MemberSince = util.trim(memberSince);
        } else
            browserAPI.log("Дата регистрации is not found");
        // Доступно к обмену на товары и услуги
        var availableToSpend = $('div:contains("Доступно к обмену на") + div.data').text();
        if (availableToSpend.length > 0) {
            browserAPI.log("AvailableToSpend: " + availableToSpend);
            data.AvailableToSpend = availableToSpend;
        } else
            browserAPI.log("Доступно к обмену на товары и услуги are not found");

        provider.saveProperties(data);

        // Parsing Exp Date
        provider.setNextStep('parseExpDates', function () {
            document.location.href = 'https://malina.ru/msk/pp/forecast/';
        });
    },

    parseExpDates: function (params) {
        browserAPI.log("Retrieving expiration date");
        provider.updateAccountMessage();
        //console.log(params);
        // Exp Date
        var row = $('table.table-simple').find('tr:has(td):eq(0)');
        if (row.length > 0) {
            var d = row.children('td:eq(0)').text();
            browserAPI.log("Date: " + d);
            var month = util.findRegExp( d, /([а-я]+)\s*\d{4}$/i);
            var year = util.findRegExp( d, /(\d{4})$/i);
            // Expiring Balance
            params.account.properties.ExpiringBalance = row.children('td:eq(1)').text();
            browserAPI.log("Month: " + month + " (" + plugin.months[month.toUpperCase()] + ") "
                + year + " / " + params.account.properties.ExpiringBalance);
            if ((typeof(plugin.months[month.toUpperCase()]) !== 'undefined')) {
                var date = new Date(plugin.months[month.toUpperCase()] + '/01/' + year + ' UTC');
                var unixtime = date / 1000;
                if (date !== 'NaN') {
                    browserAPI.log("Expiration Date: " + date + " Unixtime: " + util.trim(unixtime));
                    params.account.properties.AccountExpirationDate = unixtime;
                }
            }
        } else
            browserAPI.log("Exp Date is not found");

        console.log(params.account.properties);
        provider.saveProperties(params.account.properties);
        provider.complete();
    }
}