#!/usr/bin/php
<?php

/*
 * 手動執行的版本
 * 不會匯出fullini
 * 也不會寫入版本號
 */

require 'Svn-branches.class.php';
require 'Svnhook.class.php';
require 'Zend/Config.php';
require 'Zend/Config/Ini.php';

// 最開始可被修改的變數
$svnroot = '/home/svn';
$configfile = $svnroot.'/config5.ini'; // 開發用
$nextline = "\n";

// 這個部份跟exportweb的不太一樣，純repo名稱
$v_repo = $argv[1];
$v_rev = $argv[2];

if($v_repo == ''){
	$dirlists = dirList($svnroot.'/'.$v_repo);

	if(count($dirlists) <= 0){
		echo '[ERROR] arg01 is require, but svnroot is no repo'.$nextline;
		exit;
	}

	echo '[ERROR] arg01 need repo, list by here:'.$nextline;
	foreach($dirlists as $key => $value){
		if(preg_match('/\./', $value)){
			continue;
		} else {
			echo $value.$nextline;
		}
	}
	exit;
}

// 如果沒有帶引數進來，那就離開吧
if($v_rev == ''){
	echo '[ERROR] arg02 is revision, ex: 123(單一版本號)、123:125(區間版本號)、如果是兩個連續版本號，保險起見請分兩次做'.$nextline;
	exit;
}

$repo = $v_repo;

$hookdir = $svnroot.'/'.$v_repo.'/hooks';
$logfile = $hookdir.'/log.txt';

// 載入共用的設定檔，當然是選擇自己的repo來使用
$config = new Zend_Config_Ini($configfile, $repo) or error_msg($logfile, 'load config.ini fail', 1);

// 載入設定檔後，會有一些變數需要先指定
$module = $config->modules;

/*
 * local的模組
 */
if($module->local->manual == '1'){

	$module_name = 'local';
	$mkdircmd = '';
	$returncmd = '';

	$hook = new Svnhook($repo, $config->svn->url, $module->local->branches, $config->svn->dotsubversion);

	$hook->hookInit(
			$config->svn->dir,
			$v_rev,
			$config->svn->tmp,
		   	$module_name, 
			$module->local->ignore->file->toArray(),
			$module->local->ignore->dir->toArray()
		);

	$module_tmpdir = $hook->getTmpDirByModule();

	$allresult = $hook->hookExport('1');

	$modifylist = $allresult['modify'];
	$deletelist = $allresult['delete'];

	if(count($modifylist) > 0){

		$mkdircmd .= '# ['.$module_name.']'.$nextline;

		foreach($modifylist as $dir => $items){
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
				}

				if($is_dir == '1'){
					$mkdircmd .= '# Item => dir'.$nextline;
					$mkdircmd .= 'mkdir -p '.$module->local->dir.'/'.$fullname.$nextline;
				} else {
					if($dir != '/'){
						$mkdircmd .= '# Item => file, and path is not root'.$nextline;
						$mkdircmd .= 'mkdir -p '.$module->local->dir.'/'.$dir.$nextline;
					}
					$returncmd .= 'cp --force '.$module_tmpdir.'/'.$fullname.' '.$module->local->dir.'/'.$fullname.$nextline;
				}
			}
		}
	} else {
		if($config->debug) error_msg($logfile, '[NOTICE] '.$module_name.' module modifylist is empty');
	}

	// 刪除檔案或資料夾
	if(count($deletelist) > 0){
		foreach($deletelist as $k => $fullfile){
			$returncmd .= '# ['.$module_name.']'.$nextline;
			$returncmd .= 'rm -rf '.$module->local->dir.'/'.$fullfile.$nextline;
		}
	} else {
		if($config->debug) error_msg($logfile, '[NOTICE] '.$module_name.' module deletelist is empty');
	}

	// 把建立資料夾的腳本，放到上面去
	$returncmd = $mkdircmd . $returncmd;

	// 上傳最後，更改檔案擁有者，如果有指定，才會跑這裡
	if(count($modifylist) > 0){
		$returncmd .= 'chown -R '.$module->local->uid.':'.$module->local->gid.' '.$module->local->dir.$nextline;
	} 

	// 執行腳本
	$hook->hookExecute($returncmd, $config->debug);
} // local module
 
/*
 * local2的模組
 */
