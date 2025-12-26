<?php

///*---------------------------------------------------------------------------*/
//function CheckBalanceMidwest( $sLogin, $sPassword, &$nBalance, &$nErrorCode, &$sErrorMessage, $IsRedirect, &$arProperties)
//{
//  global $post_data;
//  $http=new TCurlBrowser();
//  //$http->debug=true;
//
//    $nErrorCode = ACCOUNT_PROVIDER_ERROR;
//    $sErrorMessage = "Midwest Airlines is now Frontier Airlines. The new address is www.frontierairlines.com.";
//    return;
//
//  $http->redirect = true;
//
//  $arg = array(
//        "URL"=>"http://www.midwestairlines.com/Default.aspx",
//        "RequestMethod"=>"GET"
//  );
//  $http->open( $arg );
//
//  $ff = get_form_field( $http->body, "_default" );
//  $arg = array(
//    "URL"=>"https://www.midwestairlines.com/SignIn.aspx?mode=",
//    "RequestMethod"=>"POST",
//    "PostValues"=>array(
//      "__EVENTTARGET" => "",
//      "__EVENTARGUMENT" => "",
//      "__VIEWSTATE" => $ff[ 0 ][ "__VIEWSTATE" ],
//      urldecode("ctl00%24ctl00%24ContentMainContainer%24ContentColumn1%24_logOnControl%24_userName") => $sLogin,
//      urldecode("ctl00%24ctl00%24ContentMainContainer%24ContentColumn1%24_logOnControl%24_password") => $sPassword,
//      urldecode("ctl00%24ctl00%24ContentMainContainer%24ContentColumn1%24_logOnControl%24_logOnButton") => "Sign In",
//      "_PREVIOUSPAGE" => $ff[ 0 ][ "_PREVIOUSPAGE" ]
//    )
//  );
//
//  if (isset($IsRedirect) and $IsRedirect)
// 	 return $arg;
//  $http->open( $arg );
//
//  if(preg_match("/<span id=\"ctl00_ctl00_ContentMainContainer_ContentColumn1__logOnControl__topCustomValidator\" style=\"color:Red;\">([^<]+)<\/span>/ims", $http->body, $match) ) {
//    $nErrorCode = ACCOUNT_INVALID_PASSWORD;
//    $sErrorMessage = $match[1];
//    return;
//  }
//
//  if(preg_match("/Your mileage balance is currently unavailable/ims", $http->body, $match) ) {
//    $nErrorCode = ACCOUNT_PROVIDER_ERROR;
//    $sErrorMessage = "Midwest Airlines website returned the following error: \"Your mileage balance is currently unavailable\"";
//    return;
//  }
//
//  //Click forgot password below.
//  if( preg_match( "/Click\s+forgot\s+password\s+below/i", $http->body, $match ) ) {
//    $nErrorCode = ACCOUNT_INVALID_PASSWORD;
//    $sErrorMessage = "Account number and/or password are incorrect.";
//    return;
//  }
//
//  //Your mileage balance is 0
//  if(preg_match("/<span id=\"ctl00_ctl00_ContentMainContainer_ContentColumn1__accountBalance\">([^<]+)<\/span>/ims", $http->body, $match) ) {
//    $nBalance = $match[1];
//    $nErrorCode = ACCOUNT_CHECKED;
//  }
//
//  $arg = array(
//        "URL"=>"https://www.midwestairlines.com/MidwestMiles/AccountActivity.aspx",
//        "RequestMethod"=>"GET"
//  );
//  $http->open( $arg );
//  if( preg_match( "/<span id=\"ctl00_ctl00_ContentMainContainer_ContentColumn2__userNameLabel\">([^<]+)<\/span>
///ims", $http->body, $arMatches ) )
//  	$arProperties["Name"] = $arMatches[1];
//  if( preg_match( "/span id=\"ctl00_ctl00_ContentMainContainer_ContentColumn2__milesNumberLabel\">([^<]+)<\/span>
///ims", $http->body, $arMatches ) )
//  	$arProperties["Number"] = $arMatches[1];
//  if( preg_match( "/span id=\"ctl00_ctl00_ContentMainContainer_ContentColumn2__memberDateLabel\">([^<]*)<\/span>
///ims", $http->body, $arMatches ) )
//  	$arProperties["MemberSince"] = $arMatches[1];
//  if( preg_match( "/span id=\"ctl00_ctl00_ContentMainContainer_ContentColumn2__currentBalanceLabel\">([^<]+)<\/span>
///ims", $http->body, $arMatches ) )
//  	$arProperties["CurrentBalance"] = $arMatches[1];
//  if( preg_match( "/span id=\"ctl00_ctl00_ContentMainContainer_ContentColumn2__oneWayYearToDate\">([^<]+)<\/span>
///ims", $http->body, $arMatches ) )
//  	$arProperties["YTDOneWayTrips"] = $arMatches[1];
//  if( preg_match( "/span id=\"ctl00_ctl00_ContentMainContainer_ContentColumn2__flightMilesYearToDate\">([^<]+)<\/span>
///ims", $http->body, $arMatches ) )
//  	$arProperties["YTDFlightMiles"] = $arMatches[1];
//  // activity
//  $xpath=new DOMXPath(TidyDoc($http->body));
//  $nodes = $xpath->query("//table[@id = 'ctl00_ctl00_ContentMainContainer_ContentColumn2__activityGridView']/tr/td[1]");
//  for($n = 0; $n < $nodes->length; $n++){
//  	$s = CleanXMLValue($nodes->item($n)->nodeValue);
//  	$d = strtotime($s);
//  	if(($d !== false) && (!isset($dMaxDate) || ($dMaxDate < $d)))
//  		$dMaxDate = $d;
//  }
//  if(isset($dMaxDate)){
//  	$arProperties["LastActivity"] = date(DATE_FORMAT, $d);
//  	$arProperties["AccountExpirationDate"] = strtotime("+3 year", $d);
//  }
//  else
//	if(preg_match("/No recent activity was located/ims", $http->body))
//		$arProperties["LastActivity"] = "&nbsp;";
//  return $nErrorCode;
//}
