<?php

/*
 * 主要是做單個版本的完整比較
 * 就當這個檔案是一個範例就好了^^
 */

require 'Svn.class.php';

$baseurl = 'file:///home/svn';
$repo = 'test';
$branches = '';
$webdir = '/var/www/html/test_export';
$nextline = '<BR />';

$svn = new Svn($repo, $baseurl, $branches);

// get dir and file list
$static_diff = $svn->getFileList(array(
		$webdir,
	)	
);
$varB = $static_diff[$webdir];

$revision = $svn->getLastRevision();

echo 'Last Revision: '.$revision.$nextline;

$varA = $svn->getFullIniFile($repo, $revision);

if(count($varA) <= 0){
	echo '[ERROR] FullINI is empty!!'.$nextline;
	exit;
}

$filestatus = $svn->arrayDiff($varA, $varB);

echo 'File Update Status:'.$nextline.$nextline;

foreach($filestatus as $dir => $file_value){
	foreach($file_value as $file => $status){
		$fullname = $svn->addDirSlashes($repo, $dir, $file);
		if($status == 'NOPATCH'){
			echo '[NOPATCH] '.$fullname.$nextline;
			$errcount++;
		} elseif($status == 'NOEXIST'){ 
			echo '[NOEXIST] '.$fullname.$nextline;
			$errcount++;
		} elseif($status == 'SMALLCHANGE'){
			echo '[SMALLCHANGE] '.$fullname.$nextline;
			$errcount++;
		}
	}
}

if($errcount <= 0){
	echo '[SUCCESS]'.$nextline.$nextline;
}

$filestatus2 = $svn->arrayDiff($varB, $varA);

echo 'Unnecessary File List:'.$nextline.$nextline;

foreach($filestatus2 as $dir => $file_value){
	foreach($file_value as $file => $status){
		$fullname = $svn->addDirSlashes($repo, $dir, $file);
		if($status == 'NOEXIST'){ 
			echo '[UNNECESSARY] '.$fullname.$nextline;
			$errcount2++;
		}
	}
}

if($errcount2 <= 0){
	echo '[EMPTY]'.$nextline;	
}


