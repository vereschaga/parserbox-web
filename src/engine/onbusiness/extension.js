var plugin = {

    hideOnStart: true,
    clearCache: true,
    hosts: {'www.britishairways.com': true, 'www.onbusiness-programme.com': true, 'onbusiness.britishairways.com': true},

    getStartingUrl: function (params) {
        return "https://onbusiness.britishairways.com/group/ba/home/device-all";
    },

    start: function (params) {
        browserAPI.log("start");
        if (provider.isMobile) {
            provider.setNextStep('startWait', function () {
                browserAPI.log("start");
                setTimeout(function() {
                    browserAPI.log("force call startWait");
                    plugin.startWait(params);
                }, 3000);
            });
        }// if (!provider.isMobile)
        else
            plugin.start2(params);
    },

    // for mobile only
    startWait: function (params) {
        browserAPI.log("startWait");
        provider.setNextStep('start2', function () {
            browserAPI.log("startWait");
            setTimeout(function() {
                plugin.start2(params);
            }, 5000);
        });
    },

    start2: function (params) {
        browserAPI.log("start2");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("start waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            // if the page completely loaded
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loadAccount(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 25) {
                clearInterval(start);
                const error = $('td:contains("On Business is unavailable at the moment due to planned maintenance."):visible, span:contains("experiencing ongoing issues with the availability of our On Business platform."):visible');

                if (error.length > 0) {
                    provider.setError([error.text(), util.errorCodes.providerError], true);
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 500);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = $('span[class *= "company-membership-number"]:visible').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a:contains("Log out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#loginForm').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function(){
            $('a:contains("Log out")').eq(0).get(0).click();
        });
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        if (provider.isMobile) {
            provider.setNextStep('loadLoginFormWait2', function () {
                browserAPI.log("loadLoginForm");
                setTimeout(function() {
                    browserAPI.log("force call loadLoginFormWait2");
                    plugin.loadLoginFormWait2();
                }, 3000);
            });
        }
        else
            plugin.loadLoginFormWait();
    },

    // for mobile only
    loadLoginFormWait2: function () {
        browserAPI.log("loadLoginFormWait2");
        provider.setNextStep('loadLoginFormWait2', function () {
            browserAPI.log("loadLoginFormWait");
            setTimeout(function() {
                browserAPI.log("force call loadLoginFormWait");
                plugin.loadLoginFormWait();
            }, 3000);
        });
    },

    loadLoginFormWait: function (params) {
        browserAPI.log("loadLoginFormWait");
        setTimeout(function() {
            provider.setNextStep('start', function(){
                document.location.href = plugin.getStartingUrl();
            });
        }, 1000);
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form[id = "loginForm"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "username"]').val(params.account.login);
        form.find('input[name = "password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrorsWait', function () {
            form.submit();
        });
    },

    checkLoginErrorsWait: function (params) {
        browserAPI.log("checkLoginErrorsWait");
        if (provider.isMobile) {
            provider.setNextStep('checkLoginErrors', function () {
                browserAPI.log("checkLoginErrorsWait");
                setTimeout(function() {
                    browserAPI.log("force call checkLoginErrors");
                    plugin.checkLoginErrors(params);
                }, 3000);
            });
        }// if (!provider.isMobile)
        else
            plugin.checkLoginErrors(params);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        setTimeout(function() {
            if (plugin.checkPassUpdate()){
                return;
            }
            let errors = $('div.has-error');
            if (errors.length > 0
                && $('div:contains("To complete our records, please provide the information below.")').length == 0)
                provider.setError(errors.text(), true);
            else if (plugin.isLoggedIn()) {
                plugin.loadAccount(params);
            }
            else {
                browserAPI.log(">>> check errors");
                provider.complete();
            }
        }, 5000);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");

        if (params.autologin) {
            browserAPI.log("Only autologin");
            provider.complete();
            return;
        }

        browserAPI.log("Loading account");
        provider.updateAccountMessage();

        if (document.location.href != 'https://onbusiness.britishairways.com/group/ba/home') {
            provider.setNextStep('parse', function(){
                document.location.href = 'https://onbusiness.britishairways.com/group/ba/home';
            });
            return;
        }

        plugin.parse(params);
    },

    checkPassUpdate: function () {
        browserAPI.log("checkPassUpdate");
        if ($('form[id = "loginDetailsForm"] input[name = "confirmPassword"]:visible').length
            && $('form[id = "loginDetailsForm"] input[name = "newPassword"]:visible').length) {
            provider.setError(["British Airways On Business website needs you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
            return true;
        }
        return false;
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        if (plugin.checkPassUpdate()){
            return;
        }
        var data = {};

        // Name
        var name = util.findRegExp( $('h1#dashboard-welcome').text(), /\,\s*([^\,<]+)/i);
        if (name && name.length > 0) {
            data.Name = util.beautifulName( name );
            browserAPI.log("Name: " + data.Name );
        } else
            browserAPI.log("Name not found");
        // Company Membership
        var number = $('span[class *= "company-membership-number"]:visible').text();
        if (number.length > 0) {
            browserAPI.log("Number: " + number );
            data.Number = number;
        } else
            browserAPI.log("Number not found");
        // Company name
        var companyName = $('p[class *= "company-name"]:visible').text();
        if (companyName.length > 0) {
            browserAPI.log("Company name: " + companyName );
            data.CompanyName = companyName;
        } else
            browserAPI.log("Company name not found");
        // Balance - Points available
        var balance = util.findRegExp( $('span#dashboard-redemptionPointsBalance').text() , /([\-\d\,\.]+)/i);
        if (balance.length > 0) {
            browserAPI.log("Balance: " + balance);
            data.Balance = util.trim(balance);
        } else
            browserAPI.log("Balance not found");
        // Airline expenditure
        var airlineExpenditure = $('span#dashboard-expenditureBalancePrgBaseCurrency').text();
        if (airlineExpenditure.length > 0) {
            browserAPI.log("Company name: " + airlineExpenditure );
            data.AirlineExpenditure = airlineExpenditure;
        } else
            browserAPI.log("Airline expenditure not found");
        // Your tier
        var tier = $('span#current-tier').text();
        if (tier.length > 0) {
            browserAPI.log("Tier: " + tier );
            data.Tier = tier;
        }
        else
            browserAPI.log("Tier not found");
        var tierExpiration = $('span#date-info');
        // Your tier: <tier> until ...
        if (tier.length > 0) {
            tierExpiration = util.findRegExp(tierExpiration.text(), /until\s*([^\()]+)/);
            browserAPI.log("StatusExpiration: " + tierExpiration );
            data.StatusExpiration = tierExpiration;
        }
        else
            browserAPI.log("StatusExpiration not found");

        // Points expiring
        $('div#pts-expiring table').find('tr:has(td)').each(function () {
            var date = util.trim($('td:eq(0)', $(this)).text());
            var points = util.trim($('td:eq(2)', $(this)).text());
            browserAPI.log("Date: " + date + " / " + points);
            if (points != '' && points != 0) {
                date = util.modifyDateFormat(date);
                var exp = new Date(date + ' UTC');
                var unixtime = exp / 1000;
                if ( exp != 'NaN' && !isNaN(unixtime) ) {
                    // Extend exp date to 31 Dec 2022
                    if (unixtime === 1672444800) {
                        unixtime = 1672444800;
                        browserAPI.log("Extend Expiration Date by rules to 31 Sep 20222");
                    }
                    browserAPI.log("Expiration Date: " + exp + " Unixtime: " + util.trim(unixtime) );
                    data.AccountExpirationDate = unixtime;
                    // Points to Expire
                    data.PointsToExpire = points;
                    browserAPI.log("PointsToExpire: " + data.PointsToExpire);
                }
                return false;
            }// if (points != '' && points > 0)
            else
                browserAPI.log("Skip row");
        });

        // save data
        params.account.properties = data;
        //console.log(params.account.properties);
        provider.saveProperties(params.account.properties);

        provider.complete();
    }

}