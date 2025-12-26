var plugin = {

    hideOnStart: true,
    // keepTabOpen: true,//todo
    hosts: {'www.plenti.com': true},

    getStartingUrl: function (params) {
        return 'https://www.plenti.com/points-activity';
    },

    getFocusTab: function (account, params) {
        return true;
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
            if ($('form[name = "Login"]:visible').length > 0
                || $('a[href *= "Logout"]').length > 0) {

                clearInterval(start);

                if (plugin.isLoggedIn()) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }
            if (counter > 10) {
                clearInterval(start);
                if (error.length === 0 && $('p:contains("net::ERR_NAME_NOT_RESOLVED"):visible').length > 0
                    && $('h2:contains("Webpage not available"):visible').length > 0) {
                    provider.setError(util.errorMessages.providerErrorMessage, true);
                    return;
                }
                provider.setError(util.errorMessages.unknownLoginState);
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href *= "Logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[name = "Login"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.beautifulName(util.findRegExp($('span.username').html(), /Hi\s*([^<]+)/));
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name == account.properties.Name));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= "Logout"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "Login"]:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find("input[name = 'alias']").val(params.account.login);
            form.find("input[name = 'secret']").val(params.account.password);
            provider.setNextStep('checkLoginErrors', function() {
                setTimeout(function() {
                    var captcha = util.findRegExp( form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                    var submitButton = form.find('input[name *= "loginButton"], input[name *= "login-button"]');
                    //browserAPI.log("waiting captcha -> " + captcha);
                    if (captcha && captcha.length > 0) {
                        browserAPI.log("waiting...");
                        if (provider.isMobile) {
                            provider.command('show', function () {
                                provider.reCaptchaMessage();
                                var events = submitButton.data("events");
                                var originalFn = events[0];
                                submitButton.unbind('click');
                                submitButton.bind('click', function (event) {
                                    provider.command('hide', function () {
                                        browserAPI.log("captcha entered by user");
                                        submitButton.unbind('click');
                                        submitButton.bind('click', originalFn);
                                    });
                                    event.preventDefault();
                                });
                            });
                        }// if (provider.isMobile)
                        else {
                            provider.reCaptchaMessage();
                            if (form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"][src *= "invisible"]').length > 0) {
                                browserAPI.log("invisible captcha workaround");
                                submitButton.get(0).click();
                            }
                            browserAPI.log("waiting...");
                            provider.setNextStep('checkLoginErrors', function() {
                                var counter = 0;
                                var login = setInterval(function () {
                                    browserAPI.log("waiting... " + counter);
                                    var errors = $('div.error > p:visible');
                                    if (errors.length > 0) {
                                        clearInterval(login);
                                        provider.setError(errors.text(), true);
                                    }// if (errors.length > 0)
                                    if (counter > 120) {
                                        clearInterval(login);
                                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                                    }
                                    counter++;
                                }, 500);
                            });
                        }
                    }// if (captcha && captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        submitButton.get(0).click();
                    }
                }, 2000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var error = $('div.error > p:visible');
        if (error.length > 0)
            provider.setError(error.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        if (params.autologin) {
            provider.complete();
            return;
        }
        browserAPI.log("Loading account");
        var myAccountUrl = 'https://www.plenti.com/points-activity';
        if (document.location.href != myAccountUrl) {
            provider.setNextStep('parse', function () {
                document.location.href = myAccountUrl;
            });
        }
        else
            plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();

        var data = {};
        // Balance - Your Available Points
        var balance = $('div.pa-totalpoints-amount');
        if (balance.length > 0 && balance.text() != '') {
            data.Balance = util.trim(balance.text());
            browserAPI.log("Balance: " + data.Balance);
        }
        else {
            browserAPI.log(">>> Balance not found");
            // Balance from menu
            balance = $('span.userpoints:visible');
            if (balance.length > 0 && balance.text() != '') {
                data.Balance = util.findRegExp(balance.text(), /([\s\d\.\,\-])+/i);
                browserAPI.log("Balance: " + data.Balance);
            }
            else
                browserAPI.log(">>> Balance from menu not found");
        }
        // Name
        var name = $('span.username');
        if (name.length > 0) {
            data.Name = util.beautifulName(util.findRegExp(name.html(), /Hi\s*([^<]+)/));
            browserAPI.log("Name: " + data.Name);
        } else
            browserAPI.log(">>> Name not found");
        // Pending Points
        var pendingPoints = $('div.pa-blockedpoints-amount');
        if (pendingPoints.length > 0) {
            data.PendingPoints = pendingPoints.text();
            browserAPI.log("Pending Points: " + data.PendingPoints);
        } else
            browserAPI.log(">>> Pending Points not found");

        // refs #13683
        var seeTransactions = $('#save1');
        // if (seeTransactions.length > 0 && typeof (data.Balance) != 'undefined' && parseFloat(data.Balance) > 0) {
        if (typeof (data.Balance) != 'undefined' && parseFloat(data.Balance) > 0) {
            browserAPI.log("get exp date");

            var date = new Date('10 Jul 2018 UTC');
            var unixtime =  date / 1000;
            if ( date != 'NaN' && !isNaN(unixtime) ) {
                browserAPI.log("ExpirationDate = 10 Jul 2018 ");
                browserAPI.log("Expiration date: " + date + " Unixtime: " + unixtime );
                data.AccountExpirationDate = unixtime;
            }// if (date != 'NaN')

            /*$('#input_filter_date_from_month').val('01');
            $('#input_filter_date_from_day').val('01');
            $('#input_filter_date_from_year').val(new Date().getFullYear() - 3);

            params.data.properties = data;
            params.data.pointsEarned = [];
            params.data.endHistory = false;
            provider.saveTemp(params.data);

            provider.setNextStep('parseHistory', function () {
                seeTransactions.click();
            });*/
        }// if (seeTransactions.length > 0 && typeof (data.Balance) != 'undefined' && data.Balance > 0)
        // else {
            params.account.properties = data;
            // console.log(params.account.properties);//todo
            provider.saveProperties(params.account.properties);
            provider.complete();
        // }
    },

    parseHistory: function(params) {
        browserAPI.log("parseHistory");
        provider.updateAccountMessage();
        var history = [];

        if (typeof (params.data.Page) == 'undefined')
            params.data.Page = 2;
        var nextPage = $('button:contains("Show More Points Activity"):visible');

        if (nextPage.length > 0 && params.data.Page < 6 && !params.data.endHistory) {
            browserAPI.log(">>> page: " + params.data.Page);
            params.data.Page++;
            // save data
            provider.saveTemp(params.data);

            provider.setNextStep('parseHistory', function () {
                nextPage.get(0).click();
                var counter = 0;
                var nextHistoryPart = setInterval(function () {
                    browserAPI.log("waiting next history part... " + counter);
                    var loading = $('div:contains("Loading More"):visible');
                    if ((loading.length == 0 && nextPage.length > 0)
                        || (loading.length == 0 && nextPage.length == 0)
                        || counter > 5) {
                        clearInterval(nextHistoryPart);
                        setTimeout(function () {
                            plugin.parseHistory(params);
                        }, 1000);
                    }// if (loading.length != 0 || counter > 5)
                    counter++;
                }, 500);
            });
        }
        else {
            browserAPI.log("reached the end of history");
            var balance = params.data.properties.Balance.replace(/,/g, '');
            browserAPI.log("Balance: " + balance);
            var nodes = $('table#transactions-table tr[class *= "base-row"][class *= "redeemable"]:has(td)');
            browserAPI.log('Total ' + nodes.length + ' items were found');
            for (var i = 0; i < nodes.length; i++) {
                var row = {};
                var dateStr = util.filter(nodes.eq(i).find('td[class *= "purchaseDate"] > div').text());
                var points = util.findRegExp(nodes.eq(i).find('td[class *= "points"] > div').text(), /([\d\.\,\-]+)/ig);
                if (dateStr && points) {
                    params.data.pointsEarned[i] = {
                        'date': dateStr,
                        'points': points.replace(/,/g, '')
                    };
                    balance = balance - params.data.pointsEarned[i].points;
                    browserAPI.log("#"+ i +" Date "+ params.data.pointsEarned[i].date +" - "+ params.data.pointsEarned[i].points +" / Balance: "+ balance);

                    if (balance <= 0) {
                        browserAPI.log("Date " + params.data.pointsEarned[i].date);
                        // Earning Date     // refs #4936
                        params.data.properties.EarningDate = params.data.pointsEarned[i].date;
                        browserAPI.log("EarningDate: " + params.data.properties.EarningDate);
                        // Expiration Date

                        if ((typeof(dateStr) != 'undefined') && (dateStr != '')) {
                            var date = new Date(dateStr + ' UTC');
                            var endOfYear = date.getFullYear() + 2;
                            endOfYear = '31 Dec ' + endOfYear;
                            date = new Date(endOfYear + ' UTC');
                            var unixtime =  date / 1000;
                            if ( date != 'NaN' && !isNaN(unixtime) ) {
                                browserAPI.log("ExpirationDate = earningDate + 24 months");
                                browserAPI.log("Expiration date: " + date + " Unixtime: " + unixtime );
                                params.data.properties.AccountExpirationDate = unixtime;
                            }// if (date != 'NaN')
                        }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))

                        // Points to Expire
                        balance = balance + parseFloat(params.data.pointsEarned[i].points);
                        for (var k = i - 1; k >= 0; k--) {
                            browserAPI.log("> Balance: " + balance);
                            if (typeof(params.data.pointsEarned[k].date) != 'undefined'
                                && params.data.pointsEarned[i].date == params.data.pointsEarned[k].date) {
                                balance = parseFloat(balance) + parseFloat(params.data.pointsEarned[k].points);
                            }// if (typeof(pointsEarned[k].date) != 'undefined' && pointsEarned[i].date == pointsEarned[k].date)
                        }// for (var k = i - 1; k >= 0; k--)

                        params.data.properties.ExpiringBalance = balance;
                        browserAPI.log("ExpiringBalance: " + balance);
                        break;
                    }// if (balance <= 0)
                }// if (dateStr && points)
            }// for (var i = 0; i < nodes.length; i++)

            params.account.properties = params.data.properties;
            // console.log(params.account.properties);//todo
            provider.saveProperties(params.account.properties);
            browserAPI.log(">>> complete");
            provider.complete();
        }
    }

}