if($module->local2->manual == '1'){

	$module_name = 'local2';
	$mkdircmd = '';
	$returncmd = '';

	$hook = new Svnhook($repo, $config->svn->url, $module->local2->branches, $config->svn->dotsubversion);

	$hook->hookInit(
			$config->svn->dir,
			$v_rev,
			$config->svn->tmp,
		   	$module_name, 
			$module->local2->ignore->file->toArray(),
			$module->local2->ignore->dir->toArray()
		);

	$module_tmpdir = $hook->getTmpDirByModule();

	$allresult = $hook->hookExport('1');

	$modifylist = $allresult['modify'];
	$deletelist = $allresult['delete'];

	/*
	 * local2_files = array(
	 *    'svn.css' => '/var/www/html/svn_export/css/target.css',
	 * );
	 */
	$local2_files_tmp = $module->local2->svnfile->toArray();
	$local2_files_tmp2 = $module->local2->file->toArray();
	if(count($local2_files_tmp) == 1 and $local2_files_tmp[0] == ''){
		$local2_files = array();
	} else {
		foreach($local2_files_tmp as $k => $v){
			$local2_files[$v] = $local2_files_tmp2[$k];
		}
	}

	/*
	 * 處理後的結果是:
	 * local2_dirs = array(
	 *    'svnimage' => '/var/www/html/svn_export/images',
	 * );
	 */
	$local2_dirs_tmp = $module->local2->svndir->toArray();
	$local2_dirs_tmp2 = $module->local2->dir->toArray();
	if(count($local2_dirs_tmp) == 1 and $local2_dirs_tmp[0] == ''){
		$local2_dirs = array();
	} else {
		foreach($local2_dirs_tmp as $k => $v){
			$local2_dirs[$v] = $local2_dirs_tmp2[$k];
		}
	}

	if(count($modifylist) > 0){

		$mkdircmd .= '# ['.$module_name.']'.$nextline;

		foreach($modifylist as $dir => $items){
			foreach($items as $key => $item){

				// 看item是資料夾還是檔案
				$is_dir = '';
				if(preg_match('/\/$/', $item)){
					$is_dir = '1';
				}

				// 處理的結果會是右邊的值 download/aaa/bbb.txt
				// 別忘了修改我☆
				if($dir == '/'){
					$fullname = $item;
				} else {
					$fullname = $dir.'/'.$item;
				}

				if(count($local2_files) > 0){
					// 有符合才會處理
					foreach($local2_files as $k => $v){
						if($k == $fullname and $is_dir != '1'){
							if($dir != '/'){
								$mkdircmd .= '# [local2]'.$nextline;
								$mkdircmd .= 'mkdir -p '.$v.'/'.$dir.$nextline;
							}
							$returncmd .= '# [local2]'.$nextline;
							$returncmd .= 'cp --force '.$module_tmpdir.'/'.$fullname.' '.$v.'/'.$fullname.$nextline;
						}
					}
				}
				if(count($local2_dirs) > 0){
					foreach($local2_dirs as $k => $v){
						if(preg_match('/^'.$k.'/', $dir)){
							$mkdircmd .= '# [local2]'.$nextline;

							// 這裡類似branches的處理行為
							// 把branches給濾掉
							$new_dir = substr($dir, strlen($k)+1);

							if(strlen($new_dir) > 0){
								if($is_dir == '1'){
									$mkdircmd .= 'mkdir -p '.$v.'/'.$new_dir.'/'.$item.$nextline;
								} else {
									$returncmd .= 'cp --force '.$module_tmpdir.'/'.$fullname.' '.$v.'/'.$new_dir.'/'.$item.$nextline;
								}
							} else {
								if($is_dir == '1'){
									$mkdircmd .= 'mkdir -p '.$v.'/'.$item.$nextline;
								} else {
									$returncmd .= 'cp --force '.$module_tmpdir.'/'.$fullname.' '.$v.'/'.$item.$nextline;
								}
							}
						}
					}
				}
			}
		}
	} else {
		if($config->debug) error_msg($logfile, '[NOTICE] '.$module_name.' module modifylist is empty');
	}

	// 刪除檔案或資料夾
	if(count($deletelist) > 0){
		foreach($deletelist as $k => $fullfile){
			if(count($local2_files) > 0){
				// 有符合才會處理
				foreach($local2_files as $svnfile => $targetfile){
					if($svnfile == $fullfile){
						$returncmd .= '# [local2]'.$nextline;
						$returncmd .= 'rm -rf '.$targetfile.$nextline;
					}
				}
			}
			if(count($local2_dirs) > 0){
				foreach($local2_dirs as $svndir => $targetdir){
					if(preg_match('/^'.$svndir.'/', $fullfile)){
						$new_dir = substr($fullfile, strlen($svndir)+1);

						// 不會去刪除本層
						if(strlen($new_dir) > 0){
							$returncmd .= '# [local2]'.$nextline;
							$returncmd .= 'rm -rf '.$targetdir.'/'.$new_dir.$nextline;
						}
					}
				}
			}
		}
	} else {
		if($config->debug) error_msg($logfile, '[NOTICE] '.$module_name.' module deletelist is empty');
	}

	// 把建立資料夾的腳本，放到上面去
	$returncmd = $mkdircmd . $returncmd;

	// 執行腳本
	$hook->hookExecute($returncmd, $config->debug);
} // local2 module

