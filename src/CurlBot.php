<?php

class CurlBot {
	// single channel
	private $_channel;
	
	// the current page ingredient
	private $_pageHeader 	= '';
	private $_pageBody 		= '';
	
	// multiple channels
	private $_multiChannel;
	private $_arrayChannel = array();
	
	// proxy to use
	private $_proxy = '';
	
	// cookies and sessions
	private $_useCookies 	= true;		// use cookies by default
	private $_cookieFile 	= null;
	private $_cookieStr 	= null;
	private $_PHPSESSID		= null;
	
	// web browser
	//private $_userAgent = "Mozilla/4.0 (compatible; MSIE 6.0;Windows NT 5.1)";
	private $_userAgent = null;
	private $_useCache 	= false;
	
	/**
	 * construct the bot
	 *
	 * @param string $userAgent the user agent to use when surfing
	 * @param bool $useCookies manage to cookie conversation or not
	 * 			- if $useCookies == true, program will save all communited cookies to file and cookie string
	 * @param bool $useCache manage to use cache connection or fresh connection
	 */
	public function __construct($userAgent = null, $useCookies = true, $useCache = false) {
		// user agent
		if ($userAgent)
			$this->_userAgent = $userAgent;
		else 
			$this->_userAgent = $_SERVER['HTTP_USER_AGENT'];
			
		// use cache or fresh connect
		$this->_useCache = $useCache;

		// how to manage cookies
		// if use cookies
		if ($useCookies) {
			// yes it does!
			$this->_useCookies = true;
			
			// config default cookie path and preceding cookie name (local)
			$cookiePath = $this->configCookiePath();
			$this->_cookieFile = tempnam($cookiePath, "curlBotCookie_");
		}
		
		// sessions to communicate with server
		$this->_PHPSESSID = "PHPSESSID=" . $this->generateRandomString();
	}
	
