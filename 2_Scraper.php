<?php

	define("PPUSERNAME","y1sin");
	define("PPPASSWORD","ufji9db3");
	define("PPPROXYURL","169.197.83.75:7336");

	require_once('./db.php');

	$db = db_connection();

	$HTMLFiles 				  = "HTMLFiles";
	$ListingPageFolder  = "$HTMLFiles/ListingPages";
	
	$CookieFolder = "Cookie";
	
	if(!is_dir($HTMLFiles))											{ 	mkdir($HTMLFiles);									}
	if(!is_dir($ListingPageFolder))							{ 	mkdir($ListingPageFolder);					}
	if(!is_dir($CookieFolder))									{ 	mkdir($CookieFolder);								}

	$cookie_file = "$CookieFolder/Cookie.txt";

	if(isset($argv[1]) && $argv[1] == 'privateproxy'){
		$is_proxy = 'privateproxy';
		change_privateproxy();
	}

	$Select = "SELECT Category1, Category2, Category2URL FROM tbl_category1 WHERE Extracted = 0";

	if($dbRecords = $db->query($Select)){
		
		if($dbRecords->num_rows > 0){
			
			while($dbRows = $dbRecords->fetch_object()){

				$Category1 		= $dbRows->Category1;
				$Category2 		= $dbRows->Category2;
				$Category2URL = $dbRows->Category2URL;
				
				$category1save = trim(str_replace(array('/'),array(''),$Category1));
				$category2save = trim(str_replace(array('/'),array(''),$Category2));
				
				$FileName = "$ListingPageFolder/{$category1save}_{$category2save}.html";
				
				writeToLog("Category1: $Category1; Category2: $Category2...");
				
				if(is_file($FileName)){
					writeToLog("File Exists...");
					$homepagehtml = file_get_contents($FileName);
				}else{
					$homepagehtml = listing_homepage_download($Category2URL,$FileName);
				}
				
				ListingParsing($homepagehtml,$Category1,$Category2,$Category2URL);
				
				$update = "UPDATE tbl_category1 SET Extracted = 1 WHERE Category1 = '$Category1' AND Category2 = '$Category2' AND Category2URL = '$Category2URL'";
				
				if(!$db->query($update)){
					writeToLog($db->error.PHP_EOL);
				}else{
					writeToLog("...Done".PHP_EOL);
				}
				
			}
		}
	}

	function listing_homepage_download($Category2URL,$FileName){
	
		global $db,$proxy,$is_proxy,$cookie_file,$ppProxyPassword,$Category1Folder;

		$ch = curl_init();
		
		curl_setopt($ch, 	CURLOPT_URL,$Category2URL);
		curl_setopt($ch,	CURLOPT_FOLLOWLOCATION,	1);
		curl_setopt($ch, 	CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, 	CURLOPT_SSL_VERIFYHOST, false);
		
		if($is_proxy == 'privateproxy'){
			curl_setopt($ch,  CURLOPT_PROXY, "$proxy");				
			curl_setopt($ch,  CURLOPT_PROXYUSERPWD, $ppProxyPassword);
		}
		
		curl_setopt($ch,	CURLOPT_RETURNTRANSFER,	1);
		curl_setopt($ch,	CURLOPT_BINARYTRANSFER,	1);			
		curl_setopt($ch,	CURLOPT_ENCODING,	'gzip');
		curl_setopt($ch, 	CURLOPT_CONNECTTIMEOUT, 20); 
		curl_setopt($ch, 	CURLOPT_TIMEOUT, 20);
		curl_setopt($ch,	CURLOPT_COOKIEJAR,"./{$cookie_file}");
		curl_setopt($ch,	CURLOPT_COOKIEFILE,"./{$cookie_file}");
		curl_setopt($ch,	CURLOPT_HEADER,0);
		curl_setopt($ch,	CURLOPT_HTTPHEADER, array(

			'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
			'Accept-Encoding: gzip, deflate, br',
			'Accept-Language: en-US,en;q=0.9',
			'Cache-Control: no-cache',
			'Pragma: no-cache',
			'Sec-Ch-Ua: "Google Chrome";v="113", "Chromium";v="113", "Not-A.Brand";v="24"',
			'Sec-Ch-Ua-Mobile: ?0',
			'Sec-Ch-Ua-Platform: "Windows"',
			'Sec-Fetch-Dest: document',
			'Sec-Fetch-Mode: navigate',
			'Sec-Fetch-Site: none',
			'Sec-Fetch-User: ?1',
			'Upgrade-Insecure-Requests: 1',
			'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36'
			
		));
		$result			=	curl_exec($ch);								
		$httpCode		=	curl_getinfo($ch,	CURLINFO_HTTP_CODE);

		writeToLog("Home Page http: $httpCode...");
		
		if($httpCode	==	200){
			
			if( (stripos($result,'/distil_r_captcha.html') !== false) || (stripos($result,'<div id="px-captcha">') !== false) || (stripos($result,'Access to this page has been denied') !== false) || (stripos($result,'http://auth.contentkeeper.com/login.html') !== false)||(stripos($result,'<title>Welcome To Zscaler Directory Authentication</title>') !== false)){
				writeToLog('Captch found ');
			
				if($is_proxy == 'privateproxy'){
					change_privateproxy();
				}
				
				return listing_homepage_download($Category2URL,$FileName);		
			}
			else{
				file_put_contents($FileName,$result);			
				return $result;				
			}
			
		}else{

			if ($is_proxy == 'privateproxy'){
				change_privateproxy();
			}
			
			return listing_homepage_download($Category2URL,$FileName);			
		}
	}
	
	function ListingParsing($homepagehtml,$Category1,$Category2,$Category2URL){
		
		global $db;
		
		if(preg_match('#<div id="kipaging"></div><script>(.*?)</script>#is',$homepagehtml,$main)){
		
			if(isset($main[1])){
				
				preg_match_all('#arrObjects.push\(new pObj\((.*?)\)#is',$main[1],$data);
				print_r($data[1]);
				exit;
				$counter = 0;
				
				for($i=0; $i<count($data[1]); $i++){
					
					preg_match_all('#"(.*?)"#is',$data[1][$i],$details);
					
					$ProductName  =  "";
					$ProductURL   =  "";
					
					if(isset($details[1][2]))		{		$ProductName  =  trim($db->real_escape_string(html_entity_decode(strip_tags($details[1][2]))));		}
					if(isset($details[1][3]))		{		$ProductURL   =  "https://www.allvetsupply.com/".trim($details[1][3]);														}
					
					$db->query("SET NAMES UTF8");
					
					if($ProductURL != ''){
						
						$Insert = "INSERT IGNORE INTO tbl_product_listing(Category1, Category2, ProductName, ProductURL)VALUES('$Category1','$Category2','$ProductName','$ProductURL')";
					
						if(!$db->query($Insert)){
							writeToLog($db->error.PHP_EOL);
						}else{
							$counter++;
						}
						
					}
				}
				
				writeToLog("$counter Records Insert in tbl_product_listing");
				
			}
		}else if(preg_match('#<div class="sec-items">(.*?)<div class="footer">#is',$homepagehtml,$main)){
			
			preg_match_all('#<a href="(.*?)" title="(.*?)">(.*?)</a>#is',$main[1],$details);
			
			$counter = 0;
			
			for($j=0; $j<count($details[1]); $j++){
				
				$Category3URL  = "";
				$Category3  	 = "";
				
				if(isset($details[1][$j]))		{		$Category3URL  =  "https://www.allvetsupply.com/".trim($details[1][$j]);		}
				if(isset($details[3][$j]))		{		$Category3  	 =  trim($db->real_escape_string(html_entity_decode(strip_tags($details[3][$j]))));		}
				
				
				if($Category3 != ''){
					
					$Insert2 = "INSERT IGNORE INTO tbl_category2(Category1, Category2, Category3, Category3URL)VALUES('$Category1','$Category2','$Category3','$Category3URL')";
				
					if(!$db->query($Insert2)){
						writeToLog($db->error.PHP_EOL);
					}else{
						$counter++;
					}
					
				}
			}
			writeToLog("$counter Records Insert in tbl_category2");
		}
	}

?>