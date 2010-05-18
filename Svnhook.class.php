<?php

class SvnHook extends Svn {

	// 當前Hook所處理的版本號
	protected $revision_hook;

	// 完整的版本號
	protected $revision_range;

	// svn export的暫存父資料夾，例如/home/svn/dontuse/killme
	protected $tmpdir;

	// 放置模組的svn export 暫存資料夾
	protected $module_tmpdir;

	// 因為Hook裡面有很多模組
	protected $modulename;

	// 忽略檔案列表
	protected $ignorefiles;

	// 忽略資料夾列表
	protected $ignoredirs;

	// 不重覆的編號，這裡通常都是以日期+時間為主
	protected $seq;

	// svn資料夾名稱(例如/home/svn) 
	protected $svndir;

	// svn hook資料夾名稱
	protected $hookdir = 'hooks';

	// 放置script的地方
	protected $scriptdir = 'local';

	// script的檔案名稱
	protected $script_filename;

	// 存放版本號的檔案，當然這個是每一個模組都需要有的
	// 規則範例(/home/svn/repo01/module01-revision.txt)
	protected $revision_filename = '-revision.txt';

	// hook的Log
	protected $logfile = 'log.txt';

	/*
	 * Hook Init 
	 *
	 * @v_svn_dir svn資料夾名稱(例如/home/svn)
	 * @v_revision_hook 當前Hook所處理的版本號
	 * @v_tmpdir 存放模組暫存資料夾的資料夾
	 * @v_module_name 模組名稱
	 * @v_ignorefiles 忽略的檔案清單
	 * @v_ignoredirs 忽略的資料夾清單
	 */
	function hookInit($v_svn_dir, $v_revision_hook, $v_tmpdir,
		$v_module_name, $v_ignorefiles = array(), $v_ignoredirs = array()) {

		// hook基礎的資料夾變數
		$this->seq = date("Ymd-His");
		$this->modulename = $v_module_name;
		$this->svndir = $v_svn_dir;
		$this->hookdir = $this->svndir.'/'.$this->svnrepo.'/'.$this->hookdir;
		$this->scriptdir = $this->hookdir.'/'.$this->scriptdir;
		$this->tmpdir = $v_tmpdir;
		$this->module_tmpdir = $this->tmpdir.'/'.$this->svnrepo.'-'.$this->seq.'-'.$this->modulename;

		// 建立資料夾
		mkdir($this->scriptdir, 0777, true);
		mkdir($this->tmpdir, 0777, true);
		mkdir($this->module_tmpdir, 0777, true);


		// hook基礎的檔案變數
		$this->script_filename = $this->scriptdir.'/'.$this->seq.'-'.$this->modulename.'-rev'.$v_revision_hook.'.sh';
		$this->revision_filename = $this->hookdir.'/'.$this->modulename . $this->revision_filename;
		$this->logfile = $this->hooks.'/'.$this->logfile;

		// 版本號的部份
		$this->revision_hook = $v_revision_hook;
		$this->revision_range = $this->getRevision();

		// 忽略清單的部份
		$this->ignorefiles = $this->checkArray($v_ignorefiles);
		$this->ignoredirs = $this->checkArray($v_ignoredirs);


	}