/*
 * ftp的模組
 */
if($module->ftp->manual == '1'){

	$module_name = 'ftp';

	$returncmd = '';
	$ftpcmd = '';

	/*
	 * 1 => ftp host
	 * 2 => ftp user
	 * 3 => ftp pass
	 * 4 => module tmpdir
	 * 5 => file action
	 */
	$template = '/usr/bin/wermit <<END_OF_SESSION
ftp %s
%s
%s
lcd %s
%s
bye
quit
END_OF_SESSION';

	$hook = new Svnhook($repo, $config->svn->url, $module->ftp->branches, $config->svn->dotsubversion);

	$hook->hookInit(
			$config->svn->dir,
			$v_rev,
			$config->svn->tmp,
		   	$module_name, 
			$module->ftp->ignore->file->toArray(),
			$module->ftp->ignore->dir->toArray()
		);

	$module_tmpdir = $hook->getTmpDirByModule();

	$allresult = $hook->hookExport('1');

	$modifylist = $allresult['modify'];
	$deletelist = $allresult['delete'];

	if(count($modifylist) > 0){
		$returncmd .= 'mput /recursive .'.$nextline;
	} else {
		if($config->debug) error_msg($logfile, '[NOTICE] '.$module_name.' module modifylist is empty');
	}

	// 刪除檔案或資料夾
	if(count($deletelist) > 0){
		foreach($deletelist as $k => $fullfile){
			$returncmd .= 'rm '.$module->ftp->dir.'/'.$fullfile.$nextline;
		}
	} else {
		if($config->debug) error_msg($logfile, '[NOTICE] '.$module_name.' module deletelist is empty');
	}

	// 把建立資料夾的腳本，放到上面去
	$ftpcmd = sprintf($template, $module->ftp->host, $module->ftp->user, $module->ftp->pass, $module_tmpdir, $returncmd);

	// 執行腳本
	$hook->hookExecute($ftpcmd, $config->debug);
} // ftp module

/*
 * ssh的模組
 */
if($module->ssh->manual == '1'){

	$module_name = 'ssh';
	$returncmd = '';

	$hook = new Svnhook($repo, $config->svn->url, $module->ssh->branches, $config->svn->dotsubversion);

	$hook->hookInit(
			$config->svn->dir,
			$v_rev,
			$config->svn->tmp,
		   	$module_name, 
			$module->ssh->ignore->file->toArray(),
			$module->ssh->ignore->dir->toArray()
		);

	$module_tmpdir = $hook->getTmpDirByModule();

	$allresult = $hook->hookExport('1');

	$modifylist = $allresult['modify'];
	$deletelist = $allresult['delete'];

	// 刪除檔案或資料夾
	if(count($deletelist) > 0){
		foreach($deletelist as $k => $fullfile){
			$returncmd .= 'ssh '.$module->ssh->host.' \'rm -rf '.$module->ssh->dir.'/'.$fullfile.'\''.$nextline;
		}
	} else {
		if($config->debug) error_msg($logfile, '[NOTICE] '.$module_name.' module deletelist is empty');
	}

	// 如果有啟用ssh更新模組，那批次檔最後，要加上更改權限
	if(count($modifylist) > 0 or count($deletelist) > 0){
		$returncmd .= 'scp -r '.$module_tmpdir.'/* '.$module->ssh->host.':'.$module->ssh->dir.'/'.$nextline;
		// ssh teltel-rd-web-svnuse 'chown -R apache:apache /var/www/html/aaaaaxxx'
		$returncmd .= 'ssh '.$module->ssh->host.' \'chown -R '.$module->ssh->uid.':'.$module->ssh->gid.' '.$module->ssh->dir.'\''.$nextline;
	} 

	// 執行腳本
	$hook->hookExecute($returncmd, $config->debug);
} // ssh module