	/**
	 * check if a given proxy is safe
	 * safe means this proxy can be used to overcome some popular IP detecting website
	 * 
	 * @param string $proxy
	 * @param int $port
	 * @return bool true for safe, false for unsafe
	 */
	public function verifySafeProxy($proxy = false, $port = 80) {
		// set time limit
		set_time_limit(120);
		
		// site to check this proxy
		$siteToCheck = "http://www.cmyip.com/";
		$tmpCurl = curl_init($siteToCheck);
		
		// common options
		curl_setopt($tmpCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($tmpCurl, CURLOPT_HEADER, true);
		curl_setopt($tmpCurl, CURLOPT_PROXY, "$proxy:$port");
		
		// time out to exit if the download period is exceeded
		curl_setopt($tmpCurl, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($tmpCurl, CURLOPT_TIMEOUT, 30);
		
		// perform twice
		$data = curl_exec($tmpCurl);
		$data = curl_exec($tmpCurl);
		
		// log error
		$err = curl_error($tmpCurl);
		
		// close this temple curl
		curl_close($tmpCurl);
		
		// check if this IP is safe or not
		$detectedIp = $this->findNext(0, array('<title>', 'My IP is', '-'), 1, false, false, $data, $i);
		if ($detectedIp == $proxy)
			return true;
		else 
			return false;
	}
	
	/**
	 * check if a given proxy is available
	 * available means this proxy can be used to surf the internet but may not hide your identity
	 *
	 * @param string $proxy
	 * @param int $port
	 * @return bool true for available, false for unavailable
	 */
	public function verifyAvailableProxy($proxy = false, $port = 80) {
		// set time limit
		set_time_limit(120);
		
		// site to check this proxy
		$siteToCheck = "http://www.cmyip.com/";
		$tmpCurl = curl_init($siteToCheck);
		
		// common options
		curl_setopt($tmpCurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($tmpCurl, CURLOPT_HEADER, true);
		curl_setopt($tmpCurl, CURLOPT_PROXY, "$proxy:$port");
		
		// time out to exit if the download period is exceeded
		curl_setopt($tmpCurl, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($tmpCurl, CURLOPT_TIMEOUT, 30);
		
		// perform twice
		$data = curl_exec($tmpCurl);
		$data = curl_exec($tmpCurl);
		
		// log error
		$err = curl_error($tmpCurl);
		
		// close this temple curl
		curl_close($tmpCurl);
		
		// check if this IP is active or not
		$title = $this->findNext(0, array('<title>', '</title>'), 0, false, false, $data, $i);
		if ( substr_count($title, "My IP is") )
			return true;
		else 
			return false;
	}
	
	/**
	 * enable to use proxy
	 *
	 * @param string $proxy
	 * @param int $port
	 */
	public function enableProxy($proxy = false, $port = 80) {
		$this->_proxy = "$proxy:$port";
	}
	
	/**
	 * disable proxy usage
	 *
	 */
	public function disableProxy() {
		$this->_proxy = '';
	}
	
	/**
	 * submit a form
	 *
	 * @param string $url the url where the form submit information to
	 * @param associative array $submitData with each element is a pair of $key=>$value
	 * @param array $arrayUploadFile
	 * @param string $method 'post'|'get'
	 * @param bool $alternativePost set it to true if your want to urlencode the submit data
	 * 
	 * @uses
	 * 		if you have a form like this
	 * 			<form enctype="multipart/form-data" method="post" action="process.php">
	 * 				<input type="text" name="user_name" value="tony" ?>
	 * 				<input type="text" name="user_pass" value="lovemylove" ?>
	 * 				<input type="text" name="score[]" value="100" ?>
	 * 				<input type="text" name="score[]" value="94" ?>
	 * 				<input type="text" name="score[]" value="78" ?>
	 * 				<input type="file" name="user_file_1" value="" />
	 * 				<input type="file" name="user_file_2" value="" />
	 * 				<input type="file" name="user_score[]" value="" />
	 * 				<input type="file" name="user_score[]" value="" />
	 * 				<input type="file" name="user_score[]" value="" />
	 * 			</form>
	 * 		then the array $submitData should be an array like this
	 * 			$submitData = array(
	 * 				"user_name" => "tony",
	 * 				"user_pass" => "lovemylove",
	 * 				"score" 	=> array(100, 94, 78)
	 * 			);
	 * 		and the array $arrayUploadFile should be an array like this
	 * 			$arrayUploadFile = array(
	 * 				"user_file_1" 	=> "D:\\tony\\files\\file_1.txt",		// use absolute path to the file with double forward slash
	 * 				"user_file_1" 	=> "D:\\tony\\files\\file_2.txt",
	 * 				"user_score" 	=> array(
	 * 					"D:\\tony\\files\\score_1.txt",
	 * 					"D:\\tony\\files\\score_2.txt",
	 * 					"D:\\tony\\files\\score_3.txt"
	 * 				)
	 * 			);
	 */
	public function submitForm($url = '', $submitData = array(), $arrayUploadFile = array(), $method = 'post', $alternatePostFormat = false, $connectionTimeOut = 60) {
		// treat the array $submitData
		$originalSubmitData = $submitData;
		$submitData = array();
		if ( count($originalSubmitData) )
		{
			foreach($originalSubmitData as $key=>$val)
			{
				// if some field value is actually an array holding multiple values
				if ( is_array($val) && !empty($val) )
				{
					for ($i=0; $i<count($val); $i++)
						$submitData[$key."[$i]"] = $val[$i];
				}
				// else if it is just a single value as usual
				else 
				{
					$submitData[$key] = $val;
				}
			}
		}
		
		// if there are files to upload
		if ( count($arrayUploadFile) )
		{
			foreach($arrayUploadFile as $key=>$val)
			{
				// if there are multiple files to upload
				if ( is_array($val) && !empty($val) )
				{
					for ($i=0; $i<count($val); $i++)
					{
						$submitData[$key."[$i]"] = "@" . $val[$i];
					}
				}
				// else if there is just a single file to upload
				else 
				{
					$submitData[$key] = "@" . $val;
				}
			}
		}
		//print_r($submitData); die;
		
		// process the total post data
		if (count($submitData))
		{
			// if submit method is post
			if ($method == 'post')
			{
				// open channel
				$this->openChannel($this->_channel, $url, $connectionTimeOut);
			
				// indicate that this channel will use post data
				curl_setopt($this->_channel, CURLOPT_POST, true);
				
				// urlencode all post data if alternative post is true
				if ($alternatePostFormat)
					$submitData = $this->urlEncodeArrayData($submitData);
				// prepare all post data
				curl_setopt($this->_channel, CURLOPT_POSTFIELDS, $submitData);
			}
			// else if submit method is get
			else 
			{
				// urlencode all post data
				$submitData = $this->urlEncodeArrayData($submitData);
				// nest the submit data with the original url
				$url .= "?" . $submitData;
				
				// open channel
				$this->openChannel($this->_channel, $url, $connectionTimeOut);
			}
			
			// perform
			$response 	= curl_exec($this->_channel);
			if ($this->_proxy != '')
				$response 	= curl_exec($this->_channel);
			$info 			= curl_getinfo($this->_channel);
			$returnHeader 	= trim(substr($response, 0, $info['header_size']));
			$returnBody 	= trim(str_replace($returnHeader, '', $response));
			// log error
			$err = curl_error($this->_channel);
			
			// close channel
			$this->closeChannel($this->_channel);
			
			// store this current page header and body
			$this->_pageHeader 	= $returnHeader;
			$this->_pageBody 	= $returnBody;
			
			// if use cookies: obtain, update and save newest cookies returned
			if ($this->_useCookies)
				$this->updateCookieString();
		}
	}
	
	/**
	 * navigate to a page, just like the action as you click a link to go to a page
	 *
	 * @param string $url
	 * 
	 * @uses 
	 * 		To go to a page and get the header, body of that page for futher usage
	 * 		$bot = new CurlBot();
	 * 		$bot->navigateTo('http://www.google.com/');
	 * 		$header 	= $bot->getPageHeader();		// $header holds the header of http://www.google.com/
	 * 		$body 		= $bot->getPageBody();			// $body holds the main body content of http://www.google.com/
	 */
	public function navigateTo($url = '', $connectionTimeOut = 60)
	{
		// open channel
		$this->openChannel($this->_channel, $url, $connectionTimeOut);
		
		// get data
		// perform twice if proxy is used
		$response 	= curl_exec($this->_channel);
		if ($this->_proxy != '')
			$response 	= curl_exec($this->_channel);
		$info 		= curl_getinfo($this->_channel);
		$returnHeader 	= trim(substr($response, 0, $info['header_size']));
		$returnBody 	= trim(str_replace($returnHeader, '', $response));
		// log error
		$err = curl_error($this->_channel);
		
		// close channel
		$this->closeChannel($this->_channel);
		
		// store this current page header and body
		$this->_pageHeader 	= $returnHeader;
		$this->_pageBody 	= $returnBody;
		
		// if use cookies: obtain, update and save newest cookies returned
		if ($this->_useCookies)
			$this->updateCookieString();
	}
	
	/**
	 * download single/multiple file(s) at once
	 *
	 * @param string|array $files url to download file(s)
	 * @param string|array $newFileNames new file names for downloaded files, must be accordant to $files
	 * @param string $saveTo directory to save the downloaded files later on
	 * @param int $connectionTimeOut time to exit if the period consumption exceeds
	 * 
	 * @uses 
	 * 		$files = array(
	 * 			"http://www.ncjrs.gov/txtfiles/victcost.txt",
	 * 			"http://www.ncjrs.gov/txtfiles/victcost1.txt",
	 * 			"http://www.ncjrs.gov/txtfiles/victcost2.txt"
	 * 		);
	 * 		// or
	 * 		$files = "http://www.ncjrs.gov/txtfiles/victcost.txt";
	 * 
	 * 		$saveTo = 'download_files/'; 			// relative path to the folder to save download files, with backward slash at the end
	 * 
	 * 		$newFileNames = array(
	 * 			"myfile.txt",
	 * 			"myfile1.txt",
	 * 			"myfile2.txt"
	 * 		);
	 * 		// or
	 * 		$newFileNames = array(
	 * 			"myfile.txt",
	 * 			"",					// the second file will be named automatically according to the basename receive from url
	 * 			"myfile2.txt"
	 * 		);
	 * 		
	 * 		// or
	 * 		$newFileNames = "myfile.txt";
	 * 		// or
	 * 		$newFileNames = "";		// the file name will be automatically determined according to the basename receive from url
	 */
	public function downloadDirectFiles($files = array(), $saveTo = '', $newFileNames = array(), $connectionTimeOut = 300)
	{
		// urls to download
		$urls = array();
		// if download muiltiple files at once
		if (is_array($files))
			$urls = $files;
		// else if download a single file
		else if (!empty($files))
			$urls = array($files);
		
		// new file names to save
		$fileNames = array();
		if (!empty($newFileNames))
		{
			// if download muiltiple files at once
			if (is_array($newFileNames))
				$fileNames = $newFileNames;
			// else if download a single file
			else if (!empty($files))
				$fileNames = array($newFileNames);
		}
		
		// if there are any files to download
		if ($urls)
		{
			// open this multi channel
			$this->openMultiChannel($this->_multiChannel);
			
			// add each download url to queue
			foreach ($urls as $i => $url)
			{
				// file name to save
				if ($fileNames && !empty($fileNames[$i]))
					$saveFile = $saveTo.$fileNames[$i];
				else 
			    	$saveFile = $saveTo.basename($url);
			    // only prepare to download if this file has not existed in local computer yet and the download url is not empty
			    if (!is_file($saveFile) && !empty($url))
			    {
			    	// open download channel
			    	$this->openChannel($this->_arrayChannel[$i], $url, $connectionTimeOut);
			    	// don't include the header in the return result
			    	curl_setopt($this->_arrayChannel[$i], CURLOPT_HEADER, false);
			    	// open file to write
			        $fp[$i] = fopen($saveFile, "w");
			        // this command indicates that the return result of the url $this->_arrayChannel[$i] when executed will be written to the resource file $fp[$i]
			        curl_setopt($this->_arrayChannel[$i], CURLOPT_FILE, $fp[$i]);
			        // proceed to add this channel to queue
			        curl_multi_add_handle($this->_multiChannel, $this->_arrayChannel[$i]);
			    }
			}
			
			// proceed to download all files at once
			do
			{
			    $n = curl_multi_exec($this->_multiChannel, $active);
			}
			while ($active);
			
			// remove all channels from queue
			foreach ($urls as $i => $url)
			{
				// file name was saved
				if ($fileNames && !empty($fileNames[$i]))
					$saveFile = $saveTo.$fileNames[$i];
				else 
			    	$saveFile = $saveTo.basename($url);
			    // only remove this channel if this file has not existed in local computer and the download url is not empty
				if (!is_file($saveFile) && !empty($url))
				{
				    curl_multi_remove_handle($this->_multiChannel, $this->_arrayChannel[$i]);
				    $this->closeChannel($this->_arrayChannel[$i]);
				    fclose($fp[$i]);
				}
			}
			
			// close this multi channel
			$this->closeMultiChannel($this->_multiChannel);
		}
	}
	
	/**
	 * download a single file, need to submit a form to get the file downloaded
	 *
	 * @param string $url the url where the form submit information to
	 * @param associative array $submitData with each element is a pair of $key=>$value
	 * @param string $saveTo directory to save the downloaded file later on
	 * @param string $newFileName new file name for the downloaded file
	 * @param string $method 'post'|'get'
	 * @param bool $alternativePost set it to true if your want to urlencode the submit data
	 * 
	 * @uses 
	 * 		if you have a form like this
	 * 			<form method="post" action="process.php">
	 * 				<input type="text" name="user_name" value="tony" ?>
	 * 				<input type="text" name="user_pass" value="lovemylove" ?>
	 * 				<input type="text" name="score[]" value="100" ?>
	 * 				<input type="text" name="score[]" value="94" ?>
	 * 				<input type="text" name="score[]" value="78" ?>
	 * 			</form>
	 * 		then the array $submitData should be an array like this
	 * 			$submitData = array(
	 * 				"user_name" => "tony",
	 * 				"user_pass" => "lovemylove",
	 * 				"score" 	=> array(100, 94, 78)
	 * 			);
	 * 		
	 * 		$saveTo = 'download_files/'; 			// relative path to the folder to save download file, with backward slash at the end
	 * 
	 * 		$newFileName = "my_file.csv";
	 * 		// or
	 * 		$newFileName = "";						// the file name will be automatically determined according to the basename receive from url
	 */
	public function downloadAdvance($url = '', $submitData = array(), $saveTo = '', $newFileName = '', $method = 'post', $alternatePostFormat = false, $connectionTimeOut = 60)
	{
		// file name to save
		if ($newFileName)
			$saveFile = $saveTo.$newFileName;
		else 
	    	$saveFile = $saveTo.basename($url);
		
	    // only prepare to download if this file has not existed in local computer yet and the submit url is not empty
	    if (!is_file($saveFile) && !empty($url))
	    {
			// treat the array $submitData
			$originalSubmitData = $submitData;
			$submitData = array();
			if ( count($originalSubmitData) )
			{
				foreach($originalSubmitData as $key=>$val)
				{
					// if some field value is actually an array holding multiple values
					if ( is_array($val) && !empty($val) )
					{
						for ($i=0; $i<count($val); $i++)
							$submitData[$key."[$i]"] = $val[$i];
					}
					// else if it is just a single value as usual
					else 
					{
						$submitData[$key] = $val;
					}
				}
			}
			//print_r($submitData); die;
			
			// process the total post data
			if (count($submitData))
			{
				// if submit method is post
				if ($method == 'post')
				{
					// open channel
					$this->openChannel($this->_channel, $url, $connectionTimeOut);					
				
					// indicate that this channel will use post data
					curl_setopt($this->_channel, CURLOPT_POST, true);
					
					// urlencode all post data if alternative post is true
					if ($alternatePostFormat)
						$submitData = $this->urlEncodeArrayData($submitData);
					// prepare all post data
					curl_setopt($this->_channel, CURLOPT_POSTFIELDS, $submitData);
				}
				// else if submit method is post
				else 
				{
					// urlencode all post data
					$submitData = $this->urlEncodeArrayData($submitData);
					// nest the submit data with the original url
					$url .= "?" . $submitData;
					
					// open channel
					$this->openChannel($this->_channel, $url, $connectionTimeOut);
				}
				
				// don't include the header in the return result
				curl_setopt($this->_channel, CURLOPT_HEADER, false);

				// open file to write
		        $fp = fopen($saveFile, "w");
		        // this command indicates that the return result of the url $this->_channel when executed will be written to the resource file $fp
		        curl_setopt($this->_channel, CURLOPT_FILE, $fp);
		        
		        // perform
				curl_exec($this->_channel);
				$info 		= curl_getinfo($this->_channel);
				$returnHeader 	= trim(substr($response, 0, $info['header_size']));
				$returnBody 	= trim(str_replace($returnHeader, '', $response));
				// log error
				$err = curl_error($this->_channel);
				
				// close channel
				$this->closeChannel($this->_channel);
				// close the resource file
				fclose($fp);
				
				// store this current page header and body
				$this->_pageHeader 	= $returnHeader;
				$this->_pageBody 	= $returnBody;
				
				// if use cookies: obtain, update and save newest cookies returned
				if ($this->_useCookies)
					$this->updateCookieString();
			}
	    }
	}
	
	/**
	 * get the current navigated page header
	 *
	 * @return unknown
	 */
	public function getPageHeader()
	{
		return $this->_pageHeader;
	}
	
	/**
	 * get the current navigated page body
	 *
	 * @return unknown
	 */
	public function getPageBody()
	{
		return $this->_pageBody;
	}
	
	/**
	 * manually set cookie path and cookie preceding name for cookie files
	 *
	 * @param string $path must specify absolute path or relative path according to active application
	 * @param string $preceding
	 */
	public function setCookiePath($path, $preceding) {
		$cookiePath = $path;
		$this->_cookieFile = tempnam($cookiePath, $preceding);
	}
	
	/**
	 * get path to cookie file, PHPSESSIONID communication, and cookie string
	 * this method is to share auth between different curlBots, e.g. don't need to login again
	 *
	 */
	public function getAuth(&$cokieFile, &$PHPSESSIONID, &$cookieStr) {
		$cokieFile 		= $this->_cookieFile;
		$PHPSESSIONID 	= $this->_PHPSESSID;
		$cookieStr		= $this->_cookieStr;
	}
	
	/**
	 * set auth for this curlBot
	 *
	 * @param CurlBot::_cookieFile $cookieFile
	 * @param CurlBot::_PHPSESSID $PHPSESSIONID
	 * @param CurlBot::_cookieStr $cookieStr
	 */
	public function setAuth($cookieFile, $PHPSESSIONID, $cookieStr = '') {
		$this->_cookieFile 	= $cookieFile;
		$this->_PHPSESSID 	= $PHPSESSIONID;
		$this->_cookieStr	= $cookieStr;
	}
	
	/**
	 * temporary function to print current cookie string
	 *
	 */
	public function printCookieStr() {
		$temp = array();
		if ($this->_cookieStr != '')
			$temp = explode('; ', $this->_cookieStr);
		print_r($temp);
	}
	
	/**
	 * destruct the bot
	 *
	 */
	public function __destruct()
	{
		
	}
	
	/**
	 * config cookie path to store cookie locally
	 *
	 */
	private function configCookiePath()
	{
		return dirname(__FILE__) . '\cookiefile';
	}
	
	/**
	 * This method is called after a form submission or a page request
	 * - obtain all cookies returned from $this->_pageHeader
	 * - update these cookies to the current cookie string
	 * - update $this->_cookieStr to contain all the latest cookies
	 *
	 */
	private function updateCookieString() {
		// prepare current cookies in array format
		$temp = array();
		$curCookies = array();
		if ($this->_cookieStr != '') {
			$temp = explode('; ', $this->_cookieStr);
			foreach ($temp as &$item) {
				$item = explode('=', $item, 2);
				$curCookies[$item[0]] = $item[1];	// $item[0] is cookie name, $item[1] is cookie value
			}
		}
		//print_r($curCookies); die;
		
		// extract new cookies from page header
		$newCookies = array();
		$header = $this->_pageHeader;
		$header = str_ireplace('set-cookie', 'set-cookie', $header);
		//preg_match_all('|set-cookie: (.*);|U', $header, $cookieInfo);
		$temp = explode("\n", $header);
		$newCookies = array();
		foreach ($temp as $item)
			if (substr_count($item, 'set-cookie:')) {
				$cookie = $this->findNext(0, array('set-cookie:', ';'), 0, false, false, $item, $tempItem);
				$newCookies[] = trim($cookie);
			}
		//print_r($temp); die;
		//$newCookies = $cookieInfo[1];
		//print_r($newCookies); die;
		
		// incorporate new cookies to current cookies to make latest cookies
		foreach ($newCookies as &$item) {
			$item = explode('=', $item, 2);
			$curCookies[$item[0]] = $item[1];	// $item[0] is cookie name, $item[1] is cookie value
		}
		// reformat $curCookies
		$temp = $curCookies;
		$curCookies = array();
		foreach ($temp as $key=>$value) {
			$curCookies[] = trim($key) . '=' . trim($value);
		}
		//print_r($curCookies); die;
		
		// finally, save content to cookie string
		$this->_cookieStr = implode('; ', $curCookies);
	}
	
	/**
	 * initialize a channel
	 *
	 */
	private function openChannel(&$channel, $url = '', $connectionTimeOut = 60)
	{
		// set time limit
		if ($this->_proxy == '')
			set_time_limit($connectionTimeOut * 2);
		else 
			set_time_limit($connectionTimeOut * 4);
		
		// Init the CURL
		$channel = curl_init();
		
		// Set the global CURL options
		curl_setopt($channel, CURLOPT_VERBOSE, true); 
		curl_setopt($channel, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($channel, CURLOPT_COOKIESESSION, true);
		curl_setopt($channel, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($channel, CURLOPT_HEADER, true);
		curl_setopt($channel, CURLOPT_COOKIE, $this->_PHPSESSID);
		
		// if url is a ssl secure
		if(substr_count($url , "https://"))
			curl_setopt($channel, CURLOPT_SSL_VERIFYPEER, 0);
		// the url to execute
		curl_setopt($channel, CURLOPT_URL, $url);
		// referer
		curl_setopt($channel, CURLOPT_REFERER, $url);
		
		// time out to exit if the loading period is exceeded
		curl_setopt($channel, CURLOPT_CONNECTTIMEOUT, $connectionTimeOut);
		curl_setopt($channel, CURLOPT_TIMEOUT, $connectionTimeOut);
		
		// if want to use proxy
		if ($this->_proxy) {
			curl_setopt($channel, CURLOPT_PROXY, $this->_proxy);
		}
		
		// if use cookies
		if ($this->_useCookies) {
			curl_setopt($channel, CURLOPT_COOKIEJAR, $this->_cookieFile);
			curl_setopt($channel, CURLOPT_COOKIEFILE, $this->_cookieFile);
			curl_setopt($channel, CURLOPT_COOKIE, $this->_cookieStr);
		}
		
		// user agent
		curl_setopt($channel, CURLOPT_USERAGENT, $this->_userAgent);
		
		// use cache or fresh connect
		if ($this->_useCache) {
			curl_setopt($channel, CURLOPT_DNS_USE_GLOBAL_CACHE, true);
		} else {
			curl_setopt($channel, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
			curl_setopt($channel, CURLOPT_FRESH_CONNECT, true);
		}
	}
	
	/**
	 * close a channel
	 *
	 */
	private function closeChannel(&$channel)
	{
		// close the channel
		curl_close($channel);
		unset($channel);
	}
	
	private function openMultiChannel(&$multiChannel)
	{
		// intialize the multi channel
		$multiChannel = curl_multi_init();
	}
	
	private function closeMultiChannel(&$multiChannel)
	{
		// close this multi channel
		curl_multi_close($multiChannel);
		unset($multiChannel);
	}
	
	/**
	 * generate a random string
	 *
	 * @param int $length the length of the return random string
	 * @param string $sourceChars contains all source characters standing next to each other
	 * @return string
	 */
	private function generateRandomString($length = 26, $sourceChars = "abcdefghijklmnopqrstuvwxwz0123456789")
	{
		// start with a blank string
		$string = "";
		// define possible characters
		$possible = $sourceChars;
		// set up a counter
		$i = 0; 
		// add random characters to $string until $length is reached
		while ($i < $length)
		{ 
			// pick a random character from the possible ones
			$char = substr($possible, mt_rand(0, strlen($possible)-1), 1);
			// add this char to thr string
			$string .= $char;
			$i++;
		}
		
		// done
		return $string;
	}
	
	/**
	 * url encode an array of data
	 *
	 * @param associative array $arrayData with each element is a pair of $key=>$val, $val is a string
	 * @return string
	 */
	private function urlEncodeArrayData($arrayData = array())
	{
		$urlEncodeString = '';
		if ($arrayData)
		{
			foreach($arrayData as $key=>$val)
			{
				$urlEncodeString .= "$key=" . urlencode($val) . "&";
			}
			$urlEncodeString = substr($urlEncodeString , 0 , -1);
		}
		
		return $urlEncodeString;
	}
	
	/**
	 * extract the desired data from the given content using the array of containing strings (tokens)
	 * 
	 * @uses public
	 *
	 * @param int $iStartFrom
	 * @param array of tokens $sRoot
	 * @param int $iFirst
	 * @param bool $bIncludeFirst
	 * @param bool $bIncludeLast
	 * @param string $sString
	 * @param int& $iNewStartFrom
	 * 
	 * @return string
	 */
	private function findNext($iStartFrom, $sRoot, $iFirst, $bIncludeFirst, $bIncludeLast, $sString, &$iNewStartFrom)
	{
	    $sSubString = substr($sString,$iStartFrom,strlen($sString)-$iStartFrom);
	    $iTime   = count($sRoot);
	    $iStart  = 0;
	    $iEnd    = 0;
	    $pos     = 0;
	    $lastPos = 0;
	
	    for ($i=0; $i<$iTime; $i++)
	    {
	        $pos = strpos($sSubString, $sRoot[$i], $lastPos);
	        if ( $sRoot[$i] != substr($sSubString,0,strlen($sRoot[$i])) )
	            if ($pos==false) return "";
	        $lastPos = $pos + strlen($sRoot[$i]);
	        if ($i==($iTime-1) ) $iEnd=$pos;
	    }
	    
	    // new start from 
	    $iNewStartFrom = $iStartFrom + $iEnd + strlen($sRoot[$iTime-1]);
	    
	    if ($bIncludeLast==true)
	        $iEnd = $iEnd+strlen($sRoot[$iTime-1]);
	
	    $pos     = 0;
	    $lastPos = 0;
	    for ($i=0;$i<=$iFirst;$i++)
	    {
	        $pos = strpos($sSubString,$sRoot[$i],$lastPos);
	        $iStart = $pos;
	        $lastPos = $pos+strlen($sRoot[$i])-1;
	    }
	    if ($bIncludeFirst==false)
	        $iStart = $iStart + strlen($sRoot[$iFirst]);
	        
	    /*
		echo $iStart . '<br />';
		echo $iEnd . '<br />';
		echo trim(substr($sSubString,$iStart,$iEnd-$iStart)); die;
		*/
		return trim(substr($sSubString,$iStart,$iEnd-$iStart));
	}
}