	/*
	 * 模組執行的前置動作
	 * 每一個模組都會先執行這個動作
	 *
	 * @is_manual 如果有1這個值，代表是手動的要使用
	 */
	function hookExport($is_manual = '') { 

		if($is_manual == ''){
			$revision = $this->revision_range;
		} else {
			$revision = $this->revision_hook;
		}

		// 從svn裡面取得修改以及刪除的列表
		$all = $this->getModifyListBySvnLog($revision);
		$modify = $all['modify'];
		$delete = $all['delete'];

		if(count($modify) > 0){
			// @dir 資料夾名稱
			// @items 檔案或資料夾集合
			foreach($modify as $dir => $items){

				// 存放檢查重覆item值的陣列變數
				$checkDuplicate = array();

				// @key 陣列編號
				// @item 檔案或者是資料夾
				foreach($items as $key => $item){

					// 看item是資料夾還是檔案
					$is_dir = '';
					if(preg_match('/\/$/', $item)){
						$is_dir = '1';
					}

					// 處理的結果會是右邊的值 download/aaa/bbb.txt
					if($dir == '/'){
						$fullname = $item;
					} else {
						$fullname = $dir.'/'.$item;
						mkdir($this->module_tmpdir.'/'.$dir, 0777, true);
					}

					// 忽略不需要輸出的檔案
					// 這裡只會比對檔案
					// 也就是說，以檔案的絕對路徑，完全符合才會忽略
					$is_ignore = '';
					if(count($this->ignorefiles) > 0){
						if($is_dir == ''){
							foreach($this->ignorefiles as $k => $ignorefile){
								if($ignorefile == $fullname){
									$is_ignore = '1';
								}
							}
						}
						if($is_ignore == '1'){
							unset($all['modify'][$dir][$key]);
							continue;
						}
					}

					// 忽略資料夾
					// 這裡會比對檔案及資料夾，只要開頭符合條件就忽略掉
					$is_ignore = '';
					if(count($this->ignoredirs) > 0){
						foreach($this->ignoredirs as $k => $ignoredir){
							if(preg_match('/^'.$ignoredir.'\//', $fullname)){
								$is_ignore = '1';
							}
						}
						if($is_ignore == '1'){
							unset($all['modify'][$dir][$key]);
							continue;
						}
					}

					// 不管是什麼模組，都會先export到暫存的資料夾裡面
					$exportcmd = $this->svnExport('', '', $this->module_tmpdir.'/'.$fullname, $fullname);
					`$exportcmd`;	

				}
			}
		} 

		// 刪除檔案或資料夾
		if(count($delete) > 0){
			// @k 自動編號
			foreach($delete as $k => $fullfile){
				// 忽略不需要輸出的檔案
				// 這裡只會比對檔案
				// 也就是說，以檔案的絕對路徑，完全符合才會忽略
				$is_ignore = '';
				if(count($this->ignorefiles) > 0){
					foreach($this->ignorefiles as $kk => $ignorefile){
						if($ignorefile == $fullfile){
							$is_ignore = '1';
						}
					}
					if($is_ignore == '1'){
						unset($all['delete'][$k]);
						continue;
					}
				}

				// 忽略資料夾
				// 這裡會比對檔案及資料夾，只要開頭符合條件就忽略掉
				$is_ignore = '';
				if(count($this->ignoredirs) > 0){
					foreach($this->ignoredirs as $k => $ignoredir){
						if(preg_match('/^'.$ignoredir.'\//', $fullfile)){
							$is_ignore = '1';
						}
					}
					if($is_ignore == '1'){
						unset($all['delete'][$k]);
						continue;
					}
				}
			}
		} // delete section

		// 最後，把modifylist的陣列回傳，讓模組能夠接手使用
		return $all;
	} // hookExport

	/*
	 * 模組處理的最後流程
	 * 1.執行scripts
	 * 2.把暫存檔砍掉
	 *
	 * @v_cmd 要執行的腳本(含跳行)
	 * @v_debug 要不要debug，例如有開的話，就不去刪除暫存檔
	 */
	function hookExecute($v_cmd, $v_debug){

		// 結束前，把存放patch的資料夾(TmpDir)給砍了
		if(!$v_debug){
			$v_cmd .= 'rm -rf '.$this->module_tmpdir;
		}

		// 寫入更新批次檔以及執行它
		file_put_contents($this->script_filename, $v_cmd);
		$v_cmd = 'bash '.$this->script_filename.' > /dev/null &';
		`$v_cmd`;

	}

	/*
	 * 檢查儲存在檔案裡面的版本號欄位
	 * 這個函數目前是給getRevision函數使用
	 * 
	 * @v_revision_file 儲存在檔案裡面的版本號
	 */
	function checkRevision($v_revision_file){

		// 補上版本區間
		if($v_revision_file != ''){
			$revision = $v_revision_file.':'.$this->revision_hook;
		} else {
			$revision = '1';
		}

		return $revision;
	}

	/*
	 * 取得在檔案裡面的版本號
	 */
	function getRevision(){

		// 從文字檔讀取之前的版本號進來
		if(!file_exists($this->revision_filename)){
			$cmd = 'touch '.$this->revision_filename;
			`$cmd`;
		}

		$revisions = file($this->revision_filename);
		$revision_file = trim($revisions[0]);

		$revision = $this->checkRevision($revision_file);

		return $revision;
	}

	/*
	 * 寫入版本號到檔案裡面
	 *
	 * @v_file 版本號的絕對路徑
	 */
	function storeRevision(){
		$cmd = 'echo '.($this->revision_hook + 1).' > '.$this->revision_filename;
		`$cmd`;
	}

	function getTmpDirByModule(){
		return $this->module_tmpdir;
	}

	/*
	 * 檢查以Zend Config:Ini所讀取的空白陣列
	 * 如果ini的值是空白，那php的對應陣列，也要改成空陣列
	 *
	 * @v_array array 要檢查的陣列
	 */
	function checkArray($v_array){
		if(count($v_array) == 1 and $v_array[0] == ''){
			return array();
		} else {
			return $v_array;
		}
	}

	/*
	 * @is_exit int 如果是1，代表要寫log，也要強制離開
	 */
	function error_msg($message, $is_exit = 0){
		if($is_exit){ 
			fwrite(STDERR, $message."\n");
			exit(1);
		}
	}

}
