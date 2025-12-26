<?php

//function CheckBalanceGoldpoints( $sLogin, $sPassword, &$nBalance, &$nErrorCode, &$sErrorMessage, $IsRedirect, &$arProperties)
//{
//	$nErrorCode = ACCOUNT_INVALID_PASSWORD;
//	$sErrorMessage = "The Gold Points Reward Network program ended and all program operations ceased on December 31, 2008.
//Please remove this program from your profile.";
//	return;
//
//  $http=new TCurlBrowser();
//  //$http->debug = true;
//
//  $arg = array(
//    "URL"=>"https://www.goldpoints.com/site/login/Login.jsp",
//        "RequestMethod"=>"POST",
//        "PostValues"=>array(
//                "action"=>"LoginCustomerAction",
//                "destination_url"=>"/common/Home.jsp",
//                "login"=>"clickedonlogin",
//                "Fuserid"=>$sLogin,
//                "Fpassword"=>$sPassword
//        )
//  );
//
//  if (isset($IsRedirect) and $IsRedirect)
// 	 return $arg;
//
//  $http->open( $arg );
//
//  if( preg_match("/The\s+user\s+name\s+and\s+password\s+combination\s+you\s+entered\s+is\s+invalid\./ims", $http->body ) ) {
//    $nErrorCode = ACCOUNT_INVALID_PASSWORD;
//    $sErrorMessage = "An incorrect username or password.";
//    return;
//  }
//
//
//  if(preg_match("/Your\s+current\s+point\s+balance\s+is\:[^\d]+(\d+[\.\,]?\d*)[^\d\<]+/ims", $http->body, $match) ) {
//        //var_dump( $match[1] );
//          $nBalance = $match[1];
//          $nErrorCode = ACCOUNT_CHECKED;
//          return;
//  }
//  return;
//}