/*
 * ssh2的模組
 */
if($module->ssh2->manual == '1'){

	$module_name = 'ssh2';
	$returncmd = '';

	$hook = new Svnhook($repo, $config->svn->url, $module->ssh2->branches, $config->svn->dotsubversion);

	$hook->hookInit(
			$config->svn->dir,
			$v_rev,
			$config->svn->tmp,
		   	$module_name, 
			$module->ssh2->ignore->file->toArray(),
			$module->ssh2->ignore->dir->toArray()
		);

	$module_tmpdir = $hook->getTmpDirByModule();

	$allresult = $hook->hookExport('1');

	$modifylist = $allresult['modify'];
	$deletelist = $allresult['delete'];

	$ssh2_files_source = $hook->checkArray($module->ssh2->files_source->toArray());
	$ssh2_files_target = $hook->checkArray($module->ssh2->files_target->toArray());

	$ssh2_dirs_source = $hook->checkArray($module->ssh2->dirs_source->toArray());
	$ssh2_dirs_target = $hook->checkArray($module->ssh2->dirs_target->toArray());

	if(count($modifylist) > 0){
		// @k 自動編號
		// @v file_source
		foreach($ssh2_files_source as $k => $v){
			$returncmd .= 'scp -r '.$module_tmpdir.'/'.$v.' '.$module->ssh2->host.':'.$module->ssh2->dir.'/'.$ssh2_files_target[$k].$nextline;
			$returncmd .= 'ssh '.$module->ssh2->host.' \'chown -R '.$module->ssh2->uid.':'.$module->ssh2->gid.' '.$module->ssh2->dir.'/'.$ssh2_files_target[$k].'\''.$nextline;
		}
		// @k 自動編號
		// @v dir_source
		foreach($ssh2_dirs_source as $k => $v){
			$returncmd .= 'scp -r '.$module_tmpdir.'/'.$v.'/* '.$module->ssh2->host.':'.$module->ssh2->dir.'/'.$ssh2_dirs_target[$k].'/'.$nextline;
			$returncmd .= 'ssh '.$module->ssh2->host.' \'chown -R '.$module->ssh2->uid.':'.$module->ssh2->gid.' '.$module->ssh2->dir.'/'.$ssh2_dirs_target[$k].'\''.$nextline;
		}
	} else {
		if($config->debug) error_msg($logfile, '[NOTICE] '.$module_name.' module modifylist is empty');
	}

	// 刪除檔案或資料夾
	if(count($deletelist) > 0){
		foreach($deletelist as $k => $fullfile){
			// @k 自動編號
			// @v file_source
			foreach($ssh2_files_source as $k => $v){
				if($fullfile == $v){
					$returncmd .= 'ssh '.$module->ssh2->host.' \'rm -rf '.$module->ssh2->dir.'/'.$fullfile.'\''.$nextline;
				}
			}
			// @k 自動編號
			// @v dir_source
			foreach($ssh2_dirs_source as $k => $v){
				if(preg_match('/^'.$fullfile.'/', $v)){
					$returncmd .= 'ssh '.$module->ssh2->host.' \'rm -rf '.$module->ssh2->dir.'/'.$fullfile.'\''.$nextline;
				}
			}
		}
	} else {
		if($config->debug) error_msg($logfile, '[NOTICE] '.$module_name.' module deletelist is empty');
	}

	// 執行腳本
	$hook->hookExecute($returncmd, $config->debug);
} // ssh2 module

/*
 * @is_exit int 如果是1，代表要寫log，也要強制離開
 */
function error_msg($logfile, $message, $is_exit = 0){
	// die($error."\n");
	global $config;

	// 寫入設定檔
	if($config->debug){
		file_put_contents($logfile, $message."\n", FILE_APPEND) or die('write hooks log.txt fail');

		// 寫入設定檔後，也把它印出來
		echo $message."\n";
	}

	if($is_exit){ 
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

function dirList ($dir) 
{
    // create an array to hold directory list
    $results = array();

	if(file_exists($dir)){
		// create a handler for the directory
		$handler = opendir($dir);

		// keep going until all files in directory have been read
		while ($file = readdir($handler)) {

			// if $file isn't this directory or its parent, 
			// add it to the results array
			if ($file != '.' && $file != '..')
				$results[] = $file;
		}

		// tidy up: close the handler
		closedir($handler);

		rsort($results);
	}

    // done!
    return $results;
}
