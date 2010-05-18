<?php

/*
 * 這個版本，跟前代不一樣的是，全面檢修branches功能
 */

class Svn {

	protected $svnpath;
	protected $svn;
	protected $lang;
	protected $svnrepo;
	protected $svnbaseurl;
	protected $branches;
	protected $svnurl;
	protected $debug;

	/*
	 * @repo		string	就repo的名稱
	 * @baseurl		string	svn最前面的網址
	 * @branches	string	接在repo後面的網址
	 */
	public function __construct($repo = 'none', $baseurl = 'http://svn.1009design.com', $branches = '', $dotsubversion = ''){
		// normal variables
		$this->lang = 'LANG=en_US.UTF-8';
		$this->svnpath = '/usr/bin';
		$this->svn = $this->lang. ' && '.$this->svnpath.'/svn';

		// FIX BUG
		// svn: Can't open file '/root/.subversion/servers': Permission denied
		// 放在主機上面的時候，並且使用http觸發的程式才要考慮到這裡
		if($dotsubversion != ''){
			$this->svn .= ' --config-dir '.$dotsubversion;
		}

		$this->svnrepo = $repo;
		$this->svnbaseurl = $baseurl;
		$this->branches = $branches;

		$this->svnurl = $this->svnbaseurl.'/'.$repo;

		//if($this->branches == ''){
		//	$this->svnurl = $this->addDirSlashes($this->svnbaseurl, $repo);
		//} else {
		//	$this->svnurl = $this->addDirSlashes($this->svnbaseurl, $repo, $this->branches);
		//}
		$this->debug = '1';

		// diff variables
		$this->configfile = 'config3.php';
		$this->inidir = 'ini3';
		$this->fullinidir = 'inifull';
	}

	/*
	 * svn checkout
	 *
	 * 己支援分支功能
	 *
	 * @revision string 版本號
	 * @sourcedir string 在什麼資料夾裡面做checkout的事情
	 * @dest string svn checkout目標目錄
	 */
	public function svnCheckout($revision = '', $sourcedir, $dest){
		$cmds = array();

		if($sourcedir != ''){
			array_push($cmds, 'cd', $sourcedir, '&&');
		}

		array_push($cmds, $this->svn, 'checkout');

		if($revision != ''){
			array_push($cmds, '--revision', $revision);
		}

		// 如果有啟用分支功能，要checkout的網址後面要加上分支的路徑
		if($this->branches != ''){
			array_push($cmds, $this->svnurl.'/'.$this->branches);
		} else {
			array_push($cmds, $this->svnurl);
		}

		array_push($cmds, $dest);

		return join(' ', $cmds);
	} // end function  svnCheckout

	/*
	 * svn export
	 *
	 * 己支援分支功能
	 *
	 * @revision string 版本號
	 * @sourcedir string 在什麼資料夾裡面做checkout的事情
	 * @dest string EXPORT目標目錄
	 * @sourceurl string 就是緊接在svnurl後面的資料夾路徑，如果有啟用分支功能，帶入這裡的變數，不能含有分支的資料夾!
	 */
	public function svnExport($revision = '', $sourcedir, $dest = '', $sourceurl = ''){
		$cmds = array();

		if($sourcedir != '' ){
			array_push($cmds, 'cd', $sourcedir, '&&');
		}

		array_push($cmds, $this->svn, 'export');

		if($revision != ''){
			array_push($cmds, '--revision', $revision);
		}

		// 如果有啟用分支功能，當然要加入到svnurl的變數裡面
		if($this->branches != ''){
			$svnurl = $this->svnurl.'/'.$this->branches;
		} else {
			$svnurl = $this->svnurl;
		}

		if($sourceurl != ''){
			$svnurl .= '/'.$sourceurl;
		}

		array_push($cmds, $svnurl);

		if($dest != ''){
			array_push($cmds, $dest);
		}

		return join(' ', $cmds);

	} // end function svnExport

