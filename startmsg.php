<?php  

header('Content-Type: text/html; charset=utf-8');

$smsusers = 'c:\sms\users.txt';
$smsoutbox = 'c:\sms\outbox';
$smsinbox = 'c:\sms\inbox';

$msgreceived = 'c:\sms\MessagesReceived';
$msgtosend = 'c:\sms\MessagesToSend';




$smsrow = array();
$smslist = fopen($smsusers,'r') or die("ERR_OPEN_FILE");
while(!feof($smslist))
{
$isdn = fgets($smslist,4096);
$handle = fgets($smslist,4096);
array_push($smsrow, $isdn);
}
fclose($smslist);







// Секция SMS -> HFP


echo "\n ------ SMS -> HFP ------\n";

$smsarray = scandir($smsinbox);
$smscount = array_key_last($smsarray);


foreach ($smsarray as $srow) {
    $srow = $smsarray[$smscount];
	$smscount = $smscount - 1;


		$inboxisdn = substr($srow,21);
		$inboxisdn = substr($inboxisdn,0,strpos($inboxisdn,'_'));

If (strlen($srow)>10) {



		// Если ISDN нет в списке юзеров

		If (in_array($inboxisdn,$smsrow) === false) {
		echo $inboxisdn . " -- User not found -- \033[31m[Ignore]\033[0m\n";	
		unlink($smsinbox.'/'.$srow);
		}
			
		
		// Если ISDN есть в списке юзеров

		If (in_array($inboxisdn,$smsrow)) {
		
		$smsfile = fopen($smsinbox.'/'.$srow, 'r') or die("не удалось открыть файл");
		$smstext = trim(fgets($smsfile,4096));
		$smstextforterminal = iconv("Windows-1251", "UTF-8", $smstext);
		fclose($smsfile);
		
		
		$callsign = '';
		
		$smslist = fopen($smsusers,'r') or die("ERR_OPEN_FILE");
		while(!feof($smslist))
		{
		$isdn = fgets($smslist,4096);
		$handle = fgets($smslist,4096);
		
		If ($isdn==$inboxisdn) {
		$callsign = trim($handle);
		$callsign = str_replace("\r\n",'',$handle);
		}
		
		}
		fclose($smslist);
		
		
		
		If (substr($smstext,0,1)=='>') {
		$hfpaddr = trim(substr($smstext,1,strpos($smstext,' ')));
		$textforhfp = $callsign .': ' . trim(substr($smstext,strpos($smstext,' ')));
		echo $inboxisdn . ' '. $callsign. " -- User OK -- Command OK -- " . $smstextforterminal . " -- \033[32m[Send MSG]\033[0m\n";
		
			
			$uta = date('YmdHis');
			$filehfp = $msgtosend.'/'.$uta.'.dat';
			$filehfpnew = $msgtosend.'/'.$uta.'.msg';
			$hfptext = 'to=' . $hfpaddr . ',speed=0,askreq=1,resend=1' . "\n" . $textforhfp . "\n"   ;
			file_put_contents($filehfp, $hfptext);
			rename($filehfp, $filehfpnew);
			sleep(1);
		
			
		} else {
		echo $inboxisdn . " -- Valid user -- Command not found -- \033[31m[Ignore]\033[0m\n";
		}
		


		unlink($smsinbox.'/'.$srow);
		}
		
}
}


// *Секция SMS -> HFP







// Секция HFP -> SMS

echo "\n\n ------ HFP -> SMS ------\n";

$msgarray = scandir($msgreceived);
$count = array_key_last($msgarray);


foreach ($msgarray as $row) {
    $row = $msgarray[$count];
	$count = $count - 1;
		
If (strlen($row)>10) {
	
	$stop_f = strpos($row,'.');
	$start_f = 21;

	
	// Удолим файлы, которые не сообщения

	If (strpos($msgreceived.'/'.$row,'BEA')) {
	echo "Beacon  --  \033[31m[Ignore]\033[0m \n";
	unlink($msgreceived.'/'.$row);
	}		


	// Удолим файлы с битыми заголовкаме

	If (strpos($msgreceived.'/'.$row,'REC-BD')) {
	echo "Bad title   --  \033[31m[Ignore]\033[0m \n";
	unlink($msgreceived.'/'.$row);
	}


	// Обрабатывать будем только файлы, в имени которых есть REC

	If (strpos($msgreceived.'/'.$row,'REC-OK')) {
	echo "\033[32mMessage\033[0m   --   ";
	
	$fd = fopen($msgreceived.'/'.$row, 'r') or die("не удалось открыть файл");
	
	$zagolovok='';
	$message='';

	
	$zagolovok = trim(fgets($fd,4096));
	$zagolovok = str_replace("\r\n","",$zagolovok);

	while(!feof($fd))
	{
    $sw = fgets($fd,4096);
	$sw = str_replace("\n",'',$sw);
	$sw = str_replace("\r",'',$sw);
	If ($sw=='$EML:') {
	$sw = '';
	}
	
		
	If (($message!='') AND ($sw!='')) {
	$message = $message.' '.$sw;	
	}

	If (($message=='') AND ($sw!='')) {
	$message = $sw;	
	}	
		
	}
	fclose($fd);
	
	$message = str_replace("\r\n","",$message);
	
	
	// Удалим сообщения, адресованные не нам
	
	If (strpos($zagolovok,'> 2115') === false) {
	echo "Not for us   --  \033[31m[Ignore]\033[0m\n";
	unlink($msgreceived.'/'.$row);
	}
	
	
	
	// Обработка сообщения, отправленного нам
	
	If (strpos($zagolovok,'> 2115')) {
	
	$serial = 0;
	
	$er = substr($zagolovok,strpos($zagolovok,'Error Rate')+11);
	$er = str_replace(' :','',$er);
	$zagolovok = substr($zagolovok,0,strpos($zagolovok,' '));
	$text_for_terminal = iconv("Windows-1251", "UTF-8", $message);
	$text_for_terminal = $zagolovok . ' ('. $er . ') > ' . $text_for_terminal;

	echo "\033[32m" . $text_for_terminal . "\033[0m   --   \033[32m[SEND SMS]\033[0m \n";
	unlink($msgreceived.'/'.$row);

		
		
			// Отправляем смс по списку из файла users.txt
			
			$smslist = fopen($smsusers,'r') or die("ERR_OPEN_FILE");
			while(!feof($smslist))
			{
			$isdn = trim(fgets($smslist,4096));
			$handle = fgets($smslist,4096);

			If ($isdn<>'') {
			$ut = date('Ymd_His');
			$utsms = date('d-m-Y H:i');
			$outfile = $smsoutbox.'/OUT'.$ut.'_'.$serial.'_'.$isdn.'_00.dat';
			$outfilenew = $smsoutbox.'/OUT'.$ut.'_'.$serial.'_'.$isdn.'_00.txt';
			$sms = $utsms.' '.$zagolovok . ' ('. $er.') > ' . $message;
			file_put_contents($outfile, $sms);
			rename($outfile, $outfilenew);
			}
		
			$serial = $serial + 1;
			sleep(1);
			}
			fclose($smslist);
			
			// *Отправляем смс по списку из файла users.txt
	
	} 
	
}

} 

}


// *Секция HFP -> SMS


?>  
