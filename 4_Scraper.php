<?php

	define("PPUSERNAME","y1sin");
	define("PPPASSWORD","ufji9db3");
	define("PPPROXYURL","169.197.83.75:7336");

	require_once('./db.php');

	$db = db_connection();

	$HTMLFiles 				  = "HTMLFiles";
	$DetailPageFolder 		= "$HTMLFiles/DetailPage";
	
	$CookieFolder = "Cookie";
	
	if(!is_dir($HTMLFiles))											{ 	mkdir($HTMLFiles);								}
	if(!is_dir($DetailPageFolder))							{ 	mkdir($DetailPageFolder);					}
	
	if(!is_dir($CookieFolder))									{ 	mkdir($CookieFolder);							}

	if(isset($argv[3]) && $argv[3] == 'privateproxy'){
		$is_proxy = 'privateproxy';
		change_privateproxy();
	}
	
	if((! isset($argv[1])  && ! (isset($argv[1]) && is_numeric($argv[1]))) || (! isset($argv[2])  && ! (isset($argv[2]) && is_numeric($argv[2]))))	{
		writeToLog( "LIMIT  not set");
		exit();
	}
	$skip			=	(int)$argv[1];
	$select		=	(int)$argv[2];
	
	$cookie_file = "$CookieFolder/{$skip}_{$select}_Cookie.txt";

	$Select = "SELECT Category1, Category2, Category3, ProductName, ProductURL FROM tbl_product_listing WHERE Extracted = 1 LIMIT $skip,$select";

	if($dbRecords = $db->query($Select)){
		
		if($dbRecords->num_rows > 0){
			
			while($dbRows = $dbRecords->fetch_object()){

				$Category1 		= $dbRows->Category1;
				$Category2 		= $dbRows->Category2;
				$Category3 		= $dbRows->Category3;
				$ProductName 	= $dbRows->ProductName;
				$ProductURL 	= $dbRows->ProductURL;
				
				$category1save 	 = trim(str_replace(array('/'),array('-'),$Category1));
				$category2save 	 = trim(str_replace(array('/'),array('-'),$Category2));
				$category3save 	 = trim(str_replace(array('/'),array('-'),$Category3));
				$productnamesave = trim(str_replace(array('https://www.allvetsupply.com/','.html'),array('',''),$ProductURL));
				
				writeToLog("Category1: $Category1; Category2: $Category2; Category3: $Category3; ProductName: $ProductName...");
				
				$DetailPageFileName = "$DetailPageFolder/{$category1save}_{$category2save}_{$category3save}_{$productnamesave}.html";

				if(is_file($DetailPageFileName)){
					writeToLog("Detail Page File Exists...");
					$detailpagehtml = file_get_contents($DetailPageFileName);
				}else{
					$detailpagehtml = detailpage_download($ProductURL,$DetailPageFileName);
				}
				
				DetailPageParse($detailpagehtml,$Category1,$Category2,$Category3,$ProductName,$ProductURL);
				
				$update = "UPDATE tbl_product_listing SET Extracted = 1 WHERE Category1 = '$Category1' AND Category2 = '$Category2' AND Category3 = '$Category3' AND ProductURL = '$ProductURL'";
				
				if(!$db->query($update)){
					writeToLog($db->error.PHP_EOL);
				}else{
					writeToLog("...Done".PHP_EOL);
				}
				
			}
		}
	}

	function detailpage_download($ProductURL,$DetailPageFileName){
	
		global $db,$proxy,$is_proxy,$cookie_file,$ppProxyPassword,$Category1Folder,$userAgent;
	
		$ch = curl_init();
		
		curl_setopt($ch, 	CURLOPT_URL,$ProductURL);
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
	
		'Host: www.allvetsupply.com',
		'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/113.0',
		'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
		'Accept-Language: en-US,en;q=0.5',
		'Accept-Encoding: gzip, deflate, br',
		'DNT: 1',
		'Connection: keep-alive',
		'Upgrade-Insecure-Requests: 1',
		'Sec-Fetch-Dest: document',
		'Sec-Fetch-Mode: navigate',
		'Sec-Fetch-Site: none',
		'Sec-Fetch-User: ?1',
		'Pragma: no-cache',
		'Cache-Control: no-cache'

		));
		$result			=	curl_exec($ch);								
		$httpCode		=	curl_getinfo($ch,	CURLINFO_HTTP_CODE);
		
		writeToLog("Home Page http: $httpCode...");
		
		if( ($httpCode	==	200) && ($result != '') ){
			
			if( (stripos($result,'/distil_r_captcha.html') !== false) || (stripos($result,'<div id="px-captcha">') !== false) || (stripos($result,'Access to this page has been denied') !== false) || (stripos($result,'http://auth.contentkeeper.com/login.html') !== false)||(stripos($result,'<title>Welcome To Zscaler Directory Authentication</title>') !== false)){
				writeToLog('Captch found ');
			
				if($is_proxy == 'privateproxy'){
					change_privateproxy();
				}
				
				return detailpage_download($ProductURL,$DetailPageFileName);		
			}
			else{
				file_put_contents($DetailPageFileName,$result);			
				return $result;				
			}
			
		}else{

			if ($is_proxy == 'privateproxy'){
				change_privateproxy();
			}
			
			return detailpage_download($ProductURL,$DetailPageFileName);			
		}
	}
	
	function DetailPageParse($detailpagehtml,$Category1,$Category2,$Category3,$ProductName,$ProductURL){
		
		global $db;
		
		$Scrapedate 					= date("m/d/Y");
		$Scrapetime 					= date("H:i:s");
		$StoreName 						= 'Allvetsupply';
		$Brand 								= '';
		$ImageURL 						= '';
		$UPC 									= '';
		$Model 								= '';
		$ManufacturerNumber 	= '';
		$ListPrice 						= '';
		$ProductCategory			= '';
		$CategoryArray        = array();
		
		if($Category1 !== ''){
			$CategoryArray[] = $Category1;
		}
		
		if($Category2 !== ''){
			$CategoryArray[] = $Category2;
		}
		
		if($Category3 !== ''){
			$CategoryArray[] = $Category3;
		}

		if(!empty($CategoryArray)){
			$ProductCategory = end($CategoryArray);
		}
		
		$ProductName = trim($db->real_escape_string($ProductName));
		
		if(preg_match('#<div id="kipaging"></div><script>(.*?)</script>#is',$detailpagehtml,$main)){
		
			if(isset($main[1])){
				
				preg_match_all('#arrObjects.push\(new pObj\((.*?)\)#is',$main[1],$data);
				
				for($i=0; $i<count($data[1]); $i++){
					
					preg_match_all('#"(.*?)"#is',$data[1][$i],$details);
					
					$ProductName  =  "";
					$ProductURL   =  "";
					
					if(isset($details[1][2]))		{		$ProductName  =  trim($db->real_escape_string(html_entity_decode(strip_tags($details[1][2]))));		}
					if(isset($details[1][3]))		{		$ProductURL   =  "https://www.allvetsupply.com/".trim($details[1][3]);														}
					
					$db->query("SET NAMES UTF8");
					
					$Insert = "INSERT IGNORE INTO tbl_product_listing(Category1, Category2, Category3, ProductName, ProductURL)VALUES('$Category1','$Category2','$Category3','$ProductName','$ProductURL')";
					
					if(!$db->query($Insert)){
						writeToLog($db->error.PHP_EOL);
					}
					
				}
				
				writeToLog("$i Records Insert");
				
			}
		}else{
			
			preg_match('#\'brand\': \'(.*?)\'#is',$detailpagehtml,$brand);
			if(isset($brand[1]))		{		$Brand  =  trim($db->real_escape_string(html_entity_decode(strip_tags($brand[1]))));		}
			
			preg_match('#rel="item-gal"><img src="(.*?)"#is',$detailpagehtml,$imageUrl);
			if(isset($imageUrl[1]))		{		$ImageURL =  trim($imageUrl[1]);		}
			
			preg_match('#\'upc\': \'(.*?)\'#is',$detailpagehtml,$upc);
			if(isset($upc[1]))		{		$UPC  =  trim($db->real_escape_string(html_entity_decode(strip_tags($upc[1]))));		}
			
			preg_match('#\'model\': \'(.*?)\'#is',$detailpagehtml,$model);
			if(isset($model[1]))		{		$Model  =  trim($db->real_escape_string(html_entity_decode(strip_tags($model[1]))));		}
			
			// if(preg_match('#<div class="code">ITEM \# (.*?)</div>#is',$detailpagehtml,$itemId)){
				// if(isset($itemId[1]))		{		$ManufacturerNumber  =  trim($db->real_escape_string(html_entity_decode(strip_tags($itemId[1]))));		}
			// }else if(preg_match('#\'itemId\': \'(.*?)\'#is',$detailpagehtml,$itemId)){
				// if(isset($itemId[1]))		{		$ManufacturerNumber  =  trim($db->real_escape_string(html_entity_decode(strip_tags($itemId[1]))));		}
			// }else if(preg_match('#productId="(.*?)"#is',$detailpagehtml,$itemId)){
				// if(isset($itemId[1]))		{		$ManufacturerNumber  =  trim($db->real_escape_string(html_entity_decode(strip_tags($itemId[1]))));		}
			// }
			
			if(preg_match('#<b>SALE PRICE:</b>(.*?)</div>#is',$detailpagehtml,$listPrice)){
				if(isset($listPrice[1]))		{		$ListPrice  =  trim($db->real_escape_string(html_entity_decode(strip_tags($listPrice[1]))));		}
			}else if(preg_match('#\'listPrice\': \'(.*?)\'#is',$detailpagehtml,$listPrice)){
				if(isset($listPrice[1]))		{		$ListPrice  =  trim($db->real_escape_string(html_entity_decode(strip_tags($listPrice[1]))));		}
			}else if(preg_match('#<div class="pr">(.*?)</div>#is',$detailpagehtml,$listPrice)){
				if(isset($listPrice[1]))		{		$ListPrice  =  trim($db->real_escape_string(html_entity_decode(strip_tags($listPrice[1]))));		}
			}
			
			$ListPrice = trim(str_replace(array('Regular price','Price:','$',': '),array('','','',''),$ListPrice));
			
			if(stripos($detailpagehtml,'<div class="outofstock">') !== false){
				$InStock = 'FALSE';
			}else{
				$InStock = 'TRUE';
			}
			
			$Insert1 = "INSERT IGNORE INTO tbl_product_detailpage(ScrapeDate, ScrapeTime, StoreName, ProductBrand, ProductName, ProductURL, ProductImageURL, ProductUPC, ProductCategory, ProductModel, ManufacturerNumber, ProductPrice, InStock)VALUES('$Scrapedate','$Scrapetime','$StoreName','$Brand','$ProductName','$ProductURL','$ImageURL','$UPC','$ProductCategory','$Model','$ManufacturerNumber','$ListPrice','$InStock')";
			
			if(!$db->query($Insert1)){
				writeToLog($db->error.PHP_EOL);
			}else{
				writeToLog("Records Insert");
			}
			
		}
		
		
		
		
		
		
		
		
	}

?>