	/*
	 * svn update
	 *
	 * 不需支援分支功能
	 *
	 * @revision string 版本號
	 * @sourcedir string 在什麼資料夾裡面做checkout的事情，如果有啟用分支功能，這裡必需帶含有分支的資料夾的名稱
	 */
	public function svnUpdate($revision = '', $sourcedir){
		$cmds = array();

		if($sourcedir != ''){
			array_push($cmds, 'cd', $sourcedir, '&&');
		}

		array_push($cmds, $this->svn, 'update');

		if($revision != ''){
			array_push($cmds, '--revision', $revision);
		}

		return join(' ', $cmds);
	} // end function svnUpdate

	/*
	 * svn info
	 *
	 * 己支援分支功能
	 *
	 * @sourceitem	string	要查看info的目標，可能是檔案或是資料夾，請不要帶分支的資料夾名稱進來!!
	 */
	public function svnInfo($sourceitem = ''){
		$cmds = array($this->svn, 'info');

		// 如果有啟用分支功能，要在網址或是路徑後面要加上分支的路徑
		if($this->branches != ''){
			$svnurl = $this->svnurl.'/'.$this->branches;
		} else {
			$svnurl = $this->svnurl;
		}

		if($sourceitem != ''){
			$svnurl .= '/'.$sourceitem;
		}

		array_push($cmds, $svnurl);

		return join(' ', $cmds);
	} // end function svnInfo

	/*
	 * svn log
	 *
	 * 己支援分支功能
	 *
	 * @revision string 版本號
	 */
	public function svnLog($revision = '')
	{
		$cmds = array($this->svn, 'log');

		// 如果有啟用分支功能，要在網址或是路徑後面要加上分支的路徑
		if($this->branches != ''){
			array_push($cmds, $this->svnurl.'/'.$this->branches);
		} else {
			array_push($cmds, $this->svnurl);
		}

		if($revision != ''){
			array_push($cmds, '--revision', $revision);
		}
		return join(' ', $cmds);
	}

	/*
	 * 純把兩個或是三個變數中間加一個斜線，這是資料夾專用的
	 * 其中第三個引數是選擇性的
	 */
	public function addDirSlashes($var1, $var2, $var3 = ''){
		$vars = array($var1, $var2);
		if($var3 != ''){
			array_push($vars, $var3);
		}
		return join('/', $vars);
	}

	/*
	 * 這是svn專用的urlcode
	 */
	public function svnUrlEncode($url){
		
		// 這裡做的，是類似urlencode的動作
		// 但斜線冒號等，是不encode的
		$patterns = array();
		$patterns[0] = '/\[/';
		$patterns[1] = '/\]/';
		$patterns[2] = '/&/';
		$patterns[3] = '/@/';
		$replacements = array();
		$replacements[3] = '%5B';
		$replacements[2] = '%5D';
		$replacements[1] = '%26';
		$replacements[0] = '%40';

		$returnvalue = preg_replace($patterns, $replacements, $url);

		return $returnvalue;

	} // end function  svnUrlEncode

	/*
	 * 取得repo上最後的版本
	 *
	 * 不需支援分支功能
	 */
	public function getLastRevision(){
		$cmd = $this->svnInfo();
		$cmd_result = `$cmd`;

		if(preg_match('/Last Changed Rev: (.*)/i', $cmd_result, $matches)){
			return $matches[1];
		}
	}

