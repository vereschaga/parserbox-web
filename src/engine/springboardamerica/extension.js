var plugin = {

    hideOnStart: true,
    clearCache: true,
    hosts: {
        'www.springboardamerica.com': true,
        'www.springboardamericarewards.com': true,
        'www.unlocksurveys.com': true,
    },
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),

    getStartingUrl: function (params) {
        return 'https://www.springboardamerica.com/';
    },

    getFocusTab: function(account, params){
        return true;
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
                        plugin.loadAccount(params);
                    else
                        plugin.logout();
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
        if ($('a[href *=logout]:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#loginBtn:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.findRegExp( $('div.welcome-text > h1').text(), /Hello\s*([^<]+)/i);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (name.toLowerCase() == account.properties.Name.toLocaleLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        $('a[href *=logout]:visible').get(0).click();
    },

    login: function (params) {
        browserAPI.log("login");

        $('#loginBtn').get(0).click();
        var form = $('form[id = "login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);

            provider.setNextStep('checkLoginErrors', function () {
                if(!provider.isMobile){
                    provider.reCaptchaMessage();
                    browserAPI.log("waiting...");
                    setTimeout(function() {
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }, 50000);
                }else{
                    browserAPI.log(">>> mobile");
                    provider.command('show', function(){
                        $('.alert-box').remove();
                        provider.reCaptchaMessage();
                        var loginBtn = form.find('input[name = "signup"]');
                        //loginBtn.unbind('click');
                        loginBtn.bind('click.captcha', function(event){
                            event.preventDefault();
                            loginBtn.unbind('click');
                            if (params.autologin)
                                clickButton();
                            else {
                                provider.command('hide', function() {
                                    browserAPI.log("captcha entered by user");
                                    clickButton();
                                });
                            }
                            function clickButton() {
                                provider.setNextStep('checkLoginErrors', function() {
                                    loginBtn.get(0).click();
                                    //form.submit();
                                });
                            }
                        })
                    });
                }
            });// provider.setNextStep('checkLoginErrors', function ()
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        console.log("checkLoginErrors");
        var errors = $('ul.errors:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        if (params.autologin) {
            provider.complete();
            return;
        }
        provider.setNextStep('parse', function () {
            document.location.href = 'https://www.springboardamerica.com/profile/basic';
        });
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        provider.info("Retrieving balance");
        var data = {};
        // Name
        var firstname = $('#profile_basic_firstname');
        var surname = $('#profile_basic_surname');
        if (firstname.length > 0 && surname.length > 0) {
            data.Name = util.beautifulName(firstname.attr('value') + ' ' +  surname.attr('value'));
            browserAPI.log("Name: " + data.Name);
        } else
            browserAPI.log("Name are not found");

        params.data.properties = data;
        provider.saveTemp(params.data);

        provider.setNextStep('parseBalance', function () {
            provider.eval("var windowOpen = window.open; window.open = function(url){windowOpen(url, '_self');}");
            document.location.href = 'https://www.springboardamerica.com/points/incentives/account/PAC-vlt1-1';
        });
    },

    parseBalance: function (params) {
        browserAPI.log("parseBalance");
        // Balance
        var balance = $('p:contains("Rewards Balance") > span');
        if (balance.length == 1) {
            balance = util.findRegExp(balance.text(), /([\d\.\,\-\$]+)/i);
            browserAPI.log("Balance: " + balance);
            params.data.properties.Balance = balance;
        } else
            browserAPI.log("Balance is not found");

        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);
        provider.complete()
    }
};