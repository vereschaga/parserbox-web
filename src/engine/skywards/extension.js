var plugin = {

    // keepTabOpen: true,//todo
    hideOnStart: true,
    hosts: {
        'www.emirates.com': true,
        'mobile.emirates.com': true,
        '/fly\\d+\\.emirates\\.com/': true,
        'accounts.emirates.com': true
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function(){
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
	    if (provider.isMobile)
            return 'https://www.emirates.com/account/english/login/login.aspx?stop_mobi=yes';
        return 'https://www.emirates.com/account/english/manage-account/manage-account.aspx?stop_mobi=yes';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
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
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('section.login-form__container > form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[data-link *= "Logout"]').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = $('div.userWelcome div.membershipNumber').eq(0).text();
        if (!number)
            number = $('span.memebership-id').text();
        number = util.findRegExp(number, /^\s*([\w\s]+)/);
        if (number) {
            number = number.replace(/\s+/g, '').trim();
        }
        browserAPI.log("number: " + number);
        return (
            (typeof(account.properties) != 'undefined') &&
            (typeof(account.properties.SkywardsNo) != 'undefined') &&
            (account.properties.SkywardsNo != '') &&
            (number == account.properties.SkywardsNo.replace(/[^A-Z\d]+/ig, ''))
        );
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.emirates.com/account/system/aspx/logout.aspx';
        });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form#aspnetForm');
        if (form.length > 0) {
            form.find('input#ctl00_c_ctrlRB_txtPNR').val(properties.ConfNo);
            form.find('input#ctl00_c_ctrlRB_txtLastName').val(properties.LastName);
            provider.setNextStep('itLoginComplete', function() {
                form.find('input#ctl00_c_ctrlRB_ibtRtrvBtn').click();
            });
        }
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    login: function (params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) === "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId === 0   ) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://www.emirates.com/SessionHandler.aspx?pageurl=/MYB.aspx&pub=/english&section=MYB&j=f';
            });
            return;
        }

        const form = $('section.login-form__container > form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // form.find('input[name = "username"]').val(params.account.login);
        // form.find('input[id = "sso-password"]').val(params.account.password);
        // reactjs
        provider.eval(
            "var FindReact = function (dom) {" +
            "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
            "        return dom[key];" +
            "    }" +
            "    return null;" +
            "};" +
            "FindReact(document.querySelector('input[id = \"sso-email\"]')).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});" +
            "FindReact(document.querySelector('input[id = \"sso-password\"]')).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
        );
        provider.setNextStep('checkLoginErrors', function(){
            /*
            setTimeout(function(){
                var captcha = form.find('#c_english_login_login_maincontent_ctl00_botdetectcaptcha_CaptchaImage');
                if (captcha.length > 0) {
                    browserAPI.log("waiting...");
                    if (provider.isMobile) {
                        provider.reCaptchaMessage();
                        form.find('#btnLogin_LoginWidget').click(function(){
                            provider.checkLoginErrors(params);
                        });
                    }
                    else {
                        provider.captchaMessageDesktop();
                        plugin.saveImage(captcha, form);
                    }
                }
                else {
                    */
                    // browserAPI.log("captcha is not found");
                    // form.find('#login-button').click();
                    provider.eval("document.querySelector('#login-button').click();");
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 10000)
                    /*
                }
            }, 2000)
            */
        });
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
                form.find('#CaptchaCodeTextBox').val(response.recognized);
                form.find('#btnLogin_LoginWidget').click();
            }// if (response.success === true))
            if (response.success === false) {
                console.log("Success: " + response.success);
                provider.setError(util.errorMessages.captchaErrorMessage, true);
            }// if (response.success === false)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        var errors = $('.login-error:visible');
        if (errors.length > 0) {
            if (errors.text().indexOf('Please complete an audio or visual verification method to continue') !== -1) {
                provider.setError(util.errorMessages.captchaErrorMessage, true);
            }
            if (
                errors.text().indexOf('Sorry, the email address, Emirates Skywards number or password you entered is incorrect') !== -1
                || errors.text().indexOf('Membership Number or Password you entered is incorrect') !== -1
                || errors.text().indexOf('Sorry, the email address, Emirates Skywards number or password you entered is incorrect') !== -1
                || errors.text().indexOf('Sorry, the email address, Emirates Skywards number, or password you entered is incorrect. Please check and try again') !== -1
                || errors.text().indexOf('Sorry, there are multiple accounts active for this email address. If you\'re an Emirates Skywards') !== -1
            ) {
                provider.setError(errors.text(), true);
                return;
            }
            if (
                errors.text().indexOf('Your account has been proactively locked as a security precaution.') !== -1
                || errors.text().indexOf('Sorry, your account has been locked. If you need urgent access to your account, please talk to our representatives ') !== -1
                || errors.text().indexOf('Your account has been locked as a security precaution. To regain access to your account') !== -1
            ) {
                provider.setError([errors.text(), util.errorCodes.lockout], true);
                return;
            }
            if (
                errors.text().indexOf('Sorry, we encountered a problem when submitting this request') !== -1
                || errors.text().indexOf('Sorry, this account isn\'t accessible at the moment due to a routine review.') !== -1
                || errors.text().indexOf('Sorry there\'s a problem with our system and we\'re temporarily unable to log you in to your account.') !== -1
                || errors.text().indexOf('Sorry, this account has been deactivated.') !== -1
                || errors.text().indexOf('Sorry, this account has been cancelled.') !== -1
                || errors.text().indexOf('Sorry, this account has been canceled.') !== -1
                || errors.text().indexOf('Sorry, your account is temporarily unavailable') !== -1
                || errors.text().indexOf('Sorry, The Skywards account is not available for use') !== -1
                || errors.text().indexOf('Sorry, a Skysurfers member is not eligible to log in to book online') !== -1
                || errors.text().indexOf('Sorry, a Skysurfers member is unable to log in to emirates.com.') !== -1
                || errors.text().indexOf('Sorry, Skysurfers are not eligible to log in to emirates.com.') !== -1
                || errors.text().indexOf('Sorry, the skywards login functionality is currently unavailable') !== -1
                || errors.text().indexOf('Sorry, some information is missing from your account.') !== -1
                || errors.text().indexOf('Sorry, this membership number belongs to a merged account.') !== -1
                || errors.text().indexOf('Sorry, your account is not accessible at the moment due to a routine review') !== -1
                || errors.text().indexOf('Sorry, we have a technical problem at the moment. Please try again later.') !== -1
                || errors.text().indexOf('Sorry, a Skysurfers member is unable to log in to emirates.com') !== -1
                || errors.text().indexOf('Sorry, we encountered a problem when submitting this request.') !== -1
                || errors.text().indexOf('The email address that you are using for your account is also linked to another Emirates Skywards member') !== -1
                || errors.text().indexOf('Sorry, we\'ve encountered a problem. Please try again using your Emirates Skywards number or call an Emirates Contact Centre for assistance.') !== -1
                || errors.text().indexOf('Sorry, this account isn\'t accessible at the moment due to a routine review.') !== -1
            ) {
                provider.setError([errors.text(), util.errorCodes.providerError], true);
                return;
            }

            browserAPI.log('[Errors]: ' + errors.text());
            // provider.setError(errors.text(), true);
            provider.complete();
            return;
        }
        if ($('p:contains("An email with a 6-digit passcode has been sent to"):visible, p:contains("Please choose how you want to receive your passcode."):visible').length) {
            if (params.autologin)
                provider.setError(['It seems that Emirates needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your Emirates authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
            else {
                if (provider.isMobile) {
                    provider.setNextStep('loadAccount', function () {
                        provider.command('show', function () {
                            provider.showFader('It seems that Emirates needs to identify you before you can update this account.');/*review*/
                        });
                    });
                    return;
                }
                provider.setError(['It seems that Emirates needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Emirates authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
            }
            return;
        }
        plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log('loadAccount');
        // browserAPI.log("params.account: " + JSON.stringify(params.account));
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://emirates.com/account/SessionHandler.aspx?pageurl=/MYB.aspx&pub=/english&section=MYB&j=f';
            });
            return;
        }
        if (params.autologin) {
            // provider bug fix
            provider.setNextStep('itLoginComplete', function () {
                if (provider.isMobile)
                    document.location.href = 'https://mobile.emirates.com/english/myaccountDetails.xhtml?loginPageIdentifier=MY_ACCOUNT_DETAILS';
                else
                    document.location.href = plugin.getStartingUrl(params);
            });
            provider.complete();
            return;
        }
        if (provider.isMobile) {
            provider.command('hide', function () {
            });
        }
        var url = 'https://www.emirates.com/account/english/manage-account/manage-account.aspx';
        if (document.location.href !== url) {
            provider.setNextStep('parse', function () {
                document.location.href = 'https://www.emirates.com/account/english/manage-account/manage-account.aspx?stop_mobi=yes';
            });
        }// if (document.location.href != url)
        else
            plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log('parse');
        provider.updateAccountMessage();
        var data = {};
        // Balance - Skywards Miles
        var balance = $('div.membershipSkywardsMiles div.milesCount:eq(0)');
        if (balance.length > 0) {
            balance = util.findRegExp( balance.text(), /([\d\.\,\-]+)/i);
            browserAPI.log("Balance: " + balance );
            data.Balance = balance;
        }
        else
            browserAPI.log("Balance not found");
        // Account Number
        var number =  $('div.userWelcome div.membershipNumber span.membershipNumber:eq(0)');
        if (number.length > 0) {
            data.SkywardsNo = util.filter(util.findRegExp( number.text(), /([^\|]+)/i));
            browserAPI.log("Account #: " + data.SkywardsNo );
        }
        else
            browserAPI.log("Account # not found");
        // Name
        var name = $('div.userWelcome div.membershipName:eq(0)');
        if (name.length > 0) {
            data.Name = util.beautifulName(util.findRegExp( name.html(), /([^<]+)/));
            browserAPI.log("Name: " + data.Name );
        }
        else
            browserAPI.log("Name not found");
        // Tier
        var tier = $('#loginControl_spnMemberTier');
        if (tier.length > 0) {
            data.CurrentTier = util.filter(tier.text());
            browserAPI.log("Tier: " + data.CurrentTier );
        }
        // Tier Miles
        var tierMiles = $('span[id *= "_lblSkywardsTierMiles"]');
        if (tierMiles.length > 0) {
            data.TierMiles = util.filter(tierMiles.text());
            browserAPI.log("Tier Miles: " + data.TierMiles );
        }
        else
            browserAPI.log("Tier Miles not found");
        // Skywards Miles Expiring
        var date = util.findRegExp($('span#loginControl_spnExpiryMiles').text(), /expire\s*on\s*([^<]+)/);
        var quantity = util.findRegExp($('span#loginControl_spnExpiryMiles').text(), /([\d\.\,\s]+)\s+mile/);
        browserAPI.log("Date: " + date +" / " + quantity);
        if (quantity && quantity != '') {
            // Miles to Expire
            browserAPI.log("Miles to Expire: " + quantity);
            data.MilesToExpire = quantity;
            var exp = new Date(date + ' UTC');
            var unixtime = exp / 1000;
            if ( exp != 'NaN' && !isNaN(unixtime) ) {
                browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime );
                data.AccountExpirationDate = unixtime;
            }
        }// if (quantity && quantity != '')
        else
            browserAPI.log("Skywards Miles Expiring not found");

        params.data.properties = data;
        // console.log(params.account.properties);//todo
        provider.saveTemp(params.data);

        // "My Account" > "Skywards Skysurfer"
        provider.setNextStep('skywardsSkysurfer', function () {
            document.location.href = "https://www.emirates.com/account/english/manage-account/skywards-skysurfers.aspx";
        });
    },

    skywardsSkysurfer: function (params) {
        browserAPI.log('skywardsSkysurfer');
        provider.updateAccountMessage();
        var subAccounts = [];
        var skysurferMembers = $('div#MainContent_ctl00_linkedSkysurferMembers div.sky-surfers-user-box-container');
        browserAPI.log('Total ' + skysurferMembers.length + ' vouchers were found');
        skysurferMembers.each(function () {
            var name = util.trim($('h3.skysurfer-name', $(this)).text());
            // Account Number
            var skywardsNo = util.trim($('span[class *= "skywards-num"]', $(this)).text());
            var displayName = "Skywards Skysurfer: " + name + " (" + skywardsNo + ")";
            var balance = $('span:contains("Skywards Miles") + span[class *= "skywards-miles-earned"]', $(this)).text();
            var sub = {
                "Code": 'skywardsSkysurfer' + skywardsNo.replace(/\s+/g, ''),
                "DisplayName": displayName,
                "Balance": balance,
                // Name
                'Name': name,
                // Account Number
                'SkywardsNo': skywardsNo,
                // Tier
                'CurrentTier': util.trim($('span#skysurfer-tier', $(this)).text()),
                // Tier Miles
                'TierMiles': $('span:contains("Tier Miles") + span[class *= "skywards-miles-earned"]', $(this)).text(),
                // ... Skywards Miles are due to expire on ...
                'MilesToExpire': $('span:contains("expire on")', $(this)).prev('span:eq(0)').text()
            };
            var exp = util.trim($('span:contains("expire on") + span', $(this)).text());
            exp = new Date(exp + ' UTC');
            var unixtime =  exp / 1000;
            if (!isNaN(exp) && name)
                sub.ExpirationDate = unixtime;
            if (name)
                subAccounts.push(sub);
        });

        params.data.properties.SubAccounts = subAccounts;
        params.data.properties.CombineSubAccounts = 'false';
        params.data.BusinessSubAcc = false;
        provider.saveTemp(params.data);
        // console.log(params.account.properties);//todo
        // browserAPI.log('>> ' + JSON.stringify(params.account.properties));

        if ($('#loginControl_linkBusinessRewards[aria-label != ""]').length > 0) {
            provider.setNextStep('parseBusiness', function () {
                document.location.href = "https://www.emirates.com/account/english/business-rewards/";
            });
        }// if ($('#loginControl_linkBusinessRewards[aria-label != ""]').length > 0)
        else {
            plugin.parsingPropertiesComplete(params);
        }
    },

    parseBusiness: function (params) {
        browserAPI.log('parseBusiness');
        provider.updateAccountMessage();
        setTimeout(function () {
            var date = $('input#hdnExpireDate').attr('value');
            var quantity = $('input#hdnExpirePoint').attr('value');
            browserAPI.log("Exp date: " + date +" / " + quantity);
            var properties = {
                // Emirates Business Rewards
                'SkywardsNo': util.filter($('span.profile-links__code').text()),
                // Organisation name
                'OrganisationName': util.filter($('a.company').text()),
                // Name
                'Name': util.filter($('div.name-container div.name').text()),
                // Expiring balance
                'MilesToExpire': quantity,
                // Balance - Points balance
                'Balance': util.filter($('p#points-balance').text()),
                // You have saved ... since ...
                'Saved': util.filter($('p:contains("You have saved") > strong').text())
            };
            if (date && quantity && quantity > 0) {
                properties.MilesToExpire = quantity;
                var exp = new Date(date + ' UTC');
                var unixtime = exp / 1000;
                if ( exp != 'NaN' && !isNaN(unixtime) ) {
                    browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime );
                    properties.ExpirationDate = unixtime;
                }
            }// if (date && quantity && quantity > 0)

            params.data.Business = properties;
            params.data.BusinessSubAcc = true;
            provider.saveTemp(params.data);

            provider.setNextStep('parseBusinessInfo', function () {
                document.location.href = "https://www.emirates.com/account/english/business-rewards/account.aspx";
            });
        }, 1000)
    },

    parseBusinessInfo: function (params) {
        browserAPI.log('parseBusinessInfo');
        provider.updateAccountMessage();
        setTimeout(function () {
            // Organisation name
            params.data.Business.OrganisationName = util.filter($('a.company').text());
            // Trade licence number
            params.data.Business.TradeLicenceNumber = util.filter($('div#orgTradeLicense div.card-description').text());

            if (params.data.BusinessSubAcc) {
                params.data.Business.Code = 'skywardsBusinessRewards' + params.data.Business.SkywardsNo;
                params.data.Business.DisplayName = 'Business Rewards';
                params.data.properties.SubAccounts.push(params.data.Business);
            }// if (params.data.properties.BusinessSubAcc)
            else {
                for (var prop in params.data.Business)
                    params.data.properties.prop = params.data.Business[prop];
            }
            provider.saveTemp(params.data);
            plugin.parsingPropertiesComplete(params);
        }, 1000)
    },

    parsingPropertiesComplete: function (params) {
        browserAPI.log("parsingPropertiesComplete");
        params.account.properties = params.data.properties;
        // console.log(params.account.properties);//todo
        // browserAPI.log('>> ' + JSON.stringify(params.account.properties));
        provider.saveProperties(params.account.properties);

        if (typeof (params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
            return provider.setNextStep('beforeParseItineraries', function () {
                document.location.href = 'https://www.emirates.com/SessionHandler.aspx?pageurl=/MYB.aspx&pub=/english&section=MYB&j=f';
            });
        }

        provider.complete();
    },

    parseItineraries: function (params) {
        browserAPI.log('parseItineraries');
        provider.logBody("parseItineraries");
        provider.updateAccountMessage();
        // no Itineraries
        if ($('p:contains("You have no upcoming trips."):visible').length) {
            params.account.properties = params.data.properties;
            params.account.properties.Itineraries = [{ NoItineraries: true }];
            // console.log(params.account.properties);todo
            provider.saveProperties(params.account.properties);
            provider.complete();
            return;
        }

        provider.complete();
    },

    beforeParseItineraries: function (params) {
        browserAPI.log('beforeParseItineraries');
        provider.setNextStep('parseItineraries', function () {
            if (document.location.href.indexOf('MMBLogin.aspx') !== -1) {
                browserAPI.log('force call parseItineraries');
                plugin.parseItineraries(params);
            }
        });
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        provider.setNextStep('toItinerary', function () {
        });
    },

    toItinerary: function (params) {
        browserAPI.log('toItinerary');
        var counter = 0;
        var confNo = params.account.properties.confirmationNumber;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var manage = $('a[onClick *= "' + confNo + '"]');
            if (manage.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    manage.get(0).click();
                });
            }// if (manage.length > 0)
            if (counter > 30) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    itLoginComplete: function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    }

};