	/* 
	 * 取得指定版本的修改列表，並輸出陣列
	 *
	 * 己支援分支功能
	 *
	 * @revision  string  版本
	 */
	public function getModifyList($revision){

		// 如果有冒號，需要在檢查是否間隔1個版本號(如果是要在倒退1)
		if(preg_match('/^(.*):(.*)$/', $revision, $matches)){
			$first_revision = $matches[1];
			$last_revision = $matches[2];
			if($first_revision > 1){
				$first_revision = $first_revision - 1;
			}
			$revision = $first_revision.':'.$last_revision;
		} elseif(preg_match('/^\d+$/', $revision)){
			// 如果是處理200的這個版本號，
			// 這裡必需要變成199:200
			if($revision != '1'){
				$revision = ($revision - 1).':'.$revision;
			}
		} elseif(preg_match('/^(.*):$/', $revision, $matches)){
			$first_revision = $matches[1];
			if($first_revision > 1){
				$first_revision = $first_revision - 1;
			}
			$last_revision = $this->getLastRevision();
			$revision = $first_revision.':'.$last_revision;
		} else {
			// nothing
		} 

		$cmds = array($this->svn, 'diff', '--revision', $revision, '--summarize');

		// 如果有啟用分支功能，要在網址或是路徑後面要加上分支的路徑
		$svnurl = $this->svnurl;
		if($this->branches != ''){
			$svnurl .= '/'.$this->branches;
		}
		array_push($cmds, $svnurl);

		$cmd = join(' ', $cmds);

		$array_outfile = explode("\n", `$cmd`);
		$returnarray = array();

		foreach($array_outfile as $key => $val){
			if(preg_match('/(A|M|MM| M)\s+(.*)/', $val, $matches)){
				$full_item = $matches[2];
				$export_item = substr($full_item, strlen($svnurl)+1);

				// 如果有斜線，就代表有含資料夾，這時要取得最右邊那一個(也就是檔名)
				if(preg_match('/\//', $export_item)){
					$split_item = split('/', $export_item);
					$export_file = $split_item[count($split_item) - 1];
					array_pop($split_item);
					$export_dir = join('/', $split_item);
					$returnarray['modify'][$export_dir][] = $export_file;
				// 如果沒有斜線，就代表是最上層
				} else {
					if($export_item != '') $returnarray['modify']['/'][] = $export_item;
				}
			}
			if(preg_match('/(D)\s+(.*)/', $val, $matches)){
				$full_item = $matches[2];
				$export_item = substr($full_item, strlen($svnurl)+1);

				$returnarray['delete'][] = $export_item;
			}
		}
		return $returnarray;

	} // end function  getModifyList

	/* 
	 * 取得指定版本的修改列表，並輸出陣列
	 * 使用這種方式，不同的地方是，會輸出新增或刪除資料夾的資訊
	 *
	 * 己支援分支功能
	 * 己能處理新增資料夾功能
	 *
	 * @revision  string  版本
	 */
	public function getModifyListBySvnLog($revision = ''){

		$returnarray = array();

		$cmds = array($this->svn, 'log', '-v');

		if($revision != ''){
			array_push($cmds, '--revision', $revision);
		}

		array_push($cmds, $this->svnurl);

		$cmd = join(' ', $cmds);

		$array_outfile = explode("\n", `$cmd`);

		// 針對新增以及修改過的檔案做處理
		foreach($array_outfile as $key => $val){
			// 這個地方先mark起來，留到未來使用
			//if(preg_match('/^r(.*) \|.*/', $val, $matches)){
			//	error_msg($logfile, '# revision: '.$matches[1], 0);
			//}
			if(preg_match('/   (A|M) (.*)/', $val, $matches)){
				// 先把開頭的斜線去掉
				$export_item = substr($matches[2], 1);

				// 如果有啟用分支功能，就把branches的資料夾去掉
				if($this->branches != ''){
					$export_item = substr($export_item, strlen($this->branches)+1);
				}

				// 檢查這個物件是檔案還是資料夾
				$cmd = $this->svnInfo($export_item);
				$cmd_result = `$cmd`;

				// 如果是空白，代表該檔案或資料夾不存在，所以這裡只會處理非空白的狀況
				$check_attr = 'dir';

				// 沒有match底下的檢查，就代表這個檔案或資料夾可能在之前的版本被砍掉了
				$match = '';

				if($cmd_result != ''){
					$array_infofile = explode("\n", `$cmd`);
					// 這裡沒有match，也不意外，因為svninfo會抓到很多行
					// 而我又是一行一行的檢查
					// @k 自動編號
					// @v svninfo輸出的每一行內容
					foreach($array_infofile as $k => $v){
						if($v == '') continue;
						if(preg_match('/^Node Kind: (directory|file)/', $v, $matches)){
							if($matches[1] == 'file'){
								$check_attr = 'file';
								$match = '1';
							} elseif($matches[1] == 'directory'){
								$check_attr = 'dir';
								$match = '1';
							}
						}
					}
				} else {
					// 留給下面的去判斷，當然不會有好結果
					$check_attr = '*';
				}

				// 沒有match的話，就下一筆吧
				if($match == ''){
					continue;
				}

				// 如果有斜線，就代表有含資料夾，這時要取得最右邊那一個
				if(preg_match('/\//', $export_item)){
					$split_item = split('/', $export_item);
					$export_file = $split_item[count($split_item) - 1];
					array_pop($split_item);

					if(count($split_item) > 1){
						$export_dir = join('/', $split_item);
					} elseif(count($split_item) == 1){
						$export_dir = $split_item[0];
					} else {
						$export_dir = '/';
					}

					// 如果是空白，代表是資料夾，要在檔案名稱後面加上斜線
					if($check_attr == 'dir'){
						$export_file .= '/';
					}

					if($check_attr != '*'){
						$returnarray['modify'][$export_dir][] = $export_file;
					}
				// 如果沒有斜線，就代表是最上層
				} else {
					// 如果是空白，代表是資料夾，要在檔案名稱後面加上斜線
					if($check_attr == 'dir'){
						$export_item .= '/';
					}

					if($export_item != '' and $check_attr != '*'){
					   $returnarray['modify']['/'][] = $export_item;
					}
				}
			}

			// 刪除的功能，不需要判斷是不是資料夾
			if(preg_match('/   (D) (.*)/', $val, $matches)){

				$export_item = substr($matches[2], 1);
				$export_item = substr($export_item, strlen($svn->svnurl));

				// 如果有啟用分支功能，就把branches的資料夾去掉
				if($this->branches != ''){
					$export_item = substr($export_item, strlen($this->branches)+1);
				}

				$returnarray['delete'][] = $export_item;
			}
		}

		$modify = $returnarray['modify'];

		if(count($modify) > 0){
			// @dir 資料夾名稱
			// @items 檔案或資料夾集合
			foreach($modify as $dir => $items){

				// 存放檢查重覆item值的陣列變數
				$checkDuplicate = array();

				// @key 陣列編號
				// @item 檔案或者是資料夾
				foreach($items as $key => $item){

					// 檢查是否有重覆的item值
					// 有的話就把它給砍了
					if($checkDuplicate[$item] == '1'){
						unset($returnarray['modify'][$dir][$key]);
						continue;
					} else {
						$checkDuplicate[$item] = '1';
					}

				}
			}
		} 

		return $returnarray;
	} // end function getModifyListBySvnLog

