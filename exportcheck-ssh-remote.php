<?php

// 這支程式，是搭配exportweb-ssh.php的程式，
// 這支程式是要放在遠端的ssh主機上面，並改名為diff.php

$webdir = '/var/www/html/test_export';
$tmpdir = 'dir01';
$tmpfile = 'static_diff.php';

$svn = new Svn;

// get dir and file list
$static_diff = $svn->getFileList(array(
		$webdir,
	)	
);

$template = '<?php
$static_diff = %s;
?>';

$file = $webdir.'/'.$tmpdir.'/'.$tmpfile;

$temp = sprintf($template, var_export($static_diff, true));
file_put_contents($file,  $temp);

class Svn
{
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
			$ls_cmd = 'LANG=en_US.UTF-8 && ls -lGgR --sort none '.$dir;
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
												'md5' => md5_file($fulldir.'/'.$filename),
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
												'md5' => md5_file($fulldir.'/'.$filename),
												'size' => $filesize,
											);
						}
					} elseif(preg_match('/^total (\d+)$/', $line, $matches)){
						// it's mean total XXX KBytes
					} else {
						// maybe os is difference by ubuntu or centos??!!
						echo 'ERROR preg_match BY =>'.$line."\n";
					}
				} // subitem = start
			}
		} // foreach dirs
		return $returnarray;
	}
}

?>
