<?php

// 這支程式，是搭配exportcheck-ftp.php的程式，
// 這支程式是要放在遠端的ftp主機上面，並改名為diff.php


$webdir = '/home/user';
$tmpdir = 'dir01';
$tmpfile = 'static_diff.php';

// 因為hihosting會把第一層的資料夾隱藏起來
// 所以我必需先把第一層的資料夾建立起來
$hide_directory = array(
	'blha01',
	'blha02',
	);

$ls_array_source = array();

foreach($hide_directory as $k => $v){
	$ls_array_source_tmp = array();
	$ls_array_source_tmp = ls('*', $webdir.'/'.$v, true);
	foreach($ls_array_source_tmp as $kk => $vv){
		$ls_array_source_tmp[$kk] = $v.'/'.$vv;
	}
	$ls_array_source = array_merge($ls_array_source, $ls_array_source_tmp);
}

// @v dir and file
foreach($ls_array_source as $k => $v){
	//echo $v."\n";
	if(preg_match('/\/$/', $v)){
		//$ls_array[$v] = '';
	} else {
		$split_item = split('/', $v);
		$export_file = $split_item[count($split_item) - 1];
		array_pop($split_item);

		if(count($split_item) > 1){
			$export_dir = join('/', $split_item);
		} elseif(count($split_item) == 1){
			$export_dir = $split_item[0];
		} else {
			$export_dir = '/';
		}

		$ls_array[$export_dir][] = $export_file;
	}
}

$static_diff = array();

// @dir 資料夾名稱
// @v 檔案集合
foreach($ls_array as $dir => $v){
	// @k 檔案自動編號
	// @file 檔案
	foreach($v as $k => $file){
		$fullname = $webdir.'/'.$dir.'/'.$file;
		$static_diff[$dir][$file]['md5'] = md5_file($fullname);
		$static_diff[$dir][$file]['size'] = filesize($fullname);
	}
}

$template = '<?php
$static_diff = %s;
?>';

$file = $webdir.'/'.$tmpdir.'/'.$tmpfile;

$temp = sprintf($template, var_export($static_diff, true));
file_put_contents($file,  $temp);

/**
 * This funtion will take a pattern and a folder as the argument and go thru it(recursivly if needed)and return the list of 
 *               all files in that folder.
 * Link             : http://www.bin-co.com/php/scripts/filesystem/ls/
 * Arguments     :  $pattern - The pattern to look out for [OPTIONAL]
 *                    $folder - The path of the directory of which's directory list you want [OPTIONAL]
 *                    $recursivly - The funtion will traverse the folder tree recursivly if this is true. Defaults to false. [OPTIONAL]
 *                    $options - An array of values 'return_files' or 'return_folders' or both
 * Returns       : A flat list with the path of all the files(no folders) that matches the condition given.
 */
function ls($pattern="*", $folder="", $recursivly=false, $options=array('return_files','return_folders')) {
    if($folder) {
        $current_folder = realpath('.');
        if(in_array('quiet', $options)) { // If quiet is on, we will suppress the 'no such folder' error
            if(!file_exists($folder)) return array();
        }
        
        if(!chdir($folder)) return array();
    }
    
    
    $get_files    = in_array('return_files', $options);
    $get_folders= in_array('return_folders', $options);
    $both = array();
    $folders = array();
    
    // Get the all files and folders in the given directory.
    if($get_files) $both = glob($pattern, GLOB_BRACE + GLOB_MARK);
    if($recursivly or $get_folders) $folders = glob("*", GLOB_ONLYDIR + GLOB_MARK);
    
    //If a pattern is specified, make sure even the folders match that pattern.
    $matching_folders = array();
    if($pattern !== '*') $matching_folders = glob($pattern, GLOB_ONLYDIR + GLOB_MARK);
    
    //Get just the files by removing the folders from the list of all files.
    $all = array_values(array_diff($both,$folders));
        
    if($recursivly or $get_folders) {
        foreach ($folders as $this_folder) {
            if($get_folders) {
                //If a pattern is specified, make sure even the folders match that pattern.
                if($pattern !== '*') {
                    if(in_array($this_folder, $matching_folders)) array_push($all, $this_folder);
                }
                else array_push($all, $this_folder);
            }
            
            if($recursivly) {
                // Continue calling this function for all the folders
                $deep_items = ls($pattern, $this_folder, $recursivly, $options); // :RECURSION:
                foreach ($deep_items as $item) {
                    array_push($all, $this_folder . $item);
                }
            }
        }
    }
    
    if($folder) chdir($current_folder);
    return $all;
}