	/*
	 * 以遞迴的方式顯示資料夾內的檔案以及其大小，並存成陣列
	 * 比較特別的是，會多了一層$dirs裡面的元素
	 * 所以會變成dirs/dir/file/fileattr的樣子
	 *
	 * @dirs array 如果沒有放值進去，就會去處理設定檔裡面的東西
	 */
	public function getFileList($dirs = array())
	{

		$returnarray = array();

		foreach($dirs as $dirkey => $dir){
			$ls_cmd = $this->lang.' && ls -lGgR --sort none '.$dir;
			$ls_result = `$ls_cmd`;
			$ls_array = explode("\n", $ls_result);

			$tmps = array();

			// 把它變成3層的結構
			foreach($ls_array as $key => $line){

				if(preg_match('/^(.*):$/', $line, $matches)){
					$fulldir = $matches[1];
					if($fulldir == $dir){
						$subkey = '/';
					} else {
						$subkey = substr($fulldir, strlen($dir)+1);
					}
					$subitem = 'start';
					continue;
				}

				if(trim($line) == '' and $subkey != '' and $subitem == 'start'){
					$returnarray[$dir][$subkey] = $tmps;
					$tmps = array();
					$subitem = '';
					$subkey = '';
				}

				if($subitem == 'start'){
					//-rw-r--r-- 1 1460 Feb 10 12:27 diff.php
					if(preg_match('/^[a-z-]{10}\s+(\d+)\s+(\d+)\s+\w+\s+\d+\s+\d{2}:\d{2}\s+(.*)$/', $line, $matches)){

						// 代表這個資料夾裡面，有幾個檔案
						// 或者是有幾個hard link指向到這個物件上面
						$hardlink_number = $matches[1];

						$filesize = $matches[2];
						$filename = $matches[3];

						// 這裡不處理資料夾
						if($hardlink_number > 1 and $filesize == '4096'){
							continue;
						} else {
							$tmps[$filename] = array(
												'md5' => md5_file($this->addDirSlashes($fulldir, $filename)),
												'size' => $filesize,
											);
						}


					// 比較舊的檔案，會使用這種方式來顯示檔案
					//-rw-r--r-- 1   2644 Oct 13  2007 liu5.png
					} elseif(preg_match('/^[a-z-]{10}\s+(\d+)\s+(\d+)\s+\w+\s+\d+\s+\d{4}\s+(.*)$/', $line, $matches)){

						// 代表這個資料夾裡面，有幾個檔案
						// 或者是有幾個hard link指向到這個物件上面
						$hardlink_number = $matches[1];

						$filesize = $matches[2];
						$filename = $matches[3];

						// 這裡不處理資料夾
						if($hardlink_number > 1 and $filesize == '4096'){
							continue;
						} else {
							$tmps[$filename] = array(
												'note' => 'old file',
												'md5' => md5_file($this->addDirSlashes($fulldir, $filename)),
												'size' => $filesize,
											);
						}
					} elseif(preg_match('/^total (\d+)$/', $line, $matches)){
						// it's mean total XXX KBytes
					} else {
						// maybe os is difference by ubuntu or centos??!!
						if($this->debug) echo 'ERROR preg_match BY =>'.$line."\n";
					}
				} // subitem = start
			}
		} // foreach dirs

		return $returnarray;

	} // end function getFileList


	/*
	 * 使用版本號去取得修改過的檔案詳細資訊
	 * 其實是使用svn checkout and update來取得資訊
	 * 相依函式: getModifyList(上層)
	 *
	 * 這個函式測起來沒有什麼問題，所以就直接改名成getModifyDetail
	 * 本來的名稱是getModifyDetailBySvnUpdate
	 *
	 * 關於分支功能，這裡列入觀察
	 *
	 * @revision string 版本號，包含(:)
	 * @tmpdir string 存放複本的資料夾
	 */
	public function getModifyDetail($revision, $tmpdir){

		// 建立tmpdir資料夾
		mkdir($tmpdir, 0777, true);

		$dest_repo = $tmpdir.'/'.$this->svnrepo;

		// 如果是要處理第一版，就先砍了目標目錄，然後checkout第一版
		if($revision == '1'){
			$rmcmd = 'rm -rf '.$dest_repo;
			if($this->debug) echo $rmcmd."\n";
			`$rmcmd`;
			$checkoutcmd = $this->svnCheckout('1', '', $dest_repo);
			if($this->debug) echo $checkoutcmd."\n";
			`$checkoutcmd`;
			return;
		} else {
			$updatecmd = $this->svnUpdate($revision, $dest_repo);
			if($this->debug) echo $updatecmd."\n";
			`$updatecmd`;
		}

		/*
		 * 因為這裡取得到的只是修改的列表而以
		 * 所以接下來還要去取得它的檔案大小與MD5
		 */
		$result_all = $this->getModifyList($revision);

		foreach($result_all['modify'] as $dir => $files){
			$tmps = array();
			foreach($files as $file_seq => $file){
				if($file == '') continue;
				$dest_file = join('/', array($dest_repo, $dir, $file));

				// 如果是資料夾，就跳過
				if(is_dir($dest_file)){
					continue;
				}

				$tmps[$file] = array(
							'size' => filesize($dest_file),
							'md5' => md5_file($dest_file),
						);
			}
			$returnarray[$dir] = $tmps;
		}
				
		return $returnarray;		

	} // end function getModifyDetail

	/*
	 * 使用版本號去取得該版本所會取得到的所有檔案詳細資訊
	 *
	 * 關於分支功能，這裡列入觀察
	 *
	 * @revision string 單一版本號
	 * @tmpdir string 存放複本的資料夾
	 */
	public function getFullDetail($revision, $tmpdir){

		// 建立tmpdir資料夾
		mkdir($tmpdir, 0777, true);

		$dest_repo = join('/', array($tmpdir, $this->svnrepo));

		// 在checkout之前，先把暫存資料夾刪掉
		$rmcmd = 'rm -rf '.$dest_repo;
		if($this->debug) echo $rmcmd."\n";
		`$rmcmd`;

		// checkout
		$checkoutcmd = $this->svnCheckout($revision, '', $dest_repo);
		if($this->debug) echo $checkoutcmd."\n";
		`$checkoutcmd`;

		$filelists = $this->getFileList(array($dest_repo));
		return $filelists[$dest_repo];
	} // end function getFullDetail

	/*
	 * 取得修改過的Svn版本檔案結構的陣列內容
	 */
	public function getIniFile($repo, $revision){

		$file = join('/', array($this->inidir, $repo, $revision)).'.php';

		if(!file_exists($file)){
			return array();
		} else {
			require $file;
		}

		return $modifyname;
	}

	/*
	 * 取得完整Svn版本結構的陣列內容
	 */
	public function getFullIniFile($repo, $revision){
	
		// 這個是for 測試機的svn check
		//$file = $this->addDirSlashes('/home/svn/'.$repo.'/hooks', $this->fullinidir, $revision).'.php';
		$file = join('/', array($this->fullinidir, $repo, $revision)).'.php';

		if(!file_exists($file)){
			return array();
		} else {
			require $file;
		}

		return $modifyname;
	}

	/*
	 * 比對網頁伺服器上的檔案，與svn的ini對應檔(版本集合)，之間有沒有什麼差別
	 *
	 * @version	array	要比對的patch版本
	 * @varBs	array	檔案伺服器上的檔案陣列，可能會有多個資料夾在裡面
	 */
	public function patchDiff($version, $varBs){

		$returnarray = array();

		$patchData = $this->getPatchVersionData($version);

		foreach($patchData as $repo => $revisions){

			$varB_parentname = $this->getVarbDirName($repo);
			$varB = $varBs[$varB_parentname];

			// 檢查是單一版本號，還是區間版本號
			if(preg_match('/:/', $revisions)){
				$revision_split = split(':', $revisions);
				$first_revision = $revision_split[0];
				$revision = $revision_split[1];
			} else {
				$first_revision = $revisions;
				$revision = $revisions;
			}

			for($x=$first_revision;$x<=$revision;$x++){
				$varA = $this->getIniFile($repo, $x);

				$this->svnrepo = $repo;
				$returnarray[$repo] = $this->arrayDiff($varA, $varB);
			}

		} // end foreach

		return $returnarray;
	} // end function patchDiff

	/*
	 * 純比較兩個檔案陣列
	 *
	 * 這個函式會被完整比對所使用，或者是patch比對
	 *
	 * @varA	array	svn上所匯出的東西(也就是getFullDetail所匯出的)
	 * @varB	array	檔案伺服器上的檔案陣列，只會有一個資料夾的資訊在裡面
	 */
	public function arrayDiff($varA, $varB){

		$returnarray = array();

		$ignore_dir = $this->getIgnoreDir($this->svnrepo);
		$ignore_file = $this->getIgnoreFile($this->svnrepo);

		foreach($varA as $dir => $layer1){

			// 檢查乎略資料夾清單
			if($ignore_dir[$dir] == '1'){
				continue;
			}

			foreach($layer1 as $file => $fileattr){

				// 檢查乎略檔案清單
				if($ignore_file[$dir.'/'.$file] == '1'){
					continue;
				}

				$varA_size = $fileattr['size'];
				$varA_md5 = $fileattr['md5'];

				// 先取得與檢查檔案大小
				$varB_size = $varB[$dir][$file]['size'];
				$varB_md5 = $varB[$dir][$file]['md5'];

				// 可能是檔案不存在
				if($varB_size == ''){
					$returnarray[$dir][$file] = 'NOEXIST';
					continue;
				}

				// 開始比對檔案大小
				if($varB_size != $varA_size){
					$returnarray[$dir][$file] = 'NOPATCH';
				} else {
					// 為了把一個特別的狀況抓出來
					// 就是檔案大小一樣，但是內容不一樣的情況
					if($varB_md5 != $varA_md5){
						$returnarray[$dir][$file] = 'SMALLCHANGE';
					} else {
						//$returnarray[$dir][$file] = 'PATCHED';
					}
				}
			}
		}

		return $returnarray;
	} // end function arrayDiff

}
